<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class A2feedpay extends Model
{
    protected $primaryKey = 'staff_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;



    protected $fillable = [
        'staff_id',
        'name',
        'position',
        'join_date',
        'basic_salary',
        'days_worked',
        'absenteeism_days',
        'absent_days_deductions',
        'basic_salary_earned',
        'risk_allowance',
        'rent_allowance',
        'transport_standard',
        'transport_earned',
        'weekday_overtime_hours',
        'weekday_overtime_rate',
        'weekday_overtime_amount',
        'sunday_overtime_hours',
        'sunday_overtime_rate',
        'sunday_overtime_amount',
        'total_overtime_amount',
        'gross_salary',
        'management_fee_8',
        'getfund_2_5',
        'nhil_2_5',
        'vat_15',
        'ssf_13',
        'cost_to_a2_agro',
        'gross_salary_repeat',
        'ssnit_ee_5_5',
        'petra',
        'taxable_income',
        'paye',
        'overtime_tax',
        'other_deductions',
        'total_deductions',
        'take_home_pay',
        'ssnit_number',
        'gh_card',
        'bank',
        'account_number',
        'branch',
    ];

    protected $casts = [

        // Integers
        'days_worked'       => 'integer',
        'absenteeism_days'  => 'integer',

        // All decimal(10,2) fields → float in PHP
        'basic_salary'              => 'double',
        'absent_days_deductions'    => 'double',
        'basic_salary_earned'       => 'double',
        'risk_allowance'            => 'double',
        'rent_allowance'            => 'double',
        'transport_standard'        => 'double',
        'transport_earned'          => 'double',
        'weekday_overtime_hours'    => 'double',
        'weekday_overtime_rate'     => 'double',
        'weekday_overtime_amount'   => 'double',
        'sunday_overtime_hours'     => 'double',
        'sunday_overtime_rate'      => 'double',
        'sunday_overtime_amount'    => 'double',
        'total_overtime_amount'     => 'double',
        'gross_salary'              => 'double',
        'management_fee_8'          => 'double',
        'getfund_2_5'               => 'double',
        'nhil_2_5'                  => 'double',
        'vat_15'                    => 'double',
        'ssf_13'                    => 'double',
        'cost_to_a2_agro'           => 'double',
        'gross_salary_repeat'       => 'double',
        'ssnit_ee_5_5'              => 'double',
        'petra'                     => 'double',
        'taxable_income'            => 'double',
        'paye'                      => 'double',
        'overtime_tax'              => 'double',
        'other_deductions'          => 'double',
        'total_deductions'          => 'double',
        'take_home_pay'             => 'double',
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
                    `name` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `position` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `join_date` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `basic_salary` DECIMAL(10,2) NULL DEFAULT NULL,
                    `days_worked` INT NULL DEFAULT NULL,
                    `absenteeism_days` INT NULL DEFAULT NULL,
                    `absent_days_deductions` DECIMAL(10,2) NULL DEFAULT NULL,
                    `basic_salary_earned` DECIMAL(10,2) NULL DEFAULT NULL,
                    `risk_allowance` DECIMAL(10,2) NULL DEFAULT NULL,
                    `rent_allowance` DECIMAL(10,2) NULL DEFAULT NULL,
                    `transport_standard` DECIMAL(10,2) NULL DEFAULT NULL,
                    `transport_earned` DECIMAL(10,2) NULL DEFAULT NULL,
                    `weekday_overtime_hours` DECIMAL(10,2) NULL DEFAULT NULL,
                    `weekday_overtime_rate` DECIMAL(10,2) NULL DEFAULT NULL,
                    `weekday_overtime_amount` DECIMAL(10,2) NULL DEFAULT NULL,
                    `sunday_overtime_hours` DECIMAL(10,2) NULL DEFAULT NULL,
                    `sunday_overtime_rate` DECIMAL(10,2) NULL DEFAULT NULL,
                    `sunday_overtime_amount` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_overtime_amount` DECIMAL(10,2) NULL DEFAULT NULL,
                    `gross_salary` DECIMAL(10,2) NULL DEFAULT NULL,
                    `management_fee_8` DECIMAL(10,2) NULL DEFAULT NULL,
                    `getfund_2_5` DECIMAL(10,2) NULL DEFAULT NULL,
                    `nhil_2_5` DECIMAL(10,2) NULL DEFAULT NULL,
                    `vat_15` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ssf_13` DECIMAL(10,2) NULL DEFAULT NULL,
                    `cost_to_a2_agro` DECIMAL(10,2) NULL DEFAULT NULL,
                    `gross_salary_repeat` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ssnit_ee_5_5` DECIMAL(10,2) NULL DEFAULT NULL,
                     `petra` DECIMAL(10,2) NULL DEFAULT NULL,
                    `taxable_income` DECIMAL(10,2) NULL DEFAULT NULL,
                    `paye` DECIMAL(10,2) NULL DEFAULT NULL,
                    `overtime_tax` DECIMAL(10,2) NULL DEFAULT NULL,
                    `other_deductions` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_deductions` DECIMAL(10,2) NULL DEFAULT NULL,
                    `take_home_pay` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ssnit_number` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `gh_card` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `bank` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `account_number` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `branch` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci'
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
