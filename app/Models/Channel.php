<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ChannelMessage;

class Channel extends Model
{
    protected $fillable = ['name', 'description', 'avatar', 'private', 'created_by'];

    public function messages()
    {
        return $this->hasMany(ChannelMessage::class);
    }

    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'channel_customer', 'channel_id', 'customer_id');
    }
    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'channel_customer', 'channel_id', 'user_id');
    }

    public function customer()
    {
        return $this->customers()->limit(1);
    }
    public function unansweredQuestions()
    {
        return $this->hasMany(UnansweredQuestion::class, 'channel_id');
    }
}
