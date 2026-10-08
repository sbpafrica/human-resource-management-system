<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Olamfoodpay extends Model
{
    protected $primaryKey = 'staffid';
    public $incrementing  = false;
    protected $keyType    = 'string';
    public $timestamps    = false;

    protected $fillable = [
        'staffid',
        'name',
        'basic',
        'gross',
        'service_charge_7',
        'getfund_2_5',
        'nhil_2_5',
        'vat_15',
        'ssf_13',
        'cost_to_ofi',
        'ssnit_ee_5_5',
        'petra',
        'taxable_income',
        'paye',
        'total_deduction',
        'netpay',
    ];

    protected $casts = [
        'basic'            => 'double',
        'gross'            => 'double',
        'service_charge_7' => 'double',
        'getfund_2_5'      => 'double',
        'nhil_2_5'         => 'double',
        'vat_15'           => 'double',
        'ssf_13'           => 'double',
        'cost_to_ofi'      => 'double',
        'ssnit_ee_5_5'     => 'double',
        'petra'            => 'double',
        'taxable_income'   => 'double',
        'paye'             => 'double',
        'total_deduction'  => 'double',
        'netpay'           => 'double',
    ];

    public function __construct($attributes = [], $tableName = null)
    {
        parent::__construct($attributes);
        if ($tableName) {
            $this->setTable($tableName);
        }
    }

    public static function createTableIfNotExists($tableName)
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::statement("
                CREATE TABLE `$tableName` (
                    `staffid`           VARCHAR(50)     NOT NULL PRIMARY KEY COLLATE 'latin1_swedish_ci',
                    `name`              VARCHAR(150)    NOT NULL COLLATE 'latin1_swedish_ci',
                    `basic`             DECIMAL(12,2)   NULL DEFAULT 0.00,
                    `gross`             DECIMAL(12,2)   NULL DEFAULT 0.00,
                    `service_charge_7`  DECIMAL(12,2)   NULL DEFAULT 0.00,
                    `getfund_2_5`       DECIMAL(12,2)   NULL DEFAULT 0.00,
                    `nhil_2_5`          DECIMAL(12,2)   NULL DEFAULT 0.00,
                    `vat_15`            DECIMAL(12,2)   NULL DEFAULT 0.00,
                    `ssf_13`            DECIMAL(12,2)   NULL DEFAULT 0.00,
                    `cost_to_ofi`       DECIMAL(12,2)   NULL DEFAULT 0.00,
                    `ssnit_ee_5_5`      DECIMAL(12,2)   NULL DEFAULT 0.00,
                    `petra`             DOUBLE          NOT NULL DEFAULT 0,
                    `taxable_income`    DECIMAL(12,2)   NULL DEFAULT 0.00,
                    `paye`              DECIMAL(12,2)   NULL DEFAULT 0.00,
                    `total_deduction`   DECIMAL(12,2)   NULL DEFAULT 0.00,
                    `netpay`            DECIMAL(12,2)   NULL DEFAULT 0.00
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        }
    }

    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
