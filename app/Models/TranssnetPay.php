<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TranssnetPay extends Model
{
    protected $primaryKey = 'staffid';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'staffid',
        'name',
        'business_line',
        'department',
        'position',
        'hired_date',
        'last_date',
        'work_place',
        'staff_category',
        'expiration_date',
        'contracting_entity',
        'bank_name',
        'account_name',
        'bank_account_no',
        'branch_code',
        'basic',
        'performance_basis',
        'coefficient',
        'performance_final',
        'call_allowance',
        'house_allowance',
        'traffic_allowance',
        'commission',
        'severance_pay',
        'other_income',
        'work_days',
        'absent_days',
        'absent_days_deduction',
        'other_deduction',
        'gross_salary',
        'petra_ee_5',
        'ssnit_ee_05',
        'base_for_paye',
        'ee_paye',
        'ssnit_er_13',
        'outsourced_service',
        'getfund',
        'nhiil',
       
        'vat_for_music_ghana',
        'total_cost_of_outsource_service',
        'ee_cost',
        'er_cost',
        'after_tax_deductions',
        'netpay',
        'total_hr_cost'
    ];

    protected $casts = [


        // Integer fields
        'work_days'       => 'integer',
        'absent_days'     => 'integer',

        // All decimal(10,2) fields cast to double (float in PHP)
        'basic'                          => 'double',
        'coefficient'                    => 'double',
        'performance_final'              => 'double',
        'call_allowance'                 => 'double',
        'house_allowance'                => 'double',
        'traffic_allowance'              => 'double',
        'commission'                     => 'double',
        'severance_pay'                  => 'double',
        'other_income'                   => 'double',
        'absent_days_deduction'          => 'double',
        'other_deduction'                => 'double',
        'gross_salary'                   => 'double',
        'petra_ee_5'                     => 'double',
        'ssnit_ee_05'                    => 'double',
        'base_for_paye'                  => 'double',
        'ee_paye'                        => 'double',
        'ssnit_er_13'                    => 'double',
        'outsourced_service'             => 'double',
        'getfund'                        => 'double',
        'nhiil'                          => 'double',
        
        'vat_for_music_ghana'            => 'double',
        'total_cost_of_outsource_service'=> 'double',
        'ee_cost'                        => 'double',
        'er_cost'                        => 'double',
        'after_tax_deductions'           => 'double',
        'netpay'                         => 'double',
        'total_hr_cost'                  => 'double',
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
                    `staffid` VARCHAR(255) NOT NULL PRIMARY KEY COLLATE 'latin1_swedish_ci',
                    `name` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `business_line` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `department` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `position` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `hired_date` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `last_date` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `work_place` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `staff_category` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `expiration_date` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `contracting_entity` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `bank_name` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `account_name` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `bank_account_no` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `branch_code` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `basic` DECIMAL(10,2) NULL DEFAULT NULL,
                    `performance_basis` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `coefficient` DECIMAL(10,2) NULL DEFAULT NULL,
                    `performance_final` DECIMAL(10,2) NULL DEFAULT NULL,
                    `call_allowance` DECIMAL(10,2) NULL DEFAULT NULL,
                    `house_allowance` DECIMAL(10,2) NULL DEFAULT NULL,
                    `traffic_allowance` DECIMAL(10,2) NULL DEFAULT NULL,
                    `commission` DECIMAL(10,2) NULL DEFAULT NULL,
                    `severance_pay` DECIMAL(10,2) NULL DEFAULT NULL,
                    `other_income` DECIMAL(10,2) NULL DEFAULT NULL,
                    `work_days` INT NULL DEFAULT NULL,
                    `absent_days` INT NULL DEFAULT NULL,
                    `absent_days_deduction` DECIMAL(10,2) NULL DEFAULT NULL,
                    `other_deduction` DECIMAL(10,2) NULL DEFAULT NULL,
                    `gross_salary` DECIMAL(10,2) NULL DEFAULT NULL,
                    `petra_ee_5` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ssnit_ee_05` DECIMAL(10,2) NULL DEFAULT NULL,
                    `base_for_paye` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ee_paye` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ssnit_er_13` DECIMAL(10,2) NULL DEFAULT NULL,
                    `outsourced_service` DECIMAL(10,2) NULL DEFAULT NULL,
                    `getfund` DECIMAL(10,2) NULL DEFAULT NULL,
                    `nhiil` DECIMAL(10,2) NULL DEFAULT NULL,
                    
                    `vat_for_music_ghana` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_cost_of_outsource_service` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ee_cost` DECIMAL(10,2) NULL DEFAULT NULL,
                    `er_cost` DECIMAL(10,2) NULL DEFAULT NULL,
                    `after_tax_deductions` DECIMAL(10,2) NULL DEFAULT NULL,
                    `netpay` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_hr_cost` DECIMAL(10,2) NULL DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
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
