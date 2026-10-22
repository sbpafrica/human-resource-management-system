<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DriverPay extends Model
{
    protected $primaryKey = 'employ';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'employ', 'name', 'report', 'dept', 'dob', 'joindate', 'ssnitnum',
        'age', 'remarks', 'new_basic', 'salary', 'week', 'basic', 'allowance',
        'sat', 'satOT', 'basic_all', 'ssfyee', 'ssfyer', 'ssfyeed', 'ssfyerd', 'ottax', 'taxable', 'paye',
        'advance', 'totaldeduct', 'netpay', 'servicecharge'
    ];

    protected $casts = [
        'age'           => 'double',
        'remarks'       => 'integer',
        'new_basic'     => 'double',
        'salary'        => 'integer',
        'week'          => 'integer',
        'basic'         => 'double',
        'allowance'     => 'double',
        'sat'           => 'double',
        'satOT'         => 'double',
        'basic_all'     => 'double',
        'ssfyeed'       => 'double',
        'ssfyerd'       => 'double',
        'ottax'         => 'double',
        'taxable'       => 'double',
        'paye'          => 'double',
        'advance'       => 'double',
        'totaldeduct'   => 'double',
        'netpay'        => 'double',
        'servicecharge' => 'double',
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
                    `employ` VARCHAR(255) NOT NULL PRIMARY KEY,
                    `name` VARCHAR(255) NULL,
                    `report` VARCHAR(255) NULL,
                    `dept` VARCHAR(255) NULL,
                    `dob` VARCHAR(50) NULL,
                    `joindate` VARCHAR(50) NULL,
                    `ssnitnum` VARCHAR(50) NULL,
                    `age` DOUBLE NULL DEFAULT 0,
                    `remarks` INT NULL DEFAULT 0,
                    `new_basic` DOUBLE NULL DEFAULT 0,
                    `salary` INT NULL DEFAULT 0,
                    `week` INT NULL DEFAULT 0,
                    `basic` DOUBLE NULL DEFAULT 0,
                    `allowance` DOUBLE NULL DEFAULT 0,
                    `sat` DOUBLE NULL DEFAULT 0,
                    `satOT` DOUBLE NULL DEFAULT 0,
                    `basic_all` DOUBLE NULL DEFAULT 0,
                    `ssfyee` DOUBLE NULL DEFAULT 0,
                    `ssfyer` DOUBLE NULL DEFAULT 0,
                    `ssfyeed` DOUBLE NULL DEFAULT 0,
                    `ssfyerd` DOUBLE NULL DEFAULT 0,
                    `ottax` DOUBLE NULL DEFAULT 0,
                    `taxable` DOUBLE NULL DEFAULT 0,
                    `paye` DOUBLE NULL DEFAULT 0,
                    `advance` DOUBLE NULL DEFAULT 0,
                    `totaldeduct` DOUBLE NULL DEFAULT 0,
                    `netpay` DOUBLE NULL DEFAULT 0,
                    `servicecharge` DOUBLE NULL DEFAULT 0
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }
    }

    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
