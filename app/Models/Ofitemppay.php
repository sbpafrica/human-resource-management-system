<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Ofitemppay extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;


    protected $fillable = [
        'id',
        'names',
        'dept',
        'noofdays',
        'extraldays',
        'total1',
        'rate',
        'total2',
        'overtimehr',
        'overtimetotal',
        'overtimeweekend',
        'overtimetotal2',
        'totalamount',
        'transport',
        'servicecharge',
        'getfund',
        'nhil',
        'vat',
        'ssf',
        'leave1',
        'night',
        'nightallowance',
        'crane',
        'medical',
        'costofolam',
        'ssnitee',
        'petra',
        'taxableincome',
        'totaltaxdeduction',
        'totaldeduction',
        'takehome',
        'bank1',
        'account1',
        'branch1',
        'ssnit12',
        'ghcard1',
    ];

    protected $casts = [
        // Integers
        'noofdays'        => 'integer',
        'extraldays'      => 'integer',
        'overtimeweekend' => 'integer',
        'night'           => 'integer',

        // All double fields → float in PHP
        'total1'             => 'double',
        'rate'               => 'double',
        'total2'             => 'double',
        'overtimehr'         => 'double',
        'overtimetotal'      => 'double',
        'overtimetotal2'     => 'double',
        'totalamount'        => 'double',
        'transport'          => 'double',
        'servicecharge'      => 'double',
        'getfund'            => 'double',
        'nhil'               => 'double',
        'vat'                => 'double',
        'ssf'                => 'double',
        'leave1'             => 'double',
        'nightallowance'     => 'double',
        'crane'              => 'double',
        'medical'            => 'double',
        'costofolam'         => 'double',
        'ssnitee'            => 'double',
        'petra'            => 'double',
        'taxableincome'      => 'double',
        'totaltaxdeduction'  => 'double',
        'totaldeduction'     => 'double',
        'takehome'           => 'double',
    ];

    public function __construct($attributes = [], $tableName = null)
    {
        parent::__construct($attributes);
        if ($tableName) {
            $this->setTable($tableName);
        }
    }

    /**
     * Create monthly payroll table if it doesn't exist
     */
    public static function createTableIfNotExists($tableName)
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::statement("
                CREATE TABLE `$tableName` (
                    `id` VARCHAR(255) NOT NULL PRIMARY KEY COLLATE 'latin1_swedish_ci',
                    `names` VARCHAR(250) NOT NULL COLLATE 'latin1_swedish_ci',
                    `dept` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci',
`noofdays` INT NULL,
`extraldays` INT NULL,
`total1` DOUBLE NULL,
`rate` DOUBLE NULL,
`total2` DOUBLE NULL,
`overtimehr` DOUBLE NULL,
`overtimetotal` DOUBLE NULL,
`overtimeweekend` INT NULL,
`overtimetotal2` DOUBLE NULL,
`totalamount` DOUBLE NULL,
`transport` DOUBLE NULL,
`servicecharge` DOUBLE NULL,
`getfund` DOUBLE NULL,
`nhil` DOUBLE NULL,
`vat` DOUBLE NULL,
`ssf` DOUBLE NULL,
`leave1` DOUBLE NULL,
`night` INT NULL,
`nightallowance` DOUBLE NULL,
`crane` DOUBLE NULL,
`medical` DOUBLE NULL,
`costofolam` DOUBLE NULL,
`ssnitee` DOUBLE NULL,
`petra` DOUBLE NULL,
`taxableincome` DOUBLE NULL,
`totaltaxdeduction` DOUBLE NULL,
`totaldeduction` DOUBLE NULL,
`takehome` DOUBLE NULL,
`bank1` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci',
`account1` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci',
`branch1` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci',
`ssnit12` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci',
`ghcard1` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci'
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        }
    }

    /**
     * Switch to a specific monthly table
     */
    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
