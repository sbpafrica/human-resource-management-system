<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OlamfoodContract extends Model
{
    use HasFactory;

    protected $table = 'olamfoodcon2';

    // Important: Non-incrementing string primary key
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'employeename',
        'client',
        'location',
        'position1',
        'duration',
        'startdate',
        'enddate',
        'basic',
        'employername',
        'issuedate',
        'hremail',
        'ghanacard',
        'digiaddress',
        'signature',
        'activate',
    ];

    protected $casts = [
        'startdate'    => 'date',
        'enddate'      => 'date',
        'issuedate'    => 'date',
        'duration'     => 'integer',
        'activate'     => 'string',
    ];
}
