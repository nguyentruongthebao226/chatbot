<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Core\Model\HasApiTokens;

class Employee extends Authenticatable
{
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function answeredQuestions()
    {
        return $this->hasMany(UnansweredQuestion::class, 'answered_by', 'email');
    }
    public function channels()
    {
        return $this->belongsToMany(
            Channel::class,
            'channel_employee',   // tên bảng pivot
            'user_id',        // FK về employee
            'channel_id'          // FK về channel
        );
    }
    public function joinedChannelIds(): array
    {
        // nếu bạn đã load quan hệ trước (eager load), dùng $this->channels
        if ($this->relationLoaded('channels')) {
            return $this->channels->pluck('id')->all();
        }

        // nếu chưa thì query:
        return $this->channels()->pluck('channels.id')->all();
    }
}
