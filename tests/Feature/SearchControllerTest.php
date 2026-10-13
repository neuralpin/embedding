<?php

namespace Tests\Feature;

use App\Models\ContentChunk;
use App\Models\ContentEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_entries_search_does_not_call_ollama(): void
    {
        Http::preventStrayRequests();

        $this->getJson(route('search.entries'))
            ->assertOk()
            ->assertJsonStructure(['entries' => ['data', 'current_page']]);

        Http::assertNothingSent();
    }

    public function test_entries_search_matches_each_query_term(): void
    {
        $entry = ContentEntry::factory()->create(['title' => 'Indice glucemico para diabetes']);
        ContentEntry::factory()->create(['title' => 'Índice nutricional']);

        $this->getJson(route('search.entries', ['q' => 'indice glucemico']))
            ->assertOk()
            ->assertJsonPath('entries.total', 1)
            ->assertJsonPath('entries.data.0.id', $entry->id);
    }

    public function test_chunks_search_only_calls_the_embedding_endpoint(): void
    {
        $chunk = ContentChunk::factory()->create();
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:11434/api/embed' => Http::response(['embeddings' => [array_fill(0, 256, 0.125)]]),
        ]);

        $this->getJson(route('search.chunks', ['q' => 'vitamina']))
            ->assertOk()
            ->assertJsonPath('chunks.0.id', $chunk->id);

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
