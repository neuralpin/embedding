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

    public function test_it_lists_all_content_entries_without_running_a_search(): void
    {
        $olderEntry = ContentEntry::factory()->create([
            'title' => 'Entrada anterior',
            'created_at' => now()->subDay(),
        ]);
        $newerEntry = ContentEntry::factory()->create([
            'title' => 'Entrada reciente',
            'created_at' => now(),
        ]);

        $response = $this->getJson(route('content.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'entries')
            ->assertJsonPath('entries.0.id', $newerEntry->id)
            ->assertJsonPath('entries.1.id', $olderEntry->id);
    }

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
