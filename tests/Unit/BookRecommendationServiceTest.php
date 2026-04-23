<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\BookRecommendation;
use App\Models\User;
use App\Services\Ai\BookRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookRecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_no_books_returns_empty_library_message(): void
    {
        $user = User::factory()->create();
        $service = app(BookRecommendationService::class);

        $result = $service->recommend($user->id);

        $this->assertSame([], $result['recommendations']);
        $this->assertStringContainsString('add some books', strtolower((string) $result['message']));
    }

    public function test_previous_titles_are_included_in_exclusion_list_sent_to_ai(): void
    {
        config()->set('services.openai.key', 'test');

        $user = User::factory()->create();
        Book::factory()->for($user)->create(['title' => 'My Book']);
        BookRecommendation::factory()->create([
            'user_id' => $user->id,
            'title' => 'Dune',
            'author' => 'Frank Herbert',
            'genre' => 'Sci-Fi',
            'reason' => 'Test',
        ]);

        $sawExclusion = false;

        Http::fake(function ($request) use (&$sawExclusion) {
            $payload = $request->data();
            $content = (string) data_get($payload, 'messages.1.content', '');
            if (str_contains(strtolower($content), 'do not recommend any of these previously suggested books')
                && str_contains(strtolower($content), "dune")) {
                $sawExclusion = true;
            }

            return Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                ['title' => 'Neuromancer', 'author' => 'William Gibson', 'genre' => 'Sci-Fi', 'reason' => 'You like sci-fi.'],
                                ['title' => 'Snow Crash', 'author' => 'Neal Stephenson', 'genre' => 'Sci-Fi', 'reason' => 'You like sci-fi.'],
                                ['title' => 'Hyperion', 'author' => 'Dan Simmons', 'genre' => 'Sci-Fi', 'reason' => 'You like sci-fi.'],
                            ]),
                        ],
                    ],
                ],
            ], 200);
        });

        $service = app(BookRecommendationService::class);
        $result = $service->recommend($user->id);

        $this->assertTrue($sawExclusion);
        $this->assertCount(3, $result['recommendations']);
    }
}

