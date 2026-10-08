<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractRenewal extends Model
{
    use HasFactory;

    protected $table = 'contracttranssionemprenew';

    protected $primaryKey = 'contract_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'employeename',
        'position1',
        'department',
        'startdate',
        'enddate',
        'employername',
        'issuedate',
        'expireddate',
        'hremail',
        'email',
        'copymail',
        'signature',
        'activate',
    ];

    protected $casts = [
        'startdate' => 'date',
        'enddate' => 'date',
        'issuedate' => 'date',
        'expireddate' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}