<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ssnit extends Model
{
    protected $table = 'ssnit';
    protected $primaryKey = 'staffid';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Disable timestamps
    protected $fillable = ['staffid', 'shop', 'lastname', 'firstname', 'othername', 'ssnit1a', 'national'];
}
