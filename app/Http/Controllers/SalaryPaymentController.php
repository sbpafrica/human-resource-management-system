<?php

namespace App\Http\Controllers;

use App\Services\Export\SpreadsheetExporter;

use App\Models\MonthlySalary;
use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SalaryPaymentController extends Controller
{
    public function index(Request $request, $client_dbname)
    {
        $client = Client::where('client_dbname', $client_dbname)->firstOrFail();

        $month = $request->input('month', now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $formattedMonth = $date->format('F Y');
        $tableName = MonthlySalary::generateTableName($client_dbname, $month);

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('Payroll/SalaryPayment/Index', [
                'client' => $client,
                'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedMonth' => $month,
                'formattedMonth' => $formattedMonth,
                'totalRecords' => 0,
                'searchCount' => null,
                'flash' => ['error' => "No data available for {$formattedMonth}. Please upload using the correct template."],
            ]);
        }

        $query = DB::table("{$tableName} as ms")
            ->leftJoin('bankdetails as bd', 'ms.staffida', '=', 'bd.employid')
            ->select(
                'ms.staffida',
                'ms.name',
                'bd.shop',
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'ms.netpay'
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ms.staffida', 'like', "%{$search}%")
                  ->orWhere('ms.name', 'like', "%{$search}%")
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'staffida');
        $order = $request->input('order', 'asc');
        $sortColumn = match ($sort) {
            'shop', 'department' => 'bd.shop',
            'bank', 'bankname'   => 'bd.bank',
            'account', 'alcnum'  => 'bd.account',
            'code'               => 'bd.code',
            'branch'             => 'bd.branch',
            default              => "ms.{$sort}",
        };
        $query->orderBy($sortColumn, $order);

        $perPage = 10;
        $records = $query->paginate($perPage)->appends($request->query());

        $totalRecords = DB::table($tableName)->count();
        $searchCount = $search ? $query->count() : null;

        return Inertia::render('Payroll/SalaryPayment/Index', [
            'client' => $client,
            'records' => $records,
            'selectedMonth' => $month,
            'formattedMonth' => $formattedMonth,
            'totalRecords' => $totalRecords,
            'searchCount' => $searchCount,
            'flash' => session()->has('success') ? ['success' => session('success')] :
                     (session()->has('error') ? ['error' => session('error')] : null),
        ]);
    }

    public function export(Request $request, $client_dbname)
    {
        try {
            $month = $request->input('month');
            $date = Carbon::createFromFormat('Y-m', $month);
            $tableName = MonthlySalary::generateTableName($client_dbname, $month);

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
            }

            $query = DB::table("{$tableName} as ms")
                ->leftJoin('bankdetails as bd', 'ms.staffida', '=', 'bd.employid')
                ->select('ms.staffida', 'ms.name', 'bd.shop', 'bd.bank', 'bd.account', 'bd.code', 'bd.branch', 'ms.netpay');

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('ms.staffida', 'like', "%{$search}%")
                      ->orWhere('ms.name', 'like', "%{$search}%")
                      ->orWhere('bd.bank', 'like', "%{$search}%");
                });
            }

            $sort = $request->input('sort', 'staffida');
            $order = $request->input('order', 'asc');
            $sortColumn = match ($sort) {
                'shop', 'department' => 'bd.shop',
                'bank', 'bankname'   => 'bd.bank',
                default              => "ms.{$sort}",
            };
            $query->orderBy($sortColumn, $order);

            $records = $query->get()->map(fn($r) => [
                'staffida' => $r->staffida,
                'name'     => $r->name,
                'shop'     => $r->shop,
                'bank'     => $r->bank,
                'account'  => $r->account,
                'code'     => $r->code,
                'branch'   => $r->branch,
                'netpay'   => $r->netpay ? (float)$r->netpay : null,
            ]);

            $filename = "SalaryPayment_" . ($month ?? 'export') . ".xlsx";
            return SpreadsheetExporter::exportSalaryPayment($records, $filename);
        } catch (\Exception $e) {
            Log::error("Export failed", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Export failed'], 500);
        }
    }

    // FULLY DYNAMIC UPLOAD — EXPECTS EXACT TEMPLATE HEADERS
    public function upload(Request $request, $client_dbname)
    {
        $request->validate([
            'file'  => ['required', 'file', 'extensions:xlsx,xls,csv,ods', 'max:10240'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        try {
            $client = Client::where('client_dbname', $client_dbname)->firstOrFail();

            $selectedMonth = $request->input('month');
           $tableName = MonthlySalary::generateTableName($client_dbname, $selectedMonth);
MonthlySalary::forClient($client_dbname, $selectedMonth);



            $file = $request->file('file');
            $reader = new XlsxReader();
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            $uploadedHeaders = array_shift($rows); // First row = headers
            $uploadedHeaders = array_map('trim', $uploadedHeaders);

            // CLIENT-SPECIFIC HEADER MAPPING (EXACT FROM TEMPLATE)
            $headerMaps = [
                'transsion' => $this->getTranssionHeaders(),
                'transsiontec' => $this->getTranssionHeaders(),
                'melcom' => $this->getMelcomHeaders(),
                'nyonkopa' => $this->getNyonkopaHeaders(),
                'honda' => $this->getHondaHeaders(),
                'transsnet' => $this->getTranssnetHeaders(),
                'jkay' => $this->getJkayHeaders(),
                'pz' => $this->getPzHeaders(),
                'twelliumaccra' => $this->getTwelliumAccraHeaders(),
                'shefer' => $this->getSheferHeaders(),
                'fonpackage' => $this->getFonpackageHeaders(),
                'driverssnit' => $this->getDriverssnitHeaders(),
                'centurypay' => $this->getCenturypayHeaders(),
                'a2feed' => $this->getA2feedHeaders(),
                'a2poultry' => $this->getA2poultryHeaders(),
                'olambwh' => $this->getOlambwhHeaders(),
                'offload' => $this->getOffloadHeaders(),
                'hygieneexternal' => $this->getHygieneexternalHeaders(),
                'olamhygiene' => $this->getOlamhygieneHeaders(),
                'olamtem' => $this->getOlamtemHeaders(),
                'bungegh' => $this->getBungeghHeaders(),
                'twellium' => $this->getTwelliumHeaders(),
            ];

            $expectedHeaders = $headerMaps[$client_dbname] ?? $this->getTranssionHeaders();
            $headerToDb = $this->getHeaderToDbMap($client_dbname);

            // VALIDATE HEADERS
            $missing = array_diff($expectedHeaders, $uploadedHeaders);
            if (!empty($missing)) {
                return redirect()->back()->with('error', 'Invalid template. Missing columns: ' . implode(', ', array_slice($missing, 0, 5)));
            }

            $existingStaffIds = DB::table($tableName)->pluck('staffida')->toArray();
            $duplicateStaffIds = [];
            $validRecords = [];
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;
                $record = [];

                $staffId = null;
                $name = null;
                $netpay = null;

                foreach ($expectedHeaders as $idx => $header) {
                    $value = trim($row[$idx] ?? '');

                    if (isset($headerToDb[$header])) {
                        $dbCol = $headerToDb[$header];

                        if ($dbCol === 'netpay') {
                            $clean = preg_replace('/[^0-9.]/', '', $value);
                            $value = is_numeric($clean) ? (float)$clean : null;
                            $netpay = $value;
                        }

                        if ($dbCol === 'staffida') $staffId = $value;
                        if ($dbCol === 'name') $name = $value;

                        $record[$dbCol] = $value;
                    }
                }

                if (empty($staffId)) {
                    $errors[] = "Row {$rowNumber}: Staff ID missing";
                    continue;
                }
                if (empty($name)) {
                    $errors[] = "Row {$rowNumber}: Name missing";
                    continue;
                }
                if (is_null($netpay)) {
                    $errors[] = "Row {$rowNumber}: Invalid Net Pay";
                    continue;
                }
                if (in_array($staffId, $existingStaffIds) || in_array($staffId, array_column($validRecords, 'staffida'))) {
                    $duplicateStaffIds[] = $staffId;
                    continue;
                }

                $validRecords[] = $record;
            }

            if (!empty($errors)) {
                $msg = count($errors) > 5 ? implode(' | ', array_slice($errors, 0, 5)) . ' | ...' : implode(' | ', $errors);
                return redirect()->back()->with('error', $msg);
            }

            if (!empty($duplicateStaffIds)) {
                return redirect()->back()->with('error', 'Duplicates: ' . implode(', ', array_unique($duplicateStaffIds)));
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
            }

            return redirect()->back()->with('success', 'Uploaded ' . count($validRecords) . ' records successfully!');

        } catch (\Exception $e) {
            Log::error('Upload failed', ['client' => $client_dbname, 'error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    public function delete(Request $request, $client_dbname)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $tableName = MonthlySalary::generateTableName($client_dbname, $month);

        if (DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::table($tableName)->truncate();
            return redirect()->back()->with('success', 'Records deleted.');
        }

        return redirect()->back()->with('error', 'No records found.');
    }

    public function downloadTemplate($client_dbname)
    {
        $client = Client::where('client_dbname', $client_dbname)->firstOrFail();

        $headerMaps = [
            'transsion'       => $this->getTranssionHeaders(),
            'transsiontec'    => $this->getTranssionHeaders(),
            'melcom'          => $this->getMelcomHeaders(),
            'nyonkopa'        => $this->getNyonkopaHeaders(),
            'honda'           => $this->getHondaHeaders(),
            'transsnet'       => $this->getTranssnetHeaders(),
            'jkay'            => $this->getJkayHeaders(),
            'pz'              => $this->getPzHeaders(),
            'twelliumaccra'   => $this->getTwelliumAccraHeaders(),
            'shefer'          => $this->getSheferHeaders(),
            'fonpackage'      => $this->getFonpackageHeaders(),
            'driverssnit'     => $this->getDriverssnitHeaders(),
            'centurypay'      => $this->getCenturypayHeaders(),
            'a2feed'          => $this->getA2feedHeaders(),
            'a2poultry'       => $this->getA2poultryHeaders(),
            'olambwh'         => $this->getOlambwhHeaders(),
            'offload'         => $this->getOffloadHeaders(),
            'hygieneexternal' => $this->getHygieneexternalHeaders(),
            'olamhygiene'     => $this->getOlamhygieneHeaders(),
            'olamtem'         => $this->getOlamtemHeaders(),
            'bungegh'         => $this->getBungeghHeaders(),
            'twellium'        => $this->getTwelliumHeaders(),
        ];

        $headers = $headerMaps[$client_dbname] ?? $this->getTranssionHeaders();

        $exampleRow = array_fill(0, count($headers), '');
        $exampleRow[0] = strtoupper(substr($client_dbname, 0, 3)) . '001';
        $exampleRow[1] = 'John Doe';
        $exampleRow[array_key_last($exampleRow)] = '5000.00';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([$exampleRow], null, 'A2');

        // Styling
        $styleArray = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e40af']],
        ];
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($styleArray);
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $fileName = "{$client->short_name}_Payroll_Template_" . now()->format('M_Y') . ".xlsx";
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }
    // ==================== ALL HEADER FUNCTIONS (IN CORRECT ORDER) ====================

    private function getTranssionHeaders() {
        return ['Staff ID', 'Name', 'Department', 'Hire Date', 'Contract Start', 'Contract End', 'Position', 'Grade',
            'Categories', 'Workplace', 'Bank Name', 'Account Number', 'Branch', 'SSNIT', 'COL 12', 'Work Days',
            'Leave Days', 'OT Work Days', 'OT Weekend', 'Basic', 'Performance Bonus', 'Coefficient', 'Performance Standard',
            'Allowance', 'Commission', 'OT Working Day', 'OT Holiday', 'Other', 'Annual Bonus', 'Leave Deduction',
            'Other Deduction', 'Loan', 'Gross Pay', 'Excess', 'Total Emoluments', 'Accommodation', 'Charge Income',
            'Petra', 'SSNIT Amount', 'NSSF', 'Tax Base', 'PAYE', 'Annual Tax', 'Net Pay', 'HR Cost', 'Brand Name'];
    }

    private function getMelcomHeaders() {
        return ['employ', 'empoyeename', 'outsourcename', 'designation', 'joindate', 'shopname', 'category',
            'saturdayot', 'actualdays', 'night', 'halfdays', 'satoffdays', 'sunoffdays', 'leavededuct',
            'absentdays', 'absenthrs', 'holiday', 'weekdaysOT', 'weekendOT', 'holidayot', 'basic',
            'absentwithoutpay', 'basicentitlement', 'transport', 'normalovertime', 'saturdayovertime',
            'holidayotamt', 'gross', 'total_cash_emolument', 'total_assessable_income', 'ssnit', 'taxable', 'paye', 'totalovertime', 'overtimetax',
            'totaltax', 'trust', 'totaldeduction', 'banktransfer'];
    }

    private function getNyonkopaHeaders() {
        return ['staff_id', 'employee_name', 'start_date', 'position', 'work_location', 'base_salary',
            'fuel_and_rent_allowance', 'gross_salary', 'management_fee_8', 'getfund_2_5', 'nhil_2_5',
            'covid_19_1', 'vat_15', 'medical_insurance', 'employer_provident_fund_5', 'thirteenth_month_bonus',
            'ssnit_er_13', 'cost_to_nyonkopa', 'ssnit_ee_5_5', 'taxable_income', 'paye', 'thirteenth_month_tax',
            'total_taxation', 'total_deductions', 'take_home_pay'];
    }

    private function getHondaHeaders() {
        return ['STAFFID', 'NAME', 'DATEOFEMPLOYMENT', 'POSITION', 'BASICSALARY_GHC', 'LEGALWORKDAYS',
            'NUMBEROFDAYSWORKED', 'LEAVEDAYS', 'LEAVEAMOUNT', 'BASICSALARYEARNED_GHC', 'PERFORMANCEBONUSSTANDARD',
            'COEFFICIENT', 'PERFORMANCEBONUS', 'TRANSPORTALLOWANCE', 'foodallowance', 'medicalinsurance',
            'GROSSSALARY_GHC', 'MANAGEMENTFEE_8', 'GETFUND_2_5', 'NHIL_2_5', 'COVID_19_1', 'VAT_15',
            'SSF_13', 'COSTTOHONDA_GHC', 'SSNITEE_0_5', 'PETRA_5', 'TAXABLEINCOME_GHC', 'PAYE_GHC',
            'TOTALDEDUCTIONS_GHC', 'TAKEHOMEPAY_GHC', 'BANK', 'ACCOUNTNUMBER', 'BRANCH', 'SSNITNUMBER', 'GHCARDNUMBER'];
    }

    private function getTranssnetHeaders() {
        return ['staffid', 'name', 'business_line', 'department', 'position', 'hired_date', 'last_date', 'work_place', 'staff_category', 'expiration_date', 'contracting_entity', 'bank_name', 'account_name', 'bank_account_no', 'branch_code', 'basic', 'performance_basis', 'coefficient', 'performance_final', 'call_allowance', 'house_allowance', 'traffic_allowance', 'commission', 'severance_pay', 'other_income', 'work_days', 'absent_days', 'absent_days_deduction', 'other_deduction', 'gross_salary', 'petra_ee_5', 'ssnit_ee_05', 'base_for_paye', 'ee_paye', 'ssnit_er_13', 'outsourced_service', 'getfund', 'nhiil', 'covid_19', 'vat_for_music_ghana', 'total_cost_of_outsource_service', 'ee_cost', 'er_cost', 'after_tax_deductions', 'netpay', 'total_hr_cost'];
    }

    private function getJkayHeaders() {
        return ['staff_id', 'name', 'position', 'join_date', 'basic_salary', 'legalworkdays', 'daily_rate', 'days_worked', 'absentee_days', 'absent_deductions', 'earned_basic_salary', 'saturdays_worked', 'saturday_rate', 'saturday_amount', 'total_days_amount', 'overtime_hours', 'overtime_rate', 'overtime_amount', 'night_allowance', 'totalovertime', 'gross_salary', 'management_fee', 'getfund', 'nhil', 'covid19', 'vat', 'ssf', 'cost_to_jkay', 'ssnit_ee', 'taxable_income', 'paye', 'overtime_tax', 'surcharge_or_student_loan', 'total_deductions', 'take_home_pay'];
    }

    private function getPzHeaders() {
        return ['staffid', 'name', 'dept', 'basic', 'overtime', 'incentive', 'bonus', 'other', '13month', 'totalearn', 'ssnitee', 'overtimetaxt', 'pf', 'taxable', 'ssniter', '13tax', 'totaltaxdeduct', 'totaldeduct', 'netpay', 'bank1', 'account1', 'branch1', 'ssnit1', 'ghcard'];
    }

    private function getTwelliumAccraHeaders() {
        return ['staff_id', 'name', 'position', 'start_date', 'basic_salary', 'legal_work_days', 'days_worked', 'absent_days', 'absent_deduction', 'basic_earned', 'weekday_hours', 'weekday_overtime_amt', 'saturday_hours', 'saturday_overtime_amt', 'num_sundays_holidays', 'sunday_holiday_overtime_amt', 'overtimerefund', 'total_overtime_amt', 'gross_salary', 'management_fee', 'getfund', 'nhil', 'covid_19', 'vat_15', 'ssf_13', 'medical_insurance', 'end_of_year', 'ppe', 'workmen_compensation', 'cost_to_twellium', 'ssnit_ee', 'taxable_income', 'paye', 'deductions', 'total_deductions', 'net_salary', 'refund', 'take_home_pay'];
    }

    private function getSheferHeaders() {
        return ['staff_id', 'name1', 'position', 'department', 'startdate', 'basic', 'total_weekdays', 'no_of_days_worked', 'leavepay', 'absent_days', 'basic_earned', 'nightdays', 'weekdayOT_hours', 'weekdayOT_rate', 'weekdayOT_amount', 'weekendOT_hours', 'weekendOT_rate', 'weekendOT_amount', 'sunOT_hours', 'sunOT_rate', 'sunOT_amount', 'fixedotbasic', 'fixedot', 'total_overtime', 'foodbasic', 'food', 'airtimebasic', 'airtime', 'nightallo', 'responsebasic', 'responseallo', 'transbasic', 'transport', 'medicalbasic', 'medical', 'total_allowance', 'gross', 'emp_ssnit_13_percent', 'ssnit_5_5_percent', 'taxable_income', 'paye', 'ot_tax', 'total_tax', 'absent_without_pay', 'salaryloan', 'total_deductions', 'refunds', 'net_pay'];
    }

    private function getFonpackageHeaders() {
        return ['staff_id', 'name', 'position', 'date_of_employment', 'basic_salary', 'legal_work_days', 'number_of_days_worked', 'no_of_absent_days', 'absent_days_deduction', 'basic_salary_earned', 'number_of_days_on_night_duty', 'normal_weekday_overtime_hours', 'normal_weekday_overtime_rate', 'weekday_overtime_total', 'saturday_overtime_hours', 'saturday_overtime_rate', 'saturday_overtime_total', 'sunday_holiday_overtime_hours', 'sunday_holiday_overtime_rate', 'sunday_holiday_overtime_total', 'total_overtime_amount', 'night_duty_allowance', 'gross_salary', 'ssf_13', 'medical_insurance', 'ppe', 'workmen_compensation', 'management_fee_10', 'getfund_2_5', 'nhil_2_5', 'covid_19_1', 'vat_15', 'cost_to_fon_packaging', 'gross_salary_repeat', 'ssnit_ee_5_5', 'taxable_income', 'paye', 'overtime_tax', 'total_taxation', 'total_deductions', 'take_home_pay'];
    }

    private function getDriverssnitHeaders() {
        return ['employ', 'name', 'report', 'dept', 'dob', 'joindate', 'ssnitnum', 'age', 'remarks', 'salary', 'week', 'basic', 'allowance', 'sat', 'satOT', 'ssfyee', 'ssfy er', 'paye', 'ottax', 'advance', 'totaldeduct', 'netpay', 'servicecharge'];
    }

    private function getCenturypayHeaders() {
        return ['employ', 'empoyeename', 'outsourced', 'desig', 'joindate', 'shopname', 'actualdays', 'night', 'halfdays', 'satoffdays', 'sunoffdays', 'leavedays', 'absentdays', 'holiday', 'weekdaysOT', 'weekendOT', 'basic', 'absentwithoutpay', 'basicentitlement', 'transport', 'nightallowance', 'normalovertime', 'saturdayovertime', 'gross', 'ssnit', 'taxable', 'paye', 'totalovertime', 'overtimetax', 'totaltax', 'advance', 'totaldeduction', 'netpay'];
    }

    private function getA2feedHeaders() {
        return ['staff_id', 'name', 'position', 'join_date', 'basic_salary', 'days_worked', 'absenteeism_days', 'absent_days_deductions', 'basic_salary_earned', 'risk_allowance', 'rent_allowance', 'transport_standard', 'transport_earned', 'weekday_overtime_hours', 'weekday_overtime_rate', 'weekday_overtime_amount', 'sunday_overtime_hours', 'sunday_overtime_rate', 'sunday_overtime_amount', 'total_overtime_amount', 'gross_salary', 'management_fee_8', 'getfund_2_5', 'nhil_2_5', 'covid_1', 'vat_15', 'ssf_13', 'medical_insurance', 'workmens_compensation', 'cost_to_a2_agro', 'gross_salary_repeat', 'ssnit_ee_5_5', 'taxable_income', 'paye', 'overtime_tax', 'other_deductions', 'total_deductions', 'take_home_pay', 'ssnit_number', 'gh_card', 'bank', 'account_number', 'branch'];
    }

    private function getA2poultryHeaders() {
        return ['staff_id', 'name', 'position', 'join_date', 'basic_salary', 'legalwork', 'days_worked', 'absenteeism_days', 'absent_days_deductions', 'basic_salary_earned', 'transport_standard', 'transport_earned', 'fixed_sunday_overtime', 'sundays_worked', 'sunday_overtime_amount', 'gross_salary', 'management_fee_8', 'getfund_2_5', 'nhil_2_5', 'covid_1', 'vat_15', 'ssf_13', 'medical_insurance', 'workmens_compensation', 'cost_to_a2_agro', 'gross_salary_repeat', 'ssnit_ee_5_5', 'taxable_income', 'paye', 'overtime_tax', 'other_deductions', 'total_deductions', 'take_home_pay', 'ssnit_number', 'gh_card', 'bank', 'account_number', 'branch'];
    }

    private function getOlambwhHeaders() {
        return ['id', 'name', 'basic', 'workdays', 'f&trate', 'weekdayhr', 'weekdaytonage', 'weekdayrate', 'amt', 'weekdayothr', 'weekdayottonage', 'weekdayotrate', 'weekdayotamt1', 'weekendhr', 'weekendtonage', 'weekendrate', 'amount', 'leavedays', 'totalamount', 'phonecredit', 'ssnit13', 'f&tamount', 'servicecharge', 'getfund', 'nhil', 'covid', 'vat', 'costtoolam', 'ssnitee', 'taxincome', 'totaltax', 'totaldeduction', 'netpay', 'bank1', 'account2', 'branch', 'ssnit2', 'ghcard1'];
    }

    private function getOffloadHeaders() {
        return ['StaffID', 'employeename', 'workarea', 'basicsalary', 'arrears', 'gross', 'servicecharge', 'getfund', 'nhil', 'covid', 'vat', 'ssf', 'costtoolam', 'ssnitee', 'taxableincome', 'totaltaxdeductions', 'totaldeductions', 'takehomepay', 'bankaa', 'accountnumberaa', 'branchaa', 'ssnitaa'];
    }

    private function getHygieneexternalHeaders() {
        return ['staffid', 'employeename', 'workarea', 'startdate', 'basicsalary', 'legalworkdays', 'noofdayspresent', 'basicsalaryearned', 'weekdayovertimehr', 'weekdayovertimetotal', 'weekendovertimehr', 'weekendovertimetotal', 'totalamount', 'transport', 'servicecharge', 'getfund', 'nhil', 'covid', 'vat', 'ssf', 'costtoolam', 'ssnitee', 'taxableincome', 'totaltaxdeductions', 'totaldeductions', 'takehomepay', 'bank', 'accountnumber', 'branch', 'ssnit', 'ghcard'];
    }

    private function getOlamhygieneHeaders() {
        return ['id', 'names', 'dept', 'noofdays', 'extraldays', 'total1', 'rate', 'total2', 'overtimehr', 'overtimetotal', 'overtimeweekend', 'overtimetotal2', 'totalamount', 'transport', 'servicecharge', 'getfund', 'nhil', 'covid19', 'vat', 'ssf', 'leave1', 'night', 'nightallowance', 'medical', 'costofolam', 'ssnitee', 'taxableincome', 'totaltaxdeduction', 'totaldeduction', 'takehome', 'bank1', 'account1', 'branch1', 'ssnit12', 'ghcard1'];
    }

    private function getOlamtemHeaders() {
        return ['id', 'names', 'dept', 'noofdays', 'extraldays', 'total1', 'rate', 'total2', 'overtimehr', 'overtimetotal', 'overtimeweekend', 'overtimetotal2', 'totalamount', 'transport', 'servicecharge', 'getfund', 'nhil', 'covid19', 'vat', 'ssf', 'leave1', 'night', 'nightallowance', 'crane', 'medical', 'costofolam', 'ssnitee', 'taxableincome', 'totaltaxdeduction', 'totaldeduction', 'takehome', 'bank1', 'account1', 'branch1', 'ssnit12', 'ghcard1'];
    }

    private function getBungeghHeaders() {
        return ['STAFF_ID', 'NAME', 'POSITION', 'BASE_SALARY_GHC', 'TRANSPORT_ALLOWANCE_GHC', 'ADDITIONAL_ALLOWANCE_GHC', 'OVERTIME_GHC', 'BONUS_EARNINGS_ALLOWANCE', 'GROSS_SALARY_GHC', 'MANAGEMENT_FEE_5_PERCENT', 'SSNIT_ER_13_PERCENT', 'GETFUND_2_5_PERCENT', 'NHIL_2_5_PERCENT', 'COVID_19_1_PERCENT', 'VAT_15_PERCENT', 'COST_TO_BUNGE_GHC', 'gross2', 'SSNIT_5_5_PERCENT', 'TAXABLE_INCOME_GHC', 'PAYE_GHC', 'BONUS_TAX', 'TOTAL_TAXATION_GHC', 'TAKE_HOME_PAY_GHC', 'BANK', 'ACCOUNT_NUMBER', 'BRANCH', 'SSNIT', 'GH_CARD_NUMBER'];
    }

    private function getTwelliumHeaders() {
        return ['employ', 'name', 'department', 'dateemploy', 'expected_work_days', 'total_hours_worked', 'basic', 'basic_earned', 'food_allowance', 'night_allowance', 'refund', 'allowance', 'deduction', 'ssnit_ee', 'taxable_income', 'paye', 'ssf', 'welfare', 'medical_insurance', 'workmens_compensation', 'risk_allowance', 'end_of_year', 'ppe', 'staff_cost', 'mgt_fee', 'gfl', 'nhil', 'covid', 'vat', 'c2c', 'weekday_overtime_hours', 'weekday_overtime_rate', 'weekday_overtime_amount', 'offday_holiday_overtime_hours', 'offday_holiday_overtime_rate', 'offday_holiday_overtime_total', 'special_day_overtime_hours', 'special_day_overtime_rate', 'special_day_overtime_amount', 'total_overtime_amount', 'overtime_tax', 'total_taxation', 'total_deductions', 'netpay'];
    }

    // ==================== DB MAPPING ====================

    private function getHeaderToDbMap($client_dbname) {
        $maps = [
            'transsion' => array_combine($this->getTranssionHeaders(), [
                'staffida','name','department','hiredate','constart','conend','position','grade',
                'categories','workplace','bankname','alcnum','branch','ssnit','col12','workdays',
                'leavedays','otworkdays','otweekend','basic','performbonus','coefficient','performstand',
                'allowance','commission','otworkingday','otholiday','other','annaulbonus','leavededuction',
                'otherdeduction','loan','grosspay','excess','totalemolu','accommodation','chargeincome',
                'petra','ssnitamt','nssf','taxbase','paye','annaultax','netpay','hrcost','brandname'
            ]),
            'melcom' => ['employ'=>'staffida', 'empoyeename'=>'name', 'banktransfer'=>'netpay'],
            'nyonkopa' => ['staff_id'=>'staffida', 'employee_name'=>'name', 'take_home_pay'=>'netpay'],
            'honda' => ['STAFFID'=>'staffida', 'NAME'=>'name', 'TAKEHOMEPAY_GHC'=>'netpay'],
            'transsnet' => ['staffid'=>'staffida', 'name'=>'name', 'netpay'=>'netpay'],
            'jkay' => ['staff_id'=>'staffida', 'name'=>'name', 'take_home_pay'=>'netpay'],
            'pz' => ['staffid'=>'staffida', 'name'=>'name', 'netpay'=>'netpay'],
            'twelliumaccra' => ['staff_id'=>'staffida', 'name'=>'name', 'take_home_pay'=>'netpay'],
            'shefer' => ['staff_id'=>'staffida', 'name1'=>'name', 'net_pay'=>'netpay'],
            'fonpackage' => ['staff_id'=>'staffida', 'name'=>'name', 'take_home_pay'=>'netpay'],
            'driverssnit' => ['employ'=>'staffida', 'name'=>'name', 'netpay'=>'netpay'],
            'centurypay' => ['employ'=>'staffida', 'empoyeename'=>'name', 'netpay'=>'netpay'],
            'a2feed' => ['staff_id'=>'staffida', 'name'=>'name', 'take_home_pay'=>'netpay'],
            'a2poultry' => ['staff_id'=>'staffida', 'name'=>'name', 'take_home_pay'=>'netpay'],
            'olambwh' => ['id'=>'staffida', 'name'=>'name', 'netpay'=>'netpay'],
            'offload' => ['StaffID'=>'staffida', 'employeename'=>'name', 'takehomepay'=>'netpay'],
            'hygieneexternal' => ['staffid'=>'staffida', 'employeename'=>'name', 'takehomepay'=>'netpay'],
            'olamhygiene' => ['id'=>'staffida', 'names'=>'name', 'takehome'=>'netpay'],
            'olamtem' => ['id'=>'staffida', 'names'=>'name', 'takehome'=>'netpay'],
            'bungegh' => ['STAFF_ID'=>'staffida', 'NAME'=>'name', 'TAKE_HOME_PAY_GHC'=>'netpay'],
            'twellium' => ['employ'=>'staffida', 'name'=>'name', 'netpay'=>'netpay'],
        ];

        return $maps[$client_dbname] ?? $maps['transsion'];
    }
}
