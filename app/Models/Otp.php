<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Otp extends Model
{
    protected $fillable = ['user_id', 'contact', 'code_hash', 'expires_at', 'used', 'attempts'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
    ];

    public function isExpired()
    {
        return $this->expires_at->isPast();
    }
}