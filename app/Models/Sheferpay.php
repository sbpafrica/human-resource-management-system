<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Sheferpay extends Model
{
    protected $primaryKey = 'staff_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;



    protected $fillable = [
        'staff_id',
        'name1',
        'position',
        'department',
        'startdate',
        'basic',
        'total_weekdays',
        'no_of_days_worked',
        'leavepay',
        'absent_days',
        'basic_earned',
        'nightdays',
        'weekdayOT_hours',
        'weekdayOT_rate',
        'weekdayOT_amount',
        'weekendOT_hours',
        'weekendOT_rate',
        'weekendOT_amount',
        'sunOT_hours',
        'sunOT_rate',
        'sunOT_amount',
        'fixedotbasic',
        'fixedot',
        'total_overtime',
        'foodbasic',
        'food',
        'airtimebasic',
        'airtime',
        'nightallo',
        'responsebasic',
        'responseallo',
        'transbasic',
        'transport',
        'medicalbasic',
        'medical',
        'total_allowance',
        'gross',
        'emp_ssnit_13_percent',
        'ssnit_5_5_percent',
         'petra',
        'taxable_income',
        'paye',
        'ot_tax',
        'total_tax',
        'absent_without_pay',
        'salaryloan',
        'total_deductions',
        'refunds',
        'net_pay',
    ];

    protected $casts = [
        // Integers
        'total_weekdays'     => 'integer',
        'no_of_days_worked'  => 'integer',
        'leavepay'          => 'integer',
        'absent_days'        => 'integer',
        'nightdays'          => 'integer',

        // Decimal & Double fields → PHP float
        'basic'                  => 'double',
        'basic_earned'           => 'double',
        'weekdayOT_hours'        => 'double',
        'weekdayOT_rate'         => 'double',
        'weekdayOT_amount'       => 'double',
        'weekendOT_hours'        => 'double',
        'weekendOT_rate'         => 'double',
        'weekendOT_amount'       => 'double',
        'sunOT_hours'           => 'double',
        'sunOT_rate'             => 'double',
        'sunOT_amount'           => 'double',
        'fixedotbasic'           => 'double',
        'fixedot'                => 'double',
        'total_overtime'         => 'double',
        'foodbasic'              => 'double',
        'food'                   => 'double',
        'airtimebasic'           => 'double',
        'airtime'                => 'double',
        'nightallo'              => 'double',
        'responsebasic'          => 'double',
        'responseallo'           => 'double',
        'transbasic'             => 'double',
        'transport'              => 'double',
        'medicalbasic'           => 'double',
        'medical'                => 'double',
        'total_allowance'        => 'double',
        'gross'                  => 'double',
        'emp_ssnit_13_percent'   => 'double',
        'ssnit_5_5_percent'      => 'double',
        'petra'      => 'double',
        'taxable_income'         => 'double',
        'paye'                   => 'double',
        'ot_tax'                 => 'double',
        'total_tax'              => 'double',
        'absent_without_pay'     => 'double',
        'salaryloan'             => 'double',
        'total_deductions'       => 'double',
        'refunds'                => 'double',
        'net_pay'                => 'double',
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
                    `staff_id` VARCHAR(255) NOT NULL PRIMARY KEY COLLATE 'latin1_swedish_ci',
                    `name1` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `position` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `department` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `startdate` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `basic` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_weekdays` INT NULL DEFAULT NULL,
                    `no_of_days_worked` DECIMAL(10,2) NULL DEFAULT NULL,
                    `leavepay` DECIMAL(10,2) NULL DEFAULT NULL,
                    `absent_days` DECIMAL(10,2) NULL DEFAULT NULL,
                    `basic_earned` DECIMAL(10,2) NULL DEFAULT NULL,
                    `nightdays` DECIMAL(10,2) NULL DEFAULT NULL,
                    `weekdayOT_hours` DECIMAL(5,2) NULL DEFAULT NULL,
                    `weekdayOT_rate` DECIMAL(10,2) NULL DEFAULT NULL,
                    `weekdayOT_amount` DECIMAL(10,2) NULL DEFAULT NULL,
                    `weekendOT_hours` DECIMAL(5,2) NULL DEFAULT NULL,
                    `weekendOT_rate` DECIMAL(10,2) NULL DEFAULT NULL,
                    `weekendOT_amount` DECIMAL(10,2) NULL DEFAULT NULL,
                    `sunOT_hours` DECIMAL(5,2) NULL DEFAULT NULL,
                    `sunOT_rate` DECIMAL(10,2) NULL DEFAULT NULL,
                    `sunOT_amount` DECIMAL(10,2) NULL DEFAULT NULL,
                    `fixedotbasic` DECIMAL(10,2) NULL DEFAULT NULL,
                    `fixedot` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_overtime` DECIMAL(10,2) NULL DEFAULT NULL,
                    `foodbasic` DECIMAL(10,2) NULL DEFAULT NULL,
                    `food` DECIMAL(10,2) NULL DEFAULT NULL,
                    `airtimebasic` DECIMAL(10,2) NULL DEFAULT NULL,
                    `airtime` DECIMAL(10,2) NULL DEFAULT NULL,
                    `nightallo` DECIMAL(10,2) NULL DEFAULT NULL,
                    `responsebasic` DECIMAL(10,2) NULL DEFAULT NULL,
                    `responseallo` DECIMAL(10,2) NULL DEFAULT NULL,
                    `transbasic` DECIMAL(10,2) NULL DEFAULT NULL,
                    `transport` DECIMAL(10,2) NULL DEFAULT NULL,
                    `medicalbasic` DECIMAL(10,2) NULL DEFAULT NULL,
                    `medical` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_allowance` DECIMAL(10,2) NULL DEFAULT NULL,
                    `gross` DECIMAL(10,2) NULL DEFAULT NULL,
                    `emp_ssnit_13_percent` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ssnit_5_5_percent` DECIMAL(10,2) NULL DEFAULT NULL,
                    `petra` DECIMAL(10,2) NULL DEFAULT NULL,
                    `taxable_income` DECIMAL(10,2) NULL DEFAULT NULL,
                    `paye` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ot_tax` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_tax` DECIMAL(10,2) NULL DEFAULT NULL,
                    `absent_without_pay` DECIMAL(10,2) NULL DEFAULT NULL,
                    `salaryloan` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_deductions` DECIMAL(10,2) NULL DEFAULT NULL,
                    `refunds` DECIMAL(10,2) NULL DEFAULT NULL,
                    `net_pay` DECIMAL(10,2) NULL DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        } else {
            try {
                DB::statement("ALTER TABLE `$tableName` 
                    MODIFY COLUMN `leavepay` DECIMAL(10,2) NULL DEFAULT NULL,
                    MODIFY COLUMN `nightdays` DECIMAL(10,2) NULL DEFAULT NULL,
                    MODIFY COLUMN `foodbasic` DECIMAL(10,2) NULL DEFAULT NULL
                ");
            } catch (\Exception $e) {
                // Ignore alter errors if columns don't exist
            }
        }
    }

    /**
     * Quick way to use a specific monthly table
     */
    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
