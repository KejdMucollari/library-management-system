<?php

namespace Tests\Unit;

use App\Enums\BookStatus;
use App\Models\Book;
use App\Models\Genre;
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

    public function test_singular_most_expensive_book_question_limits_to_one_row(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Book::factory()->for($admin)->create(['title' => 'Cheap', 'price' => 5.00]);
        Book::factory()->for($admin)->create(['title' => 'Pricy', 'price' => 99.99]);
        Book::factory()->for($admin)->create(['title' => 'Mid', 'price' => 40.00]);

        $service = app(AiQueryService::class);
        $spec = [
            'type' => 'table',
            'scope' => 'all',
            'from' => 'books',
            'select' => ['id', 'title', 'author', 'price', 'genre'],
            'aggregates' => [],
            'group_by' => [],
            'order_by' => [['field' => 'price', 'dir' => 'desc']],
            'limit' => 10,
            'filters' => [],
        ];

        $result = $service->execute($spec, $admin, 'which is the most expensive book')->toArray();

        $this->assertCount(1, $result['rows']);
        $this->assertSame('Pricy', $result['rows'][0]['title']);
    }

    public function test_plural_most_expensive_books_question_keeps_higher_limit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Book::factory()->for($admin)->create(['title' => 'Cheap', 'price' => 5.00]);
        Book::factory()->for($admin)->create(['title' => 'Pricy', 'price' => 99.99]);
        Book::factory()->for($admin)->create(['title' => 'Mid', 'price' => 40.00]);

        $service = app(AiQueryService::class);
        $spec = [
            'type' => 'table',
            'scope' => 'all',
            'from' => 'books',
            'select' => ['id', 'title', 'author', 'price', 'genre'],
            'aggregates' => [],
            'group_by' => [],
            'order_by' => [['field' => 'price', 'dir' => 'desc']],
            'limit' => 10,
            'filters' => [],
        ];

        $result = $service->execute($spec, $admin, 'which are the most expensive books')->toArray();

        $this->assertCount(3, $result['rows']);
    }

    public function test_max_pages_aggregate_collapses_for_non_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Book::factory()->for($user)->create(['title' => 'Short', 'pages' => 50]);
        Book::factory()->for($user)->create(['title' => 'Long', 'pages' => 900]);

        $service = app(AiQueryService::class);
        $spec = [
            'type' => 'table',
            'scope' => 'me',
            'from' => 'books',
            'select' => ['id', 'title', 'author', 'genre', 'status', 'user_id', 'pages'],
            'aggregates' => [
                ['fn' => 'max', 'field' => 'pages', 'as' => 'max_pages'],
            ],
            'group_by' => [],
            'order_by' => [['field' => 'max_pages', 'dir' => 'desc']],
            'limit' => 1,
            'filters' => [],
        ];

        $result = $service->execute($spec, $user, 'which is the book with the most pages')->toArray();

        $this->assertCount(1, $result['rows']);
        $this->assertSame('Long', $result['rows'][0]['title']);
        $this->assertStringContainsString('900', (string) ($result['rows'][0]['pages'] ?? ''));
    }

    public function test_status_filter_plan_to_read_normalizes_spaced_label(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Book::factory()->for($user)->create([
            'title' => 'Later',
            'status' => BookStatus::PlanToRead,
        ]);
        Book::factory()->for($user)->create([
            'title' => 'Now',
            'status' => BookStatus::Reading,
        ]);

        $service = app(AiQueryService::class);
        $spec = [
            'type' => 'table',
            'scope' => 'me',
            'from' => 'books',
            'select' => ['title', 'status'],
            'aggregates' => [],
            'group_by' => [],
            'order_by' => [],
            'limit' => 25,
            'filters' => [
                ['field' => 'status', 'op' => '=', 'value' => 'plan to read'],
            ],
        ];

        $result = $service->execute($spec, $user)->toArray();

        $this->assertCount(1, $result['rows']);
        $this->assertSame('Later', $result['rows'][0]['title']);
    }

    public function test_genre_filter_normalizes_sci_fi_spellings(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $sciFi = Genre::factory()->create(['name' => 'Sci-Fi', 'slug' => 'sci-fi-'.uniqid()]);
        $fantasy = Genre::factory()->create(['name' => 'Fantasy', 'slug' => 'fantasy-'.uniqid()]);
        Book::factory()->for($user)->create(['title' => 'Nebula', 'genre_id' => $sciFi->id]);
        Book::factory()->for($user)->create(['title' => 'Castle', 'genre_id' => $fantasy->id]);

        $service = app(AiQueryService::class);

        foreach (['sci fi', 'SCI-FI', 'science fiction', 'ScI.Fi'] as $label) {
            $spec = [
                'type' => 'table',
                'scope' => 'me',
                'from' => 'books',
                'select' => ['title', 'genre'],
                'aggregates' => [],
                'group_by' => [],
                'order_by' => [],
                'limit' => 25,
                'filters' => [
                    ['field' => 'genre', 'op' => '=', 'value' => $label],
                ],
            ];
            $result = $service->execute($spec, $user)->toArray();
            $this->assertCount(1, $result['rows'], $label);
            $this->assertSame('Nebula', $result['rows'][0]['title']);
        }
    }

    public function test_max_created_at_aggregate_collapses_for_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Book::factory()->for($admin)->create(['title' => 'OldBook', 'created_at' => now()->subDays(5)]);
        Book::factory()->for($admin)->create(['title' => 'NewBook', 'created_at' => now()]);

        $service = app(AiQueryService::class);
        $spec = [
            'type' => 'table',
            'scope' => 'all',
            'from' => 'books',
            'select' => ['id', 'title', 'created_at'],
            'aggregates' => [
                ['fn' => 'max', 'field' => 'created_at', 'as' => 'latest'],
            ],
            'group_by' => [],
            'order_by' => [['field' => 'latest', 'dir' => 'desc']],
            'limit' => 1,
            'filters' => [],
        ];

        $result = $service->execute($spec, $admin, 'which is the latest added book')->toArray();

        $this->assertCount(1, $result['rows']);
        $this->assertSame('NewBook', $result['rows'][0]['title']);
        $this->assertStringContainsString('latest added', strtolower($result['summary'] ?? ''));
    }

    public function test_non_admin_execute_blocks_group_by_user_id(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Book::factory()->for($user)->create(['title' => 'Mine']);

        $service = app(AiQueryService::class);
        $spec = [
            'type' => 'ranking',
            'scope' => 'me',
            'from' => 'books',
            'select' => ['user_id'],
            'aggregates' => [['fn' => 'count', 'field' => '*', 'as' => 'count']],
            'group_by' => ['user_id'],
            'order_by' => [['field' => 'count', 'dir' => 'desc']],
            'limit' => 1,
            'filters' => [],
        ];

        $result = $service->execute($spec, $user)->toArray();

        $this->assertSame([], $result['rows']);
        $this->assertStringContainsString('own books', strtolower($result['summary']));
    }
}

