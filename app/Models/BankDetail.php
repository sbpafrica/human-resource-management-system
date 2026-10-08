<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankDetail extends Model
{
    protected $table = 'bankdetails';
    protected $primaryKey = 'employid';
    public $incrementing = false;
    protected $keyType = 'string';
     public $timestamps = false;

    protected $fillable = [
        'employid',
        'shop',
        'name',
        'bank',
        'account',
        'code',
        'branch',
        'momonum',
        'momoname',
    ];


}
