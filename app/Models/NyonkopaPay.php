<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NyonkopaPay extends Model
{
    protected $primaryKey = 'staff_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;



    protected $fillable = [
        'staff_id',
        'employee_name',
        'start_date',
        'position',
        'work_location',
        'base_salary',
        'fuel_and_rent_allowance',
        'gross_salary',
        'management_fee_8',
        'getfund_2_5',
        'nhil_2_5',
       
        'vat_15',
        'medical_insurance',
        'employer_provident_fund_5',
        'thirteenth_month_bonus',
        'workman',
        'ssnit_er_13',
        'cost_to_nyonkopa',
        'ssnit_ee_5_5',
        'petra',
        'employeepf',
        
        'taxable_income',
        'paye',
        'thirteenth_month_tax',
        'total_taxation',
        'deductions',
        'total_deductions',
        'take_home_pay',
    ];

    protected $casts = [


        // All decimal(10,2) fields → cast to float in PHP
        'base_salary'                   => 'double',
        'fuel_and_rent_allowance'       => 'double',
        'gross_salary'                  => 'double',
        'management_fee_8'              => 'double',
        'getfund_2_5'                   => 'double',
        'nhil_2_5'                      => 'double',
        
        'vat_15'                        => 'double',
        'medical_insurance'             => 'double',
        'employer_provident_fund_5'     => 'double',
        'thirteenth_month_bonus'        => 'double',
         'workman'                      => 'double',
        'ssnit_er_13'                   => 'double',
        'cost_to_nyonkopa'              => 'double',
        'ssnit_ee_5_5'                  => 'double',
        'petra'                  => 'double',
        'employeepf'                  => 'double',
        'taxable_income'                => 'double',
        'paye'                          => 'double',
        'thirteenth_month_tax'          => 'double',
        'total_taxation'                => 'double',
        'deductions'                => 'double',
        'total_deductions'              => 'double',
        'take_home_pay'                 => 'double',
    ];

    public function __construct($attributes = [], $tableName = null)
    {
        parent::__construct($attributes);
        if ($tableName) {
            $this->setTable($tableName);
        }
    }

    /**
     * Create the payroll table dynamically if it doesn't exist (for monthly tables)
     */
    public static function createTableIfNotExists($tableName)
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::statement("
                CREATE TABLE `$tableName` (
                    `staff_id` VARCHAR(50) NOT NULL PRIMARY KEY COLLATE 'latin1_swedish_ci',
                    `employee_name` VARCHAR(150) NOT NULL COLLATE 'latin1_swedish_ci',
                    `start_date` VARCHAR(150) NOT NULL COLLATE 'latin1_swedish_ci',
                    `position` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `work_location` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `base_salary` DECIMAL(10,2) NULL DEFAULT NULL,
                    `fuel_and_rent_allowance` DECIMAL(10,2) NULL DEFAULT NULL,
                    `gross_salary` DECIMAL(10,2) NULL DEFAULT NULL,
                    `management_fee_8` DECIMAL(10,2) NULL DEFAULT NULL,
                    `getfund_2_5` DECIMAL(10,2) NULL DEFAULT NULL,
                    `nhil_2_5` DECIMAL(10,2) NULL DEFAULT NULL,
                  
                    `vat_15` DECIMAL(10,2) NULL DEFAULT NULL,
                    `medical_insurance` DECIMAL(10,2) NULL DEFAULT NULL,
                    `employer_provident_fund_5` DECIMAL(10,2) NULL DEFAULT NULL,
                    `thirteenth_month_bonus` DECIMAL(10,2) NULL DEFAULT NULL,
                    `workman` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ssnit_er_13` DECIMAL(10,2) NULL DEFAULT NULL,
                    `cost_to_nyonkopa` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ssnit_ee_5_5` DECIMAL(10,2) NULL DEFAULT NULL,
                    `petra` DECIMAL(10,2) NULL DEFAULT NULL,
                    `employeepf` DECIMAL(10,2) NULL DEFAULT NULL,
                    `taxable_income` DECIMAL(10,2) NULL DEFAULT NULL,
                    `paye` DECIMAL(10,2) NULL DEFAULT NULL,
                    `thirteenth_month_tax` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_taxation` DECIMAL(10,2) NULL DEFAULT NULL,
                    `deductions` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_deductions` DECIMAL(10,2) NULL DEFAULT NULL,
                    `take_home_pay` DECIMAL(10,2) NULL DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        }
    }

    /**
     * Scope to use a different table (e.g. payroll_november_2025)
     */
    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
