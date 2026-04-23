<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminAiQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ai_query_executes_allowlisted_spec(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $u1 = User::factory()->create();
        Book::factory()->for($u1)->count(3)->create();

        $u2 = User::factory()->create();
        Book::factory()->for($u2)->count(1)->create();

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'type' => 'ranking',
                                'scope' => 'all',
                                'from' => 'books',
                                'select' => ['user_id'],
                                'aggregates' => [['fn' => 'count', 'field' => '*', 'as' => 'book_count']],
                                'group_by' => ['user_id'],
                                'order_by' => [['field' => 'book_count', 'dir' => 'desc']],
                                'limit' => 1,
                                'filters' => [],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $resp = $this->actingAs($admin)->post('/admin/ai', [
            'question' => 'Who owns the most books?',
        ]);

        $resp->assertRedirect('/admin');
        $resp->assertSessionHas('ai.result');
    }

    public function test_non_admin_cannot_access_admin_ai(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->post('/admin/ai', ['question' => 'x'])
            ->assertForbidden();
    }
}

