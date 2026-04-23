<?php

namespace Database\Factories;

use App\Models\Genre;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Genre>
 */
class GenreFactory extends Factory
{
    protected $model = Genre::class;

    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Fantasy',
            'Sci-Fi',
            'Mystery',
            'Romance',
            'Nonfiction',
            'History',
            'Biography',
        ]);

        $base = Str::of($name)->lower()->replace(' ', '-')->toString();

        return [
            'name' => $name,
            // Slug is unique in DB; add a small suffix so tests can create many genres safely.
            'slug' => $base.'-'.Str::lower(Str::random(6)),
        ];
    }
}

