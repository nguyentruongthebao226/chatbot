<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_session_id',
        'bot_id',
        'sender',
        'question',
        'answer',
        'embedding_id',
        'question_embedding',
        'question_qdrant_id'
    ];

    protected $casts = [
        'question_embedding' => 'array'
    ];

    public function session()
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function bot()
    {
        return $this->belongsTo(BotAI::class, 'bot_id');
    }
}
