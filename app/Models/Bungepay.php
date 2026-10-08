<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Bungepay extends Model
{
    protected $primaryKey = 'STAFF_ID';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;


    protected $fillable = [
        'STAFF_ID',
        'NAME',
        'POSITION',
        'BASE_SALARY_GHC',
        'BACKPAY',
        'TRANSPORT_ALLOWANCE_GHC',
        'ADDITIONAL_ALLOWANCE_GHC',
        'Rent',
        'tmporary_ALLOWANCE_GHC',
        'OVERTIME_GHC',
        'BONUS_EARNINGS_ALLOWANCE',
        'GROSS_SALARY_GHC',
        'MANAGEMENT_FEE_5_PERCENT',
        'SSNIT_ER_13_PERCENT',
        'GETFUND_2_5_PERCENT',
        'NHIL_2_5_PERCENT',
        'VAT_15_PERCENT',
        'COST_TO_BUNGE_GHC',
        'gross2',
        'SSNIT_5_5_PERCENT',
        'PETRA',
        'TAXABLE_INCOME_GHC',
        'PAYE_GHC',
        'BONUS_TAX',
        'TOTAL_TAXATION_GHC',
        'TAKE_HOME_PAY_GHC',
        'BANK',
        'ACCOUNT_NUMBER',
        'BRANCH',
        'SSNIT',
        'GH_CARD_NUMBER',
    ];

    protected $casts = [
        // All decimal(10,2) fields → cast to double (float in PHP)
        'BASE_SALARY_GHC'            => 'double',
        'BACKPAY'            => 'double',
        'TRANSPORT_ALLOWANCE_GHC'    => 'double',
        'ADDITIONAL_ALLOWANCE_GHC'   => 'double',
        'Rent'   => 'double',
        'ADDITIONAL_ALLOWANCE_GHC'   => 'double',
        'OVERTIME_GHC'               => 'double',
        'tmporary_ALLOWANCE_GHC'   => 'double',
        'GROSS_SALARY_GHC'           => 'double',
        'MANAGEMENT_FEE_5_PERCENT'   => 'double',
        'SSNIT_ER_13_PERCENT'        => 'double',
        'GETFUND_2_5_PERCENT'        => 'double',
        'NHIL_2_5_PERCENT'           => 'double',
        'VAT_15_PERCENT'             => 'double',
        'COST_TO_BUNGE_GHC'          => 'double',
        'gross2'                     => 'double',
        'SSNIT_5_5_PERCENT'          => 'double',
        'PETRA'          => 'double',
        'TAXABLE_INCOME_GHC'         => 'double',
        'PAYE_GHC'                   => 'double',
        'BONUS_TAX'                  => 'double',
        'TOTAL_TAXATION_GHC'         => 'double',
        'TAKE_HOME_PAY_GHC'          => 'double',
    ];

    public function __construct($attributes = [], $tableName = null)
    {
        parent::__construct($attributes);
        if ($tableName) {
            $this->setTable($tableName);
        }
    }

    /**
     * Create the monthly payroll table if it doesn't exist
     */
    public static function createTableIfNotExists($tableName)
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::statement("
                CREATE TABLE `$tableName` (
                    `STAFF_ID` VARCHAR(255) NOT NULL PRIMARY KEY COLLATE 'latin1_swedish_ci',
                    `NAME` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `POSITION` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `BASE_SALARY_GHC` DECIMAL(10,2) NULL DEFAULT NULL,
                    `BACKPAY` DECIMAL(10,2) NULL DEFAULT NULL,
                    `TRANSPORT_ALLOWANCE_GHC` DECIMAL(10,2) NULL DEFAULT NULL,
                     `ADDITIONAL_ALLOWANCE_GHC` DECIMAL(10,2) NULL DEFAULT NULL,
                    `Rent` DECIMAL(10,2) NULL DEFAULT NULL,
                     `tmporary_ALLOWANCE_GHC` DECIMAL(10,2) NULL DEFAULT NULL,
                     
                    `OVERTIME_GHC` DECIMAL(10,2) NULL DEFAULT NULL,
                    `BONUS_EARNINGS_ALLOWANCE` DECIMAL(10,2) NULL DEFAULT NULL,
                    `GROSS_SALARY_GHC` DECIMAL(10,2) NULL DEFAULT NULL,
                    `MANAGEMENT_FEE_5_PERCENT` DECIMAL(10,2) NULL DEFAULT NULL,
                    `SSNIT_ER_13_PERCENT` DECIMAL(10,2) NULL DEFAULT NULL,
                    `GETFUND_2_5_PERCENT` DECIMAL(10,2) NULL DEFAULT NULL,
                    `NHIL_2_5_PERCENT` DECIMAL(10,2) NULL DEFAULT NULL,
                    `VAT_15_PERCENT` DECIMAL(10,2) NULL DEFAULT NULL,
                    `COST_TO_BUNGE_GHC` DECIMAL(10,2) NULL DEFAULT NULL,
                    `gross2` DECIMAL(10,2) NULL DEFAULT NULL,
                    `SSNIT_5_5_PERCENT` DECIMAL(10,2) NULL DEFAULT NULL,
                    `PETRA` DECIMAL(10,2) NULL DEFAULT NULL,
                    `TAXABLE_INCOME_GHC` DECIMAL(10,2) NULL DEFAULT NULL,
                    `PAYE_GHC` DECIMAL(10,2) NULL DEFAULT NULL,
                    `BONUS_TAX` DECIMAL(10,2) NULL DEFAULT NULL,
                    `TOTAL_TAXATION_GHC` DECIMAL(10,2) NULL DEFAULT NULL,
                    `TAKE_HOME_PAY_GHC` DECIMAL(10,2) NULL DEFAULT NULL,
                    `BANK` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `ACCOUNT_NUMBER` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `BRANCH` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `SSNIT` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `GH_CARD_NUMBER` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci'
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        } else {
            // Table exists — ensure all expected columns are present (older tables may be missing some)
            $existingColumns = collect(DB::select("SHOW COLUMNS FROM `$tableName`"))
                ->pluck('Field')
                ->map(fn ($f) => strtolower($f))
                ->toArray();

            $requiredColumns = [
                'BACKPAY'                   => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'Rent'                      => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'tmporary_ALLOWANCE_GHC'    => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'VAT_15_PERCENT'            => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'gross2'                    => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'BONUS_TAX'                 => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'BONUS_EARNINGS_ALLOWANCE'  => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'TRANSPORT_ALLOWANCE_GHC'   => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'ADDITIONAL_ALLOWANCE_GHC'  => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'OVERTIME_GHC'              => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'GROSS_SALARY_GHC'          => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'MANAGEMENT_FEE_5_PERCENT'  => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'SSNIT_ER_13_PERCENT'       => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'GETFUND_2_5_PERCENT'       => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'NHIL_2_5_PERCENT'          => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'COST_TO_BUNGE_GHC'         => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'SSNIT_5_5_PERCENT'         => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'PETRA'                     => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'TAXABLE_INCOME_GHC'        => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'PAYE_GHC'                  => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'TOTAL_TAXATION_GHC'        => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'TAKE_HOME_PAY_GHC'         => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'BANK'                      => "VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci'",
                'ACCOUNT_NUMBER'            => "VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci'",
                'BRANCH'                    => "VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci'",
                'SSNIT'                     => "VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci'",
                'GH_CARD_NUMBER'            => "VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci'",
            ];

            foreach ($requiredColumns as $col => $definition) {
                if (!in_array(strtolower($col), $existingColumns)) {
                    DB::statement("ALTER TABLE `$tableName` ADD COLUMN `$col` $definition");
                }
            }
        }
    }

    /**
     * Switch to a specific monthly table
     */
    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
