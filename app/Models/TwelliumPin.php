<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TwelliumPin extends Model
{
    use HasFactory;

    protected $table = 'userstwi';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'staff_id',
        'company',
        'password',
        'email',
        'email_verified_at',
        'remember_token',
    ];

    /**
     * Generate a unique 4-digit pincode (0000 - 9999)
     */
    public static function generateUniquePincode(): string
    {
        do {
            $pincode = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (static::where('password', $pincode)->exists());

        return $pincode;
    }

    public function contact()
    {
        return $this->hasOne(EmployeeContact::class, 'staffid', 'staff_id');
    }
}
