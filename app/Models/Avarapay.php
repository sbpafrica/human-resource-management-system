<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Avarapay extends Model
{
    protected $primaryKey = 'STAFFID';
    public $incrementing  = false;
    protected $keyType    = 'string';
    public $timestamps    = true;

    // Avara uses uppercase column names — map Laravel's timestamp columns
    const CREATED_AT = 'CREATED_AT';
    const UPDATED_AT = 'UPDATED_AT';

    protected $fillable = [
        'STAFFID',
        'NAME',
        'POSITION',
        'JOIN_DATE',
        'SHOP',
        'BASIC_SALARY_GHC',
        'LEGAL_WORK_DAYS',
        'NUMBER_OF_DAYS_WORKED',
        'LEAVE_DAYS',
        'ABSENT_DAYS',
        'ABSENT_DAYS_DEDUCTIONS_GHC',
        'BASIC_SALARY_EARNED_GHC',
        'TRANSPORT_GHC',
        'WEEKDAY_OVERTIME_HRS',
        'WEEKDAY_OVERTIME_RATE',
        'WEEKDAY_OVERTIME_AMOUNT_GHC',
        'WEEKEND_OVERTIME_HRS',
        'WEEKEND_OVERTIME_RATE',
        'WEEKEND_OVERTIME_AMOUNT_GHC',
        'HOLIDAY_OVERTIME_HRS',
        'HOLIDAY_OVERTIME_RATE',
        'HOLIDAY_OVERTIME_AMOUNT_GHC',
        'TOTAL_OVERTIME_AMOUNT_GHC',
        'GROSS_SALARY_GHC',
        'MANAGEMENT_FEE_8_PERCENT',
        'GETFUND_2_5_PERCENT',
        'NHIL_2_5_PERCENT',
        'VAT_15_PERCENT',
        'SSF_13_PERCENT',
        'COST_TO_AVARA_GHC',
        'SSNIT_EE_0_5_PERCENT',
        'PETRA_5_PERCENT',
        'TAXABLE_INCOME_GHC',
        'PAYE_GHC',
        'OVERTIME_TAX_GHC',
        'DEDUCTIONS_SURCHARGE_STUDENT_LOAN_GHC',
        'TOTAL_DEDUCTIONS_GHC',
        'TAKE_HOME_PAY_GHC',
        'SSNIT',
        'GH_CARD',
        'BANK',
        'ACCOUNT_NUMBER',
        'BRANCH',
    ];

    protected $casts = [
        'BASIC_SALARY_GHC'                      => 'double',
        'LEGAL_WORK_DAYS'                        => 'integer',
        'NUMBER_OF_DAYS_WORKED'                  => 'integer',
        'LEAVE_DAYS'                             => 'integer',
        'ABSENT_DAYS'                            => 'integer',
        'ABSENT_DAYS_DEDUCTIONS_GHC'             => 'double',
        'BASIC_SALARY_EARNED_GHC'                => 'double',
        'TRANSPORT_GHC'                          => 'double',
        'WEEKDAY_OVERTIME_HRS'                   => 'double',
        'WEEKDAY_OVERTIME_RATE'                  => 'double',
        'WEEKDAY_OVERTIME_AMOUNT_GHC'            => 'double',
        'WEEKEND_OVERTIME_HRS'                   => 'double',
        'WEEKEND_OVERTIME_RATE'                  => 'double',
        'WEEKEND_OVERTIME_AMOUNT_GHC'            => 'double',
        'HOLIDAY_OVERTIME_HRS'                   => 'double',
        'HOLIDAY_OVERTIME_RATE'                  => 'double',
        'HOLIDAY_OVERTIME_AMOUNT_GHC'            => 'double',
        'TOTAL_OVERTIME_AMOUNT_GHC'              => 'double',
        'GROSS_SALARY_GHC'                       => 'double',
        'MANAGEMENT_FEE_8_PERCENT'               => 'double',
        'GETFUND_2_5_PERCENT'                    => 'double',
        'NHIL_2_5_PERCENT'                       => 'double',
        'VAT_15_PERCENT'                         => 'double',
        'SSF_13_PERCENT'                         => 'double',
        'COST_TO_AVARA_GHC'                      => 'double',
        'SSNIT_EE_0_5_PERCENT'                   => 'double',
        'PETRA_5_PERCENT'                        => 'double',
        'TAXABLE_INCOME_GHC'                     => 'double',
        'PAYE_GHC'                               => 'double',
        'OVERTIME_TAX_GHC'                       => 'double',
        'DEDUCTIONS_SURCHARGE_STUDENT_LOAN_GHC'  => 'double',
        'TOTAL_DEDUCTIONS_GHC'                   => 'double',
        'TAKE_HOME_PAY_GHC'                      => 'double',
    ];

    public function __construct($attributes = [], $tableName = null)
    {
        parent::__construct($attributes);
        if ($tableName) {
            $this->setTable($tableName);
        }
    }

    /**
     * Build the monthly table name: avara + lowercase month + 2-digit year
     * e.g. avaramarch26
     */
    public static function tableName(string $month): string
    {
        $date = \Carbon\Carbon::createFromFormat('Y-m', $month);
        return 'avara' . strtolower($date->format('F')) . $date->format('y');
    }

    public static function createTableIfNotExists(string $tableName): void
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::statement("
                CREATE TABLE `{$tableName}` (
                    `STAFFID`                               VARCHAR(50)     NOT NULL,
                    `NAME`                                  VARCHAR(255)    NOT NULL,
                    `POSITION`                              VARCHAR(100)    NULL DEFAULT NULL,
                    `JOIN_DATE`                             DATE            NULL DEFAULT NULL,
                    `SHOP`                                  VARCHAR(100)    NULL DEFAULT NULL,
                    `BASIC_SALARY_GHC`                      DECIMAL(10,2)   NULL DEFAULT NULL,
                    `LEGAL_WORK_DAYS`                       INT(11)         NULL DEFAULT NULL,
                    `NUMBER_OF_DAYS_WORKED`                 INT(11)         NULL DEFAULT NULL,
                    `LEAVE_DAYS`                            INT(11)         NULL DEFAULT NULL,
                    `ABSENT_DAYS`                           INT(11)         NULL DEFAULT NULL,
                    `ABSENT_DAYS_DEDUCTIONS_GHC`            DECIMAL(10,2)   NULL DEFAULT NULL,
                    `BASIC_SALARY_EARNED_GHC`               DECIMAL(10,2)   NULL DEFAULT NULL,
                    `TRANSPORT_GHC`                         DECIMAL(10,2)   NULL DEFAULT NULL,
                    `WEEKDAY_OVERTIME_HRS`                  DECIMAL(10,2)   NULL DEFAULT NULL,
                    `WEEKDAY_OVERTIME_RATE`                 DECIMAL(10,2)   NULL DEFAULT NULL,
                    `WEEKDAY_OVERTIME_AMOUNT_GHC`           DECIMAL(10,2)   NULL DEFAULT NULL,
                    `WEEKEND_OVERTIME_HRS`                  DECIMAL(10,2)   NULL DEFAULT NULL,
                    `WEEKEND_OVERTIME_RATE`                 DECIMAL(10,2)   NULL DEFAULT NULL,
                    `WEEKEND_OVERTIME_AMOUNT_GHC`           DECIMAL(10,2)   NULL DEFAULT NULL,
                    `HOLIDAY_OVERTIME_HRS`                  DECIMAL(10,2)   NULL DEFAULT NULL,
                    `HOLIDAY_OVERTIME_RATE`                 DECIMAL(10,2)   NULL DEFAULT NULL,
                    `HOLIDAY_OVERTIME_AMOUNT_GHC`           DECIMAL(10,2)   NULL DEFAULT NULL,
                    `TOTAL_OVERTIME_AMOUNT_GHC`             DECIMAL(10,2)   NULL DEFAULT NULL,
                    `GROSS_SALARY_GHC`                      DECIMAL(10,2)   NULL DEFAULT NULL,
                    `MANAGEMENT_FEE_8_PERCENT`              DECIMAL(10,2)   NULL DEFAULT NULL,
                    `GETFUND_2_5_PERCENT`                   DECIMAL(10,2)   NULL DEFAULT NULL,
                    `NHIL_2_5_PERCENT`                      DECIMAL(10,2)   NULL DEFAULT NULL,
                    `VAT_15_PERCENT`                        DECIMAL(10,2)   NULL DEFAULT NULL,
                    `SSF_13_PERCENT`                        DECIMAL(10,2)   NULL DEFAULT NULL,
                    `COST_TO_AVARA_GHC`                     DECIMAL(10,2)   NULL DEFAULT NULL,
                    `SSNIT_EE_0_5_PERCENT`                  DECIMAL(10,2)   NULL DEFAULT NULL,
                    `PETRA_5_PERCENT`                       DECIMAL(10,2)   NULL DEFAULT NULL,
                    `TAXABLE_INCOME_GHC`                    DECIMAL(10,2)   NULL DEFAULT NULL,
                    `PAYE_GHC`                              DECIMAL(10,2)   NULL DEFAULT NULL,
                    `OVERTIME_TAX_GHC`                      DECIMAL(10,2)   NULL DEFAULT NULL,
                    `DEDUCTIONS_SURCHARGE_STUDENT_LOAN_GHC` DECIMAL(10,2)   NULL DEFAULT NULL,
                    `TOTAL_DEDUCTIONS_GHC`                  DECIMAL(10,2)   NULL DEFAULT NULL,
                    `TAKE_HOME_PAY_GHC`                     DECIMAL(10,2)   NULL DEFAULT NULL,
                    `SSNIT`                                 VARCHAR(50)     NULL DEFAULT NULL,
                    `GH_CARD`                               VARCHAR(50)     NULL DEFAULT NULL,
                    `BANK`                                  VARCHAR(100)    NULL DEFAULT NULL,
                    `ACCOUNT_NUMBER`                        VARCHAR(50)     NULL DEFAULT NULL,
                    `BRANCH`                                VARCHAR(100)    NULL DEFAULT NULL,
                    `CREATED_AT`                            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `UPDATED_AT`                            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`STAFFID`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ");
        }
    }

    public static function withTable(string $tableName): self
    {
        return new static([], $tableName);
    }
}
