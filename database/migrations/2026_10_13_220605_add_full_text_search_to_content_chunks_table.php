<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE content_chunks ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('spanish', coalesce(content, ''))) STORED");
        DB::statement('CREATE INDEX content_chunks_search_vector_idx ON content_chunks USING gin (search_vector)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS content_chunks_search_vector_idx');
        DB::statement('ALTER TABLE content_chunks DROP COLUMN IF EXISTS search_vector');
    }
};
