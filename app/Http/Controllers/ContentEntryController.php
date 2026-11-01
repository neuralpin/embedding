<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContentEntryRequest;
use App\Http\Requests\UpdateContentEntryRequest;
use App\Jobs\ChunkContentEntry;
use App\Jobs\ExtractPdfContent;
use App\Models\ContentEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ContentEntryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'entries' => ContentEntry::query()
                ->latest()
                ->get(['id', 'title', 'created_at']),
        ]);
    }

    public function show(ContentEntry $contentEntry): JsonResponse
    {
        return response()->json(['entry' => $contentEntry]);
    }

    public function store(StoreContentEntryRequest $request): JsonResponse
    {
        $entry = ContentEntry::create($request->validated());
        ChunkContentEntry::dispatch($entry->id)->onQueue('chunking')->afterCommit();

        return response()->json(['entry' => $entry], 201);
    }

    public function update(UpdateContentEntryRequest $request, ContentEntry $contentEntry): JsonResponse
    {
        $contentEntry->update($request->validated());
        ChunkContentEntry::dispatch($contentEntry->id)->onQueue('chunking')->afterCommit();

        return response()->json(['entry' => $contentEntry->fresh()]);
    }

    public function extractPdf(): JsonResponse
    {
        /** @var UploadedFile $pdf */
        $pdf = request()->validate(['pdf' => ['required', 'file', 'mimes:pdf', 'max:20480']])['pdf'];
        $token = (string) Str::uuid();
        $path = $pdf->store('pdf-imports');
        Cache::put('pdf-extraction:'.$token, ['status' => 'queued'], now()->addHour());
        ExtractPdfContent::dispatch($token, $path)->onQueue('documents')->afterCommit();

        return response()->json(['token' => $token], 202);
    }

    public function extraction(string $token): JsonResponse
    {
        return response()->json(Cache::get('pdf-extraction:'.$token, ['status' => 'missing']));
    }
}
