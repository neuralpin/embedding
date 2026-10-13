<?php

namespace App\Services;

use App\Models\ContentChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HybridChunkSearch
{
    /** @param array<int, float> $embedding */
    public function search(string $query, array $embedding, int $limit = 12): Collection
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return ContentChunk::query()
                ->where('content', 'like', "%{$query}%")
                ->limit($limit)
                ->get()
                ->each->setAttribute('hybrid_score', 1.0)
                ->load('contentEntry:id,title,created_at');
        }

        $vector = json_encode($embedding, JSON_THROW_ON_ERROR);
        $sql = <<<'SQL'
WITH vector_candidates AS (
    SELECT id, row_number() OVER (ORDER BY embedding <=> ?::vector) AS vector_rank,
           1 - (embedding <=> ?::vector) AS vector_score
    FROM content_chunks WHERE embedding IS NOT NULL
    ORDER BY embedding <=> ?::vector LIMIT 40
), text_candidates AS (
    SELECT id, row_number() OVER (ORDER BY ts_rank_cd(search_vector, websearch_to_tsquery('spanish', ?)) DESC) AS text_rank,
           ts_rank_cd(search_vector, websearch_to_tsquery('spanish', ?)) AS text_score
    FROM content_chunks WHERE search_vector @@ websearch_to_tsquery('spanish', ?)
    ORDER BY text_score DESC LIMIT 40
), candidates AS (
    SELECT id FROM vector_candidates UNION SELECT id FROM text_candidates
)
SELECT chunks.id, chunks.content_entry_id, chunks.position, chunks.content, chunks.created_at, chunks.updated_at,
       vector_candidates.vector_score, text_candidates.text_score,
       coalesce(1.0 / (60 + vector_candidates.vector_rank), 0) +
       coalesce(1.0 / (60 + text_candidates.text_rank), 0) AS hybrid_score
FROM content_chunks AS chunks
INNER JOIN candidates ON candidates.id = chunks.id
LEFT JOIN vector_candidates ON vector_candidates.id = chunks.id
LEFT JOIN text_candidates ON text_candidates.id = chunks.id
ORDER BY hybrid_score DESC, chunks.id DESC LIMIT ?
SQL;

        $chunks = ContentChunk::hydrate(array_map(
            static fn (object $row): array => (array) $row,
            DB::select($sql, [$vector, $vector, $vector, $query, $query, $query, $limit]),
        ));

        return $chunks->load('contentEntry:id,title,created_at');
    }
}
