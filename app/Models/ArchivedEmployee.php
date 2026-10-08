<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivedEmployee extends Model
{
    protected $connection = 'mysql';
    protected $table = 'newemployeedataarchieve';
    protected $primaryKey = 'staffid';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'staffid',
        'employeename',
        'company',
        'shop',
        'position',
        'qualification',
        'startdate',
        'dateofbirth',
        'ssnit',
        'ghcard',
        'bank',
        'account',
        'branch',
        'momonumber',
        'momaname',
        'phone1',
        'phone2',
        'gender',
        'contactgua',
        'email',
        'nextofkin',
        'relation',
        'contact',
        'guarantor',
        'nationality',
        'profilepic',
        'ghanafront',
        'ghanaback',
        'gpsaddress',
        'archived_at',
    ];
}
