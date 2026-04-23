<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\BookStatus;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BookController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->isAdmin()) {
            abort(403);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'genre_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $books = Book::query()
            ->with(['user:id,name,email', 'genre:id,name'])
            ->when($filters['q'] ?? null, function ($q, string $term) {
                $q->where(function ($qq) use ($term) {
                    $qq->where('title', 'like', "%{$term}%")
                        ->orWhere('author', 'like', "%{$term}%");
                });
            })
            ->when($filters['genre_id'] ?? null, fn ($q, int $genreId) => $q->where('genre_id', $genreId))
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Books/Index', [
            'books' => $books,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'genre_id' => $filters['genre_id'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'statusOptions' => BookStatus::values(),
            'genres' => Genre::query()->orderBy('name')->get(['id', 'name']),
            'isAdmin' => true,
        ]);
    }
}
