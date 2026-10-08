<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CenturyPay extends Model
{
    protected $primaryKey = 'employ';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'employ',
        'empoyeename',
        'outsourced',
        'desig',
        'joindate',
        'shopname',
        'actualdays',
        'night',
        'halfdays',
        'satoffdays',
        'sunoffdays',
        'leavedays',
        'absentdays',
        'holiday',
        'weekdaysOT',
        'weekendOT',
        'basic',
        'absentwithoutpay',
        'basicentitlement',
        'transport',
        'nightallowance',
        'normalovertime',
        'saturdayovertime',
        'gross',
        'ssniter',
        'ssnitee',
        'ssnit',
        'taxabled',
        'paye',
        'totalovertime',
        'overtimetax',
        'totaltax',
        'advance',
        'totaldeduction',
        'netpay'
    ];

    protected $casts = [
        'actualdays'        => 'integer',
        'night'             => 'integer',
        'halfdays'          => 'integer',
        'satoffdays'        => 'integer',
        'sunoffdays'        => 'integer',
        'leavedays'         => 'integer',
        'absentdays'        => 'integer',
        'holiday'           => 'integer',
        'weekdaysOT'        => 'integer',
        'weekendOT'         => 'integer',
        'basic'             => 'double',
        'absentwithoutpay'  => 'double',
        'basicentitlement'  => 'double',
        'transport'         => 'double',
        'nightallowance'    => 'double',
        'normalovertime'    => 'double',
        'saturdayovertime'  => 'double',
        'gross'             => 'double',
        'ssniter'             => 'double',
        'ssnitee'             => 'double',
        'ssnit'             => 'double',
       
        'taxabled'          => 'double',
        'paye'              => 'double',
        'totalovertime'     => 'double',
        'overtimetax'       => 'double',
        'totaltax'          => 'double',
        'advance'           => 'double',
        'totaldeduction'    => 'double',
        'netpay'            => 'double',
    ];

    public function __construct($attributes = [], $tableName = null)
    {
        parent::__construct($attributes);
        if ($tableName) {
            $this->setTable($tableName);
        }
    }

    public static function createTableIfNotExists($tableName)
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::statement("
                CREATE TABLE `$tableName` (
                    `employ` VARCHAR(10) NOT NULL PRIMARY KEY,
                    `empoyeename` VARCHAR(150) NULL DEFAULT NULL,
                    `outsourced` VARCHAR(255) NULL DEFAULT NULL,
                    `desig` VARCHAR(255) NULL DEFAULT NULL,
                    `joindate` VARCHAR(255) NULL DEFAULT NULL,
                    `shopname` VARCHAR(150) NULL DEFAULT NULL,
                    `actualdays` INT NOT NULL DEFAULT 0,
                    `night` INT NOT NULL DEFAULT 0,
                    `halfdays` INT NOT NULL DEFAULT 0,
                    `satoffdays` INT NOT NULL DEFAULT 0,
                    `sunoffdays` INT NOT NULL DEFAULT 0,
                    `leavedays` INT NOT NULL DEFAULT 0,
                    `absentdays` INT NOT NULL DEFAULT 0,
                    `holiday` INT NOT NULL DEFAULT 0,
                    `weekdaysOT` INT NOT NULL DEFAULT 0,
                    `weekendOT` INT NOT NULL DEFAULT 0,
                    `basic` DOUBLE NOT NULL DEFAULT 0,
                    `absentwithoutpay` DOUBLE NOT NULL DEFAULT 0,
                    `basicentitlement` DOUBLE NOT NULL DEFAULT 0,
                    `transport` DOUBLE NOT NULL DEFAULT 0,
                    `nightallowance` DOUBLE NOT NULL DEFAULT 0,
                    `normalovertime` DOUBLE NOT NULL DEFAULT 0,
                    `saturdayovertime` DOUBLE NOT NULL DEFAULT 0,
                    `gross` DOUBLE NOT NULL DEFAULT 0,
                    `ssniter` DOUBLE NOT NULL DEFAULT 0,
                    `ssnitee` DOUBLE NOT NULL DEFAULT 0,
                    `ssnit` DOUBLE NOT NULL DEFAULT 0,
                    `taxabled` DOUBLE NOT NULL DEFAULT 0,
                    `paye` DOUBLE NOT NULL DEFAULT 0,
                    `totalovertime` DOUBLE NOT NULL DEFAULT 0,
                    `overtimetax` DOUBLE NOT NULL DEFAULT 0,
                    `totaltax` DOUBLE NOT NULL DEFAULT 0,
                    `advance` DOUBLE NOT NULL DEFAULT 0,
                    `totaldeduction` DOUBLE NOT NULL DEFAULT 0,
                    `netpay` DOUBLE NOT NULL DEFAULT 0
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        }
    }

    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
