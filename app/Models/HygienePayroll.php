<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class HygienePayroll extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id', 'names', 'dept', 'noofdays', 'extraldays', 'total1', 'rate', 'overtimehr',
        'overtimeweekend', 'transport', 'night', 'crane', 'total2', 'overtimetotal',
        'overtimetotal2', 'totalamount', 'servicecharge', 'getfund', 'nhil',
        'vat', 'ssf', 'leave1', 'nightallowance', 'medical', 'costofolam', 'ssnitee',
        'taxableincome', 'totaltaxdeduction', 'totaldeduction', 'takehome'
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
                    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
                    `names` VARCHAR(150) NULL,
                    `dept` VARCHAR(50) NULL,
                    `noofdays` DOUBLE NULL,
                    `extraldays` DOUBLE NULL,
                    `total1` DOUBLE NULL,
                    `rate` DOUBLE NULL,
                    `overtimehr` DOUBLE NULL,
                    `overtimeweekend` DOUBLE NULL,
                    `transport` DOUBLE NULL,
                    `night` DOUBLE NULL,
                    `crane` DOUBLE NULL,
                    `total2` DOUBLE NULL,
                    `overtimetotal` DOUBLE NULL,
                    `overtimetotal2` DOUBLE NULL,
                    `totalamount` DOUBLE NULL,
                    `servicecharge` DOUBLE NULL,
                    `getfund` DOUBLE NULL,
                    `nhil` DOUBLE NULL,
                    `vat` DOUBLE NULL,
                    `ssf` DOUBLE NULL,
                    `leave1` DOUBLE NULL,
                    `nightallowance` DOUBLE NULL,
                    `medical` DOUBLE NULL,
                    `costofolam` DOUBLE NULL,
                    `ssnitee` DOUBLE NULL,
                    `taxableincome` DOUBLE NULL,
                    `totaltaxdeduction` DOUBLE NULL,
                    `totaldeduction` DOUBLE NULL,
                    `takehome` DOUBLE NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }
    }

    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
