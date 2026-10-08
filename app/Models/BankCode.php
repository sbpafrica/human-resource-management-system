<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankCode extends Model
{
    protected $table = 'bankcodes';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'bankname',
        'branchcodes',
        'branch',
    ];
}
