<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use DateTime;
use Exception;

class MelcomBasicController extends Controller
{
    public function index(Request $request)
    {
        $monthYear = $request->input('month_year', '');
        $search    = trim($request->input('search', ''));
        $perPage   = (int) $request->input('per_page', 25);

        $underpaidEmployees = [];
        $errorMsg = null;

        if (!empty($monthYear)) {
            $data = $this->getUnderpaidEmployees($monthYear);
            if (isset($data['error'])) {
                $errorMsg = $data['error'];
            } else {
                $underpaidEmployees = $data['records'];
            }
        }

        if (!empty($search) && empty($errorMsg)) {
            $underpaidEmployees = array_filter($underpaidEmployees, function ($row) use ($search) {
                $searchLower = strtolower($search);
                return str_contains(strtolower($row['staff_id']), $searchLower)
                    || str_contains(strtolower($row['name']), $searchLower)
                    || str_contains(strtolower($row['shop']), $searchLower);
            });
            $underpaidEmployees = array_values($underpaidEmployees);
        }

        $totalRecords = count($underpaidEmployees);

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = array_slice($underpaidEmployees, ($currentPage - 1) * $perPage, $perPage);
        $paginatedRecords = new LengthAwarePaginator($currentItems, $totalRecords, $perPage, $currentPage, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => $request->query()
        ]);

        return Inertia::render('HrAudit/MelcomBasic', [
            'records' => $paginatedRecords,
            'filters' => [
                'month_year' => $monthYear,
                'search' => $search,
            ],
            'totalRecords' => $totalRecords,
            'flash' => [
                'success' => session('success'),
                'error'   => $errorMsg ?? session('error'),
            ],
        ]);
    }

    public function exportExcel(Request $request)
    {
        $monthYear = $request->input('month_year', '');
        $search    = trim($request->input('search', ''));

        if (empty($monthYear)) {
            return redirect()->back()->with('error', 'Month/Year is required for export.');
        }

        $data = $this->getUnderpaidEmployees($monthYear);
        if (isset($data['error'])) {
            return redirect()->back()->with('error', $data['error']);
        }

        $underpaidEmployees = $data['records'];

        if (!empty($search)) {
            $underpaidEmployees = array_filter($underpaidEmployees, function ($row) use ($search) {
                $searchLower = strtolower($search);
                return str_contains(strtolower($row['staff_id']), $searchLower)
                    || str_contains(strtolower($row['name']), $searchLower)
                    || str_contains(strtolower($row['shop']), $searchLower);
            });
            $underpaidEmployees = array_values($underpaidEmployees);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Underpaid Employees');

        $headers = ['Staff ID', 'Name', 'Start Date', 'Shop', 'Position', 'Length of Service', 'Current Basic', 'Entitled Basic', 'Deficit'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D97706']], // Amber-600
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

        $rowNum = 2;
        $currentBasicTotal = 0;
        $entitledBasicTotal = 0;
        $deficitTotal = 0;

        foreach ($underpaidEmployees as $emp) {
            $sheet->setCellValue("A{$rowNum}", $emp['staff_id']);
            $sheet->setCellValue("B{$rowNum}", $emp['name']);
            $sheet->setCellValue("C{$rowNum}", $emp['start_date']);
            $sheet->setCellValue("D{$rowNum}", $emp['shop']);
            $sheet->setCellValue("E{$rowNum}", $emp['position']);
            $sheet->setCellValue("F{$rowNum}", $emp['length_of_service']);
            $sheet->setCellValue("G{$rowNum}", $emp['current_basic']);
            $sheet->setCellValue("H{$rowNum}", $emp['entitled_basic']);
            $sheet->setCellValue("I{$rowNum}", $emp['deficit']);

            $sheet->getStyle("G{$rowNum}:I{$rowNum}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

            $currentBasicTotal += $emp['current_basic'];
            $entitledBasicTotal += $emp['entitled_basic'];
            $deficitTotal += $emp['deficit'];
            $rowNum++;
        }

        // Totals Row
        $sheet->setCellValue("A{$rowNum}", 'TOTALS');
        $sheet->setCellValue("G{$rowNum}", $currentBasicTotal);
        $sheet->setCellValue("H{$rowNum}", $entitledBasicTotal);
        $sheet->setCellValue("I{$rowNum}", $deficitTotal);
        
        $totalsStyle = ['font' => ['bold' => true]];
        $sheet->getStyle("A{$rowNum}:I{$rowNum}")->applyFromArray($totalsStyle);
        $sheet->getStyle("G{$rowNum}:I{$rowNum}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $safeRange = str_replace([' ', '/'], '_', $monthYear);
        $fileName = "Melcom_Underpaid_Audit_{$safeRange}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function getUnderpaidEmployees($monthYear): array
    {
        $date = DateTime::createFromFormat('m/Y', $monthYear);
        if (!$date) {
            return ['error' => 'Invalid month/year format.'];
        }

        $monthName = strtolower($date->format('F'));
        $yearStr = $date->format('y');
        $tableName = "melcom{$monthName}{$yearStr}";
        $payrollDate = $date->format('Y-m-t');

        $tableExists = collect(DB::select("SHOW TABLES LIKE ?", [$tableName]))->isNotEmpty();
        if (!$tableExists) {
            return ['error' => "Payroll data table '{$tableName}' does not exist for {$monthYear}."];
        }

        $employees = DB::table($tableName)
            ->select('employ as staff_id', 'empoyeename as name', 'joindate as start_date', 'shopname as shop', 'designation as position', 'basic')
            ->whereNotNull('basic')
            ->get();

        $underpaid = [];

        foreach ($employees as $emp) {
            if (empty($emp->start_date)) continue;
            
            $service = $this->calculateLengthOfService($emp->start_date, $payrollDate);
            if (!$service) continue;

            $entitled = $this->getEntitledBasic($service['totalMonths']);
            $current = floatval($emp->basic);

            if ($current < $entitled) {
                $underpaid[] = [
                    'staff_id' => $emp->staff_id ?? 'N/A',
                    'name' => $emp->name ?? 'N/A',
                    'start_date' => $emp->start_date ?? 'N/A',
                    'shop' => $emp->shop ?? 'N/A',
                    'position' => $emp->position ?? 'N/A',
                    'length_of_service' => $service['string'],
                    'current_basic' => $current,
                    'entitled_basic' => $entitled,
                    'deficit' => $entitled - $current,
                ];
            }
        }

        usort($underpaid, fn($a, $b) => strcmp($a['name'], $b['name']));

        return ['records' => $underpaid];
    }

    private function calculateLengthOfService($joinDate, $payrollDate)
    {
        try {
            $date = DateTime::createFromFormat('Y-m-d', $joinDate) ?: 
                    DateTime::createFromFormat('d/m/Y', $joinDate) ?:
                    DateTime::createFromFormat('d-M-y', $joinDate);
            
            if (!$date) return null;

            $payrollDateObj = new DateTime($payrollDate);
            $interval = $date->diff($payrollDateObj);
            
            $years = $interval->y;
            $months = $interval->m;
            $totalMonths = $years * 12 + $months;
            
            $los_string = '';
            if ($years > 0) {
                $los_string .= "$years year" . ($years > 1 ? 's' : '');
            }
            if ($years > 0 && $months > 0) {
                $los_string .= ', ';
            }
            if ($months > 0) {
                $los_string .= "$months month" . ($months > 1 ? 's' : '');
            }
            if ($los_string === '') {
                $los_string = '0 months';
            }
            
            return [
                'years' => $years,
                'months' => $months,
                'totalMonths' => $totalMonths,
                'string' => $los_string
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    private function getEntitledBasic($totalMonths)
    {
        if ($totalMonths >= 91) return 920.00;
        if ($totalMonths >= 72) return 891.25;
        if ($totalMonths >= 62) return 862.50;
        if ($totalMonths >= 48) return 805.00;
        if ($totalMonths >= 38) return 776.25;
        if ($totalMonths >= 24) return 747.50;
        if ($totalMonths >= 4) return 650.00;
        return 650.00;
    }
}
