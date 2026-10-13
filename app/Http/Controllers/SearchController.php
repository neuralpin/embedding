<?php

namespace App\Http\Controllers;

use App\Models\ContentChunk;
use App\Services\HybridChunkSearch;
use App\Services\OllamaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SearchController extends Controller
{
    public function chunks(Request $request, OllamaClient $ollama, HybridChunkSearch $hybridSearch): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'max:500']]);
        $chunks = $hybridSearch->search($validated['q'], $ollama->embed($validated['q']));
        $entries = $chunks
            ->groupBy('content_entry_id')
            ->map(function (Collection $entryChunks): array {
                /** @var ContentChunk $bestChunk */
                $bestChunk = $entryChunks->first();

                return [
                    'id' => $bestChunk->contentEntry->id,
                    'title' => $bestChunk->contentEntry->title,
                    'created_at' => $bestChunk->contentEntry->created_at,
                    'relevance' => max($entryChunks->pluck('hybrid_score')->map(static fn (mixed $score): float => (float) $score)->all()),
                    'matched_chunks' => $entryChunks->count(),
                ];
            })
            ->sortByDesc('relevance')
            ->values();

        return response()->json(['chunks' => $chunks, 'entries' => $entries]);
    }

    public function rag(Request $request, OllamaClient $ollama): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:500'],
            'chunk_ids' => ['required', 'array', 'min:1', 'max:12'],
            'chunk_ids.*' => ['integer', 'distinct', 'exists:content_chunks,id'],
        ]);
        $chunkIds = $validated['chunk_ids'];
        $chunks = ContentChunk::query()
            ->whereIn('id', $chunkIds)
            ->get(['id', 'content'])
            ->sortBy(fn (ContentChunk $chunk): int => array_search($chunk->id, $chunkIds, true))
            ->values();

        if ($chunks->isEmpty()) {
            return response()->json(['summary' => 'No hay fragmentos recuperados para generar un microresumen.']);
        }

        return response()->json([
            'summary' => $ollama->summarize($validated['q'], $chunks->pluck('content')->implode("\n\n")),
        ]);
    }
}
