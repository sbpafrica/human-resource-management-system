<?php

namespace App\Services\Payroll;

use App\Services\Payroll\ColumnMatcher\HybridColumnMatcher;
use App\Services\Payroll\Anomaly\HybridAnomalyDetector;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class DynamicIngestionService
{
    public function __construct(
        protected HybridColumnMatcher $columnMatcher,
        protected HybridAnomalyDetector $anomalyDetector
    ) {}

    /**
     * Ingests and parses any client payroll spreadsheet using PhpSpreadsheet,
     * resolves column mappings through 3-tier matcher (Grok AI -> Rules -> Legacy),
     * and performs pre-flight fraud & anomaly checks.
     *
     * @param UploadedFile|string $file Uploaded Excel file or path
     * @param string $clientSlug Client slug identifier (e.g. 'jaykay', 'ofitemp', 'melcom')
     * @param string $payrollPeriod 'YYYY-MM'
     * @return array [
     *     'success' => bool,
     *     'client' => string,
     *     'period' => string,
     *     'tier_used' => string,
     *     'confidence' => float,
     *     'column_mapping' => array,
     *     'headers' => array,
     *     'records' => array,
     *     'audit' => array,
     *     'errors' => array,
     * ]
     */
    public function process(UploadedFile|string $file, string $clientSlug, string $payrollPeriod): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $extension = $file instanceof UploadedFile ? $file->getClientOriginalExtension() : pathinfo($filePath, PATHINFO_EXTENSION);

        try {
            $reader = strtolower($extension) === 'xls' ? new XlsReader() : new XlsxReader();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
        } catch (Throwable $e) {
            Log::channel('payroll_ai')->error("Failed to read spreadsheet for {$clientSlug}: " . $e->getMessage());
            return [
                'success' => false,
                'errors' => ["Could not read spreadsheet file: " . $e->getMessage()],
            ];
        }

        if (empty($rows)) {
            return [
                'success' => false,
                'errors' => ["Spreadsheet is empty."],
            ];
        }

        // Extract header row (row 1)
        $rawHeaders = array_shift($rows);
        $sampleRows = array_slice($rows, 0, 5);

        // 3-Tier Column Resolution (Grok AI -> PHP Rules -> Legacy Fallback)
        $matchResult = $this->columnMatcher->match($rawHeaders, $clientSlug, $sampleRows);
        $cols = $matchResult['mapping'];

        $parsedRecords = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            // Extract standard fields safely using resolved indices
            $staffId = isset($cols['staff_id'], $row[$cols['staff_id']]) ? trim((string) $row[$cols['staff_id']]) : null;
            $name = isset($cols['name'], $row[$cols['name']]) ? trim((string) $row[$cols['name']]) : null;

            // Skip empty rows
            if (empty($staffId) && empty($name)) {
                continue;
            }

            $basicSalary = isset($cols['basic_salary'], $row[$cols['basic_salary']]) && is_numeric($row[$cols['basic_salary']])
                ? (float) $row[$cols['basic_salary']] : null;
            $dailyRate = isset($cols['daily_rate'], $row[$cols['daily_rate']]) && is_numeric($row[$cols['daily_rate']])
                ? (float) $row[$cols['daily_rate']] : null;
            $daysWorked = isset($cols['days_worked'], $row[$cols['days_worked']]) && is_numeric($row[$cols['days_worked']])
                ? (float) $row[$cols['days_worked']] : null;
            $overtimeAmount = isset($cols['overtime_amount'], $row[$cols['overtime_amount']]) && is_numeric($row[$cols['overtime_amount']])
                ? (float) $row[$cols['overtime_amount']] : null;
            $weekdayHours = isset($cols['weekday_hours'], $row[$cols['weekday_hours']]) && is_numeric($row[$cols['weekday_hours']])
                ? (float) $row[$cols['weekday_hours']] : null;
            $weekdayOvertimeAmt = isset($cols['weekday_overtime_amt'], $row[$cols['weekday_overtime_amt']]) && is_numeric($row[$cols['weekday_overtime_amt']])
                ? (float) $row[$cols['weekday_overtime_amt']] : null;
            $saturdayHours = isset($cols['saturday_hours'], $row[$cols['saturday_hours']]) && is_numeric($row[$cols['saturday_hours']])
                ? (float) $row[$cols['saturday_hours']] : null;
            $saturdayOvertimeAmt = isset($cols['saturday_overtime_amt'], $row[$cols['saturday_overtime_amt']]) && is_numeric($row[$cols['saturday_overtime_amt']])
                ? (float) $row[$cols['saturday_overtime_amt']] : null;
            $numSundaysHolidays = isset($cols['num_sundays_holidays'], $row[$cols['num_sundays_holidays']]) && is_numeric($row[$cols['num_sundays_holidays']])
                ? (float) $row[$cols['num_sundays_holidays']] : null;
            $sundayHolidayOvertimeAmt = isset($cols['sunday_holiday_overtime_amt'], $row[$cols['sunday_holiday_overtime_amt']]) && is_numeric($row[$cols['sunday_holiday_overtime_amt']])
                ? (float) $row[$cols['sunday_holiday_overtime_amt']] : null;
            $overtimeRefund = isset($cols['overtimerefund'], $row[$cols['overtimerefund']]) && is_numeric($row[$cols['overtimerefund']])
                ? (float) $row[$cols['overtimerefund']] : null;
            $totalOvertimeAmt = isset($cols['total_overtime_amt'], $row[$cols['total_overtime_amt']]) && is_numeric($row[$cols['total_overtime_amt']])
                ? (float) $row[$cols['total_overtime_amt']] : null;

            // Compute effective total overtime across aggregate or component fields
            $componentOtSum = ($weekdayOvertimeAmt !== null || $saturdayOvertimeAmt !== null || $sundayHolidayOvertimeAmt !== null)
                ? ((float)($weekdayOvertimeAmt ?? 0) + (float)($saturdayOvertimeAmt ?? 0) + (float)($sundayHolidayOvertimeAmt ?? 0))
                : null;
            $effectiveOvertime = $totalOvertimeAmt ?? $overtimeAmount ?? $componentOtSum;

            $grossSalary = isset($cols['gross_salary'], $row[$cols['gross_salary']]) && is_numeric($row[$cols['gross_salary']])
                ? (float) $row[$cols['gross_salary']] : null;
            $ssnit = isset($cols['ssnit_ee'], $row[$cols['ssnit_ee']]) && is_numeric($row[$cols['ssnit_ee']])
                ? (float) $row[$cols['ssnit_ee']] : null;
            $petra = isset($cols['petra'], $row[$cols['petra']]) && is_numeric($row[$cols['petra']])
                ? (float) $row[$cols['petra']] : null;
            $paye = isset($cols['paye'], $row[$cols['paye']]) && is_numeric($row[$cols['paye']])
                ? (float) $row[$cols['paye']] : null;
            $netPay = isset($cols['net_pay'], $row[$cols['net_pay']]) && is_numeric($row[$cols['net_pay']])
                ? (float) $row[$cols['net_pay']] : null;
            $bank = isset($cols['bank'], $row[$cols['bank']]) ? trim((string) $row[$cols['bank']]) : null;
            $account = isset($cols['account'], $row[$cols['account']]) ? trim((string) $row[$cols['account']]) : null;

            $parsedRecords[] = [
                'row_number'                  => $rowNum,
                'staff_id'                    => $staffId,
                'name'                        => $name,
                'position'                    => isset($cols['position'], $row[$cols['position']]) ? trim((string) $row[$cols['position']]) : null,
                'basic_salary'                => $basicSalary,
                'daily_rate'                  => $dailyRate,
                'days_worked'                 => $daysWorked,
                'overtime_amount'             => $effectiveOvertime,
                'total_overtime_amt'          => $effectiveOvertime,
                'weekday_hours'               => $weekdayHours,
                'weekday_overtime_amt'        => $weekdayOvertimeAmt,
                'saturday_hours'              => $saturdayHours,
                'saturday_overtime_amt'       => $saturdayOvertimeAmt,
                'num_sundays_holidays'        => $numSundaysHolidays,
                'sunday_holiday_overtime_amt' => $sundayHolidayOvertimeAmt,
                'overtimerefund'              => $overtimeRefund,
                'gross_salary'                => $grossSalary,
                'ssnit_ee'                    => $ssnit,
                'petra'                       => $petra,
                'paye'                        => $paye,
                'net_pay'                     => $netPay,
                'bank'                        => $bank,
                'account'                     => $account,
                'raw_row'                     => $row,
            ];
        }

        // Run Pre-Flight Anomaly & Fraud Audit
        $auditReport = $this->anomalyDetector->detect($parsedRecords, $clientSlug, $payrollPeriod);

        return [
            'success' => true,
            'client' => $clientSlug,
            'period' => $payrollPeriod,
            'tier_used' => $matchResult['tier'],
            'confidence' => $matchResult['confidence'],
            'column_mapping' => $cols,
            'headers' => $rawHeaders,
            'records' => $parsedRecords,
            'audit' => $auditReport,
            'errors' => $errors,
        ];
    }
}
