<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Twelliumpay extends Model
{
    protected $primaryKey = 'employ';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;



    protected $fillable = [
        'employ',
        'name',
        'department',
        'dateemploy',
        'expected_work_days',
        'total_hours_worked',
        'basic',
        'basic_earned',
        'food_allowance',
        'night_allowance',
        'refund',
        'allowance',
        'total_cash_emolument',
        'deduction',
        'total_assessable_income',
        'ssnit_ee',
        'petra',
        'taxable_income',
        'paye',
        'ssf',
        'welfare',
        'medical_insurance',
        'workmens_compensation',
        'risk_allowance',
        'end_of_year',
        'ppe',
        'staff_cost',
        'mgt_fee',
        'gfl',
        'nhil',
        'covid',
        'vat',
        'c2c',
        'weekday_overtime_hours',
        'weekday_overtime_rate',
        'weekday_overtime_amount',
        'offday_holiday_overtime_hours',
        'offday_holiday_overtime_rate',
        'offday_holiday_overtime_total',
        'special_day_overtime_hours',
        'special_day_overtime_rate',
        'special_day_overtime_amount',
        'total_overtime_amount',
        'overtime_tax',
        'total_taxation',
        'total_deductions',
        'netpay',
    ];

    protected $casts = [
        // Date


        // Integer
        'expected_work_days' => 'integer',

        // Decimal(8,2) fields
        'total_hours_worked'              => 'double',
        'weekday_overtime_hours'          => 'double',
        'offday_holiday_overtime_hours'   => 'double',
        'special_day_overtime_hours'      => 'double',

        // All decimal(12,2) fields → cast to double (float in PHP)
        'basic'                           => 'double',
        'basic_earned'                    => 'double',
        'food_allowance'                  => 'double',
        'night_allowance'                 => 'double',
        'refund'                          => 'double',
        'allowance'                       => 'double',
        'total_cash_emolument'            => 'double',
        'deduction'                       => 'double',
        'total_assessable_income'         => 'double',
        'ssnit_ee'                        => 'double',
        'petra'                        => 'double',
        'taxable_income'                  => 'double',
        'paye'                            => 'double',
        'ssf'                             => 'double',
        'welfare'                         => 'double',
        'medical_insurance'               => 'double',
        'workmens_compensation'           => 'double',
        'risk_allowance'                  => 'double',
        'end_of_year'                     => 'double',
        'ppe'                             => 'double',
        'staff_cost'                      => 'double',
        'mgt_fee'                         => 'double',
        'gfl'                             => 'double',
        'nhil'                            => 'double',
        'covid'                           => 'double',
        'vat'                             => 'double',
        'c2c'                             => 'double',
        'weekday_overtime_rate'           => 'double',
        'weekday_overtime_amount'         => 'double',
        'offday_holiday_overtime_rate'    => 'double',
        'offday_holiday_overtime_total'   => 'double',
        'special_day_overtime_rate'       => 'double',
        'special_day_overtime_amount'     => 'double',
        'total_overtime_amount'           => 'double',
        'overtime_tax'                    => 'double',
        'total_taxation'                  => 'double',
        'total_deductions'                => 'double',
        'netpay'                          => 'double',
    ];

    public function __construct($attributes = [], $tableName = null)
    {
        parent::__construct($attributes);
        if ($tableName) {
            $this->setTable($tableName);
        }
    }

    /**
     * Create the table dynamically if it doesn't exist (ideal for monthly payroll tables)
     */
    public static function createTableIfNotExists($tableName)
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::statement("
                CREATE TABLE `$tableName` (
                    `employ` VARCHAR(20) NOT NULL PRIMARY KEY COLLATE 'latin1_swedish_ci',
                    `name` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `department` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `dateemploy` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `expected_work_days` INT NULL DEFAULT NULL,
                    `total_hours_worked` DECIMAL(8,2) NULL DEFAULT NULL,
                    `basic` DECIMAL(12,2) NULL DEFAULT NULL,
                    `basic_earned` DECIMAL(12,2) NULL DEFAULT NULL,
                    `food_allowance` DECIMAL(12,2) NULL DEFAULT NULL,
                    `night_allowance` DECIMAL(12,2) NULL DEFAULT NULL,
                    `refund` DECIMAL(12,2) NULL DEFAULT NULL,
                    `allowance` DECIMAL(12,2) NULL DEFAULT NULL,
                    `total_cash_emolument` DECIMAL(12,2) NULL DEFAULT NULL,
                    `deduction` DECIMAL(12,2) NULL DEFAULT NULL,
                    `total_assessable_income` DECIMAL(12,2) NULL DEFAULT NULL,
                    `ssnit_ee` DECIMAL(12,2) NULL DEFAULT NULL,
                    `petra` DECIMAL(12,2) NULL DEFAULT NULL,
                    `taxable_income` DECIMAL(12,2) NULL DEFAULT NULL,
                    `paye` DECIMAL(12,2) NULL DEFAULT NULL,
                    `ssf` DECIMAL(12,2) NULL DEFAULT NULL,
                    `welfare` DECIMAL(12,2) NULL DEFAULT NULL,
                    `medical_insurance` DECIMAL(12,2) NULL DEFAULT NULL,
                    `workmens_compensation` DECIMAL(12,2) NULL DEFAULT NULL,
                    `risk_allowance` DECIMAL(12,2) NULL DEFAULT NULL,
                    `end_of_year` DECIMAL(12,2) NULL DEFAULT NULL,
                    `ppe` DECIMAL(12,2) NULL DEFAULT NULL,
                    `staff_cost` DECIMAL(12,2) NULL DEFAULT NULL,
                    `mgt_fee` DECIMAL(12,2) NULL DEFAULT NULL,
                    `gfl` DECIMAL(12,2) NULL DEFAULT NULL,
                    `nhil` DECIMAL(12,2) NULL DEFAULT NULL,
                    `covid` DECIMAL(12,2) NULL DEFAULT NULL,
                    `vat` DECIMAL(12,2) NULL DEFAULT NULL,
                    `c2c` DECIMAL(12,2) NULL DEFAULT NULL,
                    `weekday_overtime_hours` DECIMAL(8,2) NULL DEFAULT NULL,
                    `weekday_overtime_rate` DECIMAL(12,2) NULL DEFAULT NULL,
                    `weekday_overtime_amount` DECIMAL(12,2) NULL DEFAULT NULL,
                    `offday_holiday_overtime_hours` DECIMAL(8,2) NULL DEFAULT NULL,
                    `offday_holiday_overtime_rate` DECIMAL(12,2) NULL DEFAULT NULL,
                    `offday_holiday_overtime_total` DECIMAL(12,2) NULL DEFAULT NULL,
                    `special_day_overtime_hours` DECIMAL(8,2) NULL DEFAULT NULL,
                    `special_day_overtime_rate` DECIMAL(12,2) NULL DEFAULT NULL,
                    `special_day_overtime_amount` DECIMAL(12,2) NULL DEFAULT NULL,
                    `total_overtime_amount` DECIMAL(12,2) NULL DEFAULT NULL,
                    `overtime_tax` DECIMAL(12,2) NULL DEFAULT NULL,
                    `total_taxation` DECIMAL(12,2) NULL DEFAULT NULL,
                    `total_deductions` DECIMAL(12,2) NULL DEFAULT NULL,
                    `netpay` DECIMAL(12,2) NULL DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        } else {
            // Upgrade older monthly tables with the two new Kumasi columns (existing data untouched)
            $schema = DB::getSchemaBuilder();
            if (!$schema->hasColumn($tableName, 'total_cash_emolument')) {
                DB::statement("ALTER TABLE `$tableName` ADD COLUMN `total_cash_emolument` DECIMAL(12,2) NULL DEFAULT NULL AFTER `allowance`");
            }
            if (!$schema->hasColumn($tableName, 'total_assessable_income')) {
                DB::statement("ALTER TABLE `$tableName` ADD COLUMN `total_assessable_income` DECIMAL(12,2) NULL DEFAULT NULL AFTER `deduction`");
            }
        }
    }

    /**
     * Switch to a specific monthly payroll table easily
     */
    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
