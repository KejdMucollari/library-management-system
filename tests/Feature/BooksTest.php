<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_own_books_index(): void
    {
        $user = User::factory()->create();
        Book::factory()->for($user)->create(['title' => 'My Book']);

        $this->actingAs($user)
            ->get('/books')
            ->assertOk()
            ->assertSee('Books')
            ->assertSee('My Book');
    }

    public function test_user_cannot_edit_other_users_book(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $book = Book::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get("/books/{$book->id}/edit")
            ->assertForbidden();
    }
}

