<?php
namespace App\Http\Controllers;

use App\Models\BankDetail;
use App\Models\BankCode;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class BankDetailInhouseController extends Controller
{
    public function index(Request $request)
    {
        BankDetailController::autoCleanDatabase();
        $includedShops = [
            'SBP AFRICA',
            'SBP AFRICA LTD',
            'SBP AFRICA LTD.',
            'SBP SOLUTIONS',
            'SBP SOLUTIONS LTD',
            'SBP SOLUTIONS LTD.'
        ];

        $query = BankDetail::query()->whereIn('shop', $includedShops);
        $totalRecords = BankDetail::whereIn('shop', $includedShops)->count();
        $searchCount = null;

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('employid', 'like', "%{$search}%")
                  ->orWhere('shop', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('bank', 'like', "%{$search}%")
                  ->orWhere('momoname', 'like', "%{$search}%");
            });
            $searchCount = $query->count();
        }

        $sort = $request->input('sort', 'employid');
        $order = $request->input('order', 'asc');
        $query->orderBy($sort, $order);
        $bankDetails = $query->paginate(10)->withQueryString();

        $bankCodes = BankCode::select('id', 'bankname', 'branchcodes', 'branch')->get();

        return Inertia::render('BankDetailInhouse/Index', [
            'bankDetails' => $bankDetails,
            'bankCodes' => $bankCodes,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'order' => $order,
            ],
            'totalRecords' => $totalRecords,
            'searchCount' => $searchCount,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $bankCodes = BankCode::pluck('bankname')->toArray();
        $includedShops = [
            'SBP AFRICA',
            'SBP AFRICA LTD',
            'SBP AFRICA LTD.',
            'SBP SOLUTIONS',
            'SBP SOLUTIONS LTD',
            'SBP SOLUTIONS LTD.'
        ];

        $rules = [
            'employid' => [
                'required',
                'string',
                'max:20',
                'unique:bankdetails,employid,NULL,employid',
                'no_french_accents',
            ],
            'shop' => ['required', 'string', 'max:20', Rule::in($includedShops), 'no_french_accents'],
            'name' => ['required', 'string', 'max:50', 'no_french_accents'],
            'bank' => ['nullable', 'string', 'max:50', Rule::in($bankCodes), 'no_french_accents'],
            'account' => ['nullable', 'string', 'max:50', 'no_french_accents'],
            'code' => ['nullable', 'string', 'max:155', 'no_french_accents'],
            'branch' => ['nullable', 'string', 'max:50', 'no_french_accents'],
            'momonum' => ['nullable', 'string', 'max:255', 'no_french_accents'],
            'momoname' => ['nullable', 'string', 'max:255', 'no_french_accents'],
        ];

        // Conditional validation
        if ($request->filled('bank')) {
            $rules['account'] = ['required', 'string', 'max:50', 'no_french_accents'];
            $rules['code'] = ['required', 'string', 'max:155', 'no_french_accents'];
            $rules['branch'] = ['required', 'string', 'max:50', 'no_french_accents'];
        }

        if ($request->filled('momonum')) {
            $rules['momoname'] = ['required', 'string', 'max:255', 'no_french_accents'];
        }

        if ($request->filled('momoname')) {
            $rules['momonum'] = ['required', 'string', 'max:255', 'no_french_accents'];
        }

        $messages = [
            'employid.unique' => 'The Employee ID already exists in the database.',
            'shop.in' => 'The selected shop must be one of: ' . implode(', ', $includedShops),
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        // Validate bank, code, and branch against bankcodes
        if ($request->filled('bank')) {
            $bankCode = BankCode::where('bankname', $request->bank)
                ->where('branchcodes', $request->code)
                ->first();
            if (!$bankCode) {
                $validator->errors()->add('bank', 'Invalid bank or sort code.');
            } elseif ($request->branch && $bankCode->branch && $request->branch !== $bankCode->branch) {
                $validator->errors()->add('branch', 'Branch does not match bank code.');
            }
        }

        if ($validator->fails()) {
            Log::error('Store validation failed', ['errors' => $validator->errors()]);
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to create bank detail inhouse record: ' . implode(' | ', $validator->errors()->all()));
        }

        BankDetail::create($request->all());
        return redirect()->route('bankdetailsinhouse.index')->with('success', 'Bank detail inhouse record created successfully.');
    }

    public function update(Request $request, $employid = null)
    {
        BankDetailController::autoCleanDatabase();

        $rawTargetId = $request->input('original_employid', $employid ?: $request->input('employid', ''));
        $targetId = trim((string)$rawTargetId);
        $cleanTarget = trim($targetId, " \t\n\r\0\x0B'\"");

        if (!$targetId && !$cleanTarget) {
            return redirect()->back()->with('error', 'Update failed: Employee ID is missing.');
        }

        $bankDetail = BankDetail::where('employid', $targetId)
            ->orWhere('employid', $cleanTarget)
            ->orWhere('employid', "'" . $cleanTarget)
            ->orWhereRaw('TRIM(employid) = ?', [$targetId])
            ->orWhereRaw('TRIM(employid) = ?', [$cleanTarget])
            ->orWhereRaw("TRIM(BOTH '\'' FROM employid) = ?", [$cleanTarget])
            ->first();

        if (!$bankDetail && $request->filled('name') && $request->filled('shop')) {
            $bankDetail = BankDetail::where('name', trim($request->input('name')))
                ->where('shop', trim($request->input('shop')))
                ->first();
        }

        if (!$bankDetail) {
            return redirect()->back()->with('error', "Update failed: Record for ID '{$targetId}' could not be found.");
        }

        $bankCodes = BankCode::pluck('bankname')->toArray();
        $includedShops = [
            'SBP AFRICA',
            'SBP AFRICA LTD',
            'SBP AFRICA LTD.',
            'SBP SOLUTIONS',
            'SBP SOLUTIONS LTD',
            'SBP SOLUTIONS LTD.'
        ];

        $trimmedInputId = trim($request->input('employid', ''));
        $trimmedCurrentId = trim($bankDetail->employid);

        $employidRules = ['required', 'string', 'max:20', 'no_french_accents'];
        if (strtoupper($trimmedInputId) !== strtoupper($trimmedCurrentId)) {
            $employidRules[] = Rule::unique('bankdetails', 'employid')->where(function ($query) use ($bankDetail) {
                return $query->where('employid', '!=', $bankDetail->employid)
                             ->whereRaw('TRIM(employid) != ?', [trim($bankDetail->employid)]);
            });
        }

        $request->merge([
            'employid' => $trimmedInputId,
            'shop'     => trim($request->input('shop', '')),
            'name'     => trim($request->input('name', '')),
            'bank'     => $request->filled('bank') ? trim($request->input('bank')) : null,
            'account'  => $request->filled('account') ? trim($request->input('account')) : null,
            'branch'   => $request->filled('branch') ? trim($request->input('branch')) : null,
            'momonum'  => $request->filled('momonum') ? trim($request->input('momonum')) : null,
            'momoname' => $request->filled('momoname') ? trim($request->input('momoname')) : null,
        ]);

        $rules = [
            'employid' => $employidRules,
            'shop' => ['required', 'string', 'max:50', Rule::in($includedShops), 'no_french_accents'],
            'name' => ['required', 'string', 'max:50', 'no_french_accents'],
            'bank' => ['nullable', 'string', 'max:50', Rule::in($bankCodes), 'no_french_accents'],
            'account' => ['nullable', 'string', 'max:50', 'no_french_accents'],
            'code' => ['nullable', 'string', 'max:155', 'no_french_accents'],
            'branch' => ['nullable', 'string', 'max:50', 'no_french_accents'],
            'momonum' => ['nullable', 'string', 'max:255', 'no_french_accents'],
            'momoname' => ['nullable', 'string', 'max:255', 'no_french_accents'],
        ];

        // Conditional validation
        if ($request->filled('bank')) {
            $rules['account'] = ['required', 'string', 'max:50', 'no_french_accents'];
            $rules['code'] = ['required', 'string', 'max:155', 'no_french_accents'];
            $rules['branch'] = ['required', 'string', 'max:50', 'no_french_accents'];
        }

        if ($request->filled('momonum')) {
            $rules['momoname'] = ['required', 'string', 'max:255', 'no_french_accents'];
        }

        if ($request->filled('momoname')) {
            $rules['momonum'] = ['required', 'string', 'max:255', 'no_french_accents'];
        }

        $messages = [
            'employid.unique' => 'The Employee ID already exists in the database.',
            'shop.in' => 'The selected shop must be one of: ' . implode(', ', $includedShops),
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        // Validate bank, code, and branch against bankcodes
        if ($request->filled('bank')) {
            $bankCode = BankCode::where('bankname', $request->bank)
                ->where('branchcodes', $request->code)
                ->first();
            if (!$bankCode) {
                $validator->errors()->add('bank', 'Invalid bank or sort code.');
            } elseif ($request->branch && $bankCode->branch && $request->branch !== $bankCode->branch) {
                $validator->errors()->add('branch', 'Branch does not match bank code.');
            }
        }

        if ($validator->fails()) {
            Log::error('Update validation failed', ['errors' => $validator->errors(), 'input' => $request->all()]);
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to update bank detail inhouse record: ' . implode(' | ', $validator->errors()->all()));
        }

        $bankDetail->update($request->all());
        return redirect()->route('bankdetailsinhouse.index')->with('success', 'Bank detail inhouse record updated successfully.');
    }

    public function destroy(Request $request, $employid = null)
    {
        BankDetailController::autoCleanDatabase();

        $rawTargetId = $request->input('employid', $employid ?: '');
        $targetId = trim((string)$rawTargetId);
        $cleanTarget = trim($targetId, " \t\n\r\0\x0B'\"");

        if (!$targetId && !$cleanTarget) {
            return redirect()->route('bankdetailsinhouse.index')->with('error', 'Delete failed: Employee ID is missing.');
        }

        $deleted = BankDetail::where('employid', $targetId)
            ->orWhere('employid', $cleanTarget)
            ->orWhere('employid', "'" . $cleanTarget)
            ->orWhereRaw('TRIM(employid) = ?', [$targetId])
            ->orWhereRaw('TRIM(employid) = ?', [$cleanTarget])
            ->orWhereRaw("TRIM(BOTH '\'' FROM employid) = ?", [$cleanTarget])
            ->delete();

        if (!$deleted && $request->filled('name') && $request->filled('shop')) {
            BankDetail::where('name', trim($request->input('name')))
                ->where('shop', trim($request->input('shop')))
                ->delete();
        }

        return redirect()->route('bankdetailsinhouse.index')->with('success', 'Bank detail inhouse record deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'employids' => ['required', 'array', 'min:1'],
            'employids.*' => ['required', 'string'],
        ]);

        $employids = array_map('trim', $request->input('employids', []));
        Log::info('Bulk destroy called', ['employids' => $employids]);

        $deletedCount = BankDetail::whereIn('employid', $employids)
            ->orWhereIn(DB::raw('TRIM(employid)'), $employids)
            ->delete();

        if ($deletedCount > 0) {
            return redirect()->route('bankdetailsinhouse.index')->with('success', "Successfully deleted $deletedCount bank detail inhouse record(s).");
        }

        return redirect()->route('bankdetailsinhouse.index')->with('error', 'No bank detail inhouse records were deleted.');
    }

    public function export(Request $request)
    {
        $includedShops = [
            'SBP AFRICA',
            'SBP AFRICA LTD',
            'SBP AFRICA LTD.',
            'SBP SOLUTIONS',
            'SBP SOLUTIONS LTD',
            'SBP SOLUTIONS LTD.'
        ];

        $search = $request->input('search');
        $sort = $request->input('sort', 'employid');
        $order = $request->input('order', 'asc');

        $query = BankDetail::query()->whereIn('shop', $includedShops);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('employid', 'like', "%{$search}%")
                  ->orWhere('shop', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('bank', 'like', "%{$search}%")
                  ->orWhere('momoname', 'like', "%{$search}%");
            });
        }
        $query->orderBy($sort, $order);
        $bankDetails = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Employee ID');
        $sheet->setCellValue('B1', 'Shop');
        $sheet->setCellValue('C1', 'Name');
        $sheet->setCellValue('D1', 'Bank');
        $sheet->setCellValue('E1', 'Account');
        $sheet->setCellValue('F1', 'Sort Code');
        $sheet->setCellValue('G1', 'Branch');
        $sheet->setCellValue('H1', 'Mobile Money Number');
        $sheet->setCellValue('I1', 'Mobile Money Name');

        $row = 2;
        foreach ($bankDetails as $bankDetail) {
            $sheet->setCellValue('A' . $row, $bankDetail->employid);
            $sheet->setCellValue('B' . $row, $bankDetail->shop ?? 'N/A');
            $sheet->setCellValue('C' . $row, $bankDetail->name ?? 'N/A');
            $sheet->setCellValue('D' . $row, $bankDetail->bank ?? 'N/A');
            $sheet->setCellValue('E' . $row, $bankDetail->account ?? 'N/A');
            $sheet->setCellValue('F' . $row, $bankDetail->code ?? 'N/A');
            $sheet->setCellValue('G' . $row, $bankDetail->branch ?? 'N/A');
            $sheet->setCellValue('H' . $row, $bankDetail->momonum ?? 'N/A');
            $sheet->setCellValue('I' . $row, $bankDetail->momoname ?? 'N/A');
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'bank_details_inhouse.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Employee ID');
        $sheet->setCellValue('B1', 'Shop');
        $sheet->setCellValue('C1', 'Name');
        $sheet->setCellValue('D1', 'Bank');
        $sheet->setCellValue('E1', 'Account');
        $sheet->setCellValue('F1', 'Sort Code');
        $sheet->setCellValue('G1', 'Branch');
        $sheet->setCellValue('H1', 'Mobile Money Number');
        $sheet->setCellValue('I1', 'Mobile Money Name');

        // Add example rows
        $sheet->setCellValue('A2', 'EMP123');
        $sheet->setCellValue('B2', 'SBP AFRICA');
        $sheet->setCellValue('C2', 'John Doe');
        $sheet->setCellValue('D2', 'UBA');
        $sheet->setCellValue('E2', '1234567890');
        $sheet->setCellValue('F2', '500069');
        $sheet->setCellValue('G2', 'ACHIMOTA NO2');
        $sheet->setCellValue('H2', '');
        $sheet->setCellValue('I2', '');
        $sheet->setCellValue('A3', 'EMP124');
        $sheet->setCellValue('B3', 'SBP SOLUTIONS');
        $sheet->setCellValue('C3', 'Jane Doe');
        $sheet->setCellValue('D3', '');
        $sheet->setCellValue('E3', '');
        $sheet->setCellValue('F3', '');
        $sheet->setCellValue('G3', '');
        $sheet->setCellValue('H3', '0987654321');
        $sheet->setCellValue('I3', 'Jane Doe');

        $writer = new Xlsx($spreadsheet);
        $fileName = 'bank_details_inhouse_template.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:2048'],
        ]);

        try {
            $file = $request->file('file');
            $reader = new XlsxReader();
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            array_shift($rows); // Remove header row

            $bankCodes = BankCode::select('bankname', 'branchcodes', 'branch')->get();
            $bankNames = $bankCodes->pluck('bankname')->unique()->toArray();
            $includedShops = [
                'SBP AFRICA',
                'SBP AFRICA LTD',
                'SBP AFRICA LTD.',
                'SBP SOLUTIONS',
                'SBP SOLUTIONS LTD',
                'SBP SOLUTIONS LTD.'
            ];
            $existingEmployIds = BankDetail::pluck('employid')->toArray();
            $errors = [];
            $validRecords = [];

            foreach ($rows as $index => $row) {
                $data = [
                    'employid' => $row[0] ?? '',
                    'shop' => $row[1] ?? '',
                    'name' => $row[2] ?? '',
                    'bank' => !empty($row[3]) ? $row[3] : null,
                    'account' => !empty($row[4]) ? $row[4] : null,
                    'code' => !empty($row[5]) ? $row[5] : null,
                    'branch' => !empty($row[6]) ? $row[6] : null,
                    'momonum' => !empty($row[7]) ? $row[7] : null,
                    'momoname' => !empty($row[8]) ? $row[8] : null,
                ];

                // Check for French accented characters
                foreach ($data as $field => $value) {
                    if ($value && preg_match('/[ÂÊÎÔÛâêîôû]/u', $value)) {
                        $errors[] = "Row " . ($index + 2) . ": French accented characters (Â, Ê, Î, Ô, Û, â, ê, î, ô, û) are not allowed in $field.";
                        continue 2; // Skip to next row
                    }
                }

                $rules = [
                    'employid' => [
                        'required',
                        'string',
                        'max:20',
                        Rule::notIn($existingEmployIds),
                        'no_french_accents',
                    ],
                    'shop' => ['required', 'string', 'max:20', Rule::in($includedShops), 'no_french_accents'],
                    'name' => ['required', 'string', 'max:50', 'no_french_accents'],
                    'bank' => ['nullable', 'string', 'max:50', Rule::in($bankNames), 'no_french_accents'],
                    'account' => ['nullable', 'string', 'max:50', 'no_french_accents'],
                    'code' => ['nullable', 'string', 'max:155', 'no_french_accents'],
                    'branch' => ['nullable', 'string', 'max:50', 'no_french_accents'],
                    'momonum' => ['nullable', 'string', 'max:255', 'no_french_accents'],
                    'momoname' => ['nullable', 'string', 'max:255', 'no_french_accents'],
                ];

                // Conditional validation
                if (!empty($data['bank'])) {
                    $rules['account'] = ['required', 'string', 'max:50', 'no_french_accents'];
                    $rules['code'] = ['required', 'string', 'max:155', 'no_french_accents'];
                    $rules['branch'] = ['required', 'string', 'max:50', 'no_french_accents'];
                }

                if (!empty($data['momonum'])) {
                    $rules['momoname'] = ['required', 'string', 'max:255', 'no_french_accents'];
                }

                if (!empty($data['momoname'])) {
                    $rules['momonum'] = ['required', 'string', 'max:255', 'no_french_accents'];
                }

                $messages = [
                    'employid.not_in' => "Row " . ($index + 2) . ": The Employee ID already exists in the database.",
                    'account.not_in' => "Row " . ($index + 2) . ": The Account Number already exists in the database.",
                    'momonum.not_in' => "Row " . ($index + 2) . ": The Mobile Money Number already exists in the database.",
                    'shop.in' => "Row " . ($index + 2) . ": The selected shop must be one of: " . implode(', ', $includedShops),
                ];

                $validator = Validator::make($data, $rules, $messages);

                // Validate bank, code, and branch against bankcodes
                if (!empty($data['bank'])) {
                    $bankCode = $bankCodes->where('bankname', $data['bank'])
                        ->where('branchcodes', $data['code'])
                        ->first();
                    if (!$bankCode) {
                        $errors[] = "Row " . ($index + 2) . ": Invalid bank or sort code.";
                        continue;
                    }
                    if ($data['branch'] && $bankCode->branch && $data['branch'] !== $bankCode->branch) {
                        $errors[] = "Row " . ($index + 2) . ": Branch does not match bank code.";
                        continue;
                    }
                }

                if ($validator->fails()) {
                    $errors = array_merge($errors, array_map(function ($error) use ($index) {
                        return "Row " . ($index + 2) . ": " . $error;
                    }, $validator->errors()->all()));
                    continue;
                }

                $validRecords[] = $data;
                $existingEmployIds[] = $data['employid'];
            }

            if (!empty($errors)) {
                Log::error('Bulk upload validation failed', ['errors' => $errors]);
                return redirect()->route('bankdetailsinhouse.index')->with('error', implode(' | ', $errors));
            }

            foreach ($validRecords as $record) {
                BankDetail::create($record);
            }

            return redirect()->route('bankdetailsinhouse.index')->with('success', 'Successfully uploaded ' . count($validRecords) . ' bank detail inhouse record(s).');
        } catch (\Exception $e) {
            Log::error('Bulk upload failed', ['error' => $e->getMessage()]);
            return redirect()->route('bankdetailsinhouse.index')->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }
}
