<?php

namespace Database\Factories;

use App\Models\BookRecommendation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookRecommendation>
 */
class BookRecommendationFactory extends Factory
{
    protected $model = BookRecommendation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'author' => $this->faker->name(),
            'genre' => $this->faker->word(),
            'reason' => $this->faker->sentence(),
        ];
    }
}

