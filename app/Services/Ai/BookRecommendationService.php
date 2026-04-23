<?php

namespace App\Services\Ai;

use App\Enums\BookStatus;
use App\Models\Book;
use App\Models\BookRecommendation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class BookRecommendationService
{
    private const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';

    /**
     * @return array{recommendations: array<int, array{title: string, author: string, genre: string, reason: string}>, message: string|null}
     */
    public function recommend(int $userId): array
    {
        $apiKey = (string) config('services.openai.key', env('GROQ_API_KEY'));
        if ($apiKey === '') {
            return [
                'recommendations' => [],
                'message' => 'Recommendations are unavailable right now (missing GROQ_API_KEY).',
            ];
        }

        $books = Book::query()
            ->where('user_id', $userId)
            ->with(['genre:id,name'])
            ->orderByDesc('updated_at')
            ->limit(120)
            ->get(['title', 'author', 'status', 'genre_id']);

        if ($books->isEmpty()) {
            return [
                'recommendations' => [],
                'message' => "Add some books to your library first and we'll suggest what to read next.",
            ];
        }

        $previousTitles = BookRecommendation::query()
            ->where('user_id', $userId)
            ->pluck('title')
            ->filter()
            ->map(fn ($t) => mb_strtolower(trim((string) $t)))
            ->unique()
            ->values()
            ->all();

        $existingTitles = $books
            ->pluck('title')
            ->filter()
            ->map(fn ($t) => mb_strtolower(trim((string) $t)))
            ->unique()
            ->values()
            ->all();

        $bookList = $books->map(function (Book $b) {
            $title = (string) $b->title;
            $author = (string) ($b->author ?? '—');
            $genre = (string) ($b->genre?->name ?? 'Unknown');
            $status = $b->status instanceof BookStatus ? $b->status->value : (string) $b->status;

            return "{$title} by {$author} (Genre: {$genre}, Status: {$status})";
        })->join("\n");

        $exclusionList = $previousTitles !== []
            ? "Do NOT recommend any of these previously suggested books:\n".implode("\n", $previousTitles)
            : '';

        $prompt = implode("\n", [
            "The user's current library (do not recommend any titles already present):",
            $bookList,
            '',
            $exclusionList,
            '',
            'Recommend exactly 3 books they would enjoy that are NOT already in their list.',
            'Also do NOT recommend any titles from the exclusion list above.',
            'Return ONLY a valid JSON array (no markdown, no extra text):',
            '[',
            '  { "title": "Book Title", "author": "Author Name", "genre": "Genre", "reason": "One sentence why they would enjoy this" }',
            ']',
        ]);

        try {
            $resp = Http::timeout(30)
                ->withToken($apiKey)
                ->post(self::GROQ_URL, [
                    'model' => 'llama-3.1-8b-instant',
                    'temperature' => 0.3,
                    'max_tokens' => 700,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => implode("\n", [
                                'You are a book recommendation assistant.',
                                'You only return valid JSON arrays.',
                                'Never include markdown, backticks, or explanations outside JSON.',
                                'Ensure the 3 recommendations are not already in the user\'s list.',
                                'Never recommend books already present in the exclusion list.',
                            ]),
                        ],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ])
                ->throw()
                ->json();
        } catch (ConnectionException) {
            return [
                'recommendations' => [],
                'message' => 'Could not generate recommendations right now. Try again later.',
            ];
        } catch (\Throwable) {
            return [
                'recommendations' => [],
                'message' => 'Could not generate recommendations right now. Try again later.',
            ];
        }

        $content = data_get($resp, 'choices.0.message.content');
        if (!is_string($content) || trim($content) === '') {
            return [
                'recommendations' => [],
                'message' => 'Could not generate recommendations right now. Try again later.',
            ];
        }

        $clean = preg_replace('/```(?:json)?\s*|\s*```/i', '', $content) ?? $content;
        $decoded = json_decode(trim($clean), true);
        if (!is_array($decoded)) {
            return [
                'recommendations' => [],
                'message' => 'Could not generate recommendations right now. Try again later.',
            ];
        }

        $recs = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = trim((string) ($item['title'] ?? ''));
            $author = trim((string) ($item['author'] ?? ''));
            $genre = trim((string) ($item['genre'] ?? ''));
            $reason = trim((string) ($item['reason'] ?? ''));
            if ($title === '' || $author === '' || $genre === '' || $reason === '') {
                continue;
            }

            $tKey = mb_strtolower($title);
            if (in_array($tKey, $existingTitles, true)) {
                continue;
            }
            if (in_array($tKey, $previousTitles, true)) {
                continue;
            }

            $recs[] = [
                'title' => $title,
                'author' => $author,
                'genre' => $genre,
                'reason' => $reason,
            ];

            if (count($recs) >= 3) {
                break;
            }
        }

        if ($recs === []) {
            return [
                'recommendations' => [],
                'message' => 'Could not generate recommendations right now. Try again later.',
            ];
        }

        foreach ($recs as $rec) {
            BookRecommendation::create([
                'user_id' => $userId,
                'title' => $rec['title'],
                'author' => $rec['author'],
                'genre' => $rec['genre'],
                'reason' => $rec['reason'],
            ]);
        }

        return [
            'recommendations' => $recs,
            'message' => null,
        ];
    }
}

