import Alpine from 'alpinejs';
import { marked } from 'marked';
import hljs from 'highlight.js';
import DOMPurify from 'dompurify';
import 'highlight.js/styles/github-dark.css';

const renderer = new marked.Renderer();
renderer.html = () => '';
renderer.code = ({ text, lang }) => {
    const highlighted = lang && hljs.getLanguage(lang)
        ? hljs.highlight(text, { language: lang }).value
        : hljs.highlightAuto(text).value;

    return `<pre><code class="hljs language-${lang || 'plaintext'}">${highlighted}</code></pre>`;
};

marked.use({
    renderer,
    hooks: {
        postprocess(html) {
            return html;
        },
    },
});

window.Alpine = Alpine;

Alpine.data('embeddingApp', () => ({
    query: '', entries: { data: [], current_page: 1, last_page: 1 }, chunks: [], summary: 'Escribe una consulta para recuperar fragmentos y generar un microresumen.', modalOpen: false, saving: false, loadingIndex: false, loadingChunks: false, loadingRag: false, entry: { id: null, title: '', body: '' }, searchController: null,
    init() { this.search(); },
    async search(page = 1) { this.searchController?.abort(); this.searchController = new AbortController(); const signal = this.searchController.signal; await Promise.all([this.searchEntries(page, signal), this.searchChunks(signal)]); },
    async loadPage(page) { this.searchController?.abort(); this.searchController = new AbortController(); await this.searchEntries(page, this.searchController.signal); },
    async searchEntries(page, signal) { this.loadingIndex = true; try { const response = await fetch(`/search/entries?q=${encodeURIComponent(this.query)}&page=${page}`, { headers: { Accept: 'application/json' }, signal }); if (!response.ok) throw new Error(); this.entries = (await response.json()).entries; this.applySemanticEntries(); } catch (error) { if (error.name !== 'AbortError') this.entries = { data: [], current_page: 1, last_page: 1, total: 0 }; } finally { this.loadingIndex = false; } },
    async searchChunks(signal) { const query = this.query.trim(); this.chunks = []; this.summary = query ? 'Recuperando fragmentos relevantes…' : 'Escribe una consulta para recuperar fragmentos y generar un microresumen.'; if (!query) return; this.loadingChunks = true; try { const response = await fetch(`/search/chunks?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' }, signal }); if (!response.ok) throw new Error(); this.chunks = (await response.json()).chunks; this.applySemanticEntries(); await this.generateRag(query, this.chunks.map(chunk => chunk.id), signal); } catch (error) { if (error.name !== 'AbortError') this.summary = 'No fue posible recuperar fragmentos desde Ollama.'; } finally { this.loadingChunks = false; } },
    applySemanticEntries() { if ((this.entries.total || 0) > 0 || !this.chunks.length) return; const entries = [...new Map(this.chunks.filter(chunk => chunk.content_entry).map(chunk => [chunk.content_entry.id, chunk.content_entry])).values()]; this.entries = { data: entries, current_page: 1, last_page: 1, total: entries.length }; },
    async generateRag(query, chunkIds, signal) { if (!chunkIds.length) { this.summary = 'No se recuperaron fragmentos para generar una respuesta.'; return; } this.loadingRag = true; this.summary = 'Generando respuesta RAG…'; try { const response = await fetch('/search/rag', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ q: query, chunk_ids: chunkIds }), signal }); if (!response.ok) throw new Error(); this.summary = (await response.json()).summary; } catch (error) { if (error.name !== 'AbortError') this.summary = 'No fue posible generar la respuesta RAG.'; } finally { this.loadingRag = false; } },
    newEntry() { this.entry = { id: null, title: '', body: '' }; this.modalOpen = true; },
    async openEntry(id) { const response = await fetch(`/content/${id}`, { headers: { Accept: 'application/json' } }); this.entry = (await response.json()).entry; this.modalOpen = true; },
    async save() { this.saving = true; try { const url = this.entry.id ? `/content/${this.entry.id}` : '/content'; const response = await fetch(url, { method: this.entry.id ? 'PUT' : 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify(this.entry) }); if (!response.ok) throw new Error('No se pudo guardar el contenido.'); this.modalOpen = false; await this.search(this.entries.current_page); } finally { this.saving = false; } },
    async extractPdf(event) { const file = event.target.files[0]; if (!file) return; const form = new FormData(); form.append('pdf', file); const response = await fetch('/content/extract-pdf', { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: form }); const { token } = await response.json(); const timer = setInterval(async () => { const status = await (await fetch(`/content/extractions/${token}`)).json(); if (status.status === 'completed') { this.entry.body = status.body; clearInterval(timer); } if (status.status === 'failed') clearInterval(timer); }, 1200); },
    preview() { return DOMPurify.sanitize(marked.parse(this.entry.body || '')); },
}));

Alpine.start();
