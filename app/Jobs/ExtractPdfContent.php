<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class ExtractPdfContent implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $token, public string $path)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $text = (new Parser)->parseFile(Storage::path($this->path))->getText();
            Cache::put('pdf-extraction:'.$this->token, ['status' => 'completed', 'body' => trim($text)], now()->addHour());
        } catch (\Throwable $exception) {
            report($exception);
            Cache::put('pdf-extraction:'.$this->token, ['status' => 'failed'], now()->addHour());
        } finally {
            Storage::delete($this->path);
        }
    }
}
