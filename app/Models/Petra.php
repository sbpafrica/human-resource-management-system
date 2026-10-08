<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Petra extends Model
{
    protected $table = 'petraorg';
    protected $primaryKey = 'staffid';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $fillable = [
        'staffid',
        'shop',
        'lastname',
        'firstname',
        'othername',
        'ssnit1a',
        'national',
    ];
}
