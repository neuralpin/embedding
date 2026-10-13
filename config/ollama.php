<?php

return [
    'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
    'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'embeddinggemma:300m-qat-q4_0'),
    'generation_model' => env('OLLAMA_GENERATION_MODEL', 'qwen3:0.6b'),
];
