<?php

namespace App\Models;

use Database\Factories\ContentChunkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentChunk extends Model
{
    /** @use HasFactory<ContentChunkFactory> */
    use HasFactory;

    protected $fillable = ['content', 'position'];

    public function contentEntry(): BelongsTo
    {
        return $this->belongsTo(ContentEntry::class);
    }
}
