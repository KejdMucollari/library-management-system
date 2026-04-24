<?php

namespace App\Services\Ai;

use App\Enums\BookStatus;
use App\Models\Book;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiQueryService
{
    private const OPENAI_URL = 'https://api.groq.com/openai/v1/chat/completions';

    /**
     * The only operations we execute.
     */
    private const ALLOWED_AGG = ['count', 'sum', 'avg', 'min', 'max'];
    private const ALLOWED_ORDER_DIR = ['asc', 'desc'];

    /** Equality and range filters on timestamps (other columns only allow "="). */
    private const ALLOWED_FILTER_OPS = ['=', '>=', '<=', '<', '>'];

    private const DATETIME_FILTER_FIELDS = ['created_at', 'updated_at'];

    /**
     * Allowlisted fields (what the model is allowed to reference).
     */
    private const BOOK_FIELDS = [
        'id',
        'user_id',
        'title',
        'author',
        'genre',
        'status',
        'pages',
        'price',
        'created_at',
        'updated_at',
    ];

    private const USER_FIELDS = [
        'id',
        'name',
        'email',
        'is_admin',
        'created_at',
        'updated_at',
    ];

    /**
     * Translate a natural language question into a strict JSON spec.
     *
     * @return array<string, mixed>
     */
    public function translateToSpec(string $question, bool $isAdmin): array
    {
        // This service is a query-to-spec translator/executor (tables/metrics/rankings), not a recommender.
        // Recommendation / suggestion prompts should not be forced into a DB query.
        $ql = strtolower($question);
        $isRecommendation =
            str_contains($ql, 'recommend') ||
            str_contains($ql, 'suggest') ||
            str_contains($ql, 'what should i read') ||
            str_contains($ql, 'pick a book') ||
            str_contains($ql, 'choose a book');
        if ($isRecommendation) {
            return [
                'summary' => $isAdmin
                    ? "I can't recommend books yet — I can only run queries over your library data. Try asking something like 'Which genre is most popular?' or 'Show books I marked completed'."
                    : "I can't recommend books yet — I can only answer questions by querying your own library data. Try asking 'Show my completed books' or 'Which genre do I read most?'",
                'columns' => [],
                'rows' => [],
            ];
        }

        if (! $isAdmin && $this->nonAdminQuestionRequiresCrossUserBookContext($question)) {
            return [
                'summary' => 'I can only answer questions about your own books. I can\'t compare members or show who owns the most books across the whole library. Try asking "How many books do I have?" or "Show my completed books."',
                'columns' => [],
                'rows' => [],
            ];
        }

        $apiKey = (string) config('services.openai.key', env('GROQ_API_KEY'));
        if ($apiKey === '') {
            throw new \RuntimeException('GROQ_API_KEY is not configured.');
        }

        $systemLines = [
            'You translate questions into JSON query specs for a library app.',
            'Return ONLY valid JSON (no markdown).',
            'The database has tables: users and books.',
            'The books table has only these fields: id, title, author, genre, status, user_id, created_at, updated_at.',
            'Book status filter values in the database are exactly these strings: plan_to_read, reading, completed, paused. Natural phrases like "plan to read", "want to read", or "TBR" must be mapped to plan_to_read in filters (field status, op =).',
            'Genre filters use genres.name (e.g. "Sci-Fi"). Users may write "sci fi", "sci-fi", "scifi", or "science fiction" — use filter value "Sci-Fi" for that genre so it matches the database.',
            'The users table has only these fields: id, name, email, is_admin, created_at, updated_at.',
            'When the question asks for a person\'s name but the answer comes from book ownership, use from="books", select only ["user_id"] (never put "name" in select for books — that column is on users). The app will attach user_name from the users table automatically.',
        ];

        if (!$isAdmin) {
            $systemLines[] = 'You are a personal library assistant.';
            $systemLines[] = 'You can ONLY answer questions about the current user\'s own books.';
            $systemLines[] = 'Hard rules:';
            $systemLines[] = '- Never return data about other users.';
            $systemLines[] = '- Never return email addresses or sensitive user data.';
            $systemLines[] = '- Never allow scope: "all". Scope is always "me".';
            $systemLines[] = '- Never query the users table (from="users" is forbidden).';
            $systemLines[] = '- If asked about other users, ownership comparisons, or global stats, return {"error":"out_of_scope"}.';
            $systemLines[] = 'Useful examples:';
            $systemLines[] = '- "Show my completed books"';
            $systemLines[] = '- "Which genre do I read most?"';
            $systemLines[] = '- "How many books am I currently reading?"';
            $systemLines[] = '- "Show my paused books"';
            $systemLines[] = '- "How many books do I have in total?"';
        }

        $systemLines = array_merge($systemLines, [
            'Allowed spec format:',
            '{',
            '  "type": "metric" | "table" | "ranking",',
            '  "scope": "me" | "all",',
            '  "from": "books" | "users",',
            '  "select": ["field", ...],',
            '  "aggregates": [{"fn":"count|sum|avg|min|max","field":"field|*","as":"alias"}],',
            '  "group_by": ["field", ...],',
            '  "order_by": [{"field":"field|alias","dir":"asc|desc"}],',
            '  "limit": number,',
            '  "filters": [{"field":"field","op":"=|>=|<=|<|>","value":"string|number"}],',
            '  "filter_or_groups": [[{"field":"created_at","op":">=","value":"..."},{"field":"created_at","op":"<","value":"..."}], ...],',
            '}',
            'If the question is not related to library data (books, users, genres, reading status), return this exact JSON: {"error": "out_of_scope"}.',
            'If the question asks about a field that does not exist in the database, return this exact JSON: {"error": "unknown_field", "field": "<the field name>"}.',
            'If the question references any field not in the field lists above, return {"error": "unknown_field", "field": "<field name>"} immediately.',
            'Rules:',
            "- If not admin, scope MUST be \"me\".",
            '- If viewer_is_admin is true, use scope "all" for any catalog or cross-user question (lists, counts, rankings, genres, dates, user tables). Use scope "me" only when the user clearly means their own items (my/mine, "books I have", "do I have", "I added/created …"). The server enforces this for admins even if you output "me" by mistake.',
            '- Never request raw SQL. Never include joins. Use from="books" for most questions.',
            '- Prefer ranking/table for lists, metric for a single number.',
            '- For "most popular book", group by title and author, aggregate count(*), order desc.',
            '- For "who owns the most books", from="books", group_by user_id, aggregate count(*), order desc, limit 1, and include select ["user_id"].',
            '- For "who owns the fewest books" / "least books" / "minimum books per user", from="books", group_by user_id, aggregate count(*), order asc, limit 1, and include select ["user_id"].',
            '- For "most expensive" / "highest price" books: type "table" or "ranking", from="books", select title/author/price (and genre if needed), aggregates [], order_by price desc, limit N. Use limit 10 (or similar) when the user says plural "books"; use limit 1 only when they clearly mean a single book (singular "book"). Only count(*) may use field "*"; never max(*), min(*), sum(*), or avg(*).',
            '- For "most pages" / "longest book": same pattern using order_by pages desc and explicit field pages (not max(*) mixed with other columns).',
            '- For "latest / newest / most recently added book": use order_by created_at desc, limit 1, aggregates [] — never MAX(created_at) mixed with id/title in one SELECT without GROUP BY.',
            '- Filters must use columns that exist on the chosen table only: is_admin, name, and email are on users, not books. For book-count questions use from="books" and omit user-only filters unless from="users".',
            '- For created_at/updated_at you may use op =, >=, <=, <, > with string values. Use the calendar_day_start and calendar_day_end_exclusive lines from the user message for "today" windows (half-open interval: >= start AND < end).',
            '- For "created or edited today" on books: type "table", from "books", filters [], filter_or_groups with two groups: (created_at >= start AND created_at < end) OR (updated_at >= start AND updated_at < end), order_by updated_at desc, limit 100.',
        ]);

        $system = implode("\n", $systemLines);

        $dayStart = now()->startOfDay();
        $dayEndExclusive = $dayStart->copy()->addDay();

        $user = implode("\n", [
            'viewer_is_admin: '.($isAdmin ? 'true' : 'false'),
            'calendar_day_start: '.$dayStart->format('Y-m-d H:i:s'),
            'calendar_day_end_exclusive: '.$dayEndExclusive->format('Y-m-d H:i:s'),
            "question: {$question}",
        ]);

        try {
            $resp = Http::timeout(30)
                ->withToken($apiKey)
                ->post(self::OPENAI_URL, [
                    'model' => 'llama-3.1-8b-instant',
                    'temperature' => 0,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ])
                ->throw()
                ->json();
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Groq connection failed.');
        }

        $content = data_get($resp, 'choices.0.message.content');
        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Groq returned an empty response.');
        }

        $spec = json_decode($content, true);
        if (!is_array($spec)) {
            throw new \RuntimeException('Groq did not return valid JSON.');
        }

        // Model-signaled errors for unsupported questions / unknown fields.
        if (isset($spec['error'])) {
            if ($spec['error'] === 'out_of_scope') {
                if (!$isAdmin) {
                    return [
                        'summary' => "I can only answer questions about your own library. Try asking 'Show my completed books' or 'Which genre do I read most?'",
                        'columns' => [],
                        'rows' => [],
                    ];
                }
                return [
                    'summary' => "I can only answer questions about your library data — books, users, genres, and reading status. Try asking something like 'Who owns the most books?' or 'Which genre is most popular?'",
                    'columns' => [],
                    'rows' => [],
                ];
            }

            if ($spec['error'] === 'unknown_field') {
                $field = is_string($spec['field'] ?? null) ? $spec['field'] : 'that field';
                return [
                    'summary' => "I don't have {$field} data available. I can filter books by title, author, genre, or reading status.",
                    'columns' => [],
                    'rows' => [],
                ];
            }
        }

        if (!$isAdmin) {
            $spec['scope'] = 'me';
        }

        $spec = $this->applyBooksTouchedTodayHeuristic($spec, $question, $dayStart, $dayEndExclusive);

        // User safety: never allow non-admin to query users table.
        if (!$isAdmin && (($spec['from'] ?? null) === 'users')) {
            return [
                'summary' => "I can only answer questions about your own library. Try asking 'Show my completed books' or 'Which genre do I read most?'",
                'columns' => [],
                'rows' => [],
            ];
        }

        $spec = $this->normalizeAdminCatalogScope($spec, $question, $isAdmin);

        return $this->validateAndNormalizeSpec($spec, $isAdmin, $question);
    }

    /**
     * When the model omits date ranges, "books … today" questions otherwise return unrelated recent rows.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function applyBooksTouchedTodayHeuristic(array $spec, string $question, $dayStart, $dayEndExclusive): array
    {
        $ql = strtolower($question);
        if (! str_contains($ql, 'today') || ! str_contains($ql, 'book')) {
            return $spec;
        }
        if (($spec['from'] ?? '') !== 'books') {
            return $spec;
        }
        $mentionsTouch = str_contains($ql, 'creat')
            || str_contains($ql, 'edit')
            || str_contains($ql, 'updat')
            || str_contains($ql, 'add')
            || str_contains($ql, 'modif');
        if (! $mentionsTouch) {
            return $spec;
        }

        // Models often emit created_at = "YYYY-MM-DD", which never matches a full timestamp and ANDs away all rows
        // when combined with our range groups. Drop those filters and apply server-side windows only.
        if (isset($spec['filters']) && is_array($spec['filters'])) {
            $spec['filters'] = array_values(array_filter(
                $spec['filters'],
                static function ($f) {
                    if (! is_array($f) || ! isset($f['field']) || ! is_string($f['field'])) {
                        return true;
                    }

                    return ! in_array($f['field'], self::DATETIME_FILTER_FIELDS, true);
                },
            ));
        }

        $start = $dayStart->format('Y-m-d H:i:s');
        $end = $dayEndExclusive->format('Y-m-d H:i:s');
        $createdWindow = [
            ['field' => 'created_at', 'op' => '>=', 'value' => $start],
            ['field' => 'created_at', 'op' => '<', 'value' => $end],
        ];
        $includeUpdatedWindow = (bool) preg_match('/\b(edited|updated|modified)\b/u', $ql)
            || (str_contains($ql, ' or ')
                && (str_contains($ql, 'creat') || str_contains($ql, 'edit') || str_contains($ql, 'updat')));

        $spec['filter_or_groups'] = $includeUpdatedWindow
            ? [
                $createdWindow,
                [
                    ['field' => 'updated_at', 'op' => '>=', 'value' => $start],
                    ['field' => 'updated_at', 'op' => '<', 'value' => $end],
                ],
            ]
            : [$createdWindow];

        if (($spec['type'] ?? '') === 'table' && ($spec['limit'] ?? null) === null) {
            $spec['limit'] = 100;
        }

        return $spec;
    }

    /**
     * Groq often returns scope "me" for admins; that hides every other member's books and breaks
     * library-wide questions (rankings, "today", totals, user tables). Default admins to the full
     * catalog unless the natural-language question clearly refers only to the viewer's own items.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function normalizeAdminCatalogScope(array $spec, string $question, bool $isAdmin): array
    {
        if (! $isAdmin) {
            return $spec;
        }

        $from = $spec['from'] ?? null;
        if ($from !== 'books' && $from !== 'users') {
            return $spec;
        }

        if ($this->questionImpliesPersonalBookScope($question)) {
            $spec['scope'] = 'me';

            return $spec;
        }

        if ($from === 'users') {
            $spec['scope'] = 'all';

            return $spec;
        }

        $spec['scope'] = 'all';

        return $spec;
    }

    /**
     * Member-facing AI: questions that need the full member list / cross-user rankings.
     */
    private function nonAdminQuestionRequiresCrossUserBookContext(string $question): bool
    {
        $q = strtolower(trim($question));
        if ($q === '') {
            return false;
        }

        if (preg_match('/\b(all|every)\s+(users?|members?|people|readers?)\b/u', $q)) {
            return true;
        }
        if (preg_match('/\bfrom\s+all\s+users?\b/u', $q)) {
            return true;
        }
        if (preg_match('/\b(across|among)\s+(all\s+)?(the\s+)?(users?|members?|everyone|people|readers?)\b/u', $q)) {
            return true;
        }
        if (preg_match('/\bwho\s+owns\b/u', $q) && preg_match('/\b(most|more|fewest|least)\b/u', $q) && preg_match('/\bbooks?\b/u', $q)) {
            return true;
        }
        if (preg_match('/\bwhich\s+user\b/u', $q)) {
            return true;
        }
        if (preg_match('/\btop\s+reader\b/u', $q)) {
            return true;
        }
        if (preg_match('/\bwho\s+has\s+the\s+(most|fewest|least)\b/u', $q) && preg_match('/\bbooks?\b/u', $q)) {
            return true;
        }
        if (preg_match('/\b(user|member|reader)\s+with\s+the\s+(most|fewest|least)\s+books\b/u', $q)) {
            return true;
        }
        if (preg_match('/\b(most|fewest|least)\s+books\b/u', $q) && preg_match('/\b(in|across|throughout)\s+the\s+(library|system|app|database)\b/u', $q)) {
            return true;
        }

        return false;
    }

    private function questionImpliesPersonalBookScope(string $question): bool
    {
        $q = strtolower($question);

        if (preg_match('/\b(my|mine)\b/u', $q)) {
            return true;
        }

        if (preg_match('/\b(books?|titles?)\s+i\s+(have|own|read|added|created|want|wanted)\b/u', $q)) {
            return true;
        }

        if (preg_match('/\bi\s+(have|own|added|created|read|finished|started|bought|got)\s+(any\s+)?(books?|titles?|entries)\b/u', $q)) {
            return true;
        }

        if (preg_match('/\b(do|does|did)\s+i\s+have\b/u', $q)) {
            return true;
        }

        if (preg_match('/\bi\s+(just\s+)?(added|created|bought|got)\b/u', $q)) {
            return true;
        }

        return false;
    }

    /**
     * True when the user clearly means one book ranked by price (singular "book"),
     * not a list of top expensive books (plural "books").
     */
    private function questionAsksSingleTopBookByPrice(string $question): bool
    {
        $q = strtolower(trim($question));
        if ($q === '') {
            return false;
        }

        // Plural / list phrasing: keep the model's limit (e.g. top 10).
        if (preg_match('/\b(most expensive|least expensive|cheapest|priciest|highest[- ]priced|lowest[- ]priced)\s+books\b/u', $q)) {
            return false;
        }
        if (preg_match('/\bexpensive\s+books\b/u', $q)) {
            return false;
        }

        if (preg_match('/\b(which|what)\s+(is|was)\s+the\s+most\s+expensive\s+book\b/u', $q)) {
            return true;
        }
        if (preg_match('/\b(which|what)\s+(is|was)\s+the\s+(cheapest|least\s+expensive)\s+book\b/u', $q)) {
            return true;
        }
        if (preg_match('/\b(most expensive|priciest|highest[- ]price|costliest)\s+book\b/u', $q)) {
            return true;
        }
        if (preg_match('/\b(least expensive|cheapest|lowest[- ]price)\s+book\b/u', $q)) {
            return true;
        }

        return false;
    }

    /**
     * Singular "book with the most pages" / "longest book" → limit 1 when sorted by pages.
     */
    private function questionAsksSingleTopBookByPages(string $question): bool
    {
        $q = strtolower(trim($question));
        if ($q === '') {
            return false;
        }

        if (preg_match('/\bbooks?\s+with\s+the\s+(most|fewest|least)\s+pages\b/u', $q)) {
            return false;
        }

        if (preg_match('/\b(which|what)\s+is\s+the\s+book\s+with\s+the\s+most\s+pages\b/u', $q)) {
            return true;
        }
        if (preg_match('/\b(which|what)\s+book\s+(has|had)\s+the\s+most\s+pages\b/u', $q)) {
            return true;
        }
        if (preg_match('/\bbook\s+with\s+the\s+most\s+pages\b/u', $q)) {
            return true;
        }
        if (preg_match('/\b(most|fewest|least)\s+pages\b/u', $q) && preg_match('/\bbook\b/u', $q) && ! preg_match('/\bbooks\b/u', $q)) {
            return true;
        }
        if (preg_match('/\blongest\s+book\b/u', $q)) {
            return true;
        }

        return false;
    }

    /**
     * When the model uses max(*)/min(*) on books, pick a real column (only COUNT(*) is valid with *).
     */
    private function inferScalarAggregateFieldFromQuestion(?string $question): string
    {
        $q = strtolower(trim((string) $question));
        if ($q !== '' && preg_match('/\b(page|pages|longest|shortest|length)\b/u', $q)) {
            return 'pages';
        }
        if ($q !== '' && preg_match('/\b(latest|newest|most\s+recent|recently\s+added|last\s+added)\b/u', $q) && preg_match('/\bbook\b/u', $q)) {
            return 'created_at';
        }
        if ($q !== '' && preg_match('/\b(last|latest)\s+(edit|update|modified)\b/u', $q)) {
            return 'updated_at';
        }
        if ($q !== '' && preg_match('/\b(price|expensive|cheap|cost|priced)\b/u', $q)) {
            return 'price';
        }

        return 'price';
    }

    /**
     * Singular "latest added book" → limit 1 when sorted by created_at.
     */
    private function questionAsksSingleLatestBookByCreatedAt(string $question): bool
    {
        $q = strtolower(trim($question));
        if ($q === '') {
            return false;
        }
        if (preg_match('/\b(which|what)\s+is\s+the\s+latest\b/u', $q) && preg_match('/\bbook\b/u', $q)) {
            return true;
        }
        if (preg_match('/\b(latest|newest|most\s+recently)\s+added\s+book\b/u', $q)) {
            return true;
        }
        if (preg_match('/\bbook\b/u', $q) && preg_match('/\b(latest|newest)\s+(added|created)\b/u', $q)) {
            return true;
        }

        return false;
    }

    /**
     * Execute a validated spec safely using Query Builder/Eloquent.
     *
     * @param  string|null  $question  Original NL question (used to force limit=1 for singular "most expensive book").
     */
    public function execute(array $spec, User $actor, ?string $question = null): AiQueryResult
    {
        // Safety net: non-admin users are always scoped to their own books, and may not query users.
        if (!$actor->isAdmin()) {
            $spec['scope'] = 'me';
            if (($spec['from'] ?? null) === 'users') {
                return new AiQueryResult(
                    columns: [],
                    rows: [],
                    summary: "I can only answer questions about your own books. Try asking 'Show my completed books' or 'Which genre do I read most?'",
                    debug: ['spec' => $spec],
                );
            }
            if (($spec['from'] ?? null) === 'books' && in_array('user_id', $spec['group_by'] ?? [], true)) {
                return new AiQueryResult(
                    columns: [],
                    rows: [],
                    summary: 'I can only answer questions about your own books. I can\'t rank or compare other readers. Try asking "How many books do I have?" or "Show my completed books."',
                    debug: ['spec' => $spec],
                );
            }
        }

        $spec = $this->validateAndNormalizeSpec($spec, $actor->isAdmin(), $question);

        // The model may put user columns (e.g. "name") on a books query — books only has user_id.
        if (($spec['from'] ?? '') === 'books') {
            $spec['select'] = array_values(array_filter(
                $spec['select'] ?? [],
                static fn ($f) => is_string($f) && in_array($f, self::BOOK_FIELDS, true),
            ));
            if ($spec['select'] === [] && in_array('user_id', $spec['group_by'] ?? [], true)) {
                $spec['select'] = ['user_id'];
            }
        }

        $from = $spec['from'];
        $scope = $spec['scope'];

        if ($scope === 'all' && !$actor->isAdmin()) {
            throw new \RuntimeException('Not authorized for cross-user queries.');
        }

        if ($from === 'books') {
            $qb = Book::query()->toBase();
            if ($scope === 'me') {
                $qb->where('user_id', $actor->id);
            }

            $allowedFields = self::BOOK_FIELDS;
            $qb->from('books');
        } else {
            $qb = User::query()->toBase();
            $allowedFields = self::USER_FIELDS;
            $qb->from('users');

            // Non-admin should never query users table.
            if (!$actor->isAdmin()) {
                throw new \RuntimeException('Not authorized.');
            }
        }

        // "genre" in the UI is genres.name (books.genre_id), not the legacy books.genre text column.
        // We keep the spec field as "genre" for UX, but execute it safely via a controlled join.
        $usesGenreName =
            $from === 'books' && (
                in_array('genre', $spec['select'] ?? [], true) ||
                in_array('genre', $spec['group_by'] ?? [], true) ||
                collect($spec['filters'] ?? [])->contains(fn ($f) => is_array($f) && ($f['field'] ?? null) === 'genre') ||
                collect($spec['order_by'] ?? [])->contains(fn ($o) => is_array($o) && ($o['field'] ?? null) === 'genre')
            );

        if ($usesGenreName) {
            $qb->leftJoin('genres', 'genres.id', '=', 'books.genre_id');
        }

        $bookCol = static fn (string $col): string => "books.{$col}";
        $genreCol = static fn (): string => 'genres.name';

        // Drop filters on columns that do not exist on the target table (model often copies user fields onto books).
        $spec['filters'] = array_values(array_filter(
            $spec['filters'] ?? [],
            static function ($f) use ($allowedFields) {
                if (! is_array($f) || ! isset($f['field']) || ! is_string($f['field'])) {
                    return false;
                }

                return in_array($f['field'], $allowedFields, true);
            },
        ));

        $rawOr = $spec['filter_or_groups'] ?? [];
        $spec['filter_or_groups'] = [];
        if (is_array($rawOr)) {
            foreach ($rawOr as $group) {
                if (count($spec['filter_or_groups']) >= 4) {
                    break;
                }
                if (! is_array($group)) {
                    continue;
                }
                $inner = [];
                foreach ($group as $f) {
                    if (count($inner) >= 6) {
                        break;
                    }
                    if (! is_array($f) || ! isset($f['field']) || ! is_string($f['field'])) {
                        continue;
                    }
                    if (! in_array($f['field'], $allowedFields, true)) {
                        continue;
                    }
                    $op = isset($f['op']) && is_string($f['op']) ? strtolower($f['op']) : '=';
                    if (! in_array($op, self::ALLOWED_FILTER_OPS, true)) {
                        continue;
                    }
                    if (! in_array($f['field'], self::DATETIME_FILTER_FIELDS, true) && $op !== '=') {
                        continue;
                    }
                    if (! array_key_exists('value', $f)) {
                        continue;
                    }
                    $inner[] = ['field' => $f['field'], 'op' => $op, 'value' => $f['value']];
                }
                if ($inner !== []) {
                    $spec['filter_or_groups'][] = $inner;
                }
            }
        }

        foreach ($spec['filters'] as $f) {
            $field = $f['field'];
            if (!in_array($field, $allowedFields, true)) {
                throw new \RuntimeException("Filter field not allowed: {$field}");
            }
            $op = $f['op'] ?? '=';
            if (!in_array($op, self::ALLOWED_FILTER_OPS, true)) {
                throw new \RuntimeException("Filter op not allowed: {$op}");
            }
            if (!in_array($field, self::DATETIME_FILTER_FIELDS, true) && $op !== '=') {
                throw new \RuntimeException("Range filters are only allowed on created_at and updated_at.");
            }
            if ($usesGenreName && $field === 'genre') {
                // Case-insensitive matching for genres (users often type "history" while stored is "History").
                if ($op === '=' && is_string($f['value'])) {
                    $qb->whereRaw('lower('.$genreCol().') = lower(?)', [$f['value']]);
                } else {
                    $qb->where($genreCol(), $op, $f['value']);
                }
            } else {
                $qb->where($usesGenreName && $from === 'books' ? $bookCol($field) : $field, $op, $f['value']);
            }
        }

        $orGroups = $spec['filter_or_groups'];
        if ($orGroups !== []) {
            $qb->where(function ($q) use ($orGroups) {
                $firstGroup = true;
                foreach ($orGroups as $group) {
                    if (! is_array($group) || $group === []) {
                        continue;
                    }
                    $sub = function ($q2) use ($group) {
                        foreach ($group as $f) {
                            if (! is_array($f) || ! isset($f['field'], $f['value'])) {
                                continue;
                            }
                            $op = $f['op'] ?? '=';
                            $field = (string) $f['field'];
                            if (in_array($field, self::DATETIME_FILTER_FIELDS, true)) {
                                $q2->where($field, $op, $f['value']);
                            }
                        }
                    };
                    if ($firstGroup) {
                        $q->where($sub);
                        $firstGroup = false;
                    } else {
                        $q->orWhere($sub);
                    }
                }
            });
        }

        $select = [];
        foreach ($spec['select'] as $field) {
            if (!in_array($field, $allowedFields, true)) {
                throw new \RuntimeException("Select field not allowed: {$field}");
            }
            if ($usesGenreName && $field === 'genre') {
                $select[] = $genreCol().' as genre';
            } else {
                $select[] = $usesGenreName && $from === 'books' ? $bookCol($field)." as {$field}" : $field;
            }
        }

        $aggAliases = [];
        foreach ($spec['aggregates'] as $agg) {
            $fn = strtolower((string) ($agg['fn'] ?? ''));
            $field = (string) ($agg['field'] ?? '');
            $as = (string) ($agg['as'] ?? '');

            if (! in_array($fn, self::ALLOWED_AGG, true)) {
                throw new \RuntimeException("Aggregate not allowed: {$fn}");
            }

            if ($field !== '*' && ! in_array($field, $allowedFields, true)) {
                throw new \RuntimeException("Aggregate field not allowed: {$field}");
            }

            if ($field === '*' && $fn !== 'count') {
                throw new \RuntimeException("Invalid aggregate {$fn}(*): only count(*) may use *.");
            }

            if ($as === '') {
                throw new \RuntimeException('Aggregate alias required.');
            }

            $aggAliases[] = $as;

            if ($fn === 'count' && $field === '*') {
                $select[] = 'COUNT(*) as '.$as;
            } else {
                $aggFieldSql = $field;
                if ($from === 'books') {
                    $aggFieldSql = ($usesGenreName && $field === 'genre')
                        ? $genreCol()
                        : $bookCol($field);
                }
                $select[] = strtoupper($fn)."({$aggFieldSql}) as {$as}";
            }
        }

        if (count($select) === 0) {
            $select = ['*'];
        }

        $qb->selectRaw(implode(', ', $select));

        foreach ($spec['group_by'] as $g) {
            if (!in_array($g, $allowedFields, true)) {
                throw new \RuntimeException("Group by not allowed: {$g}");
            }
            if ($usesGenreName && $g === 'genre') {
                $qb->groupBy($genreCol());
            } else {
                $qb->groupBy($usesGenreName && $from === 'books' ? $bookCol($g) : $g);
            }
        }

        foreach ($spec['order_by'] as $o) {
            $field = $o['field'];
            $dir = $o['dir'];

            $isAllowed =
                in_array($field, $allowedFields, true) ||
                in_array($field, $aggAliases, true);

            if (!$isAllowed) {
                throw new \RuntimeException("Order by not allowed: {$field}");
            }

            if (!in_array($dir, self::ALLOWED_ORDER_DIR, true)) {
                throw new \RuntimeException("Order direction not allowed: {$dir}");
            }

            if ($usesGenreName && $field === 'genre') {
                $qb->orderBy($genreCol(), $dir);
            } elseif (in_array($field, $aggAliases, true)) {
                // Aggregate aliases are not real columns; do not qualify (e.g. count(*) as count).
                $qb->orderBy($field, $dir);
            } else {
                $qb->orderBy($usesGenreName && $from === 'books' ? $bookCol($field) : $field, $dir);
            }
        }

        if ($spec['limit'] !== null) {
            $qb->limit((int) $spec['limit']);
        }

        $rows = $qb->get()->map(fn ($r) => (array) $r)->all();

        $columns = $spec['select'];
        foreach ($spec['aggregates'] as $agg) {
            $columns[] = $agg['as'];
        }
        $columns = array_values(array_unique($columns));

        if ($from === 'books' && $rows !== []) {
            [$rows, $columns] = $this->attachUserNamesForBookRows($rows, $columns);
        }

        if ($rows === []) {
            $emptySummary = $from === 'users'
                ? "No users found matching your query. Try broadening your search or ask something like 'List all users' to see everyone."
                : "No books found matching your query. Try broadening your search or ask something like 'Show all books' to see everything.";

            return new AiQueryResult(
                columns: [],
                rows: [],
                summary: $emptySummary,
                debug: ['spec' => $spec],
            );
        }

        $summary = $this->summarize($spec, $rows);

        return new AiQueryResult(
            columns: $columns,
            rows: $rows,
            summary: $summary,
            debug: ['spec' => $spec],
        );
    }

    private function summarize(array $spec, array $rows): string
    {
        if ($spec['type'] === 'metric') {
            $first = $rows[0] ?? null;
            $alias = $spec['aggregates'][0]['as'] ?? null;
            if (is_array($first) && is_string($alias) && array_key_exists($alias, $first)) {
                return "Result: ".$first[$alias];
            }
        }

        if (($spec['from'] ?? '') === 'books' && count($rows) === 1) {
            $first = $rows[0];
            $ob = $spec['order_by'] ?? [];
            $o0 = $ob[0] ?? null;
            if (is_array($first) && is_array($o0) && ($o0['field'] ?? '') === 'price') {
                $title = $first['title'] ?? null;
                $price = $first['price'] ?? null;
                if (is_string($title) && $title !== '' && $price !== null && $price !== '') {
                    $dir = strtolower((string) ($o0['dir'] ?? 'desc'));
                    $phrase = $dir === 'asc' ? 'least expensive' : 'most expensive';
                    $priceNum = is_numeric($price) ? (float) $price : null;
                    $priceOut = $priceNum !== null ? '$'.number_format($priceNum, 2) : (string) $price;

                    return "The {$phrase} book is {$title} ({$priceOut}).";
                }
            }
            if (is_array($first) && is_array($o0) && ($o0['field'] ?? '') === 'pages') {
                $title = $first['title'] ?? null;
                $pages = $first['pages'] ?? null;
                if (is_string($title) && $title !== '' && $pages !== null && $pages !== '') {
                    $dir = strtolower((string) ($o0['dir'] ?? 'desc'));
                    $phrase = $dir === 'asc' ? 'fewest pages' : 'most pages';
                    $pagesOut = is_numeric($pages) ? (string) (int) $pages : (string) $pages;

                    return "The book with the {$phrase} is {$title} ({$pagesOut} pages).";
                }
            }
            $tsField = is_array($o0) ? (string) ($o0['field'] ?? '') : '';
            if (is_array($first) && is_array($o0) && in_array($tsField, ['created_at', 'updated_at'], true)) {
                $title = $first['title'] ?? null;
                $ts = $first[$tsField] ?? null;
                if (is_string($title) && $title !== '' && $ts !== null && $ts !== '') {
                    $dir = strtolower((string) ($o0['dir'] ?? 'desc'));
                    if ($tsField === 'updated_at') {
                        $phrase = $dir === 'asc' ? 'least recently updated' : 'last updated';
                    } else {
                        $phrase = $dir === 'asc' ? 'earliest added' : 'latest added';
                    }
                    try {
                        $out = Carbon::parse((string) $ts)
                            ->timezone((string) config('app.timezone'))
                            ->format('M j, Y g:i A');
                    } catch (\Throwable) {
                        $out = (string) $ts;
                    }

                    return "The {$phrase} book is {$title} ({$out}).";
                }
            }
        }

        if ($spec['type'] === 'ranking' && count($rows) === 1) {
            $first = $rows[0];
            if (is_array($first) && isset($first['user_name'], $first['user_id'])) {
                return 'Top reader: '.$first['user_name'].' (user #'.$first['user_id'].').';
            }
        }

        return 'Showing '.count($rows).' row(s).';
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $columns
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function attachUserNamesForBookRows(array $rows, array $columns): array
    {
        $hasUserIdColumn = in_array('user_id', $columns, true)
            || collect($rows)->contains(fn ($r) => is_array($r) && array_key_exists('user_id', $r));

        if (! $hasUserIdColumn) {
            return [$rows, $columns];
        }

        $ids = collect($rows)
            ->pluck('user_id')
            ->filter(fn ($id) => is_int($id) || is_string($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [$rows, $columns];
        }

        /** @var array<int, string> $idToName */
        $idToName = User::query()
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->all();

        foreach ($rows as $i => $row) {
            if (! is_array($row) || ! array_key_exists('user_id', $row)) {
                continue;
            }
            $uid = (int) $row['user_id'];
            $rows[$i]['user_name'] = $idToName[$uid] ?? '—';
        }

        if (! in_array('user_name', $columns, true)) {
            $idx = array_search('user_id', $columns, true);
            if ($idx !== false) {
                array_splice($columns, $idx + 1, 0, ['user_name']);
            } else {
                $columns[] = 'user_name';
            }
        }

        return [$rows, array_values(array_unique($columns))];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function validateAndNormalizeSpec(array $spec, bool $isAdmin, ?string $question = null): array
    {
        $type = $spec['type'] ?? null;
        $scope = $spec['scope'] ?? null;
        $from = $spec['from'] ?? null;

        if (!in_array($type, ['metric', 'table', 'ranking'], true)) {
            throw new \RuntimeException('Invalid spec.type');
        }

        if (!in_array($scope, ['me', 'all'], true)) {
            throw new \RuntimeException('Invalid spec.scope');
        }

        if (!$isAdmin && $scope !== 'me') {
            $scope = 'me';
        }

        if (!in_array($from, ['books', 'users'], true)) {
            throw new \RuntimeException('Invalid spec.from');
        }

        if (!$isAdmin && $from === 'users') {
            throw new \RuntimeException('Non-admin cannot query users.');
        }

        $select = $spec['select'] ?? [];
        $aggregates = $spec['aggregates'] ?? [];
        $groupBy = $spec['group_by'] ?? [];
        $orderBy = $spec['order_by'] ?? [];
        $limit = $spec['limit'] ?? null;
        $filters = $spec['filters'] ?? [];
        $filterOrGroups = $spec['filter_or_groups'] ?? [];
        if (! is_array($filterOrGroups)) {
            $filterOrGroups = [];
        }

        if (!is_array($select) || !is_array($aggregates) || !is_array($groupBy) || !is_array($orderBy) || !is_array($filters)) {
            throw new \RuntimeException('Invalid spec shape.');
        }

        if ($limit !== null && (!is_int($limit) || $limit < 1 || $limit > 100)) {
            $limit = 25;
        }

        // Default limit for non-metric queries.
        if ($type !== 'metric' && $limit === null) {
            $limit = 25;
        }

        // Normalize fields arrays to strings only.
        $select = array_values(array_filter($select, 'is_string'));
        $groupBy = array_values(array_filter($groupBy, 'is_string'));

        $aggregatesNorm = [];
        foreach ($aggregates as $a) {
            if (! is_array($a)) {
                continue;
            }
            $fn = $a['fn'] ?? null;
            $field = $a['field'] ?? null;
            $as = $a['as'] ?? null;
            if (! is_string($fn) || ! is_string($field) || ! is_string($as)) {
                continue;
            }
            $fn = strtolower($fn);
            if ($field === '*' && $fn === 'count') {
                // keep *
            } elseif ($field === '*' && $from === 'books' && in_array($fn, ['sum', 'avg', 'min', 'max'], true)) {
                // Groq may emit max(*) — only COUNT(*) is valid; infer column from the question when possible.
                $field = $this->inferScalarAggregateFieldFromQuestion($question);
            } elseif ($field === '*' && $fn !== 'count') {
                continue;
            }
            $aggregatesNorm[] = [
                'fn' => $fn,
                'field' => $field,
                'as' => Str::snake($as),
            ];
        }

        $orderNorm = [];
        foreach ($orderBy as $o) {
            if (! is_array($o)) {
                continue;
            }
            $field = $o['field'] ?? null;
            $dir = $o['dir'] ?? null;
            if (! is_string($field) || ! is_string($dir)) {
                continue;
            }
            $orderNorm[] = [
                'field' => $field,
                'dir' => strtolower($dir),
            ];
        }

        // Mixing row columns with MAX/MIN(scalar) and no GROUP BY breaks MySQL ONLY_FULL_GROUP_BY.
        // Rewrite to ORDER BY that column + drop the aggregate (same top row as MAX/MIN would pick).
        $scalarMaxMin = ['price', 'pages', 'created_at', 'updated_at'];
        if ($from === 'books' && $groupBy === [] && count($aggregatesNorm) === 1 && $select !== []) {
            $a0 = $aggregatesNorm[0];
            $fn0 = $a0['fn'];
            $f0 = $a0['field'];
            if (in_array($fn0, ['max', 'min'], true) && in_array($f0, $scalarMaxMin, true)) {
                $scalar = $f0;
                $dir = $fn0 === 'max' ? 'desc' : 'asc';
                $alias = $a0['as'];
                $aggregatesNorm = [];
                $mapped = false;
                $newOrder = [];
                foreach ($orderNorm as $o) {
                    if (($o['field'] ?? '') === $alias) {
                        $newOrder[] = ['field' => $scalar, 'dir' => $dir];
                        $mapped = true;
                    } else {
                        $newOrder[] = $o;
                    }
                }
                $orderNorm = $mapped ? $newOrder : array_merge([['field' => $scalar, 'dir' => $dir]], $orderNorm);
                if (! in_array($scalar, $select, true)) {
                    $select[] = $scalar;
                }
                $select = ['title', $scalar];
            }
        }

        // Singular "most expensive book" / "book with the most pages" → one row; plural lists keep model limit.
        if ($from === 'books' && $question !== null && $question !== '' && $groupBy === [] && $aggregatesNorm === []) {
            $o0 = $orderNorm[0] ?? null;
            if (is_array($o0) && ($o0['field'] ?? '') === 'price' && $this->questionAsksSingleTopBookByPrice($question)) {
                $limit = 1;
            }
            if (is_array($o0) && ($o0['field'] ?? '') === 'pages' && $this->questionAsksSingleTopBookByPages($question)) {
                $limit = 1;
            }
            if (is_array($o0) && ($o0['field'] ?? '') === 'created_at' && $this->questionAsksSingleLatestBookByCreatedAt($question)) {
                $limit = 1;
            }
        }

        // Tidy columns when returning a single row sorted by price, pages, or timestamps.
        if ($from === 'books' && $groupBy === [] && $aggregatesNorm === [] && ($limit === 1)) {
            $o0 = $orderNorm[0] ?? null;
            if (is_array($o0) && ($o0['field'] ?? '') === 'price' && count($select) > 2) {
                $select = ['title', 'price'];
            }
            if (is_array($o0) && ($o0['field'] ?? '') === 'pages' && count($select) > 2) {
                $select = ['title', 'pages'];
            }
            if (is_array($o0) && ($o0['field'] ?? '') === 'created_at' && count($select) > 2) {
                $select = ['title', 'created_at'];
            }
            if (is_array($o0) && ($o0['field'] ?? '') === 'updated_at' && count($select) > 2) {
                $select = ['title', 'updated_at'];
            }
        }

        $filtersNorm = [];
        foreach ($filters as $f) {
            $n = $this->normalizeFilterEntry($f);
            if ($n !== null) {
                if ($from === 'books' && ($n['field'] ?? '') === 'status' && is_string($n['value'])) {
                    $n['value'] = $this->normalizeBookStatusFilterValue($n['value']);
                }
                if ($from === 'books' && ($n['field'] ?? '') === 'genre' && is_string($n['value'])) {
                    $n['value'] = $this->normalizeGenreFilterValue($n['value']);
                }
                $filtersNorm[] = $n;
            }
        }

        $orGroupsNorm = [];
        foreach ($filterOrGroups as $group) {
            if (count($orGroupsNorm) >= 4) {
                break;
            }
            if (! is_array($group)) {
                continue;
            }
            $inner = [];
            foreach ($group as $f) {
                if (count($inner) >= 6) {
                    break;
                }
                $n = $this->normalizeFilterEntry($f);
                if ($n !== null) {
                    if ($from === 'books' && ($n['field'] ?? '') === 'status' && is_string($n['value'])) {
                        $n['value'] = $this->normalizeBookStatusFilterValue($n['value']);
                    }
                    if ($from === 'books' && ($n['field'] ?? '') === 'genre' && is_string($n['value'])) {
                        $n['value'] = $this->normalizeGenreFilterValue($n['value']);
                    }
                    $inner[] = $n;
                }
            }
            if ($inner !== []) {
                $orGroupsNorm[] = $inner;
            }
        }

        return [
            'type' => $type,
            'scope' => $scope,
            'from' => $from,
            'select' => $select,
            'aggregates' => $aggregatesNorm,
            'group_by' => $groupBy,
            'order_by' => $orderNorm,
            'limit' => $limit,
            'filters' => $filtersNorm,
            'filter_or_groups' => $orGroupsNorm,
        ];
    }

    /**
     * @param  mixed  $f
     * @return array{field: string, op: string, value: string|int|float}|null
     */
    private function normalizeFilterEntry(mixed $f): ?array
    {
        if (! is_array($f)) {
            return null;
        }
        $field = $f['field'] ?? null;
        $op = $f['op'] ?? '=';
        $value = $f['value'] ?? null;
        if (! is_string($field)) {
            return null;
        }
        if (! is_string($op)) {
            return null;
        }
        $op = strtolower(trim($op));
        if (! in_array($op, self::ALLOWED_FILTER_OPS, true)) {
            return null;
        }
        if (! in_array($field, self::DATETIME_FILTER_FIELDS, true) && $op !== '=') {
            return null;
        }
        if ($op === '=' && ! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }
        if ($op !== '=' && ! is_string($value)) {
            return null;
        }

        return [
            'field' => $field,
            'op' => $op,
            'value' => $value,
        ];
    }

    /**
     * Map NL / spaced status labels to the exact DB enum strings so filters match MySQL rows.
     */
    private function normalizeBookStatusFilterValue(string $value): string
    {
        $trim = trim($value);
        $lower = strtolower($trim);

        if (in_array($lower, BookStatus::values(), true)) {
            return $lower;
        }

        $snake = strtolower(preg_replace('/[\s\-]+/', '_', $trim));
        $snake = preg_replace('/_+/', '_', $snake);
        if (in_array($snake, BookStatus::values(), true)) {
            return $snake;
        }

        if (preg_match('/^tbr$/i', $trim)) {
            return BookStatus::PlanToRead->value;
        }
        if (preg_match('/want\s+to\s+read|plan(?:ned)?\s+to\s+read/i', $trim)) {
            return BookStatus::PlanToRead->value;
        }
        if (preg_match('/\bplan\s+to\s+read\b/i', $trim)) {
            return BookStatus::PlanToRead->value;
        }
        if ($snake === 'want_to_read' || $snake === 'to_read') {
            return BookStatus::PlanToRead->value;
        }

        if (preg_match('/\bcompleted\b|\bfinished\b|\bdone\b/i', $trim)) {
            return BookStatus::Completed->value;
        }
        if (preg_match('/\bpaused\b|\bon\s+hold\b/i', $trim)) {
            return BookStatus::Paused->value;
        }
        if (preg_match('/\breading\b/i', $trim) && ! preg_match('/plan|want|tbr/i', $trim)) {
            return BookStatus::Reading->value;
        }

        return $lower;
    }

    /**
     * Map common spellings to the canonical genres.name stored in the DB (seeders use "Sci-Fi", etc.).
     */
    private function normalizeGenreFilterValue(string $value): string
    {
        $trim = trim($value);
        if ($trim === '') {
            return $value;
        }

        $fold = strtolower(preg_replace('/[\s\-_\.]+/u', '', $trim));

        // sci fi, sci-fi, sci_fi, scifi, sci.fi, SCIFI, science fiction, sciencefiction
        if ($fold === 'scifi' || $fold === 'scify' || $fold === 'sciencefiction') {
            return 'Sci-Fi';
        }

        return $trim;
    }
}

