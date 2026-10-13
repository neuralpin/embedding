<?php

namespace Tests\Feature;

use App\Jobs\GenerateChunkEmbedding;
use App\Models\ContentChunk;
use App\Services\OllamaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateChunkEmbeddingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_the_embedding_returned_by_ollama(): void
    {
        $chunk = ContentChunk::factory()->create(['content' => 'Contenido para vectorizar.']);
        $embedding = array_fill(0, 256, 0.125);
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:11434/api/embed' => Http::response(['embeddings' => [$embedding]]),
        ]);

        (new GenerateChunkEmbedding($chunk->id))->handle(app(OllamaClient::class));

        $savedEmbedding = json_decode($chunk->refresh()->getRawOriginal('embedding'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($embedding, $savedEmbedding);
        Http::assertSentCount(1);
    }
}
