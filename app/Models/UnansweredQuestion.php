<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnansweredQuestion extends Model
{
    protected $fillable = ['chat_session_id', 'channel_id', 'question', 'questioner', 'answered', 'answered_by'];

    public function session()
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function answeredByUser()
    {
        return $this->belongsTo(User::class, 'answered_by', 'email');
    }

    public function hasChannel()
    {
        return !is_null($this->channel_id);
    }
}
