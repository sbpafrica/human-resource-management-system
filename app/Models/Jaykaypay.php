<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Jaykaypay extends Model
{
    protected $primaryKey = 'staff_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $table = 'payroll'; // default table, can be changed dynamically

    protected $fillable = [
        'staff_id',
        'name',
        'position',
        'join_date',
        'basic_salary',
        'legalworkdays',
        'daily_rate',
        'days_worked',
        'absentee_days',
        'absent_deductions',
        'earned_basic_salary',
        'saturdays_worked',
        'saturday_rate',
        'saturday_amount',
        'total_days_amount',
        'overtime_hours',
        'overtime_rate',
        'overtime_amount',
        'night_allowance',
        'totalovertime',
        'gross_salary',
        'management_fee',
        'getfund',
        'nhil',
        'vat',
        'ssf',
        'cost_to_jkay',
        'ssnit_ee',
        'petra',
        'taxable_income',
        'paye',
        'overtime_tax',
        'surcharge_or_student_loan',
        'total_deductions',
        'take_home_pay',
    ];

    protected $casts = [


        // Integers
        'legalworkdays'    => 'integer',
        'days_worked'      => 'integer',
        'absentee_days'    => 'integer',
        'saturdays_worked' => 'integer',

        // Decimal / Double fields
        'basic_salary'              => 'double',
        'daily_rate'                => 'double',
        'absent_deductions'         => 'double',
        'earned_basic_salary'       => 'double',
        'saturday_rate'             => 'double',
        'saturday_amount'           => 'double',
        'total_days_amount'         => 'double',
        'overtime_hours'            => 'double',
        'overtime_rate'             => 'double',
        'overtime_amount'           => 'double',
        'night_allowance'           => 'double',
        'totalovertime'             => 'double',
        'gross_salary'              => 'double',
        'management_fee'            => 'double',
        'getfund'                   => 'double',
        'nhil'                      => 'double',
        
        'vat'                       => 'double',
        'ssf'                       => 'double',
        'cost_to_jkay'              => 'double',
        'ssnit_ee'                  => 'double',
         'petra'                  => 'double',
        'taxable_income'            => 'double',
        'paye'                      => 'double',
        'overtime_tax'              => 'double',
        'surcharge_or_student_loan'=> 'double',
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
                    `join_date` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `basic_salary` DECIMAL(10,2) NULL DEFAULT NULL,
                    `legalworkdays` INT NOT NULL,
                    `daily_rate` DECIMAL(10,2) NULL DEFAULT NULL,
                    `days_worked` INT NULL DEFAULT NULL,
                    `absentee_days` INT NULL DEFAULT NULL,
                    `absent_deductions` DECIMAL(10,2) NULL DEFAULT NULL,
                    `earned_basic_salary` DECIMAL(10,2) NULL DEFAULT NULL,
                    `saturdays_worked` INT NULL DEFAULT NULL,
                    `saturday_rate` DECIMAL(10,2) NULL DEFAULT NULL,
                    `saturday_amount` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_days_amount` DECIMAL(10,2) NULL DEFAULT NULL,
                    `overtime_hours` DECIMAL(10,2) NULL DEFAULT NULL,
                    `overtime_rate` DECIMAL(10,2) NULL DEFAULT NULL,
                    `overtime_amount` DECIMAL(10,2) NULL DEFAULT NULL,
                    `night_allowance` DECIMAL(10,2) NULL DEFAULT NULL,
                    `totalovertime` DOUBLE NOT NULL,
                    `gross_salary` DECIMAL(10,2) NULL DEFAULT NULL,
                    `management_fee` DECIMAL(10,2) NULL DEFAULT NULL,
                    `getfund` DECIMAL(10,2) NULL DEFAULT NULL,
                    `nhil` DECIMAL(10,2) NULL DEFAULT NULL,
                   
                    `vat` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ssf` DECIMAL(10,2) NULL DEFAULT NULL,
                    `cost_to_jkay` DECIMAL(10,2) NULL DEFAULT NULL,
                    `ssnit_ee` DECIMAL(10,2) NULL DEFAULT NULL,
                    `petra` DECIMAL(10,2) NULL DEFAULT NULL,
                    `taxable_income` DECIMAL(10,2) NULL DEFAULT NULL,
                    `paye` DECIMAL(10,2) NULL DEFAULT NULL,
                    `overtime_tax` DECIMAL(10,2) NULL DEFAULT NULL,
                    `surcharge_or_student_loan` DECIMAL(10,2) NULL DEFAULT NULL,
                    `total_deductions` DECIMAL(10,2) NULL DEFAULT NULL,
                    `take_home_pay` DECIMAL(10,2) NULL DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        }
    }

    /**
     * Switch to a different monthly table easily
     */
    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
