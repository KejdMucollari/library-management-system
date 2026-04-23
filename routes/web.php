<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BookController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('books.index');
    }

    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/books', [BookController::class, 'index'])->name('books.index');
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');

    // User AI query route (personal scope only).
    Route::post('/ai', [\App\Http\Controllers\User\AiQueryController::class, 'query'])
        ->middleware('throttle:ai')
        ->name('ai.query');

    Route::get('/recommendations', [\App\Http\Controllers\RecommendationController::class, 'index'])
        ->name('recommendations');

    Route::delete('/recommendations/reset', [\App\Http\Controllers\RecommendationController::class, 'reset'])
        ->name('recommendations.reset');

    Route::get('/recommendations/history', function () {
        $userId = Auth::id();
        if (!$userId) {
            abort(403);
        }

        $history = \App\Models\BookRecommendation::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->select('title', 'author', 'genre', 'created_at')
            ->get();

        return response()->json($history);
    })->name('recommendations.history');
});

Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', \App\Http\Controllers\Admin\DashboardController::class)->name('dashboard');

        Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/edit', [\App\Http\Controllers\Admin\UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/books', [\App\Http\Controllers\Admin\BookController::class, 'index'])->name('books.index');
        Route::post('/ai', [\App\Http\Controllers\Admin\AiQueryController::class, 'query'])
            ->middleware('throttle:ai')
            ->name('ai.query');
    });

require __DIR__.'/auth.php';
