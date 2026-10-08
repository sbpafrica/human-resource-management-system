<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GmoneyCon extends Model
{
    protected $table = 'gmoneycon';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'employeename',
        'ghanacard',
        'digiaddress',
        'position1',
        'client',
        'location',
        'startdate',
        'issuedate',
        'basic',
        'transport',
        'signature',
        'activate',

    ];

    protected $casts = [
        'startdate'  => 'date',
        'issuedate'  => 'date',
        'basic'      => 'decimal:2',
        'transport'  => 'decimal:2',
        'kpi'        => 'decimal:2',
        'activate'   => 'integer',
    ];
}
