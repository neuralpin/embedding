<?php

namespace App\Http\Controllers;

use App\Models\ContentChunk;
use App\Models\ContentEntry;
use App\Services\OllamaClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function entries(Request $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());
        $terms = collect(preg_split('/\s+/', Str::lower(Str::ascii($query))) ?: [])
            ->filter()
            ->unique()
            ->values();
        $entries = ContentEntry::query()
            ->when($terms->isNotEmpty(), fn ($builder) => $this->matchEntryTerms($builder, $terms->all()))
            ->select(['id', 'title', 'created_at'])
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(8);

        return response()->json(['entries' => $entries]);
    }

    /** @param array<int, string> $terms */
    private function matchEntryTerms(Builder $builder, array $terms): void
    {
        foreach ($terms as $term) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                $builder->whereRaw(
                    "translate(lower(title || ' ' || body), 'áéíóúüñ', 'aeiouun') like ?",
                    ["%{$term}%"],
                );

                continue;
            }

            $builder->whereRaw("lower(title || ' ' || body) like ?", ["%{$term}%"]);
        }
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
            ->limit(12)
            ->get();
    }
}
