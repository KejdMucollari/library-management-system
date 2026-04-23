<?php

namespace App\Services\Ai;

use App\Models\Book;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
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
        $apiKey = (string) config('services.openai.key', env('GROQ_API_KEY'));
        if ($apiKey === '') {
            throw new \RuntimeException('GROQ_API_KEY is not configured.');
        }

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

        $systemLines = [
            'You translate questions into JSON query specs for a library app.',
            'Return ONLY valid JSON (no markdown).',
            'The database has tables: users and books.',
            'The books table has only these fields: id, title, author, genre, status, user_id, created_at, updated_at.',
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
            '- For "most expensive books", from="books", order_by price desc, limit 5.',
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

        return $this->validateAndNormalizeSpec($spec, $isAdmin);
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
     * Execute a validated spec safely using Query Builder/Eloquent.
     */
    public function execute(array $spec, User $actor): AiQueryResult
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
        }

        $spec = $this->validateAndNormalizeSpec($spec, $actor->isAdmin());

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
            $fn = $agg['fn'];
            $field = $agg['field'];
            $as = $agg['as'];

            if (!in_array($fn, self::ALLOWED_AGG, true)) {
                throw new \RuntimeException("Aggregate not allowed: {$fn}");
            }

            if ($field !== '*' && !in_array($field, $allowedFields, true)) {
                throw new \RuntimeException("Aggregate field not allowed: {$field}");
            }

            if (!is_string($as) || $as === '') {
                throw new \RuntimeException('Aggregate alias required.');
            }

            $aggAliases[] = $as;
            $aggField = $field;
            if ($field !== '*' && $from === 'books' && $usesGenreName) {
                $aggField = $field === 'genre' ? $genreCol() : $bookCol($field);
            }
            $expr = $field === '*' ? "{$fn}(*)" : "{$fn}({$aggField})";
            $select[] = "{$expr} as {$as}";
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
    private function validateAndNormalizeSpec(array $spec, bool $isAdmin): array
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
            if (!is_array($a)) {
                continue;
            }
            $fn = $a['fn'] ?? null;
            $field = $a['field'] ?? null;
            $as = $a['as'] ?? null;
            if (!is_string($fn) || !is_string($field) || !is_string($as)) {
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
            if (!is_array($o)) {
                continue;
            }
            $field = $o['field'] ?? null;
            $dir = $o['dir'] ?? null;
            if (!is_string($field) || !is_string($dir)) {
                continue;
            }
            $orderNorm[] = [
                'field' => $field,
                'dir' => strtolower($dir),
            ];
        }

        $filtersNorm = [];
        foreach ($filters as $f) {
            $n = $this->normalizeFilterEntry($f);
            if ($n !== null) {
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
}

