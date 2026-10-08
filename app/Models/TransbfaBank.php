<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TransbfaBank extends Model
{
    protected $table = 'bfabank';
    protected $primaryKey = 'staffida';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $fillable = ['staffida', 'employname', 'bankname', 'accountnum'];
}
