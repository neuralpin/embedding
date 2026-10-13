<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>EmbeddingLab · búsqueda semántica</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0b1020] font-sans text-slate-100 antialiased">
    <main x-data="embeddingApp" class="mx-auto max-w-[1800px] p-5 lg:p-8">
        <header class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">Embedding Lab</h1>
            </div>
            <button @click="newEntry" class="rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-cyan-300">+ Añadir contenido</button>
        </header>

        <div class="relative mb-6">
            <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
            <input x-model="query" @input.debounce.400ms="search()" type="search" placeholder="Pregunta algo a tu base de conocimiento…" class="w-full rounded-2xl border border-slate-700 bg-slate-900 py-4 pl-12 pr-12 text-base outline-none transition placeholder:text-slate-500 focus:border-cyan-400 focus:ring-4 focus:ring-cyan-400/10">
            <span x-show="loadingIndex || loadingChunks || loadingRag" class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-cyan-300">buscando…</span>
        </div>

        <section class="grid gap-5 xl:grid-cols-3">
            <section class="min-h-[580px] rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-2xl shadow-slate-950/30">
                <div class="mb-5 flex items-center justify-between"><h2 class="font-semibold">Índice de contenido</h2><span class="rounded-full bg-slate-800 px-2 py-1 text-xs text-slate-400" x-text="entries.total || 0"></span></div>
                <div class="space-y-2">
                    <template x-for="entry in entries.data" :key="entry.id"><button @click="openEntry(entry.id)" class="w-full rounded-xl border border-transparent bg-slate-800/60 p-3 text-left transition hover:border-cyan-500/60 hover:bg-slate-800"><p class="truncate font-medium" x-text="entry.title"></p><p class="mt-1 text-xs text-slate-500" x-text="`${entry.matched_chunks} chunks · score ${Number(entry.relevance).toFixed(3)}`"></p></button></template>
                    <p x-show="!entries.data.length" class="py-12 text-center text-sm text-slate-500">No hay contenido que mostrar.</p>
                </div>
            </section>

            <section class="min-h-[580px] rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-2xl shadow-slate-950/30">
                <div class="mb-5 flex items-center justify-between"><h2 class="font-semibold">Chunks recuperados</h2><span class="text-xs text-slate-500">híbrido + reranking</span></div>
                <div class="space-y-3"><template x-for="chunk in chunks" :key="chunk.id"><article class="rounded-xl border border-slate-800 bg-slate-950/40 p-4"><p class="mb-2 text-xs font-medium text-cyan-400" x-text="chunk.content_entry?.title"></p><p class="line-clamp-4 text-sm leading-6 text-slate-300" x-text="chunk.content"></p><p x-show="chunk.hybrid_score" class="mt-3 text-xs text-slate-500" x-text="`score híbrido ${Number(chunk.hybrid_score).toFixed(3)}`"></p></article></template><p x-show="!chunks.length" class="py-12 text-center text-sm text-slate-500">Los fragmentos relevantes aparecerán aquí.</p></div>
            </section>

            <section class="min-h-[580px] rounded-2xl border border-cyan-900/70 bg-gradient-to-b from-slate-900 to-cyan-950/20 p-5 shadow-2xl shadow-slate-950/30">
                <div class="mb-5 flex items-center justify-between"><h2 class="font-semibold">Respuesta RAG</h2><span class="rounded-full bg-cyan-400/10 px-2 py-1 text-xs text-cyan-300">qwen3:0.6b</span></div>
                <div class="rounded-xl border border-cyan-900/60 bg-slate-950/50 p-5"><p class="whitespace-pre-line text-sm leading-7 text-slate-200" x-text="summary"></p></div>
                <p class="mt-4 text-xs leading-5 text-slate-500">El resumen se genera solo con los fragmentos recuperados, mediante Ollama local.</p>
            </section>
        </section>

        <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-slate-950/80 p-4 backdrop-blur-sm" @keydown.escape.window="modalOpen = false">
            <section @click.outside="modalOpen = false" class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
                <div class="mb-5 flex items-center justify-between"><div><p class="text-xs font-semibold tracking-wider text-cyan-400" x-text="entry.id ? 'EDITAR ENTRADA' : 'NUEVA ENTRADA'"></p><h2 class="text-xl font-bold">Contenido Markdown</h2></div><button @click="modalOpen = false" class="text-2xl text-slate-500 hover:text-white">×</button></div>
                <div class="mb-4 flex items-center gap-3"><label class="cursor-pointer rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:border-cyan-400"><input @change="extractPdf($event)" type="file" accept="application/pdf" class="hidden">Extraer cuerpo de PDF</label><span class="text-xs text-slate-500">El título y metadatos se capturan manualmente.</span></div>
                <input x-model="entry.title" class="mb-4 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 font-medium outline-none focus:border-cyan-400" placeholder="Título de la entrada">
                <div class="grid gap-4 md:grid-cols-2"><textarea x-model="entry.body" rows="18" class="min-h-96 rounded-xl border border-slate-700 bg-slate-950 p-4 font-mono text-sm leading-6 outline-none focus:border-cyan-400" placeholder="# Escribe en Markdown…"></textarea><article class="min-h-96 overflow-auto rounded-xl border border-slate-800 bg-slate-950/50 p-5"><p class="mb-3 text-xs font-semibold tracking-wider text-slate-500">PREVIEW</p><div class="markdown-preview text-sm leading-7 text-slate-300" x-html="preview()"></div></article></div>
                <div class="mt-5 flex justify-end gap-3"><button @click="modalOpen = false" class="rounded-xl px-4 py-2 text-sm text-slate-400">Cancelar</button><button @click="save" :disabled="saving" class="rounded-xl bg-cyan-400 px-4 py-2 text-sm font-bold text-slate-950 disabled:opacity-60" x-text="saving ? 'Guardando…' : 'Guardar y vectorizar'"></button></div>
            </section>
        </div>
    </main>
</body>
</html>
