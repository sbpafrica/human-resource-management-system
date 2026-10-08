<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeContact extends Model
{
    protected $table = 'employeecontact';

    protected $primaryKey = 'staffid';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true; // Enables created_at and updated_at

    protected $fillable = [
        'staffid',
        'name',
        'shop',
        'phone',
    ];

    protected $casts = [
        'phone' => 'string',
    ];
}
