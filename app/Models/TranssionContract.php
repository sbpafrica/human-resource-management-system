<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TranssionContract extends Model
{
    use HasFactory;

    protected $table = 'transsioncon';

    // Very important!
    protected $primaryKey = 'id';           // ← Tell Laravel the PK is 'id'
    public $incrementing = false;           // ← NOT auto-incrementing
    protected $keyType = 'string';          // ← It's a string, not integer

    protected $fillable = [
        'id',
        'employeename',
        'client',
        'location',
        'position1',
        'department',
        'duration',
        'startdate',
        'enddate',
        'basic',
        'allowance',
        'kpi',
        'employername',
        'issuedate',
        'hremail',
        'email',
        'copymail',
        'ghanacard',
        'digiaddress',
        'signature',
        'activate',
    ];

    protected $casts = [
        'startdate' => 'date',
        'enddate' => 'date',
        'issuedate' => 'date',
        'duration' => 'integer',
        'activate' => 'string',
    ];
}
