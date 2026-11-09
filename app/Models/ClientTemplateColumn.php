<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClientTemplateColumn extends Model
{
    use HasFactory;

    protected $table = 'client_template_columns';

    protected $fillable = [
        'client_id',
        'client_dbname',
        'module',
        'column_key',
        'display_label',
        'header_aliases',
        'data_type',
        'is_active',
        'is_required',
        'is_custom',
        'sort_order',
        'sample_value',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_required' => 'boolean',
        'is_custom'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    /**
     * Scope for a specific client and module.
     */
    public function scopeForClientAndModule($query, string $clientDb, string $module)
    {
        return $query->where('client_dbname', $clientDb)
                     ->where('module', $module)
                     ->orderBy('sort_order', 'asc')
                     ->orderBy('id', 'asc');
    }

    /**
     * Canonical Standard Catalog for each supported module.
     */
    public static function getCanonicalCatalog(string $module): array
    {
        return match ($module) {
            'payroll'  => self::getCanonicalPayrollColumns(),
            'ssnit'    => self::getCanonicalSsnitColumns(),
            'petra'    => self::getCanonicalPetraColumns(),
            'bank'     => self::getCanonicalBankColumns(),
            'gra'      => self::getCanonicalGraColumns(),
            'contract' => self::getCanonicalContractColumns(),
            default    => [],
        };
    }

    /**
     * Canonical standard fields for Salary Payment / Payroll.
     */
    public static function getCanonicalPayrollColumns(): array
    {
        return [
            ['key' => 'staff_id',             'label' => 'Staff ID',             'type' => 'string',  'required' => true,  'active' => true,  'sample' => 'STF001', 'aliases' => 'staff_id,staffid,id,employee_id,stf_id'],
            ['key' => 'name',                 'label' => 'Employee Name',        'type' => 'string',  'required' => true,  'active' => true,  'sample' => 'John Doe', 'aliases' => 'name,employeename,employee_name,fullname,full_name'],
            ['key' => 'position',             'label' => 'Position',             'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'Machine Operator', 'aliases' => 'position,designation,role,job_title'],
            ['key' => 'department',           'label' => 'Department',           'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'Operations', 'aliases' => 'department,dept,section,division'],
            ['key' => 'start_date',           'label' => 'Start Date',           'type' => 'date',    'required' => false, 'active' => false, 'sample' => '2023-01-15', 'aliases' => 'start_date,startdate,date_employed,join_date'],
            ['key' => 'basic_salary',         'label' => 'Basic Salary',         'type' => 'decimal', 'required' => true,  'active' => true,  'sample' => '3500.00', 'aliases' => 'basic_salary,basicsalary,basic,salary'],
            ['key' => 'total_weekdays',       'label' => 'Total Weekdays',       'type' => 'integer', 'required' => false, 'active' => false, 'sample' => '22', 'aliases' => 'total_weekdays,totalweekdays,legalworkdays,workdays'],
            ['key' => 'days_worked',          'label' => 'Days Worked',          'type' => 'integer', 'required' => false, 'active' => true,  'sample' => '22', 'aliases' => 'days_worked,daysworked,days,actual_days'],
            ['key' => 'absent_days',          'label' => 'Absent Days',          'type' => 'integer', 'required' => false, 'active' => false, 'sample' => '0', 'aliases' => 'absent_days,absentee_days,absent'],
            ['key' => 'earned_basic_salary',  'label' => 'Earned Basic Salary',  'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '3500.00', 'aliases' => 'earned_basic_salary,earned_basic,basic_earned'],
            ['key' => 'night_days',           'label' => 'Night Days',           'type' => 'integer', 'required' => false, 'active' => false, 'sample' => '0', 'aliases' => 'night_days,nightdays'],
            ['key' => 'weekday_ot_hours',     'label' => 'Weekday OT Hours',     'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'weekday_ot_hours,weekday_ot,ot_hours'],
            ['key' => 'weekday_ot_amount',    'label' => 'Weekday OT Amount',    'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'weekday_ot_amount,weekday_amount'],
            ['key' => 'weekend_ot_hours',     'label' => 'Weekend OT Hours',     'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'weekend_ot_hours,weekend_ot'],
            ['key' => 'weekend_ot_amount',    'label' => 'Weekend OT Amount',    'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'weekend_ot_amount,weekend_amount'],
            ['key' => 'sunday_ot_hours',      'label' => 'Sunday OT Hours',      'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'sunday_ot_hours,sunday_ot'],
            ['key' => 'sunday_ot_amount',     'label' => 'Sunday OT Amount',     'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'sunday_ot_amount,sunday_amount'],
            ['key' => 'total_overtime',       'label' => 'Total Overtime',       'type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '0.00', 'aliases' => 'total_overtime,totalovertime,overtime_amount,overtime'],
            ['key' => 'food_allowance',       'label' => 'Food Allowance',       'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'food_allowance,foodallowance,meal_allowance'],
            ['key' => 'transport_allowance',  'label' => 'Transport Allowance',  'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'transport_allowance,transportallowance,travel_allowance'],
            ['key' => 'night_allowance',      'label' => 'Night Allowance',      'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'night_allowance,nightallowance'],
            ['key' => 'medical_allowance',    'label' => 'Medical Allowance',    'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'medical_allowance,medicalallowance'],
            ['key' => 'airtime_allowance',    'label' => 'Airtime Allowance',    'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'airtime_allowance,airtimeallowance'],
            ['key' => 'total_allowance',      'label' => 'Total Allowance',      'type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '0.00', 'aliases' => 'total_allowance,totalallowance,allowances'],
            ['key' => 'gross_salary',         'label' => 'Gross Salary',         'type' => 'decimal', 'required' => true,  'active' => true,  'sample' => '3500.00', 'aliases' => 'gross_salary,grosssalary,gross'],
            ['key' => 'total_cash_emolument', 'label' => 'Total Cash Emolument', 'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '3500.00', 'aliases' => 'total_cash_emolument,cash_emolument'],
            ['key' => 'assessable_income',    'label' => 'Total Assessable Income','type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '3500.00', 'aliases' => 'assessable_income,total_assessable_income'],
            ['key' => 'employer_ssnit',       'label' => 'Employer SSNIT (13%)', 'type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '455.00', 'aliases' => 'employer_ssnit,employerssnit,ssf_employer,ssnit_er'],
            ['key' => 'employee_ssnit',       'label' => 'Employee SSNIT (5.5%)','type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '192.50', 'aliases' => 'employee_ssnit,employeessnit,ssf_employee,ssnit_ee,ssf'],
            ['key' => 'ssnit_ee_0_5',         'label' => 'Employee SSNIT (0.5%)','type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '17.50',  'aliases' => 'ssnit_0_5,ssnit_ee_0_5,ssnit_0.5%,0.5%'],
            ['key' => 'petra',                'label' => 'Tier 2 (PETRA 5%)',    'type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '175.00', 'aliases' => 'petra,tier2,petraorg,tier_2'],
            ['key' => 'taxable_income',       'label' => 'Taxable Income',       'type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '3132.50', 'aliases' => 'taxable_income,taxableincome'],
            ['key' => 'paye',                 'label' => 'PAYE Tax',             'type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '420.00', 'aliases' => 'paye,payetax,income_tax,tax'],
            ['key' => 'ot_tax',               'label' => 'OT Tax',               'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'ot_tax,overtime_tax'],
            ['key' => 'total_tax',            'label' => 'Total Tax',            'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '420.00', 'aliases' => 'total_tax,totaltax'],
            ['key' => 'absentee_deductions',  'label' => 'Absent Deductions',    'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'absentee_deductions,absent_deductions'],
            ['key' => 'salary_loan',          'label' => 'Salary Loan',          'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'salary_loan,loan,staff_loan'],
            ['key' => 'total_deductions',     'label' => 'Total Deductions',     'type' => 'decimal', 'required' => true,  'active' => true,  'sample' => '787.50', 'aliases' => 'total_deductions,totaldeductions,deductions'],
            ['key' => 'refunds',              'label' => 'Refunds / Adjustments','type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'refunds,refund,adjustments'],
            ['key' => 'net_pay',              'label' => 'Net Pay',              'type' => 'decimal', 'required' => true,  'active' => true,  'sample' => '2712.50', 'aliases' => 'net_pay,netpay,take_home,take_home_pay'],
        ];
    }

    /**
     * Canonical standard fields for SSNIT (Tier 1).
     */
    public static function getCanonicalSsnitColumns(): array
    {
        return [
            ['key' => 'staff_id',             'label' => 'Staff ID',             'type' => 'string',  'required' => true,  'active' => true,  'sample' => 'STF001', 'aliases' => 'staff_id,staffid,id'],
            ['key' => 'shop',                 'label' => 'Shop / Branch',        'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'Main Branch', 'aliases' => 'shop,branch,company,location'],
            ['key' => 'name',                 'label' => 'Full Name',            'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'John Doe', 'aliases' => 'name,employeename,full_name'],
            ['key' => 'last_name',            'label' => 'Last Name',            'type' => 'string',  'required' => false, 'active' => false, 'sample' => 'Doe', 'aliases' => 'last_name,lastname,surname'],
            ['key' => 'first_name',           'label' => 'First Name',           'type' => 'string',  'required' => false, 'active' => false, 'sample' => 'John', 'aliases' => 'first_name,firstname'],
            ['key' => 'other_name',           'label' => 'Other Name',           'type' => 'string',  'required' => false, 'active' => false, 'sample' => '', 'aliases' => 'other_name,othername,middlename'],
            ['key' => 'ssnit_number',         'label' => 'SSNIT Number',         'type' => 'string',  'required' => true,  'active' => true,  'sample' => 'C018301110076', 'aliases' => 'ssnit_number,ssnit1a,ssnit_no,ssnit'],
            ['key' => 'national_id',          'label' => 'Ghana Card ID',        'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'GHA-712092453-9', 'aliases' => 'national_id,national,ghana_card,ghcard'],
            ['key' => 'basic_salary',         'label' => 'Basic Salary',         'type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '3500.00', 'aliases' => 'basic_salary,basic'],
            ['key' => 'employee_ssnit_5_5',   'label' => 'Employee 5.5%',        'type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '192.50', 'aliases' => 'employee_ssnit,ssnit_ee,5.5%'],
            ['key' => 'employer_ssnit_13',    'label' => 'Employer 13%',         'type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '455.00', 'aliases' => 'employer_ssnit,ssnit_er,13%'],
            ['key' => 'total_ssnit_18_5',     'label' => 'Total SSNIT (18.5%)',  'type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '647.50', 'aliases' => 'total_ssnit,18.5%'],
            ['key' => 'date_of_birth',        'label' => 'Date of Birth',        'type' => 'date',    'required' => false, 'active' => false, 'sample' => '1995-05-12', 'aliases' => 'dob,date_of_birth,birth_date'],
            ['key' => 'contact_number',       'label' => 'Phone Number',         'type' => 'string',  'required' => false, 'active' => false, 'sample' => '0241234567', 'aliases' => 'phone,mobile,contact_number,contact'],
            ['key' => 'hire_date',            'label' => 'Hire Date',            'type' => 'date',    'required' => false, 'active' => false, 'sample' => '2023-01-15', 'aliases' => 'hire_date,date_employed,start_date'],
        ];
    }

    /**
     * Canonical standard fields for PETRA (Tier 2 Pension).
     */
    public static function getCanonicalPetraColumns(): array
    {
        return [
            ['key' => 'staff_id',             'label' => 'Staff ID',             'type' => 'string',  'required' => true,  'active' => true,  'sample' => 'STF001', 'aliases' => 'staff_id,staffid,id'],
            ['key' => 'shop',                 'label' => 'Shop / Branch',        'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'Main Branch', 'aliases' => 'shop,branch,company'],
            ['key' => 'name',                 'label' => 'Full Name',            'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'John Doe', 'aliases' => 'name,employeename,full_name'],
            ['key' => 'last_name',            'label' => 'Last Name',            'type' => 'string',  'required' => false, 'active' => false, 'sample' => 'Doe', 'aliases' => 'last_name,lastname,surname'],
            ['key' => 'first_name',           'label' => 'First Name',           'type' => 'string',  'required' => false, 'active' => false, 'sample' => 'John', 'aliases' => 'first_name,firstname'],
            ['key' => 'other_name',           'label' => 'Other Name',           'type' => 'string',  'required' => false, 'active' => false, 'sample' => '', 'aliases' => 'other_name,othername'],
            ['key' => 'petra_id',             'label' => 'Petra / Tier 2 ID',    'type' => 'string',  'required' => true,  'active' => true,  'sample' => 'PTR123456789', 'aliases' => 'petra_id,petraid,tier2_id,petra_number'],
            ['key' => 'national_id',          'label' => 'Ghana Card ID',        'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'GHA-712092453-9', 'aliases' => 'national_id,national,ghana_card,ghcard'],
            ['key' => 'basic_salary',         'label' => 'Basic Salary',         'type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '3500.00', 'aliases' => 'basic_salary,basic'],
            ['key' => 'petra_5_percent',      'label' => 'Tier 2 Contribution (5%)', 'type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '175.00', 'aliases' => 'petra_5,tier2_5,5%'],
            ['key' => 'contact_number',       'label' => 'Phone Number',         'type' => 'string',  'required' => false, 'active' => false, 'sample' => '0241234567', 'aliases' => 'phone,mobile,contact_number'],
            ['key' => 'date_of_birth',        'label' => 'Date of Birth',        'type' => 'date',    'required' => false, 'active' => false, 'sample' => '1995-05-12', 'aliases' => 'dob,date_of_birth'],
        ];
    }

    /**
     * Canonical standard fields for GRA (Ghana Revenue Authority / PAYE Monthly Tax Return).
     */
    public static function getCanonicalGraColumns(): array
    {
        return [
            ['key' => 'serial_no',            'label' => 'Ser. No',              'type' => 'integer', 'required' => false, 'active' => true,  'sample' => '1', 'aliases' => 'serial_no,ser_no,sn,no'],
            ['key' => 'tin_ghana_card',       'label' => 'TIN / Ghana Card No.', 'type' => 'string',  'required' => true,  'active' => true,  'sample' => 'GHA-712092453-9', 'aliases' => 'tin_ghana_card,tin,ghana_card,gh_card'],
            ['key' => 'name',                 'label' => 'Name of Employee',     'type' => 'string',  'required' => true,  'active' => true,  'sample' => 'John Doe', 'aliases' => 'name,employeename,name_of_employee'],
            ['key' => 'position',             'label' => 'Position',             'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'Machine Operator', 'aliases' => 'position,role,job_title'],
            ['key' => 'residency_status',     'label' => 'Residency Status',     'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'Resident', 'aliases' => 'residency_status,residency,status'],
            ['key' => 'basic_salary',         'label' => 'Basic Salary',         'type' => 'decimal', 'required' => true,  'active' => true,  'sample' => '3500.00', 'aliases' => 'basic_salary,basicsalary,basic'],
            ['key' => 'secondary_employment', 'label' => 'Secondary Employment (Y/N)', 'type' => 'string', 'required' => false, 'active' => true, 'sample' => 'N', 'aliases' => 'secondary_employment,sec_emp'],
            ['key' => 'paid_ssnit',           'label' => 'Paid SSNIT (Y/N)',     'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'Y', 'aliases' => 'paid_ssnit,ssnit_paid'],
            ['key' => 'ssnit_5_5',            'label' => 'Social Security (5.5%)', 'type' => 'decimal', 'required' => true, 'active' => true, 'sample' => '192.50', 'aliases' => 'ssnit_5_5,ssf,employee_ssnit'],
            ['key' => 'tier_3',               'label' => 'Third Tier Pension',   'type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '0.00', 'aliases' => 'tier_3,third_tier,provident_fund'],
            ['key' => 'cash_allowances',      'label' => 'Cash Allowances',      'type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '0.00', 'aliases' => 'cash_allowances,allowances'],
            ['key' => 'bonus_income',         'label' => 'Bonus Income',         'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'bonus_income,bonus'],
            ['key' => 'final_tax_bonus',      'label' => 'Final Tax on Bonus',   'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'final_tax_bonus,bonus_tax'],
            ['key' => 'excess_bonus',         'label' => 'Excess Bonus',         'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'excess_bonus'],
            ['key' => 'total_cash_emolument', 'label' => 'Total Cash Emolument', 'type' => 'decimal', 'required' => true,  'active' => true,  'sample' => '3500.00', 'aliases' => 'total_cash_emolument,cash_emolument'],
            ['key' => 'accommodation_element','label' => 'Accommodation Element','type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'accommodation_element,accommodation'],
            ['key' => 'vehicle_element',      'label' => 'Vehicle Element',      'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'vehicle_element,vehicle'],
            ['key' => 'non_cash_benefit',     'label' => 'Non Cash Benefit',     'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'non_cash_benefit,non_cash'],
            ['key' => 'total_assessable_income','label'=> 'Total Assessable Income','type' => 'decimal','required' => true,  'active' => true,  'sample' => '3500.00', 'aliases' => 'total_assessable_income,assessable_income'],
            ['key' => 'deductible_reliefs',   'label' => 'Deductible Reliefs',   'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'deductible_reliefs,reliefs'],
            ['key' => 'total_reliefs',        'label' => 'Total Reliefs',        'type' => 'decimal', 'required' => false, 'active' => true,  'sample' => '192.50', 'aliases' => 'total_reliefs,reliefs_total'],
            ['key' => 'chargeable_income',    'label' => 'Chargeable Income',    'type' => 'decimal', 'required' => true,  'active' => true,  'sample' => '3307.50', 'aliases' => 'chargeable_income,taxable_income'],
            ['key' => 'tax_deductible',       'label' => 'Tax Deductible (PAYE)', 'type' => 'decimal', 'required' => true, 'active' => true, 'sample' => '420.00', 'aliases' => 'tax_deductible,paye,income_tax'],
            ['key' => 'overtime_income',      'label' => 'Overtime Income',      'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'overtime_income,ot_income'],
            ['key' => 'overtime_tax',         'label' => 'Overtime Tax',         'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'overtime_tax,ot_tax'],
            ['key' => 'total_tax_payable',    'label' => 'Total Tax Payable to GRA', 'type' => 'decimal', 'required' => true, 'active' => true, 'sample' => '420.00', 'aliases' => 'total_tax_payable,tax_payable,total_tax'],
            ['key' => 'severance_pay',        'label' => 'Severance Pay Paid',   'type' => 'decimal', 'required' => false, 'active' => false, 'sample' => '0.00', 'aliases' => 'severance_pay'],
            ['key' => 'remarks',              'label' => 'Remarks',              'type' => 'string',  'required' => false, 'active' => false, 'sample' => '', 'aliases' => 'remarks,comment'],
        ];
    }

    /**
     * Canonical standard fields for Contracts and Renewals.
     */
    public static function getCanonicalContractColumns(): array
    {
        return [
            ['key' => 'staff_id',             'label' => 'Staff ID',             'type' => 'string',  'required' => true,  'active' => true,  'sample' => 'STF001', 'aliases' => 'staff_id,staffid,id'],
            ['key' => 'name',                 'label' => 'Employee Name',        'type' => 'string',  'required' => true,  'active' => true,  'sample' => 'John Doe', 'aliases' => 'name,employeename,full_name'],
            ['key' => 'client',               'label' => 'Client',               'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'Client Company', 'aliases' => 'client,client_name,company'],
            ['key' => 'location',             'label' => 'Location / Branch',    'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'Accra', 'aliases' => 'location,branch,shop'],
            ['key' => 'position',             'label' => 'Position',             'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'Machine Operator', 'aliases' => 'position,role,job_title'],
            ['key' => 'start_date',           'label' => 'Contract Start Date',  'type' => 'date',    'required' => true,  'active' => true,  'sample' => '2025-01-01', 'aliases' => 'start_date,contract_start,constart'],
            ['key' => 'end_date',             'label' => 'Contract End Date',    'type' => 'date',    'required' => true,  'active' => true,  'sample' => '2025-12-31', 'aliases' => 'end_date,contract_end,conend'],
            ['key' => 'basic_salary',         'label' => 'Basic Salary',         'type' => 'decimal', 'required' => true,  'active' => true,  'sample' => '3500.00', 'aliases' => 'basic_salary,salary,basic'],
            ['key' => 'employer_name',        'label' => 'Employer Name',        'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'SBP Africa', 'aliases' => 'employer_name,employer'],
            ['key' => 'issue_date',           'label' => 'Issue Date',           'type' => 'date',    'required' => false, 'active' => false, 'sample' => '2024-12-20', 'aliases' => 'issue_date,date_issued'],
            ['key' => 'hr_email',             'label' => 'HR Email',             'type' => 'string',  'required' => false, 'active' => false, 'sample' => 'hr@sbpafrica.group', 'aliases' => 'hr_email'],
            ['key' => 'employee_email',       'label' => 'Employee Email',       'type' => 'string',  'required' => false, 'active' => false, 'sample' => 'john@example.com', 'aliases' => 'employee_email,email'],
            ['key' => 'national_id',          'label' => 'Ghana Card ID',        'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'GHA-712092453-9', 'aliases' => 'national_id,ghana_card'],
        ];
    }

    /**
     * Canonical standard fields for Bank Details.
     */
    public static function getCanonicalBankColumns(): array
    {
        return [
            ['key' => 'staff_id',             'label' => 'Staff ID',             'type' => 'string',  'required' => true,  'active' => true,  'sample' => 'STF001', 'aliases' => 'staff_id,staffid,employid'],
            ['key' => 'name',                 'label' => 'Employee Name',        'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'John Doe', 'aliases' => 'name,employeename,full_name'],
            ['key' => 'shop',                 'label' => 'Shop / Branch',        'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'Accra Branch', 'aliases' => 'shop,branch,company'],
            ['key' => 'bank_name',            'label' => 'Bank Name',            'type' => 'string',  'required' => true,  'active' => true,  'sample' => 'GCB Bank', 'aliases' => 'bank_name,bankname,bank'],
            ['key' => 'branch',               'label' => 'Bank Branch',          'type' => 'string',  'required' => false, 'active' => true,  'sample' => 'High Street', 'aliases' => 'branch,bank_branch'],
            ['key' => 'account_number',       'label' => 'Account Number',       'type' => 'string',  'required' => true,  'active' => true,  'sample' => '1234567890123', 'aliases' => 'account_number,accountno,account,acc_num'],
            ['key' => 'account_name',         'label' => 'Account Name',         'type' => 'string',  'required' => false, 'active' => false, 'sample' => 'John Doe', 'aliases' => 'account_name,acc_name'],
            ['key' => 'momo_number',          'label' => 'Momo Number',          'type' => 'string',  'required' => false, 'active' => false, 'sample' => '0241234567', 'aliases' => 'momo_number,momonumber,momo'],
            ['key' => 'momo_name',            'label' => 'Momo Name',            'type' => 'string',  'required' => false, 'active' => false, 'sample' => 'John Doe', 'aliases' => 'momo_name,momoname'],
            ['key' => 'network',              'label' => 'Mobile Network',       'type' => 'string',  'required' => false, 'active' => false, 'sample' => 'MTN', 'aliases' => 'network,telco'],
        ];
    }

    /**
     * Seeds or initializes default columns for a client for a given module.
     */
    public static function seedDefaultsForClient(int $clientId, string $clientDb, string $module): void
    {
        $existing = self::where('client_dbname', $clientDb)
            ->where('module', $module)
            ->exists();

        if ($existing) {
            return;
        }

        $catalog = self::getCanonicalCatalog($module);
        $records = [];
        $order = 0;

        foreach ($catalog as $col) {
            $records[] = [
                'client_id'      => $clientId,
                'client_dbname'  => $clientDb,
                'module'         => $module,
                'column_key'     => $col['key'],
                'display_label'  => $col['label'],
                'header_aliases' => $col['aliases'] ?? null,
                'data_type'      => $col['type'] ?? 'string',
                'is_active'      => $col['active'] ?? true,
                'is_required'    => $col['required'] ?? false,
                'is_custom'      => false,
                'sort_order'     => $order++,
                'sample_value'   => $col['sample'] ?? '',
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        if (!empty($records)) {
            self::insert($records);
        }
    }

    /**
     * Seed all module defaults for a client (payroll, ssnit, petra, bank, gra, contract).
     */
    public static function seedAllModulesForClient(int $clientId, string $clientDb): void
    {
        foreach (['payroll', 'ssnit', 'petra', 'bank', 'gra', 'contract'] as $module) {
            self::seedDefaultsForClient($clientId, $clientDb, $module);
        }
    }

    /**
     * Clone all column configurations from a source client to a target client.
     */
    public static function cloneFromClient(string $targetDb, int $targetId, string $sourceDb): void
    {
        $sourceColumns = self::where('client_dbname', $sourceDb)->get();

        if ($sourceColumns->isEmpty()) {
            return;
        }

        // Delete existing target columns
        self::where('client_dbname', $targetDb)->delete();

        $rows = [];
        foreach ($sourceColumns as $col) {
            $rows[] = [
                'client_id'      => $targetId,
                'client_dbname'  => $targetDb,
                'module'         => $col->module,
                'column_key'     => $col->column_key,
                'display_label'  => $col->display_label,
                'header_aliases' => $col->header_aliases,
                'data_type'      => $col->data_type,
                'is_active'      => $col->is_active,
                'is_required'    => $col->is_required,
                'is_custom'      => $col->is_custom,
                'sort_order'     => $col->sort_order,
                'sample_value'   => $col->sample_value,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        self::insert($rows);
    }

    /**
     * Get effective active columns for a client and module (used for template generation & upload parsing).
     * If the client has not configured any, returns the canonical defaults.
     */
    public static function getEffectiveColumns(string $clientDb, string $module): Collection
    {
        $saved = self::forClientAndModule($clientDb, $module)->get();

        if ($saved->isNotEmpty()) {
            return $saved;
        }

        // Return canonical collection as fallback
        $catalog = self::getCanonicalCatalog($module);
        return collect($catalog)->map(function ($col, $idx) use ($clientDb, $module) {
            return new self([
                'client_dbname'  => $clientDb,
                'module'         => $module,
                'column_key'     => $col['key'],
                'display_label'  => $col['label'],
                'header_aliases' => $col['aliases'] ?? null,
                'data_type'      => $col['type'] ?? 'string',
                'is_active'      => $col['active'] ?? true,
                'is_required'    => $col['required'] ?? false,
                'is_custom'      => false,
                'sort_order'     => $idx,
                'sample_value'   => $col['sample'] ?? '',
            ]);
        });
    }

    /**
     * Get only active columns in sort order for Excel template generation.
     */
    public static function getActiveColumnsForTemplate(string $clientDb, string $module): Collection
    {
        return self::getEffectiveColumns($clientDb, $module)->filter(function ($col) {
            return (bool) $col->is_active;
        })->values();
    }
}
