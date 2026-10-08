<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePin extends Model
{
    use HasFactory;

    protected $table = 'employeepin';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'staff_id',
        'client',
        'pincode',
    ];

    /**
     * Generate a unique 4-digit pincode (1000 - 9999)
     */
    public static function generateUniquePincode(): int
    {
        do {
            $pincode = random_int(1000, 9999);
        } while (static::where('pincode', $pincode)->exists());

        return $pincode;
    }
}
