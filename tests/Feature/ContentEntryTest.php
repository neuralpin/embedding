<?php

namespace Tests\Feature;

use App\Jobs\ChunkContentEntry;
use App\Models\ContentEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContentEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_content_and_queues_chunking(): void
    {
        Queue::fake();

        $response = $this->postJson(route('content.store'), [
            'title' => 'Guía de embeddings',
            'body' => '# Embeddings\n\nContenido para indexar.',
        ]);

        $response->assertCreated()->assertJsonPath('entry.title', 'Guía de embeddings');
        $this->assertDatabaseHas('content_entries', ['title' => 'Guía de embeddings']);
        Queue::assertPushed(ChunkContentEntry::class, fn (ChunkContentEntry $job): bool => $job->contentEntryId === ContentEntry::firstOrFail()->id);
    }
}
