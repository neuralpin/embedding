<?php

use App\Http\Controllers\ContentEntryController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('embedding');
})->name('embedding.index');

Route::get('/search/chunks', [SearchController::class, 'chunks'])->name('search.chunks');
Route::post('/search/rag', [SearchController::class, 'rag'])->name('search.rag');
Route::get('/content', [ContentEntryController::class, 'index'])->name('content.index');
Route::post('/content', [ContentEntryController::class, 'store'])->name('content.store');
Route::post('/content/extract-pdf', [ContentEntryController::class, 'extractPdf'])->name('content.extract-pdf');
Route::get('/content/extractions/{token}', [ContentEntryController::class, 'extraction'])->name('content.extraction');
Route::get('/content/{contentEntry}', [ContentEntryController::class, 'show'])->whereNumber('contentEntry')->name('content.show');
Route::put('/content/{contentEntry}', [ContentEntryController::class, 'update'])->whereNumber('contentEntry')->name('content.update');
