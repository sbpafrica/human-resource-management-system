<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class MelcomSalary extends Model
{
    protected $primaryKey = 'employ';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    public function __construct($attributes = [], $tableName = null)
    {
        parent::__construct($attributes);
        if ($tableName) {
            $this->setTable($tableName);
        }
    }

    public static function createTableIfNotExists($tableName)
    {
        if (!Schema::hasTable($tableName)) {
            DB::statement("
                CREATE TABLE `$tableName` (
                    `employ` VARCHAR(50) NOT NULL PRIMARY KEY,
                    `empoyeename` VARCHAR(150) NULL,
                    `outsourcename` VARCHAR(100) NULL,
                    `designation` VARCHAR(100) NULL,
                    `joindate` VARCHAR(50) NULL,
                    `shopname` VARCHAR(100) NULL,
                    `category` VARCHAR(50) NULL,
                    `saturdayot` DOUBLE NULL,
                    `actualdays` DOUBLE NULL,
                    `night` DOUBLE NULL,
                    `halfdays` DOUBLE NULL,
                    `satoffdays` DOUBLE NULL,
                    `sunoffdays` DOUBLE NULL,
                    `leavededuct` DOUBLE NULL,
                    `absentdays` DOUBLE NULL,
                    `absenthrs` DOUBLE NULL,
                    `holiday` DOUBLE NULL,
                    `weekdaysOT` DOUBLE NULL,
                    `weekendOT` DOUBLE NULL,
                    `holidayot` DOUBLE NULL,
                    `basic` DOUBLE NULL,
                    `absentwithoutpay` DOUBLE NULL,
                    `basicentitlement` DOUBLE NULL,
                    `transport` DOUBLE NULL,
                    `normalovertime` DOUBLE NULL,
                    `saturdayovertime` DOUBLE NULL,
                    `holidayotamt` DOUBLE NULL,
                    `gross` DOUBLE NULL,
                    `ssnit` DOUBLE NULL,
                    `taxable` DOUBLE NULL,
                    `paye` DOUBLE NULL,
                    `totalovertime` DOUBLE NULL,
                    `overtimetax` DOUBLE NULL,
                    `totaltax` DOUBLE NULL,
                    `trust` DOUBLE NULL,
                    `totaldeduction` DOUBLE NULL,
                    `banktransfer` DOUBLE NULL,
                    INDEX idx_empoyeename (empoyeename(50)),
                    INDEX idx_shopname (shopname(50)),
                    INDEX idx_banktransfer (banktransfer)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }
    }

    public static function withTable($tableName)
    {
        $instance = new static([], $tableName);
        return $instance;
    }

    public static function getTableName($month)
    {
        $date = Carbon::parse($month);
        return 'melcom' . strtolower($date->format('F')) . $date->format('y');
    }
}
