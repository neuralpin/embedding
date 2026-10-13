<?php

namespace App\Http\Controllers;

use App\Models\ContentChunk;
use App\Models\ContentEntry;
use App\Services\OllamaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function entries(Request $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());
        $entries = ContentEntry::query()
            ->when($query !== '', fn ($builder) => $builder->where(function ($queryBuilder) use ($query): void {
                $queryBuilder->where('title', 'ilike', "%{$query}%")->orWhere('body', 'ilike', "%{$query}%");
            }))
            ->select(['id', 'title', 'created_at'])
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(8);

        return response()->json(['entries' => $entries]);
    }

    public function chunks(Request $request, OllamaClient $ollama): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'max:500']]);

        return response()->json(['chunks' => $this->semanticChunks($ollama->embed($validated['q']))]);
    }

    public function rag(Request $request, OllamaClient $ollama): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:500'],
            'chunk_ids' => ['required', 'array', 'min:1', 'max:8'],
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

    /** @param array<int, float> $embedding */
    private function semanticChunks(array $embedding): Collection
    {
        $vector = json_encode($embedding, JSON_THROW_ON_ERROR);

        return ContentChunk::query()
            ->with('contentEntry:id,title')
            ->when(DB::connection()->getDriverName() === 'pgsql', fn ($builder) => $builder
                ->whereNotNull('embedding')
                ->select('content_chunks.*')
                ->selectRaw('1 - (embedding <=> ?::vector) as similarity', [$vector])
                ->orderByDesc('similarity'))
            ->limit(8)
            ->get();
    }
}
