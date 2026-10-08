<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MelcomContractRenewal extends Model
{
    use HasFactory;

    protected $table = 'contractmelcomemprenew';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'employeename',
        'position1',
        'startdate',
        'enddate',
        'employername',
        'issuedate',
        'expireddate',
        'hremail',
        'email',
        'signature',
        'activate',
    ];

    protected $casts = [
        'startdate'   => 'date',
        'enddate'     => 'date',
        'issuedate'   => 'date',
        'expireddate' => 'date',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];
}
