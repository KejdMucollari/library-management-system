<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_submit_ai_query(): void
    {
        config()->set('services.openai.key', 'test');
        $admin = User::factory()->create(['is_admin' => true]);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'type' => 'metric',
                                'scope' => 'all',
                                'from' => 'books',
                                'select' => [],
                                'aggregates' => [['fn' => 'count', 'field' => '*', 'as' => 'total']],
                                'group_by' => [],
                                'order_by' => [],
                                'limit' => 1,
                                'filters' => [],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $resp = $this->actingAs($admin)->post('/admin/ai', [
            'question' => 'How many books are there?',
        ]);

        $resp->assertRedirect('/admin');
        $resp->assertSessionHas('ai.result');
    }

    public function test_regular_user_cannot_access_admin_ai_route(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->post('/admin/ai', [
            'question' => 'x',
        ])->assertForbidden();
    }

    public function test_empty_query_returns_validation_error(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->from('/admin')->actingAs($admin)->post('/admin/ai', [
            'question' => '',
        ])->assertSessionHasErrors('question');
    }

    public function test_user_ai_query_is_scoped_to_own_books(): void
    {
        config()->set('services.openai.key', 'test');
        $user = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create(['is_admin' => false]);

        Book::factory()->for($user)->count(2)->create();
        Book::factory()->for($other)->count(3)->create();

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            // malicious/incorrect spec asks for all
                            'content' => json_encode([
                                'type' => 'table',
                                'scope' => 'all',
                                'from' => 'books',
                                'select' => ['id', 'user_id'],
                                'aggregates' => [],
                                'group_by' => [],
                                'order_by' => [],
                                'limit' => 100,
                                'filters' => [],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $resp = $this->actingAs($user)->post('/ai', [
            'question' => 'Show all books',
        ]);

        $resp->assertRedirect('/books');
        $result = session('ai.result');

        $this->assertIsArray($result);
        $this->assertCount(2, $result['rows']);
        foreach ($result['rows'] as $row) {
            $this->assertSame($user->id, (int) $row['user_id']);
        }
    }
}

