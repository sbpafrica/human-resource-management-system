<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class XiaomiContract extends Model
{
    use HasFactory;

    protected $table = 'xiaomicon';

    // Very important! (same as Transsion)
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
        'kpi',
        'transport',
        'basicafter',
        'kpiafter',
        'transportafter',
        'employername',
        'issuedate',
        'hremail',
        'ghanacard',
        'digiaddress',
        'signature',
        'activate',
    ];

    protected $casts = [
        'startdate'     => 'date',
        'enddate'       => 'date',
        'issuedate'     => 'date',
        'duration'      => 'integer',
        'basic'         => 'double',
        'kpi'           => 'double',
        'transport'     => 'double',
        'basicafter'    => 'double',
        'kpiafter'      => 'double',
        'transportafter'=> 'double',
        'activate'      => 'string',
    ];
}
