<?php

namespace App\Http\Controllers;

use App\Enums\BookStatus;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BookController extends Controller
{
    /**
     * Books listing after CRUD: My Library for users, All Books for admins.
     */
    private function booksListingRedirect(Request $request): string
    {
        return $request->user()->isAdmin()
            ? route('admin.books.index')
            : route('books.index');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Book::class);

        $user = $request->user();
        $isAdmin = $user?->isAdmin() ?? false;

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'genre_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $query = Book::query()
            ->with(['user:id,name,email', 'genre:id,name'])
            ->when(!$isAdmin, fn ($q) => $q->where('user_id', $user->id))
            ->when($filters['q'] ?? null, function ($q, string $term) {
                $q->where(function ($qq) use ($term) {
                    $qq->where('title', 'like', "%{$term}%")
                        ->orWhere('author', 'like', "%{$term}%");
                });
            })
            ->when($filters['genre_id'] ?? null, fn ($q, int $genreId) => $q->where('genre_id', $genreId))
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->latest();

        $books = $query->paginate(10)->withQueryString();

        $payload = [
            'books' => $books,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'genre_id' => $filters['genre_id'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'statusOptions' => BookStatus::values(),
            'genres' => Genre::query()->orderBy('name')->get(['id', 'name']),
            'isAdmin' => $isAdmin,
            'ai' => [
                'result' => session('ai.result'),
            ],
            'recommendations' => session('recommendations'),
        ];

        // Extra UX data for regular users (My Library redesign).
        if (!$isAdmin) {
            $base = Book::query()->where('user_id', $user->id);

            $totalBooks = (clone $base)->count();
            $reading = (clone $base)->where('status', 'reading')->count();
            $completed = (clone $base)->where('status', 'completed')->count();

            $fav = (clone $base)
                ->selectRaw('genres.name as name, count(books.id) as c')
                ->leftJoin('genres', 'genres.id', '=', 'books.genre_id')
                ->whereNotNull('books.genre_id')
                ->groupBy('genres.id', 'genres.name')
                ->orderByDesc('c')
                ->first();

            $favouriteGenre = $fav?->name ?? '—';

            $genreRows = (clone $base)
                ->selectRaw('genres.id as id, genres.name as name, count(books.id) as c')
                ->leftJoin('genres', 'genres.id', '=', 'books.genre_id')
                ->whereNotNull('books.genre_id')
                ->groupBy('genres.id', 'genres.name')
                ->orderByDesc('c')
                ->limit(8)
                ->get();

            $genreBreakdown = $genreRows->map(function ($r) use ($totalBooks) {
                $count = (int) $r->c;
                $pct = $totalBooks > 0 ? round(($count / $totalBooks) * 100) : 0;

                return [
                    'id' => (int) $r->id,
                    'name' => (string) $r->name,
                    'count' => $count,
                    'percent' => $pct,
                ];
            })->values();

            $completionRate = $totalBooks > 0 ? round(($completed / $totalBooks) * 100, 1) : 0.0;

            $recentAdded = (clone $base)->where('created_at', '>=', now()->subDays(30))->count();

            $payload['stats'] = [
                'totalBooks' => $totalBooks,
                'reading' => $reading,
                'completed' => $completed,
                'favouriteGenre' => $favouriteGenre,
            ];

            $payload['genreBreakdown'] = $genreBreakdown;

            $payload['insights'] = [
                [
                    'title' => 'Completion rate',
                    'body' => $completionRate.'% of your library is completed.',
                ],
                [
                    'title' => 'Recently added',
                    'body' => $recentAdded.' book(s) added in the last 30 days.',
                ],
                [
                    'title' => 'Top genre',
                    'body' => $favouriteGenre === '—' ? 'Add genres to see your top genre.' : $favouriteGenre.' is your most common genre.',
                ],
            ];
        }

        return Inertia::render('Books/Index', [
            ...$payload,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Book::class);

        return Inertia::render('Books/Create', [
            'statusOptions' => BookStatus::values(),
            'genres' => Genre::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreBookRequest $request)
    {
        $book = Book::create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return redirect()->to($this->booksListingRedirect($request))->with('success', 'Book created.');
    }

    public function edit(Book $book)
    {
        $this->authorize('update', $book);

        return Inertia::render('Books/Edit', [
            'book' => $book,
            'statusOptions' => BookStatus::values(),
            'genres' => Genre::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        $this->authorize('update', $book);

        $book->update($request->validated());

        return redirect()->to($this->booksListingRedirect($request))->with('success', 'Book updated.');
    }

    public function destroy(Request $request, Book $book)
    {
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()->to($this->booksListingRedirect($request))->with('success', 'Book deleted.');
    }
}
