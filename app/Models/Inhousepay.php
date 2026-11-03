<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Inhousepay extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'position',
        'ghcard',
        'ssnit',
        'datejoin',
        'alcnum',
        'bankname',
        'branch',
        'legalwork',
        'actualwork',
        'basic',
        'actualbasic',
        'kpi',
        'coefficient',
        'monthlykpi',
        'overtime',
        'transport',
        'leave1',
        'total',
        'ssniter',
        'ssnitpay',
        'petra',
        'taxincome',
        'paye',
        'overtimetax',
        'totaltax',
        'totaldeduction',
        'netpay1',
        'bonus',
        'bonustax',
        'bonusfinal',
        'commission_earned',
        'commission_tax',
        'commission_final',
        'numdays',
        'endyearmonth',
        'endyeartax',
        'endyearpay',
        'loans',
        'netpay',
    ];

    protected $casts = [
    // Integer fields
    'legalwork'     => 'integer',
    'actualwork'    => 'integer',
    'numdays'       => 'integer',

    // Decimal/double fields → float in PHP
    'basic'             => 'double',
    'actualbasic'       => 'double',
    'kpi'               => 'double',
    'coefficient'       => 'double',
    'monthlykpi'        => 'double',
    'overtime'          => 'double',
    'transport'         => 'double',
    'leave1'            => 'double',
    'total'             => 'double',
    'ssniter'           => 'double',
    'ssnitpay'          => 'double',
    'petra'             => 'double',
    'taxincome'         => 'double',
    'paye'              => 'double',
    'overtimetax'       => 'double',
    'totaltax'          => 'double',
    'totaldeduction'    => 'double',
    'netpay1'           => 'double',
    'bonus'             => 'double',
    'bonustax'          => 'double',
    'bonusfinal'        => 'double',
    'commission_earned' => 'double',
    'commission_tax'    => 'double',
    'commission_final'  => 'double',
    'endyearmonth'      => 'double',
    'endyeartax'        => 'double',
    'endyearpay'        => 'double',
    'loans'             => 'double',
    'netpay'            => 'double',
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
                    `id` VARCHAR(255) NOT NULL PRIMARY KEY COLLATE 'latin1_swedish_ci',
                    `name` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `position` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `ghcard` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `ssnit` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `datejoin` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `alcnum` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `bankname` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `branch` VARCHAR(255) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `legalwork` INT(11) NULL DEFAULT NULL,
                    `actualwork` INT(11) NULL DEFAULT NULL,
                    `basic` DOUBLE NULL DEFAULT NULL,
                    `actualbasic` DOUBLE NULL DEFAULT NULL,
                    `kpi` DOUBLE NULL DEFAULT NULL,
                    `coefficient` DOUBLE NULL DEFAULT NULL,
                    `monthlykpi` DOUBLE NULL DEFAULT NULL,
                    `overtime` DOUBLE NULL DEFAULT NULL,
                    `transport` DOUBLE NULL DEFAULT NULL,
                    `leave1` DOUBLE NULL DEFAULT NULL,
                    `total` DOUBLE NULL DEFAULT NULL,
                    `ssniter` DOUBLE NULL DEFAULT NULL,
                    `ssnitpay` DOUBLE NULL DEFAULT NULL,
                    `taxincome` DOUBLE NULL DEFAULT NULL,
                    `paye` DOUBLE NULL DEFAULT NULL,
                    `overtimetax` DOUBLE NULL DEFAULT NULL,
                    `totaltax` DOUBLE NULL DEFAULT NULL,
                    `totaldeduction` DOUBLE NULL DEFAULT NULL,
                    `netpay1` DOUBLE NULL DEFAULT NULL,
                    `bonus` DOUBLE NULL DEFAULT NULL,
                    `bonustax` DOUBLE NULL DEFAULT NULL,
                    `bonusfinal` DOUBLE NULL DEFAULT NULL,
                    `commission_earned` DOUBLE NULL DEFAULT NULL,
                    `commission_tax` DOUBLE NULL DEFAULT NULL,
                    `commission_final` DOUBLE NULL DEFAULT NULL,
                    `numdays` INT(11) NULL DEFAULT NULL,
                    `endyearmonth` DOUBLE NULL DEFAULT NULL,
                    `endyeartax` DOUBLE NULL DEFAULT NULL,
                    `endyearpay` DOUBLE NULL DEFAULT NULL,
                    `loans` DOUBLE NULL DEFAULT NULL,
                    `netpay` DOUBLE NULL DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        } else {
            $schema = DB::getSchemaBuilder();
            if (!$schema->hasColumn($tableName, 'bonus')) {
                DB::statement("ALTER TABLE `$tableName` ADD COLUMN `bonus` DOUBLE NULL DEFAULT NULL AFTER `netpay1`");
            }
            if (!$schema->hasColumn($tableName, 'bonustax')) {
                DB::statement("ALTER TABLE `$tableName` ADD COLUMN `bonustax` DOUBLE NULL DEFAULT NULL AFTER `bonus`");
            }
            if (!$schema->hasColumn($tableName, 'bonusfinal')) {
                DB::statement("ALTER TABLE `$tableName` ADD COLUMN `bonusfinal` DOUBLE NULL DEFAULT NULL AFTER `bonustax`");
            }
            if (!$schema->hasColumn($tableName, 'commission_earned')) {
                DB::statement("ALTER TABLE `$tableName` ADD COLUMN `commission_earned` DOUBLE NULL DEFAULT NULL AFTER `bonusfinal`");
            }
            if (!$schema->hasColumn($tableName, 'commission_tax')) {
                DB::statement("ALTER TABLE `$tableName` ADD COLUMN `commission_tax` DOUBLE NULL DEFAULT NULL AFTER `commission_earned`");
            }
            if (!$schema->hasColumn($tableName, 'commission_final')) {
                DB::statement("ALTER TABLE `$tableName` ADD COLUMN `commission_final` DOUBLE NULL DEFAULT NULL AFTER `commission_tax`");
            }
            if (!$schema->hasColumn($tableName, 'petra')) {
                DB::statement("ALTER TABLE `$tableName` ADD COLUMN `petra` DOUBLE NULL DEFAULT NULL AFTER `ssnitpay`");
            }
        }
    
        // Auto-attach any custom columns configured in PayrollColumn registry
        \App\Models\PayrollColumn::ensureColumnsForTable($tableName);
    }

    /**
     * Switch to a specific monthly table easily
     */
    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
