<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookRecommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendations_are_stored_in_database(): void
    {
        config()->set('services.openai.key', 'test');
        $user = User::factory()->create();
        Book::factory()->for($user)->create(['title' => 'Existing']);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                ['title' => 'Dune', 'author' => 'Frank Herbert', 'genre' => 'Sci-Fi', 'reason' => 'You like sci-fi.'],
                                ['title' => 'Foundation', 'author' => 'Isaac Asimov', 'genre' => 'Sci-Fi', 'reason' => 'You like sci-fi.'],
                                ['title' => 'Hyperion', 'author' => 'Dan Simmons', 'genre' => 'Sci-Fi', 'reason' => 'You like sci-fi.'],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $resp = $this->from('/books')->actingAs($user)->get('/recommendations');
        $resp->assertRedirect('/books');

        $this->assertDatabaseHas('book_recommendations', ['user_id' => $user->id, 'title' => 'Dune']);
        $this->assertDatabaseHas('book_recommendations', ['user_id' => $user->id, 'title' => 'Foundation']);
    }

    public function test_history_returns_only_own_recommendations(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        BookRecommendation::factory()->create(['user_id' => $u1->id, 'title' => 'Mine']);
        BookRecommendation::factory()->create(['user_id' => $u2->id, 'title' => 'Theirs']);

        $resp = $this->actingAs($u1)->get('/recommendations/history', [
            'Accept' => 'application/json',
        ]);

        $resp->assertOk();
        $resp->assertJsonCount(1);
        $resp->assertJsonFragment(['title' => 'Mine']);
        $resp->assertJsonMissing(['title' => 'Theirs']);
    }

    public function test_reset_clears_only_own_history(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        BookRecommendation::factory()->count(2)->create(['user_id' => $u1->id]);
        BookRecommendation::factory()->count(2)->create(['user_id' => $u2->id]);

        $this->from('/books')->actingAs($u1)->delete('/recommendations/reset')->assertRedirect('/books');

        $this->assertSame(0, BookRecommendation::query()->where('user_id', $u1->id)->count());
        $this->assertSame(2, BookRecommendation::query()->where('user_id', $u2->id)->count());
    }

    public function test_empty_library_returns_correct_message(): void
    {
        config()->set('services.openai.key', 'test');
        $user = User::factory()->create();

        $resp = $this->from('/books')->actingAs($user)->get('/recommendations');
        $resp->assertRedirect('/books');

        $payload = session('recommendations');
        $this->assertIsArray($payload);
        $this->assertSame([], $payload['recommendations']);
        $this->assertStringContainsString('add some books', strtolower((string) $payload['message']));
    }
}

