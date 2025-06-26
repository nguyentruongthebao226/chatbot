<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotAI extends Model
{
    protected $table = 'bots_ai';
    protected $fillable = ['name', 'staff_id', 'icon'];

    public function employee()
    {
        return $this->belongsTo(User::class, 'staff_id', 'id');
    }
}
