<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeaveReviewToken extends Model
{
    use HasFactory;

    protected $fillable = ['leave_application_id', 'token', 'used', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($token) {
            // Generate a 10-character token
            $token->token = self::generateUniqueToken(10);
            $token->expires_at = now()->addDays(7);
        });
    }

    /**
     * Generate a unique token of specified length.
     *
     * @param int $length
     * @return string
     */
    protected static function generateUniqueToken(int $length): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        do {
            $token = '';
            for ($i = 0; $i < $length; $i++) {
                $token .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (self::where('token', $token)->exists());

        return $token;
    }

    public function leaveApplication()
    {
        return $this->belongsTo(LeaveApplication::class);
    }

    public function isValid()
    {
        return !$this->used && $this->expires_at->isFuture();
    }
}
