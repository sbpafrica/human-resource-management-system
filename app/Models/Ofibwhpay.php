<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Ofibwhpay extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;



    protected $fillable = [
        'id',
        'name',
        'basic',
        'workdays',
        'f&trate',
        'weekdayhr',
        'weekdaytonage',
        'weekdayrate',
        'amt',
        'weekdayothr',
        'weekdayottonage',
        'weekdayotrate',
        'weekdayotamt1',
        'weekendhr',
        'weekendtonage',
        'weekendrate',
        'amount',
        'leavedays',
        'totalamount',
        'phonecredit',
        'ssnit13',
        'f&tamount',
        'servicecharge',
        'getfund',
        'nhil',
        'vat',
        'costtoolam',
        'ssnitee',
        'petra',
        'taxincome',
        'totaltax',
        'totaldeduction',
        'netpay',
        'bank1',
        'account2',
        'branch',
        'ssnit2',
        'ghcard1',
    ];

    protected $casts = [
        // Integer
        'workdays' => 'integer',

        // All double fields → PHP float
        'basic'            => 'double',
        'f&trate'          => 'double',
        'weekdayhr'        => 'double',
        'weekdaytonage'    => 'double',
        'weekdayrate'      => 'double',
        'amt'              => 'double',
        'weekdayothr'      => 'double',
        'weekdayottonage'  => 'double',
        'weekdayotrate'    => 'double',
        'weekdayotamt1'    => 'double',
        'weekendhr'        => 'double',
        'weekendtonage'    => 'double',
        'weekendrate'      => 'double',
        'amount'           => 'double',
        'leavedays'        => 'double',
        'totalamount'      => 'double',
        'phonecredit'      => 'double',
        'ssnit13'          => 'double',
        'f&tamount'        => 'double',
        'servicecharge'    => 'double',
        'getfund'          => 'double',
        'nhil'             => 'double',
        'vat'              => 'double',
        'costtoolam'       => 'double',
        'ssnitee'          => 'double',
         'petra'          => 'double',
        'taxincome'        => 'double',
        'totaltax'         => 'double',
        'totaldeduction'   => 'double',
        'netpay'           => 'double',
    ];

    public function __construct($attributes = [], $tableName = null)
    {
        parent::__construct($attributes);
        if ($tableName) {
            $this->setTable($tableName);
        }
    }

    /**
     * Create the table dynamically if it doesn't exist (monthly payroll tables)
     */
    public static function createTableIfNotExists($tableName)
    {
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::statement("
                CREATE TABLE `$tableName` (
                    `id` VARCHAR(50) NOT NULL PRIMARY KEY COLLATE 'latin1_swedish_ci',
                    `name` VARCHAR(255) NOT NULL COLLATE 'latin1_swedish_ci',
                    `basic` DOUBLE NULL,
`workdays` INT NULL,
`f&trate` DOUBLE NULL,
`weekdayhr` DOUBLE NULL,
`weekdaytonage` DOUBLE NULL,
`weekdayrate` DOUBLE NULL,
`amt` DOUBLE NULL,
`weekdayothr` DOUBLE NULL,
`weekdayottonage` DOUBLE NULL,
`weekdayotrate` DOUBLE NULL,
`weekdayotamt1` DOUBLE NULL,
`weekendhr` DOUBLE NULL,
`weekendtonage` DOUBLE NULL,
`weekendrate` DOUBLE NULL,
`amount` DOUBLE NULL,
`leavedays` DOUBLE NULL,
`totalamount` DOUBLE NULL,
`phonecredit` DOUBLE NULL,
`ssnit13` DOUBLE NULL,
`f&tamount` DOUBLE NULL,
`servicecharge` DOUBLE NULL,
`getfund` DOUBLE NULL,
`nhil` DOUBLE NULL,
`vat` DOUBLE NULL,
`costtoolam` DOUBLE NULL,
`ssnitee` DOUBLE NULL,
`petra` DOUBLE NULL,
`taxincome` DOUBLE NULL,
`totaltax` DOUBLE NULL,
`totaldeduction` DOUBLE NULL,
`netpay` DOUBLE NULL,
`bank1` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci',
`account2` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci',
`branch` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci',
`ssnit2` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci',
`ghcard1` VARCHAR(255) NULL COLLATE 'latin1_swedish_ci'

                ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
            ");
        }
    }

    /**
     * Switch to monthly table easily
     */
    public static function withTable($tableName)
    {
        return new static([], $tableName);
    }
}
