<?php

namespace Tests\Feature;

use App\Enums\BookStatus;
use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_book(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $genre = Genre::factory()->create();

        $resp = $this->actingAs($user)->post('/books', [
            'title' => 'New Book',
            'author' => 'Author',
            'genre_id' => $genre->id,
            'status' => BookStatus::values()[0],
            'pages' => 123,
            'price' => 9.99,
        ]);

        $resp->assertRedirect('/books');
        $this->assertDatabaseHas('books', [
            'title' => 'New Book',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_edit_own_book(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $book = Book::factory()->for($user)->create(['title' => 'Old']);

        $resp = $this->actingAs($user)->put("/books/{$book->id}", [
            'title' => 'Updated',
            'author' => $book->author,
            'genre_id' => $book->genre_id,
            'status' => $book->status->value ?? (string) $book->status,
            'pages' => $book->pages,
            'price' => $book->price,
        ]);

        $resp->assertRedirect('/books');
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Updated',
        ]);
    }

    public function test_user_can_delete_own_book(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $book = Book::factory()->for($user)->create();

        $resp = $this->actingAs($user)->delete("/books/{$book->id}");

        $resp->assertRedirect('/books');
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_user_cannot_edit_another_users_book(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $book = Book::factory()->for($owner)->create(['title' => 'Owner Book']);

        $resp = $this->actingAs($other)->put("/books/{$book->id}", [
            'title' => 'Hack',
            'author' => $book->author,
            'genre_id' => $book->genre_id,
            'status' => $book->status->value ?? (string) $book->status,
        ]);

        $resp->assertForbidden();
        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => 'Owner Book']);
    }

    public function test_user_cannot_delete_another_users_book(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $book = Book::factory()->for($owner)->create();

        $resp = $this->actingAs($other)->delete("/books/{$book->id}");

        $resp->assertForbidden();
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_admin_can_edit_any_book(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create(['is_admin' => false]);
        $book = Book::factory()->for($owner)->create(['title' => 'Before']);

        $resp = $this->actingAs($admin)->put("/books/{$book->id}", [
            'title' => 'After',
            'author' => $book->author,
            'genre_id' => $book->genre_id,
            'status' => $book->status->value ?? (string) $book->status,
            'pages' => $book->pages,
            'price' => $book->price,
        ]);

        $resp->assertRedirect('/admin/books');
        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => 'After']);
    }

    public function test_admin_can_delete_any_book(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create(['is_admin' => false]);
        $book = Book::factory()->for($owner)->create();

        $resp = $this->actingAs($admin)->delete("/books/{$book->id}");

        $resp->assertRedirect('/admin/books');
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_admin_can_see_all_books(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        Book::factory()->for($u1)->create(['title' => 'Book 1']);
        Book::factory()->for($u2)->create(['title' => 'Book 2']);

        $resp = $this->actingAs($admin)->get('/books');

        $resp->assertOk();
        $resp->assertSee('Book 1');
        $resp->assertSee('Book 2');
    }
}

