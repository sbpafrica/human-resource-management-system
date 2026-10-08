<?php
namespace App\Http\Controllers;

use App\Models\Ssnit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Illuminate\Support\Facades\Response;

class SsnitController extends Controller
{
    private function applySearchFilter($query, ?string $search): void
    {
        $search = trim((string)$search);
        if ($search === '') return;

        $query->where(function ($q) use ($search) {
            $q->where('staffid',   'like', "%{$search}%")
              ->orWhere('shop',      'like', "%{$search}%")
              ->orWhere('lastname',  'like', "%{$search}%")
              ->orWhere('firstname', 'like', "%{$search}%")
              ->orWhere('othername', 'like', "%{$search}%")
              ->orWhere('ssnit1a',   'like', "%{$search}%")
              ->orWhere('national',  'like', "%{$search}%")
              ->orWhereRaw("CONCAT_WS(' ', firstname, lastname, othername) LIKE ?", ["%{$search}%"])
              ->orWhereRaw("CONCAT_WS(' ', lastname, firstname, othername) LIKE ?", ["%{$search}%"])
              ->orWhereRaw("CONCAT_WS(' ', firstname, othername, lastname) LIKE ?", ["%{$search}%"]);

            $words = array_filter(explode(' ', $search));
            if (count($words) > 1) {
                $q->orWhere(function ($subQ) use ($words) {
                    foreach ($words as $word) {
                        $subQ->where(function ($wQ) use ($word) {
                            $wQ->where('staffid',   'like', "%{$word}%")
                               ->orWhere('shop',      'like', "%{$word}%")
                               ->orWhere('lastname',  'like', "%{$word}%")
                               ->orWhere('firstname', 'like', "%{$word}%")
                               ->orWhere('othername', 'like', "%{$word}%")
                               ->orWhere('ssnit1a',   'like', "%{$word}%")
                               ->orWhere('national',  'like', "%{$word}%");
                        });
                    }
                });
            }
        });
    }

    public function index(Request $request)
    {
        // No shop exclusions — ALL staff including inhouse (SBP AFRICA) are shown here.
        // The separate SsnitInhouse/Index page is kept for inhouse-only management.
        $query = Ssnit::query();
        $totalRecords = Ssnit::count();
        $searchCount = null;

        if ($search = $request->input('search')) {
            $this->applySearchFilter($query, $search);
            $searchCount = (clone $query)->count();
        }

        $sort = $request->input('sort', 'staffid');
        $order = $request->input('order', 'asc');
        $query->orderBy($sort, $order);
        $ssnits = $query->paginate(10)->withQueryString();

        return Inertia::render('Ssnit/Index', [
            'ssnits' => $ssnits,
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
        $rules = [
            'staffid' => [
                'required',
                'string',
                'max:20',
                'unique:ssnit,staffid',
            ],
            'shop' => ['required', 'string', 'max:150'],
            'lastname' => ['required', 'string', 'max:150'],
            'firstname' => ['required', 'string', 'max:150'],
            'othername' => ['nullable', 'string', 'max:150'],
            'ssnit1a' => ['required', 'string', 'max:20'],
            'national' => ['nullable', 'string', 'max:255'],
        ];

        $messages = [
            'staffid.unique' => 'The Staff ID already exists in the database.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            Log::error('Store validation failed', ['errors' => $validator->errors(), 'request_data' => $request->all()]);
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to create SSNIT record: ' . implode(' | ', $validator->errors()->all()));
        }

        Ssnit::create($request->all());
        return redirect()->route('ssnitdetails.index')->with('success', 'SSNIT record created successfully.');
    }

    public function update(Request $request, $staffid)
    {
        Log::info('Update called', ['staffid' => $staffid, 'request_data' => $request->all()]);
        $ssnit = Ssnit::findOrFail($staffid);
        Log::info('Record found', ['ssnit' => $ssnit->toArray()]);

        $data = array_merge($ssnit->toArray(), $request->all());

        $rules = [
            'staffid' => [
                'required',
                'string',
                'max:20',
                Rule::unique('ssnit')->ignore($ssnit->staffid, 'staffid'),
            ],
            'shop' => ['required', 'string', 'max:150'],
            'lastname' => ['required', 'string', 'max:150'],
            'firstname' => ['required', 'string', 'max:150'],
            'othername' => ['nullable', 'string', 'max:150'],
            'ssnit1a' => ['required', 'string', 'max:20'],
            'national' => ['nullable', 'string', 'max:255'],
        ];

        $messages = [
            'staffid.unique' => 'The Staff ID already exists in the database.',
        ];

        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            Log::error('Update validation failed', ['errors' => $validator->errors(), 'request_data' => $request->all(), 'merged_data' => $data]);
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to update SSNIT record: ' . implode(' | ', $validator->errors()->all()));
        }

        $ssnit->update($data);
        return redirect()->route('ssnitdetails.index')->with('success', 'SSNIT record updated successfully.');
    }

    public function destroy($staffid)
    {
        Log::info('Destroy called', ['staffid' => $staffid]);
        $ssnit = Ssnit::findOrFail($staffid);
        $ssnit->delete();
        return redirect()->route('ssnitdetails.index')->with('success', 'SSNIT record deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'staffids' => ['required', 'array', 'min:1'],
            'staffids.*' => ['required', 'string', 'exists:ssnit,staffid'],
        ]);

        $staffids = $request->input('staffids');
        Log::info('Bulk destroy called', ['staffids' => $staffids]);

        $deletedCount = Ssnit::whereIn('staffid', $staffids)->delete();

        if ($deletedCount > 0) {
            return redirect()->route('ssnitdetails.index')->with('success', "Successfully deleted $deletedCount SSNIT record(s).");
        }

        return redirect()->route('ssnitdetails.index')->with('error', 'No SSNIT records were deleted.');
    }

    public function export(Request $request)
    {
        // No shop exclusions — export all records including inhouse
        $search = $request->input('search');
        $sort = $request->input('sort', 'staffid');
        $order = $request->input('order', 'asc');

        $query = Ssnit::query();
        if ($search) {
            $this->applySearchFilter($query, $search);
        }
        $query->orderBy($sort, $order);
        $ssnits = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Staff ID');
        $sheet->setCellValue('B1', 'Shop');
        $sheet->setCellValue('C1', 'Last Name');
        $sheet->setCellValue('D1', 'First Name');
        $sheet->setCellValue('E1', 'Other Name');
        $sheet->setCellValue('F1', 'SSNIT Number');
        $sheet->setCellValue('G1', 'National ID');

        $row = 2;
        foreach ($ssnits as $ssnit) {
            $sheet->setCellValue('A' . $row, $ssnit->staffid);
            $sheet->setCellValue('B' . $row, $ssnit->shop);
            $sheet->setCellValue('C' . $row, $ssnit->lastname);
            $sheet->setCellValue('D' . $row, $ssnit->firstname);
            $sheet->setCellValue('E' . $row, $ssnit->othername ?? 'N/A');
            $sheet->setCellValue('F' . $row, $ssnit->ssnit1a);
            $sheet->setCellValue('G' . $row, $ssnit->national ?? 'N/A');
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'ssnit_records.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Staff ID');
        $sheet->setCellValue('B1', 'Shop');
        $sheet->setCellValue('C1', 'Last Name');
        $sheet->setCellValue('D1', 'First Name');
        $sheet->setCellValue('E1', 'Other Name');
        $sheet->setCellValue('F1', 'SSNIT Number');
        $sheet->setCellValue('G1', 'National ID');

        $sheet->setCellValue('A2', 'STF123');
        $sheet->setCellValue('B2', 'Main Shop');
        $sheet->setCellValue('C2', 'Doe');
        $sheet->setCellValue('D2', 'John');
        $sheet->setCellValue('E2', '');
        $sheet->setCellValue('F2', 'SSN123456789');
        $sheet->setCellValue('G2', 'NAT123456');
        $sheet->setCellValue('A3', 'STF124');
        $sheet->setCellValue('B3', 'Branch Shop');
        $sheet->setCellValue('C3', 'Smith');
        $sheet->setCellValue('D3', 'Jane');
        $sheet->setCellValue('E3', 'Ann');
        $sheet->setCellValue('F3', 'SSN987654321');
        $sheet->setCellValue('G3', '');

        $writer = new Xlsx($spreadsheet);
        $fileName = 'ssnit_template.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    private function resolveNames(?string $rawFirst, ?string $rawLast, ?string $rawFull): array
    {
        $first = trim((string)$rawFirst);
        $last  = trim((string)$rawLast);
        $full  = trim((string)$rawFull);

        if ($first !== '' && $last !== '') {
            return ['firstname' => $first, 'lastname' => $last];
        }

        if ($first === '' && $last !== '') {
            $parts = preg_split('/\s+/', $last);
            if (count($parts) >= 2) {
                $first = array_shift($parts);
                $last  = implode(' ', $parts);
            } else {
                $first = $last;
            }
            return ['firstname' => $first, 'lastname' => $last];
        }

        if ($first !== '' && $last === '') {
            $parts = preg_split('/\s+/', $first);
            if (count($parts) >= 2) {
                $first = array_shift($parts);
                $last  = implode(' ', $parts);
            } else {
                $last = $first;
            }
            return ['firstname' => $first, 'lastname' => $last];
        }

        if ($full !== '') {
            $parts = preg_split('/\s+/', $full);
            if (count($parts) >= 2) {
                $first = array_shift($parts);
                $last  = implode(' ', $parts);
            } else {
                $first = $full;
                $last  = $full;
            }
            return ['firstname' => $first, 'lastname' => $last];
        }

        return ['firstname' => '-', 'lastname' => '-'];
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

            if (empty($rows)) {
                return redirect()->route('ssnitdetails.index')->with('error', 'Uploaded file is empty.');
            }

            // Dynamically locate primary header row
            $headerRowIdx = -1;
            foreach ($rows as $rIdx => $row) {
                foreach ($row as $cell) {
                    $cleanCell = strtolower(trim((string)$cell));
                    if (str_contains($cleanCell, 'staff') || str_contains($cleanCell, 'ssnit') || str_contains($cleanCell, 'first name') || str_contains($cleanCell, 'full name') || str_contains($cleanCell, 'name')) {
                        $headerRowIdx = $rIdx;
                        break 2;
                    }
                }
            }

            if ($headerRowIdx === -1) {
                $headerRowIdx = 0;
            }

            $headerRow = $rows[$headerRowIdx];
            $dataRows = array_slice($rows, $headerRowIdx + 1);

            $aliasMap = [
                'staffid'   => ['staff id', 'staff.no', 'staff no', 'staff_id', 'staff_no', 'no.', 'emp id', 'employee id', 'staffid'],
                'shop'      => ['shop', 'shop / location', 'department', 'workplace', 'location', 'branch', 'client', 'company'],
                'lastname'  => ['last name', 'lastname', 'last_name', 'surname', 'nom', 'family name'],
                'firstname' => ['first name', 'firstname', 'first_name', 'prenom', 'forename', 'given name'],
                'othername' => ['other name', 'othername', 'other_name', 'middle name', 'other names'],
                'fullname'  => ['full name', 'fullname', 'full_name', 'staff name', 'employee name', 'name of staff', 'nom et prenoms', 'names'],
                'ssnit1a'   => ['ssnit', 'ssnit number', 'ssnit no', 'ssnit_no', 'ssnit1a', 'cnss', 'ssnit #', 'ssnit#', 'social security'],
                'national'  => ['national', 'nationality', 'ghana card', 'gh card', 'national_id', 'nia', 'id number', 'national id'],
            ];

            $dbColToIdx = [];

            // Pass 1: Exact matching first
            foreach ($aliasMap as $dbCol => $aliases) {
                foreach ($headerRow as $idx => $headerText) {
                    $cleanHeader = strtolower(trim((string)$headerText));
                    if (in_array($cleanHeader, $aliases, true)) {
                        $dbColToIdx[$dbCol] = $idx;
                        break;
                    }
                }
            }

            // Pass 2: Substring matching for unmapped columns
            foreach ($aliasMap as $dbCol => $aliases) {
                if (isset($dbColToIdx[$dbCol])) continue;
                foreach ($headerRow as $idx => $headerText) {
                    if (in_array($idx, $dbColToIdx, true)) continue;
                    $cleanHeader = strtolower(trim((string)$headerText));
                    foreach ($aliases as $alias) {
                        if (strlen($alias) >= 4 && str_contains($cleanHeader, $alias)) {
                            $dbColToIdx[$dbCol] = $idx;
                            break 2;
                        }
                    }
                }
            }

            $errors = [];
            $validRecords = [];

            foreach ($dataRows as $index => $row) {
                $staffidIdx = $dbColToIdx['staffid'] ?? 0;
                $staffid = trim((string)($row[$staffidIdx] ?? ''));
                if ($staffid === '' || strtolower($staffid) === 'staff id' || strtolower($staffid) === 'total') continue;

                $rawFirst = isset($dbColToIdx['firstname']) ? trim((string)($row[$dbColToIdx['firstname']] ?? '')) : null;
                $rawLast  = isset($dbColToIdx['lastname']) ? trim((string)($row[$dbColToIdx['lastname']] ?? '')) : null;
                $rawFull  = isset($dbColToIdx['fullname']) ? trim((string)($row[$dbColToIdx['fullname']] ?? '')) : null;

                // Position fallback if headers weren't found
                if ($rawFirst === null && $rawLast === null && $rawFull === null) {
                    $c2 = trim((string)($row[2] ?? ''));
                    $c3 = trim((string)($row[3] ?? ''));
                    if ($c3 !== '') {
                        $rawLast = $c2;
                        $rawFirst = $c3;
                    } else if ($c2 !== '') {
                        $rawLast = $c2;
                    }
                }

                $names = $this->resolveNames($rawFirst, $rawLast, $rawFull);
                $firstname = $names['firstname'];
                $lastname  = $names['lastname'];

                $shopRaw = isset($dbColToIdx['shop']) ? trim((string)($row[$dbColToIdx['shop']] ?? '')) : (trim((string)($row[1] ?? '')) ?: 'General');
                $shop = $shopRaw !== '' ? $shopRaw : 'General';

                $ssnitRaw = isset($dbColToIdx['ssnit1a']) ? trim((string)($row[$dbColToIdx['ssnit1a']] ?? '')) : (trim((string)($row[5] ?? '')) ?: 'N/A');
                $ssnit1a = $ssnitRaw !== '' ? $ssnitRaw : 'N/A';

                $othername = isset($dbColToIdx['othername']) ? (trim((string)($row[$dbColToIdx['othername']] ?? '')) ?: null) : (!empty($row[4]) ? trim((string)$row[4]) : null);
                $national = isset($dbColToIdx['national']) ? (trim((string)($row[$dbColToIdx['national']] ?? '')) ?: null) : (!empty($row[6]) ? trim((string)$row[6]) : null);

                $data = [
                    'staffid'   => $staffid,
                    'shop'      => $shop,
                    'lastname'  => $lastname,
                    'firstname' => $firstname,
                    'othername' => $othername,
                    'ssnit1a'   => $ssnit1a,
                    'national'  => $national,
                ];

                $rules = [
                    'staffid'   => ['required', 'string', 'max:50'],
                    'shop'      => ['required', 'string', 'max:150'],
                    'lastname'  => ['required', 'string', 'max:150'],
                    'firstname' => ['required', 'string', 'max:150'],
                    'othername' => ['nullable', 'string', 'max:150'],
                    'ssnit1a'   => ['required', 'string', 'max:50'],
                    'national'  => ['nullable', 'string', 'max:255'],
                ];

                $validator = Validator::make($data, $rules);

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $err) {
                        $errors[] = "Row " . ($index + $headerRowIdx + 2) . ": " . $err;
                    }
                    continue;
                }

                $validRecords[] = $data;
            }

            if (!empty($errors)) {
                Log::error('Bulk upload validation failed', ['errors' => $errors]);
                return redirect()->route('ssnitdetails.index')->with('error', implode(' | ', array_slice($errors, 0, 15)));
            }

            foreach ($validRecords as $record) {
                Ssnit::updateOrCreate(['staffid' => $record['staffid']], $record);
            }

            return redirect()->route('ssnitdetails.index')->with('success', 'Successfully processed ' . count($validRecords) . ' SSNIT record(s).');
        } catch (\Exception $e) {
            Log::error('Bulk upload failed', ['error' => $e->getMessage()]);
            return redirect()->route('ssnitdetails.index')->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }
}
