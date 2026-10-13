<?php

namespace App\Jobs;

use App\Models\ContentEntry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ChunkContentEntry implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $contentEntryId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $entry = ContentEntry::find($this->contentEntryId);

        if ($entry === null) {
            return;
        }

        $entry->chunks()->delete();
        $chunks = $this->split($entry->body);

        foreach ($chunks as $position => $content) {
            $chunk = $entry->chunks()->create(['position' => $position, 'content' => $content]);
            GenerateChunkEmbedding::dispatch($chunk->id)->onQueue('embeddings');
        }
    }

    /** @return array<int, string> */
    private function split(string $body): array
    {
        $words = preg_split('/\\s+/', trim($body)) ?: [];
        $chunks = [];

        foreach (array_chunk($words, 180) as $wordsInChunk) {
            $chunks[] = implode(' ', $wordsInChunk);
        }

        return $chunks;
    }
}
