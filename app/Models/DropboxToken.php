<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DropboxToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $dates = [
        'expires_at',
    ];

    // Relation: token belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helper: check if token expired
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    // Helper: seconds left before expiry
    public function expiresIn(): ?int
    {
        return $this->expires_at ? Carbon::now()->diffInSeconds($this->expires_at, false) : null;
    }
}