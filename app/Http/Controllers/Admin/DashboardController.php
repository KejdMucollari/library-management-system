<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            abort(403);
        }

        $totalBooks = Book::query()->count();
        $totalUsers = User::query()->count();

        $topGenreRow = Genre::query()
            ->selectRaw('genres.name as name, count(books.id) as c')
            ->join('books', 'books.genre_id', '=', 'genres.id')
            ->groupBy('genres.id', 'genres.name')
            ->orderByDesc('c')
            ->first();

        $mostPopularGenre = $topGenreRow?->name ?? '—';

        $completed = Book::query()->where('status', 'completed')->count();
        $reading = Book::query()->where('status', 'reading')->count();
        $planToRead = Book::query()->where('status', 'plan_to_read')->count();

        $completionRate = $totalBooks > 0 ? round(($completed / $totalBooks) * 100, 1) : 0.0;

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'totalBooks' => $totalBooks,
                'totalUsers' => $totalUsers,
                'mostPopularGenre' => $mostPopularGenre,
            ],
            'ai' => [
                'result' => $request->session()->get('ai.result'),
            ],
            'insights' => [
                'completed' => $completed,
                'reading' => $reading,
                'planToRead' => $planToRead,
                'completionRate' => $completionRate,
            ],
        ]);
    }
}

