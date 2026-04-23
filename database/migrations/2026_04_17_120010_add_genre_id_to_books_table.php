<?php

use App\Models\Genre;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->foreignId('genre_id')->nullable()->after('author')->constrained('genres')->nullOnDelete();
            $table->index(['genre_id']);
        });

        // Backfill genre_id from legacy books.genre (case-insensitive) if present.
        if (!Schema::hasColumn('books', 'genre')) {
            return;
        }

        $rows = DB::table('books')
            ->select('id', 'genre')
            ->whereNotNull('genre')
            ->get();

        foreach ($rows as $row) {
            $name = trim((string) $row->genre);
            if ($name === '') {
                continue;
            }

            $slug = Str::of($name)->lower()->trim()->replaceMatches('/\s+/', '-')->toString();

            /** @var Genre $genre */
            $genre = Genre::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name],
            );

            DB::table('books')->where('id', $row->id)->update([
                'genre_id' => $genre->id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropConstrainedForeignId('genre_id');
        });
    }
};

