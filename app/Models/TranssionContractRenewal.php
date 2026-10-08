<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranssionContractRenewal extends Model
{
    protected $table = 'contracttranssionemprenew';

    // Since contract_id is auto-increment primary key
    protected $primaryKey = 'contract_id';

    // We don't want Laravel to auto-increment the default 'id' field
    public $incrementing = true;

    // The primary key is not the default 'id'
    protected $keyType = 'int';

    protected $fillable = [
        'id',               // original contract id (varchar(20))
        'employeename',
        'position1',
        'department',
        'startdate',
        'enddate',
        'expireddate',
        'employername',
        'issuedate',
        'hremail',
        'email',
        'copymail',
        'signature',
        'activate',
    ];

    protected $casts = [
        'startdate'     => 'date',
        'enddate'       => 'date',
        'expireddate'   => 'date',
        'issuedate'     => 'date',
        'activate'      => 'string',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    // Optional: scope for latest renewal per original contract
    public function scopeLatestForContract($query, $originalId)
    {
        return $query->where('id', $originalId)
                     ->orderBy('issuedate', 'desc')
                     ->orderBy('contract_id', 'desc');
    }
}
