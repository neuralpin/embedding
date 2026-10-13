<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create('content_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('content');
            $table->timestamps();
            $table->unique(['content_entry_id', 'position']);
            $table->index('content_entry_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE content_chunks ADD COLUMN embedding vector(256) NULL');
            DB::statement('CREATE INDEX content_chunks_embedding_cosine_idx ON content_chunks USING hnsw (embedding vector_cosine_ops)');
        } else {
            Schema::table('content_chunks', function (Blueprint $table): void {
                $table->json('embedding')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_chunks');
    }
};
