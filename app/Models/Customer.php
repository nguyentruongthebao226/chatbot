<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Customer extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'avatar',
    ];

    protected $hidden = [

        'remember_token',
    ];

    // Nếu cần xác thực, có thể bổ sung các method ở đây
    // public function getAuthIdentifierName() { ... }
    // public function getAuthIdentifier() { ... }
    // public function getAuthPassword() { ... }

    public function channelMessages()
    {
        return $this->hasMany(ChannelMessage::class);
    }
    public function channels()
    {
        return $this->belongsToMany(Channel::class, 'channel_customer', 'customer_id', 'channel_id');
    }
}
