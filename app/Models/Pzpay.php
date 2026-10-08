<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Pzpay extends Model
{
    protected $primaryKey = 'staffid';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;



    protected $fillable = [
        'staffid',
        'name',
        'dept',
        'basic',
        'overtime',
        'incentive',
        'bonus',
        'other',
        '13month',
        'totalearn',
        'ssnitee',
        'overtimetaxt',
        'pf',
        'taxable',
        'ssniter',
        'petra',
        '13tax',
        'totaltaxdeduct',
        'totaldeduct',
        'netpay',
        'bank1',
        'account1',
        'branch1',
        'ssnit1',
        'ghcard',
    ];

    protected $casts = [
        // All double columns → cast to float in PHP
        'basic'          => 'double',
        'overtime'       => 'double',
        'incentive'      => 'double',
        'bonus'          => 'double',
        'other'          => 'double',
        '13month'        => 'double',
        'totalearn'      => 'double',
        'ssnitee'        => 'double',
        'overtimetaxt'   => 'double',
        'pf'             => 'double',
        'taxable'        => 'double',
        'ssniter'        => 'double',
        'petra'          => 'double',
        '13tax'          => 'double',
        'totaltaxdeduct' => 'double',
        'totaldeduct'    => 'double',
        'netpay'         => 'double',
    ];

    public function __construct($attributes = [], $tableName = null)
    {
        parent::__construct($attributes);
        if ($tableName) {
            $this->setTable($tableName);
        }
    }

    /**
     * Create table dynamically if it doesn't exist (great for monthly payroll tables)
     */
    public static function createTableIfNotExists($tableName)
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::statement("
                CREATE TABLE `$tableName` (
                    `staffid` VARCHAR(20) NOT NULL PRIMARY KEY COLLATE 'latin1_swedish_ci',
                    `name` VARCHAR(255) NOT NULL COLLATE 'latin1_swedish_ci',
                    `dept` VARCHAR(255) NOT NULL COLLATE 'latin1_swedish_ci',
                    `basic` DOUBLE NOT NULL,
                    `overtime` DOUBLE NULL,
                    `incentive` DOUBLE NULL,
                    `bonus` DOUBLE NULL,
                    `other` DOUBLE NULL,
                    `13month` DOUBLE NULL,
                    `totalearn` DOUBLE NOT NULL,
                    `ssnitee` DOUBLE NULL,
                    `overtimetaxt` DOUBLE NULL,
                    `pf` DOUBLE NULL,
                    `taxable` DOUBLE NULL,
                    `ssniter` DOUBLE NULL,
                     `petra` DOUBLE NULL,
                    `13tax` DOUBLE NULL,
                    `totaltaxdeduct` DOUBLE NOT NULL,
                    `totaldeduct` DOUBLE NOT NULL,
                    `netpay` DOUBLE NOT NULL,
                    `bank1` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci',
                    `account1` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci',
                    `branch1` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci',
                    `ssnit1` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci',
                    `ghcard` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci'
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        }
    }

    /**
     * Easily switch to a monthly table (e.g. payroll_november_2025)
     */
    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
