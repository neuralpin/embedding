# Embedding

Aplicación Laravel para guardar contenido en texto plano markdown y permitir consultarlo mediante búsqueda híbrida: similitud vectorial y búsqueda de texto completo en español. Con una breve respuesta autogenerada con RAG. Acepta archivos PDF para extraer el texto plano.

Por defecto se usa el modelo **embeddinggemma:300m-qat-q4_0** para procesar el embedding, ajustando el tamaño de la representación vectorial a 256 dimensiones y el modelo **qwen3:0.6b** para generar respuesta con RAG.

## Dependencias

- PHP 8.3 o posterior y Composer.
- Node.js y npm para compilar los recursos de Vite/Tailwind CSS.
- PostgreSQL con la extensión `pgvector` (recomendado para la búsqueda híbrida). También es posible usar otro motor compatible, con búsqueda textual de respaldo.
- Ollama, con los modelos configurados para embeddings y generación.

Las dependencias incluyen Laravel 13, `smalot/pdfparser`, Alpine.js, DOMPurify, Highlight.js y Marked. Consulta `composer.json` y `package.json` para el listado y las versiones bloqueadas.

## Puesta en marcha local

```bash
composer install
copy .env.example .env
php artisan key:generate
npm install
```

Configura en `.env` la conexión a PostgreSQL, `QUEUE_CONNECTION=database` y las variables de Ollama:

```dotenv
OLLAMA_URL=http://127.0.0.1:11434
OLLAMA_EMBEDDING_MODEL=embeddinggemma:300m-qat-q4_0
OLLAMA_GENERATION_MODEL=qwen3:0.6b
```

Descarga los modelos y prepara la aplicación:

```bash
ollama pull embeddinggemma:300m-qat-q4_0
ollama pull qwen3:0.6b
php artisan migrate
npm run build
```

En desarrollo, ejecuta la aplicación, Vite y el worker de cola:

```bash
php artisan serve
npm run dev
php artisan queue:work
```

## Despliegue

En producción, configura las variables de entorno —incluidas base de datos, cola y Ollama—, y asegúrate de que Ollama sea accesible desde los procesos PHP y del worker. El usuario de PostgreSQL debe poder habilitar `vector`, ya que la migración crea esa extensión e índices de búsqueda.

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
```

Publica el directorio `public/` mediante el servidor web, concede permisos de escritura a `storage/` y `bootstrap/cache/`, y mantén un proceso supervisado para `php artisan queue:work` para procesar la extracción de PDF, fragmentación y generación de embeddings.
