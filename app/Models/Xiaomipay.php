<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Xiaomipay extends Model
{
    protected $primaryKey = 'staffida';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'staffida', 'name', 'department', 'hiredate', 'constart', 'conend', 'position', 'grade',
        'categories', 'workplace', 'bankname', 'alcnum', 'branch', 'ssnit', 'COL 12', 'workdays',
        'leavedays', 'otworkdays', 'otweekend', 'basic', 'performbonus', 'coefficient',
        'performstand', 'allowance', 'commission', 'otworkingday', 'otholiday', 'other',
        'annaulbonus', 'leavededuction', 'otherdeduction', 'loan', 'grosspay', 'excess',
        'totalemolu', 'accommodation', 'chargeincome', 'petra', 'ssnitamt', 'nssf',
        'taxbase', 'paye', 'annaultax', 'netpay', 'hrcost', 'brandname'
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
                    `staffida` VARCHAR(50) NOT NULL PRIMARY KEY,
                    `name` VARCHAR(150) NULL,
                    `department` VARCHAR(50) NULL,
                    `hiredate` VARCHAR(255) NULL,
                    `constart` VARCHAR(255) NULL,
                    `conend` VARCHAR(255) NULL,
                    `position` VARCHAR(50) NULL,
                    `grade` VARCHAR(50) NULL,
                    `categories` VARCHAR(50) NULL,
                    `workplace` VARCHAR(50) NULL,
                    `bankname` VARCHAR(50) NULL,
                    `alcnum` VARCHAR(50) NULL,
                    `branch` VARCHAR(50) NULL,
                    `ssnit` VARCHAR(50) NULL,
                    `COL 12` VARCHAR(255) NULL,
                    `workdays` DOUBLE NULL,
                    `leavedays` DOUBLE NULL,
                    `otworkdays` DOUBLE NULL,
                    `otweekend` DOUBLE NULL,
                    `basic` DOUBLE NULL,
                    `performbonus` DOUBLE NULL,
                    `coefficient` DOUBLE NULL,
                    `performstand` DOUBLE NULL,
                    `allowance` DOUBLE NULL,
                    `commission` DOUBLE NULL,
                    `otworkingday` DOUBLE NULL,
                    `otholiday` DOUBLE NULL,
                    `other` DOUBLE NULL,
                    `annaulbonus` DOUBLE NULL,
                    `leavededuction` DOUBLE NULL,
                    `otherdeduction` DOUBLE NULL,
                    `loan` DOUBLE NULL,
                    `grosspay` DOUBLE NULL,
                    `excess` DOUBLE NULL,
                    `totalemolu` DOUBLE NULL,
                    `accommodation` DOUBLE NULL,
                    `chargeincome` DOUBLE NULL,
                    `petra` DOUBLE NULL,
                    `ssnitamt` DOUBLE NULL,
                    `nssf` DOUBLE NULL,
                    `taxbase` DOUBLE NULL,
                    `paye` DOUBLE NULL,
                    `annaultax` DOUBLE NULL,
                    `netpay` DOUBLE NULL,
                    `hrcost` DOUBLE NULL,
                    `brandname` VARCHAR(255)  NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
            ");
        }
    }

    public static function withTable($tableName)
    {
        $instance = new static([], $tableName);
        return $instance;
    }
}
