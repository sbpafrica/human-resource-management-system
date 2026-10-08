<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResumeSelection extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'employeeuser_id',
        'revealed_at',
    ];

    protected $casts = [
        'revealed_at' => 'datetime',
    ];

    // Optional: Relationships
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id'); // Assuming admins are in 'users' table
    }

    public function employeeuser()
    {
        return $this->belongsTo(Employeeuser::class);
    }
}
