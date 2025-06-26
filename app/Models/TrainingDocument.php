<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingDocument extends Model
{
    protected $fillable = ['type', 'name', 'content', 'bot_id'];

    public function chunks()
    {
        return $this->hasMany(DocumentChunk::class, 'document_id');
    }

    public function bot()
    {
        return $this->belongsTo(BotAI::class, 'bot_id');
    }
}
