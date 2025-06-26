<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentChunk extends Model
{
    protected $fillable = [
        'document_id',
        'content',
        'embedding',
        'qdrant_id',
        'vector_collection'
    ];
    protected $casts = [
        'embedding' => 'array'
    ];

    public function document()
    {
        return $this->belongsTo(TrainingDocument::class, 'document_id');
    }
}
