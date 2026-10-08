<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Ofioffloadpay extends Model
{
    protected $primaryKey = 'StaffID';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;



    protected $fillable = [
        'StaffID',
        'employeename',
        'workarea',
        'basicsalary',
        'arrears',
        'gross',
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
        'bankaa',
        'accountnumberaa',
        'branchaa',
        'ssnitaa',
    ];

    protected $casts = [
        // Decimal(10,2) & Decimal(5,2) fields → float in PHP
        'basicsalary'          => 'double',
        'servicecharge'        => 'double',
        'getfund'              => 'double',
        'nhil'                 => 'double',
        
        'vat'                  => 'double',
        'ssf'                  => 'double',
        'costtoolam'           => 'double',
        'ssnitee'              => 'double',
        'petra'              => 'double',
        'taxableincome'        => 'double',
        'totaltaxdeductions'   => 'double',
        'totaldeductions'      => 'double',
        'takehomepay'          => 'double',

        // Double fields
        'arrears'              => 'double',
        'gross'                => 'double',
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
                    `StaffID` VARCHAR(255) NOT NULL PRIMARY KEY COLLATE 'latin1_swedish_ci',
                    `employeename` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `workarea` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `basicsalary` DECIMAL(10,2) NULL DEFAULT NULL,
                    `arrears` DECIMAL(10,2) NULL DEFAULT NULL,
                    `gross` DOUBLE NOT NULL,
                    `servicecharge` DECIMAL(5,2) NULL DEFAULT NULL,
                    `getfund` DECIMAL(5,2) NULL DEFAULT NULL,
                    `nhil` DECIMAL(5,2) NULL DEFAULT NULL,
                    
                    `vat` DECIMAL(5,2) NULL DEFAULT NULL,
                    `ssf` DECIMAL(5,2) NULL DEFAULT NULL,
                    `costtoolam` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ssnitee` DECIMAL(5,2) NULL DEFAULT NULL,
                    `petra` DECIMAL(5,2) NULL DEFAULT NULL,
                    `taxableincome` DECIMAL(10,2) NULL DEFAULT NULL,
                    `totaltaxdeductions` DECIMAL(10,2) NULL DEFAULT NULL,
                    `totaldeductions` DECIMAL(10,2) NULL DEFAULT NULL,
                    `takehomepay` DECIMAL(10,2) NULL DEFAULT NULL,
                    `bankaa` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `accountnumberaa` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `branchaa` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `ssnitaa` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci'
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        }
    }

    /**
     * Switch to a specific monthly table (e.g. payroll_olam_november_2025)
     */
    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
