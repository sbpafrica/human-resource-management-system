<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ArchivedEmployeeController extends Controller
{
    /**
     * Display a listing of all archived employees.
     * Matches raw PHP query: SELECT * FROM newemployeedataarchieve
     */
    public function index(Request $request)
    {
        $query = DB::table('newemployeedataarchieve');

        $totalRecords = $query->count();
        $searchCount = null;

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('staffid', 'like', "%{$search}%")
                  ->orWhere('employeename', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%")
                  ->orWhere('shop', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%")
                  ->orWhere('ssnit', 'like', "%{$search}%")
                  ->orWhere('ghcard', 'like', "%{$search}%")
                  ->orWhere('bank', 'like', "%{$search}%")
                  ->orWhere('account', 'like', "%{$search}%")
                  ->orWhere('phone1', 'like', "%{$search}%")
                  ->orWhere('phone2', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
            $searchCount = $query->count();
        }

        if ($companyFilter = $request->input('company')) {
            $query->where('company', $companyFilter);
            if ($searchCount === null) {
                $searchCount = $query->count();
            }
        }

        $sort = $request->input('sort', 'employeename');
        $order = strtolower($request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        
        $allowedSorts = [
            'staffid', 'employeename', 'company', 'shop', 'position', 
            'startdate', 'dateofbirth', 'ssnit', 'ghcard', 'bank', 
            'account', 'branch', 'phone1', 'gender', 'archived_at'
        ];
        $sortColumn = in_array($sort, $allowedSorts) ? $sort : 'employeename';
        $query->orderBy($sortColumn, $order);

        $employees = $query->paginate(15)->withQueryString();

        // Get all distinct companies from the archive table for the dropdown
        $allCompanies = DB::table('newemployeedataarchieve')
            ->whereNotNull('company')
            ->where('company', '!=', '')
            ->distinct()
            ->orderBy('company')
            ->pluck('company')
            ->toArray();

        return Inertia::render('EmployeeArchived/Index', [
            'employees'    => $employees,
            'filters'      => [
                'search'  => $search ?? '',
                'company' => $companyFilter ?? '',
                'sort'    => $sort,
                'order'   => $order,
            ],
            'totalRecords' => $totalRecords,
            'searchCount'  => $searchCount,
            'clients'      => $allCompanies,
            'flash'        => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    /**
     * Restore an archived employee back to newemployeedata.
     */
    public function restore(Request $request, $staffid)
    {
        Log::info('Restoring archived employee', ['staffid' => $staffid]);

        $archived = DB::table('newemployeedataarchieve')->where('staffid', $staffid)->first();
        if (!$archived) {
            return redirect()->back()->with('error', "Archived employee with Staff ID '{$staffid}' was not found.");
        }

        // Check if staff ID already exists in active employees
        if (DB::table('newemployeedata')->where('staffid', $staffid)->exists()) {
            return redirect()->back()->with('error', "Cannot restore: An active employee with Staff ID '{$staffid}' already exists in Employee Portal.");
        }

        try {
            DB::transaction(function () use ($archived, $staffid) {
                $data = (array) $archived;
                unset($data['archived_at']);

                // Ensure MySQL strict mode doesn't block legacy values
                DB::statement("SET SESSION sql_mode = ''");
                DB::table('newemployeedata')->insert($data);
                DB::table('newemployeedataarchieve')->where('staffid', $staffid)->delete();
                DB::statement("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            });

            return redirect()->back()->with('success', "Employee '{$archived->employeename}' ({$staffid}) successfully restored to active records.");
        } catch (\Exception $e) {
            Log::error('Restore failed', ['staffid' => $staffid, 'error' => $e->getMessage()]);
            return redirect()->back()->with('error', "Failed to restore employee: " . $e->getMessage());
        }
    }

    /**
     * Bulk restore multiple archived employees.
     */
    public function bulkRestore(Request $request)
    {
        $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string'],
        ]);

        $ids = $request->input('ids');
        $archivedRecords = DB::table('newemployeedataarchieve')->whereIn('staffid', $ids)->get();

        if ($archivedRecords->isEmpty()) {
            return redirect()->back()->with('error', 'No matching archived records found.');
        }

        $existingActiveIds = DB::table('newemployeedata')->whereIn('staffid', $ids)->pluck('staffid')->toArray();
        $restoredCount = 0;
        $skipped = [];

        try {
            DB::transaction(function () use ($archivedRecords, $existingActiveIds, &$restoredCount, &$skipped) {
                DB::statement("SET SESSION sql_mode = ''");
                foreach ($archivedRecords as $archived) {
                    if (in_array($archived->staffid, $existingActiveIds)) {
                        $skipped[] = $archived->staffid;
                        continue;
                    }

                    $data = (array) $archived;
                    unset($data['archived_at']);

                    DB::table('newemployeedata')->insert($data);
                    DB::table('newemployeedataarchieve')->where('staffid', $archived->staffid)->delete();
                    $restoredCount++;
                }
                DB::statement("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            });

            $msg = "Successfully restored {$restoredCount} employee(s) to active records.";
            if (!empty($skipped)) {
                $msg .= " " . count($skipped) . " record(s) were skipped because their Staff ID already exists in active employees: " . implode(', ', $skipped);
            }

            return redirect()->back()->with('success', $msg);
        } catch (\Exception $e) {
            Log::error('Bulk restore failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to bulk restore employees: ' . $e->getMessage());
        }
    }

    /**
     * Delete an archived record permanently.
     */
    public function destroy(Request $request, $staffid)
    {
        $archived = DB::table('newemployeedataarchieve')->where('staffid', $staffid)->first();
        if (!$archived) {
            return redirect()->back()->with('error', "Archived record '{$staffid}' not found.");
        }

        $name = $archived->employeename;
        DB::table('newemployeedataarchieve')->where('staffid', $staffid)->delete();

        return redirect()->back()->with('success', "Archived record for '{$name}' ({$staffid}) permanently deleted.");
    }

    /**
     * Bulk delete archived records.
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string'],
        ]);

        $ids = $request->input('ids');
        $deleted = DB::table('newemployeedataarchieve')->whereIn('staffid', $ids)->delete();

        return redirect()->back()->with('success', "Successfully deleted {$deleted} archived record(s).");
    }

    /**
     * Export archived records to Excel.
     */
    public function export(Request $request)
    {
        $query = DB::table('newemployeedataarchieve');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('staffid', 'like', "%{$search}%")
                  ->orWhere('employeename', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%")
                  ->orWhere('shop', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%")
                  ->orWhere('ssnit', 'like', "%{$search}%")
                  ->orWhere('ghcard', 'like', "%{$search}%")
                  ->orWhere('bank', 'like', "%{$search}%")
                  ->orWhere('account', 'like', "%{$search}%")
                  ->orWhere('phone1', 'like', "%{$search}%")
                  ->orWhere('phone2', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($companyFilter = $request->input('company')) {
            $query->where('company', $companyFilter);
        }

        $sort = $request->input('sort', 'employeename');
        $order = strtolower($request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = [
            'staffid', 'employeename', 'company', 'shop', 'position', 
            'startdate', 'dateofbirth', 'ssnit', 'ghcard', 'bank', 
            'account', 'branch', 'phone1', 'gender', 'archived_at'
        ];
        $sortColumn = in_array($sort, $allowedSorts) ? $sort : 'employeename';
        $query->orderBy($sortColumn, $order);

        $employees = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Archived Employees');

        $headers = [
            'A1' => 'Staff ID',
            'B1' => 'Employee Name',
            'C1' => 'Company',
            'D1' => 'Shop',
            'E1' => 'Position',
            'F1' => 'Start Date',
            'G1' => 'Date of Birth',
            'H1' => 'SSNIT',
            'I1' => 'Ghana Card',
            'J1' => 'Bank',
            'K1' => 'Account Number',
            'L1' => 'Branch',
            'M1' => 'MoMo Number',
            'N1' => 'MoMo Name',
            'O1' => 'Phone 1',
            'P1' => 'Phone 2',
            'Q1' => 'Gender',
            'R1' => 'Nationality',
            'S1' => 'Email',
            'T1' => 'Next of Kin',
            'U1' => 'Relation',
            'V1' => 'Kin Contact',
            'W1' => 'Guarantor',
            'X1' => 'Guarantor Contact',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
        ];
        $sheet->getStyle('A1:X1')->applyFromArray($headerStyle);

        $row = 2;
        foreach ($employees as $emp) {
            $sheet->setCellValue("A{$row}", $emp->staffid);
            $sheet->setCellValue("B{$row}", $emp->employeename);
            $sheet->setCellValue("C{$row}", $emp->company);
            $sheet->setCellValue("D{$row}", $emp->shop ?? '');
            $sheet->setCellValue("E{$row}", $emp->position ?? '');
            $sheet->setCellValue("F{$row}", $emp->startdate ?? '');
            $sheet->setCellValue("G{$row}", $emp->dateofbirth ?? '');
            $sheet->setCellValue("H{$row}", $emp->ssnit ?? '');
            $sheet->setCellValue("I{$row}", $emp->ghcard ?? '');
            $sheet->setCellValue("J{$row}", $emp->bank ?? '');
            $sheet->setCellValueExplicit("K{$row}", $emp->account ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("L{$row}", $emp->branch ?? '');
            $sheet->setCellValueExplicit("M{$row}", $emp->momonumber ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("N{$row}", $emp->momoname ?? ($emp->momaname ?? ''));
            $sheet->setCellValueExplicit("O{$row}", $emp->phone1 ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("P{$row}", $emp->phone2 ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("Q{$row}", $emp->gender ?? '');
            $sheet->setCellValue("R{$row}", $emp->nationality ?? '');
            $sheet->setCellValue("S{$row}", $emp->email ?? '');
            $sheet->setCellValue("T{$row}", $emp->nextofkin ?? '');
            $sheet->setCellValue("U{$row}", $emp->relation ?? '');
            $sheet->setCellValueExplicit("V{$row}", $emp->contact ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("W{$row}", $emp->guarantor ?? '');
            $sheet->setCellValueExplicit("X{$row}", $emp->contactgua ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $row++;
        }

        foreach (range('A', 'X') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'archived_employees_' . date('Y_m_d_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
