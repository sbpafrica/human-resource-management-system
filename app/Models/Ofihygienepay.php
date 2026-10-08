<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Ofihygienepay extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $table = 'payroll'; // default table

    protected $fillable = [
        'id',
        'names',
        'dept',
        'startdate',
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
        'covid19',
        'vat',
        'ssf',
        'leave1',
        'night',
        'nightallowance',
        'medical',
        'costofolam',
        'ssnitee',
        'petra',
        'taxableincome',
        'totaltaxdeduction',
        'totaldeduction',
        'takehome',
    ];

    protected $casts = [
        // Integers
        'noofdays'        => 'integer',
        'extraldays'      => 'integer',
        'overtimeweekend' => 'integer',
        'night'           => 'integer',

        // All double / decimal fields → float in PHP
        'total1'            => 'double',
        'rate'              => 'double',
        'total2'            => 'double',
        'overtimehr'        => 'double',
        'overtimetotal'     => 'double',
        'overtimetotal2'    => 'double',
        'totalamount'       => 'double',
        'transport'         => 'double',
        'servicecharge'     => 'double',
        'getfund'           => 'double',
        'nhil'              => 'double',
        'covid19'           => 'double',
        'vat'               => 'double',
        'ssf'               => 'double',
        'leave1'            => 'double',
        'nightallowance'    => 'double',
        'medical'           => 'double',
        'costofolam'        => 'double',
        'ssnitee'           => 'double',
        'petra'           => 'double',
        'taxableincome'     => 'double',
        'totaltaxdeduction' => 'double',
        'totaldeduction'    => 'double',
        'takehome'          => 'double',
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
                    `dept` VARCHAR(255) NOT NULL COLLATE 'latin1_swedish_ci',
                    `startdate` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `noofdays` INT NULL DEFAULT NULL,
                    `extraldays` INT NULL DEFAULT NULL,
                    `total1` DOUBLE NULL DEFAULT NULL,
                    `rate` DOUBLE NULL DEFAULT NULL,
                    `total2` DOUBLE NULL DEFAULT NULL,
                    `overtimehr` DOUBLE NULL DEFAULT NULL,
                    `overtimetotal` DOUBLE NULL DEFAULT NULL,
                    `overtimeweekend` INT NULL DEFAULT NULL,
                    `overtimetotal2` DOUBLE NULL DEFAULT NULL,
                    `totalamount` DOUBLE NULL DEFAULT NULL,
                    `transport` DOUBLE NULL DEFAULT NULL,
                    `servicecharge` DOUBLE NULL DEFAULT NULL,
                    `getfund` DOUBLE NULL DEFAULT NULL,
                    `nhil` DOUBLE NULL DEFAULT NULL,
                    `covid19` DOUBLE NULL DEFAULT NULL,
                    `vat` DOUBLE NULL DEFAULT NULL,
                    `ssf` DOUBLE NULL DEFAULT NULL,
                    `leave1` DOUBLE NULL DEFAULT NULL,
                    `night` INT NULL DEFAULT NULL,
                    `nightallowance` DOUBLE NULL DEFAULT NULL,
                    `medical` DOUBLE NULL DEFAULT NULL,
                    `costofolam` DOUBLE NULL DEFAULT NULL,
                    `ssnitee` DOUBLE NULL DEFAULT NULL,
                    `petra` DOUBLE NULL DEFAULT NULL,
                    `taxableincome` DOUBLE NULL DEFAULT NULL,
                    `totaltaxdeduction` DOUBLE NULL DEFAULT NULL,
                    `totaldeduction` DOUBLE NULL DEFAULT NULL,
                    `takehome` DOUBLE NULL DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        }
    }

    /**
     * Switch to any monthly table easily
     */
    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
