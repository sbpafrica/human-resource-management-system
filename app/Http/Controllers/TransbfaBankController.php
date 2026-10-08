<?php

namespace App\Http\Controllers;

use App\Models\TransbfaBank;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Illuminate\Support\Facades\Response;

class TransbfaBankController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $sort   = $request->input('sort', 'staffida');
        $order  = $request->input('order', 'asc');

        $totalRecords = TransbfaBank::count();

        $query = TransbfaBank::query()
            ->select(['staffida', 'employname', 'bankname', 'accountnum']);

        $searchCount = null;

        if ($search) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('staffida',   'like', "%{$search}%")
                  ->orWhere('employname', 'like', "%{$search}%")
                  ->orWhere('bankname',   'like', "%{$search}%")
                  ->orWhere('accountnum', 'like', "%{$search}%");
            });
            $searchCount = $query->count();
        }

        $allowedSorts = ['staffida', 'employname', 'bankname', 'accountnum'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'staffida';
        }

        $query->orderBy($sort, $order === 'desc' ? 'desc' : 'asc');

        $banks = $query->paginate(15)->withQueryString();

        return Inertia::render('TranssionBfa/Bank/Index', [
            'banks'        => $banks,
            'filters'      => compact('search', 'sort', 'order'),
            'totalRecords' => $totalRecords,
            'searchCount'  => $searchCount,
            'flash'        => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'staffida'   => ['required', 'string', 'max:255', Rule::unique('bfabank', 'staffida')],
            'employname' => ['required', 'string', 'max:255'],
            'bankname'   => ['required', 'string', 'max:255'],
            'accountnum' => ['required', 'string', 'max:50'],
        ]);

        TransbfaBank::create($request->only(['staffida', 'employname', 'bankname', 'accountnum']));

        return redirect()->back()->with('success', 'Bank detail created successfully.');
    }

    public function update(Request $request, $staffida)
    {
        $bank = TransbfaBank::findOrFail($staffida);

        $request->validate([
            'staffida'   => ['required', 'string', 'max:255', Rule::unique('bfabank', 'staffida')->ignore($staffida, 'staffida')],
            'employname' => ['required', 'string', 'max:255'],
            'bankname'   => ['required', 'string', 'max:255'],
            'accountnum' => ['required', 'string', 'max:50'],
        ]);

        $bank->update($request->only(['staffida', 'employname', 'bankname', 'accountnum']));

        return redirect()->back()->with('success', 'Bank detail updated successfully.');
    }

    public function destroy($staffida)
    {
        $bank = TransbfaBank::findOrFail($staffida);
        $bank->delete();

        return redirect()->back()->with('success', 'Bank detail deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $staffids = collect($request->input('staffids', []))
            ->map(fn($id) => trim($id))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($staffids)) {
            return back()->with('error', 'No staff IDs selected.');
        }

        $deletedCount = TransbfaBank::whereIn('staffida', $staffids)->delete();

        return back()->with('success', "Deleted {$deletedCount} record(s).");
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        try {
            $file   = $request->file('file');
            $reader = new XlsxReader();
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet  = $spreadsheet->getActiveSheet();
            $rows   = $sheet->toArray();
            array_shift($rows); // remove header

            $existingStaffIds   = TransbfaBank::pluck('staffida')->toArray();
            $existingAccountNums = TransbfaBank::pluck('accountnum')->toArray();

            $errors       = [];
            $validRecords = [];
            $batchStaffIds    = [];
            $batchAccountNums = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                $staffida   = trim($row[0] ?? '');
                $employname = trim($row[1] ?? '');
                $bankname   = trim($row[2] ?? '');
                $accountnum = trim($row[3] ?? '');

                // Skip empty rows
                if ($staffida === '' && $employname === '' && $bankname === '' && $accountnum === '') {
                    continue;
                }

                if ($staffida === '') {
                    $errors[] = "Row {$rowNumber}: Staff ID is required.";
                    continue;
                }
                if ($employname === '') {
                    $errors[] = "Row {$rowNumber}: Employee Name is required.";
                    continue;
                }
                if ($bankname === '') {
                    $errors[] = "Row {$rowNumber}: Bank Name is required.";
                    continue;
                }
                if ($accountnum === '') {
                    $errors[] = "Row {$rowNumber}: Account Number is required.";
                    continue;
                }

                if (in_array($staffida, $existingStaffIds) || in_array($staffida, $batchStaffIds)) {
                    $errors[] = "Row {$rowNumber}: Staff ID '{$staffida}' already exists.";
                    continue;
                }
                if (in_array($accountnum, $existingAccountNums) || in_array($accountnum, $batchAccountNums)) {
                    $errors[] = "Row {$rowNumber}: Account Number '{$accountnum}' already exists.";
                    continue;
                }

                $validRecords[]     = compact('staffida', 'employname', 'bankname', 'accountnum');
                $batchStaffIds[]    = $staffida;
                $batchAccountNums[] = $accountnum;
            }

            if (!empty($errors)) {
                return redirect()->back()
                    ->with('error', 'Bulk upload failed: ' . implode(' | ', array_slice($errors, 0, 10)));
            }

            foreach ($validRecords as $record) {
                TransbfaBank::create($record);
            }

            return redirect()->back()
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');

        } catch (\Exception $e) {
            Log::error('BFA Bank bulk upload failed', ['error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Failed to process file: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Staff ID');
        $sheet->setCellValue('B1', 'Employee Name');
        $sheet->setCellValue('C1', 'Bank Name');
        $sheet->setCellValue('D1', 'Account Number');

        // Format Account Number column as TEXT
        $sheet->getStyle('D:D')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        // Example row
        $sheet->setCellValue('A2', 'BFA001');
        $sheet->setCellValue('B2', 'Jean Dupont');
        $sheet->setCellValue('C2', 'CORIS BANK');
        $sheet->setCellValueExplicit('D2', '10234567890', DataType::TYPE_STRING);

        $writer   = new Xlsx($spreadsheet);
        $fileName = 'bfa_bank_template.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    public function export()
    {
        $records = TransbfaBank::orderBy('staffida')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Staff ID');
        $sheet->setCellValue('B1', 'Employee Name');
        $sheet->setCellValue('C1', 'Bank Name');
        $sheet->setCellValue('D1', 'Account Number');

        // Format Account Number column as TEXT (prevent scientific notation)
        $sheet->getStyle('D:D')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        $row = 2;
        foreach ($records as $record) {
            $sheet->setCellValue('A' . $row, $record->staffida ?? '');
            $sheet->setCellValue('B' . $row, $record->employname ?? '');
            $sheet->setCellValue('C' . $row, $record->bankname ?? '');
            $sheet->setCellValueExplicit('D' . $row, $record->accountnum ?? '', DataType::TYPE_STRING);
            $row++;
        }

        $writer   = new Xlsx($spreadsheet);
        $fileName = 'bfa_bank_details.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);
    }
}
