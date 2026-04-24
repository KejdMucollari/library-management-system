<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Genre::factory()->createMany([
            ['name' => 'Fantasy', 'slug' => 'fantasy'],
            ['name' => 'Sci-Fi', 'slug' => 'sci-fi'],
            ['name' => 'Mystery', 'slug' => 'mystery'],
            ['name' => 'Romance', 'slug' => 'romance'],
            ['name' => 'Nonfiction', 'slug' => 'nonfiction'],
            ['name' => 'History', 'slug' => 'history'],
            ['name' => 'Biography', 'slug' => 'biography'],
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'is_admin' => true,
        ]);

        $users = User::factory(4)->create();

        // Four regular users, then admin — same order as $owners below.
        $owners = $users->push($admin)->values();

        $genreIds = Genre::query()->pluck('id');

        // Uneven split (100 total) so admin AI can rank "most books" vs "fewest books" per user.
        $booksPerOwner = [42, 28, 15, 8, 7];

        foreach ($owners as $index => $owner) {
            $count = $booksPerOwner[$index] ?? 0;
            for ($i = 0; $i < $count; $i++) {
                Book::factory()->create([
                    'user_id' => $owner->id,
                    'genre_id' => $genreIds->random(),
                ]);
            }
        }
    }
}
