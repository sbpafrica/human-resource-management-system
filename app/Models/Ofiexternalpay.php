<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Ofiexternalpay extends Model
{
    protected $primaryKey = 'staffid';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;



    protected $fillable = [
        'staffid',
        'employeename',
        'workarea',
        'startdate',
        'basicsalary',
        'legalworkdays',
        'noofdayspresent',
        'basicsalaryearned',
        'weekdayovertimehr',
        'weekdayovertimetotal',
        'weekendovertimehr',
        'weekendovertimetotal',
        'totalamount',
        'transport',
        'servicecharge',
        'getfund',
        'nhil',
        
        'vat',
        'ssf',
        'costtoolam',
        'ssnitee',
        'petra',
        'taxableincome',
        'totaltaxdeductions',
        'totaldeductions',
        'takehomepay',
        'bank',
        'accountnumber',
        'branch',
        'ssnit',
        'ghcard',
    ];

    protected $casts = [
        // Integers
        'legalworkdays'    => 'integer',
        'noofdayspresent'  => 'integer',

        // All decimal(10,2) fields → float in PHP
        'basicsalary'           => 'double',
        'basicsalaryearned'     => 'double',
        'weekdayovertimehr'     => 'double',
        'weekdayovertimetotal'  => 'double',
        'weekendovertimehr'     => 'double',
        'weekendovertimetotal'  => 'double',
        'totalamount'           => 'double',
        'transport'             => 'double',
        'servicecharge'         => 'double',
        'getfund'               => 'double',
        'nhil'                  => 'double',
       
        'vat'                   => 'double',
        'ssf'                   => 'double',
        'costtoolam'            => 'double',
        'ssnitee'               => 'double',
        'petra'               => 'double',
        'taxableincome'         => 'double',
        'totaltaxdeductions'    => 'double',
        'totaldeductions'       => 'double',
        'takehomepay'           => 'double',
    ];

    public function __construct($attributes = [], $tableName = null)
    {
        parent::__construct($attributes);
        if ($tableName) {
            $this->setTable($tableName);
        }
    }

    /**
     * Create the monthly payroll table if it doesn't exist
     */
    public static function createTableIfNotExists($tableName)
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::statement("
                CREATE TABLE `$tableName` (
                    `staffid` VARCHAR(50) NOT NULL PRIMARY KEY COLLATE 'latin1_swedish_ci',
                    `employeename` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `workarea` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `startdate` VARCHAR(255) NOT NULL COLLATE 'latin1_swedish_ci',
                    `basicsalary` DECIMAL(10,2) NULL DEFAULT NULL,
                    `legalworkdays` INT NULL DEFAULT NULL,
                    `noofdayspresent` INT NULL DEFAULT NULL,
                    `basicsalaryearned` DECIMAL(10,2) NULL DEFAULT NULL,
                    `weekdayovertimehr` DECIMAL(10,2) NULL DEFAULT NULL,
                    `weekdayovertimetotal` DECIMAL(10,2) NULL DEFAULT NULL,
                    `weekendovertimehr` DECIMAL(10,2) NULL DEFAULT NULL,
                    `weekendovertimetotal` DECIMAL(10,2) NULL DEFAULT NULL,
                    `totalamount` DECIMAL(10,2) NULL DEFAULT NULL,
                    `transport` DECIMAL(10,2) NULL DEFAULT NULL,
                    `servicecharge` DECIMAL(10,2) NULL DEFAULT NULL,
                    `getfund` DECIMAL(10,2) NULL DEFAULT NULL,
                    `nhil` DECIMAL(10,2) NULL DEFAULT NULL,
                   
                    `vat` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ssf` DECIMAL(10,2) NULL DEFAULT NULL,
                    `costtoolam` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ssnitee` DECIMAL(10,2) NULL DEFAULT NULL,
                     `petra` DECIMAL(10,2) NULL DEFAULT NULL,
                    `taxableincome` DECIMAL(10,2) NULL DEFAULT NULL,
                    `totaltaxdeductions` DECIMAL(10,2) NULL DEFAULT NULL,
                    `totaldeductions` DECIMAL(10,2) NULL DEFAULT NULL,
                    `takehomepay` DECIMAL(10,2) NULL DEFAULT NULL,
                    `bank` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `accountnumber` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `branch` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `ssnit` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `ghcard` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci'
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        }
    }

    /**
     * Switch to a specific monthly table easily
     */
    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
