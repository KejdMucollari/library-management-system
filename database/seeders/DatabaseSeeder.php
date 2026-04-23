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

        // Seed 100 books across these users (including admin).
        $owners = $users->push($admin);

        Book::factory(100)->make()->each(function (Book $book) use ($owners) {
            $book->user_id = $owners->random()->id;
            $book->save();
        });
    }
}
