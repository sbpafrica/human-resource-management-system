<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $table = 'client';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'client_name',
        'client_dbname',
        'slug',
        'short_name',
        'logo',
        'color',
        'is_active',
        'display_order',
        'payroll_config',
        'currency',
        'country',
    ];

    protected $casts = [
        'payroll_config' => 'array',
        'is_active'      => 'boolean',
    ];

    // ────────────────────────────────────────────────────────────────
    // NEW: Relationship back to users
    // ────────────────────────────────────────────────────────────────
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_client');
    }

    // Keep your existing slug auto-generation – it's perfect
    protected static function booted()
    {
        static::creating(function ($client) {
            if (empty($client->slug)) {
                $client->slug = \Str::slug($client->short_name ?: $client->client_name);
            }
        });

        static::updating(function ($client) {
            if ($client->isDirty('short_name') || $client->isDirty('client_name')) {
                $client->slug = \Str::slug($client->short_name ?: $client->client_name);
            }
        });
    }
}
