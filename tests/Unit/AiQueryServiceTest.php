<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\User;
use App\Services\Ai\AiQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_scope_is_forced_to_me_regardless_of_ai_spec(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create(['is_admin' => false]);

        Book::factory()->for($user)->create(['title' => 'Mine']);
        Book::factory()->for($other)->create(['title' => 'Theirs']);

        $service = app(AiQueryService::class);

        $spec = [
            'type' => 'table',
            'scope' => 'all', // should be forced to me for non-admin
            'from' => 'books',
            'select' => ['id', 'title', 'user_id'],
            'aggregates' => [],
            'group_by' => [],
            'order_by' => [['field' => 'id', 'dir' => 'asc']],
            'limit' => 100,
            'filters' => [],
        ];

        $result = $service->execute($spec, $user)->toArray();

        $this->assertCount(1, $result['rows']);
        $this->assertSame('Mine', $result['rows'][0]['title']);
        $this->assertSame($user->id, (int) $result['rows'][0]['user_id']);
    }

    public function test_non_admin_users_table_query_returns_out_of_scope_message(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $service = app(AiQueryService::class);

        $spec = [
            'type' => 'table',
            'scope' => 'all',
            'from' => 'users',
            'select' => ['id', 'name'],
            'aggregates' => [],
            'group_by' => [],
            'order_by' => [],
            'limit' => 10,
            'filters' => [],
        ];

        $result = $service->execute($spec, $user)->toArray();

        $this->assertSame([], $result['columns']);
        $this->assertSame([], $result['rows']);
        $this->assertStringContainsString('only answer questions about your own books', strtolower($result['summary']));
    }

    public function test_unknown_field_in_model_response_returns_unknown_field_message(): void
    {
        config()->set('services.openai.key', 'test');
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'error' => 'unknown_field',
                                'field' => 'publisher',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(AiQueryService::class);
        $out = $service->translateToSpec('Show books by publisher', isAdmin: true);

        $this->assertSame([], $out['columns']);
        $this->assertSame([], $out['rows']);
        $this->assertStringContainsString('publisher', $out['summary']);
    }

    public function test_empty_result_set_returns_no_results_message(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Book::factory()->for($user)->create(['title' => 'Exists']);

        $service = app(AiQueryService::class);

        $spec = [
            'type' => 'table',
            'scope' => 'me',
            'from' => 'books',
            'select' => ['id', 'title'],
            'aggregates' => [],
            'group_by' => [],
            'order_by' => [],
            'limit' => 25,
            'filters' => [
                ['field' => 'title', 'op' => '=', 'value' => '__does_not_exist__'],
            ],
        ];

        $result = $service->execute($spec, $user)->toArray();

        $this->assertSame([], $result['columns']);
        $this->assertSame([], $result['rows']);
        $this->assertStringContainsString('no books found', strtolower($result['summary']));
    }
}

