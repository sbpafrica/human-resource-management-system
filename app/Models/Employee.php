<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'newemployeedata';
    protected $primaryKey = 'staffid';
    public $incrementing = false;
    protected $keyType = 'string';
     public $timestamps = false;

    protected $fillable = [
        'staffid', 'employeename', 'company', 'shop', 'position', 'qualification',
        'startdate', 'dateofbirth', 'ghcard', 'phone1', 'phone2', 'gender',
        'nationality', 'email', 'nextofkin', 'relation', 'contact', 'guarantor',
        'contactgua', 'profilepic', 'ghanafront', 'ghanaback', 'signature',
        'gpsaddress', 'signature_date',
    ];

    public function bankDetail()
    {
        return $this->hasOne(BankDetail::class, 'employid', 'staffid');
    }

    public function petra()
    {
        return $this->hasOne(Petra::class, 'staffid', 'staffid');
    }

    public function ssnit()
    {
        return $this->hasOne(Ssnit::class, 'staffid', 'staffid');
    }
}
