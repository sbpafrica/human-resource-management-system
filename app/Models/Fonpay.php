<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Fonpay extends Model
{
    protected $primaryKey = 'staff_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;



    protected $fillable = [
        'staff_id',
        'name',
        'position',
        'date_of_employment',
        'basic_salary',
        'legal_work_days',
        'number_of_days_worked',
        'no_of_absent_days',
        'absent_days_deduction',
        'basic_salary_earned',
        'number_of_days_on_night_duty',
        'normal_weekday_overtime_hours',
        'normal_weekday_overtime_rate',
        'weekday_overtime_total',
        'saturday_overtime_hours',
        'saturday_overtime_rate',
        'saturday_overtime_total',
        'sunday_holiday_overtime_hours',
        'sunday_holiday_overtime_rate',
        'sunday_holiday_overtime_total',
        'total_overtime_amount',
        'night_duty_allowance',
        'gross_salary',
        'ssf_13',
        'medical_insurance',
        'ppe',
        'workmen_compensation',
        'management_fee_10',
        'getfund_2_5',
        'nhil_2_5',
        
        'vat_15',
        'cost_to_fon_packaging',
        'gross_salary_repeat',
        'ssnit_ee_5_5',
        'petra',
        'taxable_income',
        'paye',
        'overtime_tax',
        'total_taxation',
        'total_deductions',
        'take_home_pay',
    ];

    protected $casts = [

        // Integer fields
        'legal_work_days'           => 'integer',
        'number_of_days_worked'     => 'integer',
        'no_of_absent_days'         => 'integer',
        'number_of_days_on_night_duty' => 'integer',

        // All decimal(10,2) fields → cast to float in PHP
        'basic_salary'                  => 'double',
        'absent_days_deduction'         => 'double',
        'basic_salary_earned'           => 'double',
        'normal_weekday_overtime_hours' => 'double',
        'normal_weekday_overtime_rate'  => 'double',
        'weekday_overtime_total'        => 'double',
        'saturday_overtime_hours'       => 'double',
        'saturday_overtime_rate'        => 'double',
        'saturday_overtime_total'       => 'double',
        'sunday_holiday_overtime_hours' => 'double',
        'sunday_holiday_overtime_rate'  => 'double',
        'sunday_holiday_overtime_total' => 'double',
        'total_overtime_amount'         => 'double',
        'night_duty_allowance'          => 'double',
        'gross_salary'                  => 'double',
        'ssf_13'                        => 'double',
        'medical_insurance'             => 'double',
        'ppe'                           => 'double',
        'workmen_compensation'          => 'double',
        'management_fee_10'             => 'double',
        'getfund_2_5'                   => 'double',
        'nhil_2_5'                      => 'double',
        
        'vat_15'                        => 'double',
        'cost_to_fon_packaging'         => 'double',
        'gross_salary_repeat'           => 'double',
        'ssnit_ee_5_5'                  => 'double',
        'petra'                         => 'double',
        'taxable_income'                => 'double',
        'paye'                          => 'double',
        'overtime_tax'                  => 'double',
        'total_taxation'                => 'double',
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
     * Create monthly payroll table if it doesn't exist
     */
    public static function createTableIfNotExists($tableName)
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::statement("
                CREATE TABLE `$tableName` (
                    `staff_id` VARCHAR(50) NOT NULL PRIMARY KEY COLLATE 'latin1_swedish_ci',
                    `name` VARCHAR(150) NOT NULL COLLATE 'latin1_swedish_ci',
                    `position` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `date_of_employment` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `basic_salary` DECIMAL(10,2) NULL DEFAULT NULL,
                    `legal_work_days` INT NULL DEFAULT NULL,
                    `number_of_days_worked` INT NULL DEFAULT NULL,
                    `no_of_absent_days` INT NULL DEFAULT NULL,
                    `absent_days_deduction` DECIMAL(10,2) NULL DEFAULT NULL,
                    `basic_salary_earned` DECIMAL(10,2) NULL DEFAULT NULL,
                    `number_of_days_on_night_duty` INT NULL DEFAULT NULL,
                    `normal_weekday_overtime_hours` DECIMAL(10,2) NULL DEFAULT NULL,
                    `normal_weekday_overtime_rate` DECIMAL(10,2) NULL DEFAULT NULL,
                    `weekday_overtime_total` DECIMAL(10,2) NULL DEFAULT NULL,
                    `saturday_overtime_hours` DECIMAL(10,2) NULL DEFAULT NULL,
                    `saturday_overtime_rate` DECIMAL(10,2) NULL DEFAULT NULL,
                    `saturday_overtime_total` DECIMAL(10,2) NULL DEFAULT NULL,
                    `sunday_holiday_overtime_hours` DECIMAL(10,2) NULL DEFAULT NULL,
                    `sunday_holiday_overtime_rate` DECIMAL(10,2) NULL DEFAULT NULL,
                    `sunday_holiday_overtime_total` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_overtime_amount` DECIMAL(10,2) NULL DEFAULT NULL,
                    `night_duty_allowance` DECIMAL(10,2) NULL DEFAULT NULL,
                    `gross_salary` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ssf_13` DECIMAL(10,2) NULL DEFAULT NULL,
                    `medical_insurance` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ppe` DECIMAL(10,2) NULL DEFAULT NULL,
                    `workmen_compensation` DECIMAL(10,2) NULL DEFAULT NULL,
                    `management_fee_10` DECIMAL(10,2) NULL DEFAULT NULL,
                    `getfund_2_5` DECIMAL(10,2) NULL DEFAULT NULL,
                    `nhil_2_5` DECIMAL(10,2) NULL DEFAULT NULL,
                   
                    `vat_15` DECIMAL(10,2) NULL DEFAULT NULL,
                    `cost_to_fon_packaging` DECIMAL(10,2) NULL DEFAULT NULL,
                    `gross_salary_repeat` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ssnit_ee_5_5` DECIMAL(10,2) NULL DEFAULT NULL,
                    `petra` DECIMAL(10,2) NULL DEFAULT NULL,
                    `taxable_income` DECIMAL(10,2) NULL DEFAULT NULL,
                    `paye` DECIMAL(10,2) NULL DEFAULT NULL,
                    `overtime_tax` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_taxation` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_deductions` DECIMAL(10,2) NULL DEFAULT NULL,
                    `take_home_pay` DECIMAL(10,2) NULL DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        }
    }

    /**
     * Switch to a specific monthly table (e.g. payroll_november_2025)
     */
    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
