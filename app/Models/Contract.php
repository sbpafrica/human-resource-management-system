<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $table = 'contractmelcomemp';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $attributes = [
        'activate' => '0', // Default value for activate
    ];

    protected $fillable = [
        'id', 'employeename', 'client', 'location', 'position1', 'startdate',
        'enddate', 'basic', 'employername', 'issuedate', 'hremail', 'email',
        'ghanacard', 'digiaddress', 'signature', 'activate'
    ];
}
