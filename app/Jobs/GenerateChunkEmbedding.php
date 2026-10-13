<?php

namespace App\Jobs;

use App\Models\ContentChunk;
use App\Services\OllamaClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class GenerateChunkEmbedding implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $contentChunkId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(OllamaClient $ollama): void
    {
        $chunk = ContentChunk::find($this->contentChunkId);

        if ($chunk === null) {
            return;
        }

        $embedding = $ollama->embed($chunk->content);

        if (DB::connection()->getDriverName() === 'pgsql') {
            ContentChunk::query()
                ->whereKey($chunk->id)
                ->update(['embedding' => DB::raw("'".json_encode($embedding, JSON_THROW_ON_ERROR)."'::vector")]);

            return;
        }

        ContentChunk::query()
            ->whereKey($chunk->id)
            ->update(['embedding' => json_encode($embedding, JSON_THROW_ON_ERROR)]);
    }
}
