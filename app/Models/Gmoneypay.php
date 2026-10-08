<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Gmoneypay extends Model
{
    protected $primaryKey = 'staffid';
    public $incrementing  = false;
    protected $keyType    = 'string';
    public $timestamps    = true;

    protected $fillable = [
        'staffid',
        'name',
        'date_of_employment',
        'position',
        'location',
        'basic_salary',
        'legal_work_days',
        'number_of_days_worked',
        'leave_days',
        'total_days_worked',
        'number_of_absent_days',
        'absent_days_deductions',
        'basic_salary_earned',
        'bonus_earned',
        'gross_salary',
        'management_fee',
        'getfund',
        'nhil',
        'vat',
        'ssf',
        'cost_to_gmoney',
        'ssnit_ee',
        'petra',
        'taxable_income',
        'paye',
        'total_deductions',
        'take_home_pay',
        'bank',
        'gmoney_number',
        'wallet_name',
        'ssnit_number',
        'gh_card_number',
    ];

    protected $casts = [
        'basic_salary'                  => 'double',
        'legal_work_days'               => 'integer',
        'number_of_days_worked'         => 'integer',
        'leave_days'                    => 'integer',
        'total_days_worked'             => 'integer',
        'number_of_absent_days'         => 'integer',
        'absent_days_deductions'        => 'double',
        'basic_salary_earned'           => 'double',
        'bonus_earned'                  => 'double',
        'gross_salary'                  => 'double',
        'management_fee'                => 'double',
        'getfund'                       => 'double',
        'nhil'                          => 'double',
        'vat'                           => 'double',
        'ssf'                           => 'double',
        'cost_to_gmoney'                => 'double',
        'ssnit_ee'                      => 'double',
        'petra'                         => 'double',
        'taxable_income'                => 'double',
        'paye'                          => 'double',
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
     * Build the monthly table name: gmoney + lowercase month + 2-digit year
     * e.g. gmoneymarch26
     */
    public static function tableName(string $month): string
    {
        $date = \Carbon\Carbon::createFromFormat('Y-m', $month);
        return 'gmoney' . strtolower($date->format('F')) . $date->format('y');
    }

    public static function createTableIfNotExists(string $tableName): void
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::statement("
                CREATE TABLE `{$tableName}` (
                    `staffid`                       VARCHAR(50)     NOT NULL,
                    `name`                          VARCHAR(150)    NULL DEFAULT NULL,
                    `date_of_employment`            DATE            NULL DEFAULT NULL,
                    `position`                      VARCHAR(100)    NULL DEFAULT NULL,
                    `location`                      VARCHAR(100)    NULL DEFAULT NULL,
                    `basic_salary`                  DECIMAL(10,2)   NULL DEFAULT NULL,
                    `legal_work_days`               INT(11)         NULL DEFAULT NULL,
                    `number_of_days_worked`         INT(11)         NULL DEFAULT NULL,
                    `leave_days`                    INT(11)         NULL DEFAULT NULL,
                    `total_days_worked`             INT(11)         NULL DEFAULT NULL,
                    `number_of_absent_days`         INT(11)         NULL DEFAULT NULL,
                    `absent_days_deductions`        DECIMAL(10,2)   NULL DEFAULT NULL,
                    `basic_salary_earned`           DECIMAL(10,2)   NULL DEFAULT NULL,
                    `bonus_earned`                  DECIMAL(10,2)   NULL DEFAULT NULL,
                    `gross_salary`                  DECIMAL(10,2)   NULL DEFAULT NULL,
                    `management_fee`                DECIMAL(10,2)   NULL DEFAULT NULL,
                    `getfund`                       DECIMAL(10,2)   NULL DEFAULT NULL,
                    `nhil`                          DECIMAL(10,2)   NULL DEFAULT NULL,
                    `vat`                           DECIMAL(10,2)   NULL DEFAULT NULL,
                    `ssf`                           DECIMAL(10,2)   NULL DEFAULT NULL,
                    `cost_to_gmoney`                DECIMAL(10,2)   NULL DEFAULT NULL,
                    `ssnit_ee`                      DECIMAL(10,2)   NULL DEFAULT NULL,
                    `petra`                         DECIMAL(10,2)   NULL DEFAULT NULL,
                    `taxable_income`                DECIMAL(10,2)   NULL DEFAULT NULL,
                    `paye`                          DECIMAL(10,2)   NULL DEFAULT NULL,
                    `total_deductions`              DECIMAL(10,2)   NULL DEFAULT NULL,
                    `take_home_pay`                 DECIMAL(10,2)   NULL DEFAULT NULL,
                    `bank`                          VARCHAR(100)    NULL DEFAULT NULL,
                    `gmoney_number`                 VARCHAR(50)     NULL DEFAULT NULL,
                    `wallet_name`                   VARCHAR(150)    NULL DEFAULT NULL,
                    `ssnit_number`                  VARCHAR(50)     NULL DEFAULT NULL,
                    `gh_card_number`                VARCHAR(50)     NULL DEFAULT NULL,
                    `created_at`                    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at`                    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`staffid`)
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        }
    }

    public static function withTable(string $tableName): self
    {
        return new static([], $tableName);
    }
}
