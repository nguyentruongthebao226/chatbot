<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelMessage extends Model
{
    protected $fillable = ['channel_id', 'customer_id', 'user_id', 'message', 'parent_id'];

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
    public function employee()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }
}
