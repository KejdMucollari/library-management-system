<?php

namespace Database\Factories;

use App\Enums\BookStatus;
use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'author' => $this->faker->name(),
            'genre_id' => Genre::factory(),
            'status' => $this->faker->randomElement(BookStatus::values()),
            'pages' => $this->faker->numberBetween(64, 800),
            'price' => $this->faker->randomFloat(2, 4.99, 89.99),
        ];
    }
}
