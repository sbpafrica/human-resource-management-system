<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MelcomPay extends Model
{
    protected $primaryKey = 'employ';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    // Match your exact column names from the real table
    protected $fillable = [
        'employ',
        'empoyeename',
        'outsourcename',
        'designation',
        'joindate',
        'shopname',
        'category',
        'saturdayot',
        'actualdays',
        'night',
        'halfdays',
        'satoffdays',
        'sunoffdays',
        'leavededuct',
        'absentdays',
        'absenthrs',
        'holiday',
        'weekdaysOT',
        'weekendOT',
        'holidayot',
        'basic',
        'absentwithoutpay',
        'basicentitlement',
        'transport',
        'normalovertime',
        'saturdayovertime',
        'holidayotamts',
        'gross',
        'ssniter',
        'petra',
        'ssnit',
        'taxable',
        'paye',
        'totalovertime',
        'overtimetax',
        'totaltax',
        'trust',
        'totaldeduction',
        'banktransfer'
    ];

    protected $casts = [
        // All numeric fields cast to float for proper calculation
        'saturdayot'         => 'double',
        'actualdays'         => 'double',
        'night'              => 'double',
        'halfdays'           => 'double',
        'satoffdays'         => 'double',
        'sunoffdays'         => 'double',
        'leavededuct'        => 'double',
        'absentdays'         => 'double',
        'absenthrs'          => 'double',
        'holiday'            => 'double',
        'weekdaysOT'         => 'double',
        'weekendOT'          => 'double',
        'holidayot'          => 'double',
        'basic'              => 'double',
        'absentwithoutpay'   => 'double',
        'basicentitlement'   => 'double',
        'transport'          => 'double',
        'normalovertime'     => 'double',
        'saturdayovertime'   => 'double',
        'holidayotamt'       => 'double',
        'gross'              => 'double',
        'ssniter'              => 'double',
        'ssnitee'              => 'double',
        'ssnit'              => 'double',
        'taxable'           => 'double',
        'paye'              => 'double',
        'totalovertime'      => 'double',
        'overtimetax'        => 'double',
        'totaltax'           => 'double',
        'trust'              => 'double',
        'totaldeduction'     => 'double',
        'banktransfer'       => 'double',
    ];

    public function __construct($attributes = [], $tableName = null)
    {
        parent::__construct($attributes);
        if ($tableName) {
            $this->setTable($tableName);
        }
    }

    /**
     * Create table dynamically if it doesn't exist (useful for monthly payroll tables)
     */
    public static function createTableIfNotExists($tableName)
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::statement("
                CREATE TABLE `$tableName` (
                    `employ` VARCHAR(50) NOT NULL PRIMARY KEY,
                    `empoyeename` VARCHAR(150) NULL DEFAULT NULL,
                    `outsourcename` VARCHAR(100) NULL DEFAULT NULL,
                    `designation` VARCHAR(100) NULL DEFAULT NULL,
                    `joindate` VARCHAR(50) NULL DEFAULT NULL,
                    `shopname` VARCHAR(100) NULL DEFAULT NULL,
                    `category` VARCHAR(50) NULL DEFAULT NULL,
                    `saturdayot` DOUBLE NULL DEFAULT NULL,
                    `actualdays` DOUBLE NULL DEFAULT NULL,
                    `night` DOUBLE NULL DEFAULT NULL,
                    `halfdays` DOUBLE NULL DEFAULT NULL,
                    `satoffdays` DOUBLE NULL DEFAULT NULL,
                    `sunoffdays` DOUBLE NULL DEFAULT NULL,
                    `leavededuct` DOUBLE NULL DEFAULT NULL,
                    `absentdays` DOUBLE NULL DEFAULT NULL,
                    `absenthrs` DOUBLE NULL DEFAULT NULL,
                    `holiday` DOUBLE NULL DEFAULT NULL,
                    `weekdaysOT` DOUBLE NULL DEFAULT NULL,
                    `weekendOT` DOUBLE NULL DEFAULT NULL,
                    `holidayot` DOUBLE NULL DEFAULT NULL,
                    `basic` DOUBLE NULL DEFAULT NULL,
                    `absentwithoutpay` DOUBLE NULL DEFAULT NULL,
                    `basicentitlement` DOUBLE NULL DEFAULT NULL,
                    `transport` DOUBLE NULL DEFAULT NULL,
                    `normalovertime` DOUBLE NULL DEFAULT NULL,
                    `saturdayovertime` DOUBLE NULL DEFAULT NULL,
                    `holidayotamt` DOUBLE NULL DEFAULT NULL,
                    `gross` DOUBLE NULL DEFAULT NULL,
                    `ssniter` DOUBLE NULL DEFAULT NULL,
                     `ssnitee` DOUBLE NULL DEFAULT NULL,
                      `ssnit` DOUBLE NULL DEFAULT NULL,
                    `taxable` DOUBLE NULL DEFAULT NULL,
                    `paye` DOUBLE NULL DEFAULT NULL,
                    `totalovertime` DOUBLE NULL DEFAULT NULL,
                    `overtimetax` DOUBLE NULL DEFAULT NULL,
                    `totaltax` DOUBLE NULL DEFAULT NULL,
                    `trust` DOUBLE NULL DEFAULT NULL,
                    `totaldeduction` DOUBLE NULL DEFAULT NULL,
                    `banktransfer` DOUBLE NULL DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }
    }

    /**
     * Scope to switch table dynamically (e.g., payroll_november_2025)
     */
    public static function withTable($tableName)
    {
        return (new static([], $tableName));
    }
}
