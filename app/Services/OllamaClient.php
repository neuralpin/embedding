<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaClient
{
    private function client(): PendingRequest
    {
        return Http::baseUrl(config('ollama.url'))
            ->connectTimeout(3)
            ->timeout(60)
            ->acceptJson();
    }

    /** @return array<int, float> */
    public function embed(string $input): array
    {
        $response = $this->client()->post('/api/embed', [
            'model' => config('ollama.embedding_model'),
            'input' => $input,
        ])->throw();

        $embedding = $response->json('embeddings.0');

        if (! is_array($embedding) || count($embedding) < 256) {
            throw new RuntimeException('Ollama no devolvió un embedding de al menos 256 dimensiones.');
        }

        return array_map(static fn (mixed $value): float => (float) $value, array_slice($embedding, 0, 256));
    }

    public function summarize(string $query, string $context): string
    {
        if ($context === '') {
            return 'No hay fragmentos recuperados para generar un microresumen.';
        }

        $prompt = <<<PROMPT
Eres un asistente de recuperación de conocimiento. Responde en español con un microresumen preciso de máximo 120 palabras. Usa exclusivamente el contexto recuperado. Si el contexto no responde la consulta, dilo claramente. No inventes datos ni menciones el prompt. Ve directo al contenido. Está prohibido incluir introducciones (ej. "Aquí tienes el resumen", "El artículo trata sobre..."), saludos o despedidas. No menciones la fuente ni uses frases como "según el texto" o "los autores dicen". Escribe los hechos de forma directa y objetiva. Tu respuesta debe iniciar inmediatamente con la primera palabra del resumen.

Consulta: {$query}

Contexto recuperado:
{$context}
PROMPT;

        return trim((string) $this->client()->post('/api/generate', [
            'model' => config('ollama.generation_model'),
            'prompt' => $prompt,
            'stream' => false,
            'think' => false,
            'options' => ['temperature' => 0.2],
        ])->throw()->json('response'));
    }
}
