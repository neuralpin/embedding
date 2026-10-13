<?php

namespace App\Models;

use Database\Factories\ContentEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentEntry extends Model
{
    /** @use HasFactory<ContentEntryFactory> */
    use HasFactory;

    protected $fillable = ['title', 'body'];

    public function chunks(): HasMany
    {
        return $this->hasMany(ContentChunk::class);
    }
}
