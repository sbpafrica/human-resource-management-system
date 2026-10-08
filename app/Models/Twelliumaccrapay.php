<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Twelliumaccrapay extends Model
{
    protected $primaryKey = 'staff_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;



    protected $fillable = [
        'staff_id',
        'name',
        'position',
        'start_date',
        'basic_salary',
        'legal_work_days',
        'days_worked',
        'absent_days',
        'absent_deduction',
        'basic_earned',
        'weekday_hours',
        'weekday_overtime_amt',
        'saturday_hours',
        'saturday_overtime_amt',
        'num_sundays_holidays',
        'sunday_holiday_overtime_amt',
        'overtimerefund',
        'total_overtime_amt',
        'gross_salary',
        'management_fee',
        'getfund',
        'nhil',
        'vat_15',
        'ssf_13',
        'medical_insurance',
        'end_of_year',
        'ppe',
        'workmen_compensation',
        'cost_to_twellium',
        'ssnit_ee',
        'petra',
        'taxable_income',
        'paye',
        'deductions',
        'total_deductions',
        'net_salary',
        'refund',
        'take_home_pay',
    ];

    protected $casts = [


        // Integers
        'legal_work_days' => 'integer',
        'days_worked'     => 'integer',
        'absent_days'     => 'integer',

        // All decimal fields → cast to double (float in PHP)
        'basic_salary'               => 'double',
        'absent_deduction'           => 'double',
        'basic_earned'               => 'double',
        'weekday_hours'              => 'double',
        'weekday_overtime_amt'       => 'double',
        'saturday_hours'             => 'double',
        'saturday_overtime_amt'      => 'double',
        'num_sundays_holidays'       => 'double',
        'sunday_holiday_overtime_amt'=> 'double',
        'overtimerefund'             => 'double',
        'total_overtime_amt'         => 'double',
        'gross_salary'               => 'double',
        'management_fee'             => 'double',
        'getfund'                    => 'double',
        'nhil'                       => 'double',
        'vat_15'                     => 'double',
        'ssf_13'                     => 'double',
        'medical_insurance'          => 'double',
        'end_of_year'                => 'double',
        'ppe'                        => 'double',
        'workmen_compensation'       => 'double',
        'cost_to_twellium'           => 'double',
        'ssnit_ee'                   => 'double',
        'petra'                   => 'double',
        'taxable_income'             => 'double',
        'paye'                       => 'double',
        'deductions'                 => 'double',
        'total_deductions'           => 'double',
        'net_salary'                 => 'double',
        'refund'                     => 'double',
        'take_home_pay'              => 'double',
    ];

    public function __construct($attributes = [], $tableName = null)
    {
        parent::__construct($attributes);
        if ($tableName) {
            $this->setTable($tableName);
        }
    }

    /**
     * Create the table dynamically if it doesn't exist (perfect for monthly payroll tables)
     */
    public static function createTableIfNotExists($tableName)
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::statement("
                CREATE TABLE `$tableName` (
                    `staff_id` VARCHAR(20) NOT NULL PRIMARY KEY COLLATE 'latin1_swedish_ci',
                    `name` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `position` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `start_date` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `basic_salary` DECIMAL(12,2) NULL DEFAULT NULL,
                    `legal_work_days` INT NULL DEFAULT NULL,
                    `days_worked` INT NULL DEFAULT NULL,
                    `absent_days` INT NULL DEFAULT NULL,
                    `absent_deduction` DECIMAL(12,2) NULL DEFAULT NULL,
                    `basic_earned` DECIMAL(12,2) NULL DEFAULT NULL,
                    `weekday_hours` DECIMAL(10,2) NULL DEFAULT NULL,
                    `weekday_overtime_amt` DECIMAL(10,2) NULL DEFAULT NULL,
                    `saturday_hours` DECIMAL(10,2) NULL DEFAULT NULL,
                    `saturday_overtime_amt` DECIMAL(10,2) NULL DEFAULT NULL,
                    `num_sundays_holidays` DECIMAL(10,2) NULL DEFAULT NULL,
                    `sunday_holiday_overtime_amt` DECIMAL(10,2) NULL DEFAULT NULL,
                    `overtimerefund` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_overtime_amt` DECIMAL(10,2) NULL DEFAULT NULL,
                    `gross_salary` DECIMAL(12,2) NULL DEFAULT NULL,
                    `management_fee` DECIMAL(12,2) NULL DEFAULT NULL,
                    `getfund` DECIMAL(12,2) NULL DEFAULT NULL,
                    `nhil` DECIMAL(12,2) NULL DEFAULT NULL,
                    `vat_15` DECIMAL(12,2) NULL DEFAULT NULL,
                    `ssf_13` DECIMAL(12,2) NULL DEFAULT NULL,
                    `medical_insurance` DECIMAL(12,2) NULL DEFAULT NULL,
                    `end_of_year` DECIMAL(12,2) NULL DEFAULT NULL,
                    `ppe` DECIMAL(12,2) NULL DEFAULT NULL,
                    `workmen_compensation` DECIMAL(12,2) NULL DEFAULT NULL,
                    `cost_to_twellium` DECIMAL(12,2) NULL DEFAULT NULL,
                    `ssnit_ee` DECIMAL(12,2) NULL DEFAULT NULL,
                    `petra` DECIMAL(12,2) NULL DEFAULT NULL,
                    `taxable_income` DECIMAL(12,2) NULL DEFAULT NULL,
                    `paye` DECIMAL(12,2) NULL DEFAULT NULL,
                    `deductions` DECIMAL(12,2) NULL DEFAULT NULL,
                    `total_deductions` DECIMAL(12,2) NULL DEFAULT NULL,
                    `net_salary` DECIMAL(12,2) NULL DEFAULT NULL,
                    `refund` DECIMAL(12,2) NULL DEFAULT NULL,
                    `take_home_pay` DECIMAL(12,2) NULL DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        }
    }

    /**
     * Easily switch to a monthly payroll table
     */
    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
