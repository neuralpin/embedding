<?php

namespace Tests\Feature;

use App\Models\ContentChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_hybrid_search_returns_chunks_and_their_grouped_entries(): void
    {
        $chunk = ContentChunk::factory()->create(['content' => 'La vitamina C participa en funciones antioxidantes.']);
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:11434/api/embed' => Http::response(['embeddings' => [array_fill(0, 256, 0.125)]]),
        ]);

        $this->getJson(route('search.chunks', ['q' => 'vitamina']))
            ->assertOk()
            ->assertJsonPath('chunks.0.id', $chunk->id)
            ->assertJsonPath('entries.0.id', $chunk->contentEntry->id)
            ->assertJsonPath('entries.0.matched_chunks', 1);

        Http::assertSentCount(1);
    }

    public function test_rag_uses_the_selected_chunks_without_creating_an_embedding(): void
    {
        $chunk = ContentChunk::factory()->create(['content' => 'La vitamina C participa en funciones antioxidantes.']);
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:11434/api/generate' => Http::response(['response' => 'La vitamina C tiene una función antioxidante.']),
        ]);

        $this->postJson(route('search.rag'), ['q' => '¿Para qué sirve la vitamina C?', 'chunk_ids' => [$chunk->id]])
            ->assertOk()
            ->assertJsonPath('summary', 'La vitamina C tiene una función antioxidante.');

        Http::assertSentCount(1);
    }
}
