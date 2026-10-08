<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;           // ← ADD THIS
use Laravel\Sanctum\HasApiTokens;              // ← If you're using Sanctum (optional but common)

class User extends Authenticatable
{
use HasFactory, Notifiable, HasRoles, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',      // ← NEW: for bypassing all checks
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_super_admin'    => 'boolean',   // ← IMPORTANT
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // Relationship: Many-to-Many with clients
    // ────────────────────────────────────────────────────────────────
    public function clients()
    {
        return $this->belongsToMany(Client::class, 'user_client');
    }

    // ────────────────────────────────────────────────────────────────
    // Helper: Does this user have access to a specific client?
    // Accepts: id, slug, or Client model instance
    // ────────────────────────────────────────────────────────────────
    public function hasClientAccess($client): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        // If a slug is passed
        if (is_string($client)) {
            return $this->clients()->where('slug', $client)->exists();
        }

        // If an ID is passed
        if (is_numeric($client)) {
            return $this->clients()->where('client_id', $client)->exists();
        }

        // If a Client model instance is passed
        if ($client instanceof Client) {
            return $this->clients()->where('client_id', $client->id)->exists();
        }

        return false;
    }

    // Optional: shortcut for checking super admin in Blade/Inertia
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }
}
