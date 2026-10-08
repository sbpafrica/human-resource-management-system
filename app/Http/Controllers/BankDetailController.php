<?php

namespace App\Http\Controllers;

use App\Models\BankDetail;
use App\Models\BankCode;
use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;

class BankDetailController extends Controller
{
    public static function autoCleanDatabase()
    {
        try {
            Cache::remember('bankdetails_auto_cleaned', 600, function () {
                DB::statement("
                    DELETE b1 FROM bankdetails b1
                    INNER JOIN bankdetails b2
                      ON  TRIM(b1.employid) = TRIM(b2.employid)
                      AND b1.employid != TRIM(b1.employid)
                      AND b2.employid  = TRIM(b2.employid)
                ");

                DB::statement("
                    UPDATE bankdetails SET
                      employid = TRIM(employid),
                      name     = TRIM(name),
                      shop     = TRIM(shop),
                      bank     = TRIM(bank),
                      account  = TRIM(account),
                      code     = TRIM(code),
                      branch   = TRIM(branch),
                      momonum  = TRIM(momonum),
                      momoname = TRIM(momoname)
                ");
                return true;
            });
        } catch (\Throwable $e) {
            Log::warning('Auto-clean bankdetails error: ' . $e->getMessage());
        }
    }

    public function index(Request $request)
    {
        self::autoCleanDatabase();

        $excludedShops = [];

        $totalRecords = Cache::remember('bankdetails_total_count', 3600, function () use ($excludedShops) {
            return BankDetail::whereNotIn('shop', $excludedShops)->count();
        });

        $search = $request->input('search');
        $sort = $request->input('sort', 'employid');
        $order = $request->input('order', 'asc');

        $query = BankDetail::query()
            ->select(['employid', 'shop', 'name', 'bank', 'account', 'code', 'branch', 'momonum', 'momoname'])
            ->whereNotIn('shop', $excludedShops);

        $searchCount = null;

        if ($search) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('employid',  'like', "%{$search}%")
                  ->orWhere('shop',     'like', "%{$search}%")
                  ->orWhere('name',     'like', "%{$search}%")
                  ->orWhere('bank',     'like', "%{$search}%")
                  ->orWhere('account',  'like', "%{$search}%")
                  ->orWhere('code',     'like', "%{$search}%")
                  ->orWhere('branch',   'like', "%{$search}%")
                  ->orWhere('momonum',  'like', "%{$search}%")
                  ->orWhere('momoname', 'like', "%{$search}%");
            });

            $searchCount = Cache::remember("bankdetails_search_count_{$search}", 300, fn() => $query->count());
        }

        $query->orderBy($sort, $order);
        $bankDetails = $query->paginate(10)->withQueryString();


        $bankCodes = Cache::remember('bank_codes_all', 3600, function () {
    return BankCode::select('id', 'bankname', 'branchcodes', 'branch')
        ->get()
        ->map(function ($bc) {
            $bc->bankname = trim(strtoupper($bc->bankname));
            $bc->branch = $bc->branch ? trim($bc->branch) : null;
            $bc->branchcodes = trim($bc->branchcodes);
            return $bc;
        });
});

        // NEW: Get active clients for Shop dropdown
        $clients = Cache::remember('active_clients_for_bankdetails', 3600, fn() =>
            Client::where('is_active', true)
                  ->orderBy('display_order')
                  ->orderBy('client_name')
                  ->pluck('client_name')
                  ->toArray()
        );



        return Inertia::render('BankDetail/Index', [
            'bankDetails' => $bankDetails,
            'bankCodes' => $bankCodes,
            'clients' => $clients, // ← NEW
            'filters' => compact('search', 'sort', 'order'),
            'totalRecords' => $totalRecords,
            'searchCount' => $searchCount,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

  public function store(Request $request)
  {
    $clients = Cache::get('active_clients_for_bankdetails', fn() => Client::where('is_active', true)->pluck('client_name')->toArray());

    $trimmedInputId = trim($request->input('employid', ''));
    $isMomo = strtoupper(trim($request->input('bank', ''))) === 'MOMO';

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

    if ($isMomo) {
        $request->merge(['code' => null]);
    } elseif ($request->filled('bank')) {
        $bankCode = BankCode::where('bankname', $request->bank)
            ->where('branchcodes', $request->code)
            ->first();

        if (!$bankCode) {
            $bankCode = BankCode::where('bankname', $request->bank)->first();
        }

        if ($bankCode) {
            $request->merge([
                'code'   => $bankCode->branchcodes,
                'branch' => $bankCode->branch ?: ($request->branch ?: null),
            ]);
        }
    }

    $rules = [
        'employid' => ['required', 'string', 'max:20', Rule::unique('bankdetails', 'employid'), 'no_french_accents'],
        'shop'     => ['required', 'string', 'max:100', 'no_french_accents'],
        'name'     => ['required', 'string', 'max:100', 'no_french_accents'],
        'bank'     => ['nullable', 'string', 'max:100', 'no_french_accents'],
        'account'  => ['nullable', 'string', 'max:50', 'no_french_accents'],
        'code'     => ['nullable', 'string', 'max:20', 'no_french_accents'],
        'branch'   => ['nullable', 'string', 'max:100', 'no_french_accents'],
        'momonum'  => ['nullable', 'string', 'max:50', 'no_french_accents'],
        'momoname' => ['nullable', 'string', 'max:100', 'no_french_accents'],
    ];

    if ($request->filled('bank')) {
        $rules['account'][] = 'required';
    }

    if ($request->filled('bank') && !$isMomo) {
        $rules['code'][]   = 'required';
        $rules['branch'][] = 'required';
    }

    $validator = Validator::make($request->all(), $rules);

    if ($request->filled('bank') && !$isMomo && !BankCode::where('bankname', $request->bank)->exists()) {
        $validator->errors()->add('bank', 'Selected bank not found in system.');
    }

    if ($validator->fails()) {
        return redirect()->back()
            ->withErrors($validator)
            ->withInput()
            ->with('error', 'Validation failed: ' . implode(' | ', $validator->errors()->all()));
    }

    BankDetail::create($request->only([
        'employid', 'shop', 'name', 'bank', 'account',
        'code', 'branch', 'momonum', 'momoname'
    ]));

    return redirect()->route('bankdetails.index')->with('success', 'Bank detail created successfully.');
  }

    public function update(Request $request, $employid = null)
    {
        self::autoCleanDatabase();

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

    $clients = Cache::get('active_clients_for_bankdetails', fn() => Client::where('is_active', true)->pluck('client_name')->toArray());

    $trimmedInputId = trim($request->input('employid', ''));
    if (!$trimmedInputId) {
        $trimmedInputId = trim($bankDetail->employid);
    }
    $trimmedCurrentId = trim($bankDetail->employid);

    $isMomo = strtoupper(trim($request->input('bank', ''))) === 'MOMO';

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

    if ($isMomo) {
        $request->merge(['code' => null]);
    } elseif ($request->filled('bank')) {
        $bankCode = BankCode::where('bankname', $request->bank)->first();
        if ($bankCode) {
            $request->merge([
                'code'   => $bankCode->branchcodes,
                'branch' => $bankCode->branch ?: ($request->branch ?: null),
            ]);
        }
    }

    $employidRules = ['required', 'string', 'max:20', 'no_french_accents'];
    if (strtoupper($trimmedInputId) !== strtoupper($trimmedCurrentId)) {
        $employidRules[] = Rule::unique('bankdetails', 'employid')->where(function ($query) use ($bankDetail) {
            return $query->where('employid', '!=', $bankDetail->employid)
                         ->whereRaw('TRIM(employid) != ?', [trim($bankDetail->employid)]);
        });
    }

    $rules = [
        'employid' => $employidRules,
        'shop'     => ['required', 'string', 'max:100', 'no_french_accents'],
        'name'     => ['required', 'string', 'max:100', 'no_french_accents'],
        'bank'     => ['nullable', 'string', 'max:100', 'no_french_accents'],
        'account'  => ['nullable', 'string', 'max:50', 'no_french_accents'],
        'code'     => ['nullable', 'string', 'max:20', 'no_french_accents'],
        'branch'   => ['nullable', 'string', 'max:100', 'no_french_accents'],
        'momonum'  => ['nullable', 'string', 'max:50', 'no_french_accents'],
        'momoname' => ['nullable', 'string', 'max:100', 'no_french_accents'],
    ];

    if ($request->filled('bank')) {
        $rules['account'][] = 'required';
    }

    if ($request->filled('bank') && !$isMomo) {
        $rules['code'][]   = 'required';
        $rules['branch'][] = 'required';
    }

    $validator = Validator::make($request->all(), $rules);

    if ($request->filled('bank') && !$isMomo && !BankCode::where('bankname', $request->bank)->exists()) {
        $validator->errors()->add('bank', 'Selected bank not found.');
    }

    if ($validator->fails()) {
        return redirect()->back()
            ->withErrors($validator)
            ->withInput()
            ->with('error', 'Update failed: ' . implode(' | ', $validator->errors()->all()));
    }

    $updateData = $request->only([
        'shop',
        'name',
        'bank',
        'account',
        'code',
        'branch',
        'momonum',
        'momoname',
    ]);

    if (strtoupper($trimmedInputId) !== strtoupper($trimmedCurrentId)) {
        $updateData['employid'] = $trimmedInputId;
    }

    $bankDetail->update($updateData);

    return redirect()->route('bankdetails.index')->with('success', 'Bank detail updated successfully.');
   }

    // FIXED: FULL ACCOUNT & MOMO NUMBERS (NO SCIENTIFIC NOTATION)
    public function export(Request $request)
{
    $search = $request->input('search');
    $sort = $request->input('sort', 'employid');
    $order = $request->input('order', 'asc');

    $query = BankDetail::query();
    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('employid',  'like', "%{$search}%")
              ->orWhere('shop',     'like', "%{$search}%")
              ->orWhere('name',     'like', "%{$search}%")
              ->orWhere('bank',     'like', "%{$search}%")
              ->orWhere('account',  'like', "%{$search}%")
              ->orWhere('code',     'like', "%{$search}%")
              ->orWhere('branch',   'like', "%{$search}%")
              ->orWhere('momonum',  'like', "%{$search}%")
              ->orWhere('momoname', 'like', "%{$search}%");
        });
    }
    $query->orderBy($sort, $order);
    $bankDetails = $query->get();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Headers
    $sheet->setCellValue('A1', 'Employee ID');
    $sheet->setCellValue('B1', 'Shop');
    $sheet->setCellValue('C1', 'Name');
    $sheet->setCellValue('D1', 'Bank');
    $sheet->setCellValue('E1', 'Account');
    $sheet->setCellValue('F1', 'Sort Code');
    $sheet->setCellValue('G1', 'Branch');
    $sheet->setCellValue('H1', 'Mobile Money Number');
    $sheet->setCellValue('I1', 'Mobile Money Name');

    // Format columns as TEXT (prevent scientific notation)
    $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
    $sheet->getStyle('H:H')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

    $row = 2;
    foreach ($bankDetails as $bankDetail) {
        $sheet->setCellValue('A' . $row, $bankDetail->employid ?? '');
        $sheet->setCellValue('B' . $row, $bankDetail->shop ?? 'N/A');
        $sheet->setCellValue('C' . $row, $bankDetail->name ?? 'N/A');
        $sheet->setCellValue('D' . $row, $bankDetail->bank ?? 'N/A');

        // FIXED: Force as STRING, NO visible '
        $account = $bankDetail->account ?? 'N/A';
        $sheet->setCellValueExplicit(
            'E' . $row,
            $account !== 'N/A' ? $account : $account,
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
        );

        $sheet->setCellValue('F' . $row, $bankDetail->code ?? 'N/A');
        $sheet->setCellValue('G' . $row, $bankDetail->branch ?? 'N/A');

        $momonum = $bankDetail->momonum ?? 'N/A';
        $sheet->setCellValueExplicit(
            'H' . $row,
            $momonum !== 'N/A' ? $momonum : $momonum,
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
        );

        $sheet->setCellValue('I' . $row, $bankDetail->momoname ?? 'N/A');
        $row++;
    }

    $writer = new Xlsx($spreadsheet);
    $fileName = 'bank_details.xlsx';
    $tempFile = tempnam(sys_get_temp_dir(), $fileName);
    $writer->save($tempFile);

    return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);
}

    // FIXED: Template with FULL numbers
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

        // FIXED: Format columns
        $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('H:H')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        // Example 1: Bank
        $sheet->setCellValue('A2', 'EMP123');
        $sheet->setCellValue('B2', 'Main Shop');
        $sheet->setCellValue('C2', 'John Doe');
        $sheet->setCellValue('D2', 'FIDELITY');
        $sheet->setCellValue('E2', "'2100910001234"); // 13-digit
        $sheet->setCellValue('F2', '240100');
        $sheet->setCellValue('G2', 'ASHAIMAN');

        // Example 2: Mobile Money
        $sheet->setCellValue('A3', 'EMP124');
        $sheet->setCellValue('B3', 'Branch Shop');
        $sheet->setCellValue('C3', 'Jane Doe');
        $sheet->setCellValue('H3', "'0594726029");
        $sheet->setCellValue('I3', 'Jane Doe');

        $writer = new Xlsx($spreadsheet);
        $fileName = 'bank_details_template.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    public function destroy(Request $request, $employid = null)
    {
        self::autoCleanDatabase();

        $rawTargetId = $request->input('employid', $employid ?: '');
        $targetId = trim((string)$rawTargetId);
        $cleanTarget = trim($targetId, " \t\n\r\0\x0B'\"");

        if (!$targetId && !$cleanTarget) {
            return redirect()->route('bankdetails.index')->with('error', 'Delete failed: Employee ID is missing.');
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

        return redirect()->route('bankdetails.index')->with('success', 'Bank detail record deleted successfully.');
    }

    public function bulkDestroy(Request $request)
{
    \Log::info('Bulk destroy raw input', $request->all());

    $employids = collect($request->input('employids', []))
        ->map(fn($id) => trim($id))
        ->filter()
        ->unique()
        ->values()
        ->toArray();

    if (empty($employids)) {
        return back()->with('error', 'No employee IDs selected.');
    }

    \Log::info('Attempting to delete employids', ['employids' => $employids]);

    // FIXED: Removed invalid ' A'semployid'
    $deletedCount = BankDetail::whereIn('employid', $employids)->delete();

    \Log::info('Deleted count', ['count' => $deletedCount]);

    return back()->with('success', "Deleted $deletedCount record(s).");
}

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        try {
            $file = $request->file('file');
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            array_shift($rows); // remove header row

            $clients = Cache::remember('active_clients_for_bankdetails', 3600, fn() =>
                Client::where('is_active', true)
                      ->orderBy('display_order')
                      ->orderBy('client_name')
                      ->pluck('client_name')
                      ->toArray()
            );

            $bankCodes = BankCode::select('bankname', 'branchcodes', 'branch')->get();
            
            $validBankNamesFromDb = $bankCodes->pluck('bankname')
                ->filter()
                ->map(fn($b) => strtoupper(trim($b)))
                ->unique()
                ->toArray();

            $knownBankAliases = ['CBG', 'GCB', 'GT', 'GTB', 'FAB', 'STANBIC', 'ACCESS', 'CAL', 'FIDELITY', 'ABSA', 'ECOBANK', 'UBA', 'ZENITH', 'PRUDENTIAL', 'FBN', 'NLA', 'BOA', 'BSIC', 'ADB', 'SG-SSB', 'SGB', 'FIRST ATLANTIC'];
            $allValidBankNames = array_unique(array_merge($validBankNamesFromDb, $knownBankAliases));

            $existingEmployIds = BankDetail::pluck('employid')->toArray();

            $errors = [];
            $validRecords = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // for error messages

                $employId = trim((string)($row[0] ?? ''));
                $shop     = trim((string)($row[1] ?? ''));
                $name     = trim((string)($row[2] ?? ''));
                $rawBank  = trim((string)($row[3] ?? ''));
                $account  = trim((string)($row[4] ?? ''));
                $code     = trim((string)($row[5] ?? ''));
                $branch   = trim((string)($row[6] ?? ''));
                $momonum  = trim((string)($row[7] ?? ''));
                $momoname = trim((string)($row[8] ?? ''));

                $data = [
                    'employid' => $employId,
                    'shop'     => $shop,
                    'name'     => $name,
                    'bank'     => $rawBank,
                    'account'  => $account,
                    'code'     => $code,
                    'branch'   => $branch,
                    'momonum'  => $momonum,
                    'momoname' => $momoname,
                ];

                // Skip completely empty rows
                if (empty(array_filter($data))) {
                    continue;
                }

                $upperBank = strtoupper($rawBank);
                $isMomo = in_array($upperBank, ['MOMO', 'MOBILE MONEY', 'MTN', 'VODAFONE', 'TELECEL', 'AIRTELTIGO', 'MTN MOMO'])
                    || !empty($momonum)
                    || !empty($momoname);

                if ($isMomo) {
                    if (in_array($upperBank, ['MOMO', 'MOBILE MONEY', 'MTN', 'VODAFONE', 'TELECEL', 'AIRTELTIGO', 'MTN MOMO'])) {
                        $data['bank'] = 'MOMO';
                    }
                    if (!empty($account) && empty($data['momonum'])) {
                        $data['momonum'] = $account;
                    }
                    if (empty($data['momoname']) && !empty($branch)) {
                        $data['momoname'] = $branch;
                    }
                }

                // French accent check
                foreach ($data as $field => $value) {
                    if ($value && preg_match('/[ÂÊÎÔÛâêîôû]/u', $value)) {
                        $errors[] = "Row $rowNumber: French accents not allowed in $field.";
                        continue 2;
                    }
                }

                $rules = [
                    'employid' => ['required', 'string', 'max:20', 'no_french_accents'],
                    'shop'     => ['required', 'string', 'max:100', 'no_french_accents'],
                    'name'     => ['required', 'string', 'max:100', 'no_french_accents'],
                    'bank'     => ['nullable', 'string', 'max:100', 'no_french_accents'],
                    'account'  => ['nullable', 'string', 'max:50', 'no_french_accents'],
                    'code'     => ['nullable', 'string', 'max:20', 'no_french_accents'],
                    'branch'   => ['nullable', 'string', 'max:100', 'no_french_accents'],
                    'momonum'  => ['nullable', 'string', 'max:50', 'no_french_accents'],
                    'momoname' => ['nullable', 'string', 'max:100', 'no_french_accents'],
                ];

                // Validate regular banks
                if (!empty($data['bank']) && !$isMomo) {
                    if (!empty($allValidBankNames) && !in_array(strtoupper($data['bank']), $allValidBankNames)) {
                        $errors[] = "Row $rowNumber: The selected bank '{$data['bank']}' is invalid.";
                        continue;
                    }

                    $rules['account'] = ['required', 'string', 'max:50'];

                    // Auto-lookup branch/code from BankCode if missing
                    if (empty($data['code']) || empty($data['branch'])) {
                        $foundBc = $bankCodes->first(function ($bc) use ($data) {
                            return strtoupper(trim($bc->bankname)) === strtoupper(trim($data['bank']))
                                || (!empty($data['code']) && trim($bc->branchcodes) === trim($data['code']));
                        });
                        if ($foundBc) {
                            if (empty($data['code'])) $data['code'] = $foundBc->branchcodes;
                            if (empty($data['branch'])) $data['branch'] = $foundBc->branch;
                        }
                    }

                    $rules['code']   = ['required', 'string', 'max:20'];
                    $rules['branch'] = ['required', 'string', 'max:100'];
                }

                if (!empty($data['momonum'])) {
                    $rules['momoname'] = ['required'];
                }
                if (!empty($data['momoname'])) {
                    $rules['momonum'] = ['required'];
                }

                $validator = Validator::make($data, $rules);

                // Allow any shop name if it's a MELCOM branch
                $validator->sometimes('shop', Rule::in($clients), function ($input) {
                    $shop = trim($input->shop ?? '');
                    return $shop !== '' && !str_starts_with(strtoupper($shop), 'MELCOM');
                });

                if ($validator->fails()) {
                    $errors = array_merge($errors, array_map(fn($e) => "Row $rowNumber: $e", $validator->errors()->all()));
                    continue;
                }

                $validRecords[] = $data;
            }

            if (!empty($errors)) {
                return redirect()->route('bankdetails.index')
                    ->with('error', 'Bulk upload failed: ' . implode(' | ', array_slice($errors, 0, 10)));
            }

            foreach ($validRecords as $record) {
                BankDetail::updateOrCreate(['employid' => $record['employid']], $record);
            }

            return redirect()->route('bankdetails.index')
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' records.');

        } catch (\Exception $e) {
            Log::error('Bulk upload failed', ['error' => $e->getMessage()]);
            return redirect()->route('bankdetails.index')
                ->with('error', 'Failed to process file: ' . $e->getMessage());
        }
    }
}
