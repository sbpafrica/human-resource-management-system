<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Hondapay extends Model
{
    protected $primaryKey = 'STAFFID';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;



    protected $fillable = [
        'STAFFID',
        'NAME',
        'DATEOFEMPLOYMENT',
        'POSITION',
        'BASICSALARY_GHC',
        'LEGALWORKDAYS',
        'NUMBEROFDAYSWORKED',
        'LEAVEDAYS',
        'LEAVEAMOUNT',
        'BASICSALARYEARNED_GHC',
        'PERFORMANCEBONUSSTANDARD',
        'COEFFICIENT',
        'PERFORMANCEBONUS',
        'TRANSPORTALLOWANCE',
        'foodallowance',
        'holiovertime',
        'holiovertimeamt',
        'medicalinsurance',
        'GROSSSALARY_GHC',
        'MANAGEMENTFEE_8',
        'GETFUND_2_5',
        'NHIL_2_5',
        'COVID_19_1',
        'VAT_15',
        'SSF_13',
        'COSTTOHONDA_GHC',
        'SSNITEE_0_5',
        'PETRA_5',
        'TAXABLEINCOME_GHC',
        'PAYE_GHC',
        'TOTALDEDUCTIONS_GHC',
        'TAKEHOMEPAY_GHC',
        'BANK',
        'ACCOUNTNUMBER',
        'BRANCH',
        'SSNITNUMBER',
        'GHCARDNUMBER',
    ];

    protected $casts = [
        // Integers (stored as varchar but represent whole numbers)
        'LEGALWORKDAYS'       => 'integer',
        'NUMBEROFDAYSWORKED'  => 'integer',
        'LEAVEDAYS'           => 'integer',

        // Monetary fields stored as VARCHAR → force to double for math
        'BASICSALARY_GHC'         => 'double',
        'LEAVEAMOUNT'             => 'double',
        'BASICSALARYEARNED_GHC'   => 'double',
        'PERFORMANCEBONUSSTANDARD'=> 'double',
        'COEFFICIENT'             => 'double',
        'PERFORMANCEBONUS'        => 'double',
        'TRANSPORTALLOWANCE'      => 'double',
        'GROSSSALARY_GHC'         => 'double',
        'MANAGEMENTFEE_8'         => 'double',
        'GETFUND_2_5'             => 'double',
        'NHIL_2_5'                => 'double',
        'COVID_19_1'              => 'double',
        'VAT_15'                  => 'double',
        'SSF_13'                  => 'double',
        'COSTTOHONDA_GHC'         => 'double',
        'SSNITEE_0_5'             => 'double',
        'PETRA_5'                 => 'double',
        'TAXABLEINCOME_GHC'       => 'double',
        'PAYE_GHC'                => 'double',
        'TOTALDEDUCTIONS_GHC'     => 'double',
        'TAKEHOMEPAY_GHC'         => 'double',

        // True DOUBLE columns
        'foodallowance'     => 'double',
        'medicalinsurance'  => 'double',
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
                    `STAFFID` VARCHAR(50) NOT NULL PRIMARY KEY COLLATE 'latin1_swedish_ci',
                    `NAME` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `DATEOFEMPLOYMENT` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `POSITION` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `BASICSALARY_GHC` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `LEGALWORKDAYS` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `NUMBEROFDAYSWORKED` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `LEAVEDAYS` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `LEAVEAMOUNT` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `BASICSALARYEARNED_GHC` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `PERFORMANCEBONUSSTANDARD` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `COEFFICIENT` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `PERFORMANCEBONUS` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `TRANSPORTALLOWANCE` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `foodallowance` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `holiovertime` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `holiovertimeamt` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `medicalinsurance` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `GROSSSALARY_GHC` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `MANAGEMENTFEE_8` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `GETFUND_2_5` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `NHIL_2_5` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                   
                    `VAT_15` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `SSF_13` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `COSTTOHONDA_GHC` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `SSNITEE_0_5` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `PETRA_5` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `TAXABLEINCOME_GHC` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `PAYE_GHC` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `TOTALDEDUCTIONS_GHC` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `TAKEHOMEPAY_GHC` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `BANK` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `ACCOUNTNUMBER` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `BRANCH` VARCHAR(100) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `SSNITNUMBER` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci',
                    `GHCARDNUMBER` VARCHAR(50) NULL DEFAULT NULL COLLATE 'latin1_swedish_ci'
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
