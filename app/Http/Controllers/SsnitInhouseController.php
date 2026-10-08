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

class SsnitInhouseController extends Controller
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
        try {
            $includedShops = [
                'SBP AFRICA',
                'SBP AFRICA LTD',
                'SBP AFRICA LTD.',
                'SBP SOLUTIONS',
                'SBP SOLUTIONS LTD'
            ];

            $query = Ssnit::query()->whereIn('shop', $includedShops);
            $totalRecords = Ssnit::whereIn('shop', $includedShops)->count();
            $searchCount = null;

            if ($search = $request->input('search')) {
                $this->applySearchFilter($query, $search);
                $searchCount = (clone $query)->count();
            }

            $sort = $request->input('sort', 'staffid');
            $order = $request->input('order', 'asc');
            $query->orderBy($sort, $order);
            $ssnits = $query->paginate(10)->withQueryString();

            return Inertia::render('SsnitInhouse/Index', [
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
        } catch (\Exception $e) {
            Log::error('Error in SsnitInhouseController@index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'An error occurred while loading inhouse SSNIT details.');
        }
    }

    public function store(Request $request)
    {
        try {
            $includedShops = [
                'SBP AFRICA',
                'SBP AFRICA LTD',
                'SBP AFRICA LTD.',
                'SBP SOLUTIONS',
                'SBP SOLUTIONS LTD'
            ];

            $rules = [
                'staffid' => [
                    'required',
                    'string',
                    'max:20',
                    'unique:ssnit,staffid',
                    'no_french_accents',
                ],
                'shop' => ['required', 'string', 'max:150', Rule::in($includedShops), 'no_french_accents'],
                'lastname' => ['required', 'string', 'max:150', 'no_french_accents'],
                'firstname' => ['required', 'string', 'max:150', 'no_french_accents'],
                'othername' => ['nullable', 'string', 'max:150', 'no_french_accents'],
                'ssnit1a' => ['required', 'string', 'max:20', 'no_french_accents'],
                'national' => ['nullable', 'string', 'max:255', 'no_french_accents'],
            ];

            $messages = [
                'staffid.unique' => 'The Staff ID already exists in the database.',
                'shop.in' => 'The selected shop is not valid for inhouse records.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                Log::error('Store validation failed', ['errors' => $validator->errors(), 'input' => $request->all()]);
                return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to create inhouse SSNIT record: ' . implode(' | ', $validator->errors()->all()));
            }

            Ssnit::create($request->all());
            return redirect()->route('ssnitdetailsinhouse.index')->with('success', 'Inhouse SSNIT record created successfully.');
        } catch (\Exception $e) {
            Log::error('Error in SsnitInhouseController@store', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'An error occurred while creating the inhouse SSNIT record.');
        }
    }

    public function update(Request $request, $staffid)
    {
        try {
            Log::info('SsnitInhouseController@update called', [
                'staffid' => $staffid,
                'input' => $request->all(),
            ]);

            $ssnit = Ssnit::findOrFail($staffid);
            $includedShops = [
                'SBP AFRICA',
                'SBP AFRICA LTD',
                'SBP AFRICA LTD.',
                'SBP SOLUTIONS',
                'SBP SOLUTIONS LTD'
            ];

            $rules = [
                'staffid' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('ssnit')->ignore($ssnit->staffid, 'staffid'),
                    'no_french_accents',
                ],
                'shop' => ['required', 'string', 'max:150', Rule::in($includedShops), 'no_french_accents'],
                'lastname' => ['required', 'string', 'max:150', 'no_french_accents'],
                'firstname' => ['required', 'string', 'max:150', 'no_french_accents'],
                'othername' => ['nullable', 'string', 'max:150', 'no_french_accents'],
                'ssnit1a' => ['required', 'string', 'max:20', 'no_french_accents'],
                'national' => ['nullable', 'string', 'max:255', 'no_french_accents'],
            ];

            $messages = [
                'staffid.unique' => 'The Staff ID already exists in the database.',
                'shop.in' => 'The selected shop is not valid for inhouse records.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                Log::error('Update validation failed', [
                    'errors' => $validator->errors(),
                    'input' => $request->all(),
                ]);
                return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to update inhouse SSNIT record: ' . implode(' | ', $validator->errors()->all()));
            }

            $ssnit->update($request->all());
            return redirect()->route('ssnitdetailsinhouse.index')->with('success', 'Inhouse SSNIT record updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error in SsnitInhouseController@update', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->all(),
            ]);
            return redirect()->back()->with('error', 'An error occurred while updating the inhouse SSNIT record.');
        }
    }

    public function destroy($staffid)
    {
        try {
            Log::info('Destroy called', ['staffid' => $staffid]);
            $ssnit = Ssnit::findOrFail($staffid);
            $ssnit->delete();
            return redirect()->route('ssnitdetailsinhouse.index')->with('success', 'Inhouse SSNIT record deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error in SsnitInhouseController@destroy', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'An error occurred while deleting the inhouse SSNIT record.');
        }
    }

    public function bulkDestroy(Request $request)
    {
        try {
            $request->validate([
                'staffids' => ['required', 'array', 'min:1'],
                'staffids.*' => ['required', 'string', 'exists:ssnit,staffid'],
            ]);

            $staffids = $request->input('staffids');
            Log::info('Bulk destroy called', ['staffids' => $staffids]);

            $deletedCount = Ssnit::whereIn('staffid', $staffids)->delete();

            if ($deletedCount > 0) {
                return redirect()->route('ssnitdetailsinhouse.index')->with('success', "Successfully deleted $deletedCount inhouse SSNIT record(s).");
            }

            return redirect()->route('ssnitdetailsinhouse.index')->with('error', 'No inhouse SSNIT records were deleted.');
        } catch (\Exception $e) {
            Log::error('Error in SsnitInhouseController@bulkDestroy', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'An error occurred while deleting inhouse SSNIT records.');
        }
    }

    public function export(Request $request)
    {
        try {
            $includedShops = [
                'SBP AFRICA',
                'SBP AFRICA LTD',
                'SBP AFRICA LTD.',
                'SBP SOLUTIONS',
                'SBP SOLUTIONS LTD'
            ];

            $search = $request->input('search');
            $sort = $request->input('sort', 'staffid');
            $order = $request->input('order', 'asc');

            $query = Ssnit::query()->whereIn('shop', $includedShops);
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
            $fileName = 'ssnit_inhouse_records.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            $writer->save($tempFile);

            return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Error in SsnitInhouseController@export', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'An error occurred while exporting inhouse SSNIT records.');
        }
    }

    public function downloadTemplate()
    {
        try {
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
            $sheet->setCellValue('B2', 'SBP AFRICA');
            $sheet->setCellValue('C2', 'Doe');
            $sheet->setCellValue('D2', 'John');
            $sheet->setCellValue('E2', '');
            $sheet->setCellValue('F2', 'SSN123456789');
            $sheet->setCellValue('G2', 'NAT123456');
            $sheet->setCellValue('A3', 'STF124');
            $sheet->setCellValue('B3', 'SBP SOLUTIONS');
            $sheet->setCellValue('C3', 'Smith');
            $sheet->setCellValue('D3', 'Jane');
            $sheet->setCellValue('E3', 'Ann');
            $sheet->setCellValue('F3', 'SSN987654321');
            $sheet->setCellValue('G3', '');

            $writer = new Xlsx($spreadsheet);
            $fileName = 'ssnit_inhouse_template.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            $writer->save($tempFile);

            return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Error in SsnitInhouseController@downloadTemplate', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'An error occurred while downloading the inhouse SSNIT template.');
        }
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
        try {
            $request->validate([
                'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:2048'],
            ]);

            $includedShops = [
                'SBP AFRICA',
                'SBP AFRICA LTD',
                'SBP AFRICA LTD.',
                'SBP SOLUTIONS',
                'SBP SOLUTIONS LTD'
            ];

            $file = $request->file('file');
            $reader = new XlsxReader();
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (empty($rows)) {
                return redirect()->route('ssnitdetailsinhouse.index')->with('error', 'Uploaded file is empty.');
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

                $shopRaw = isset($dbColToIdx['shop']) ? trim((string)($row[$dbColToIdx['shop']] ?? '')) : (trim((string)($row[1] ?? '')) ?: 'SBP AFRICA');
                $shop = in_array($shopRaw, $includedShops) ? $shopRaw : 'SBP AFRICA';

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

                foreach ($data as $field => $value) {
                    if ($value && preg_match('/[ÂÊÎÔÛâêîôû]/u', $value)) {
                        $errors[] = "Row " . ($index + $headerRowIdx + 2) . ": French accented characters are not allowed in $field.";
                        continue 2;
                    }
                }

                $rules = [
                    'staffid'   => ['required', 'string', 'max:50', 'no_french_accents'],
                    'shop'      => ['required', 'string', 'max:150', Rule::in($includedShops), 'no_french_accents'],
                    'lastname'  => ['required', 'string', 'max:150', 'no_french_accents'],
                    'firstname' => ['required', 'string', 'max:150', 'no_french_accents'],
                    'othername' => ['nullable', 'string', 'max:150', 'no_french_accents'],
                    'ssnit1a'   => ['required', 'string', 'max:50', 'no_french_accents'],
                    'national'  => ['nullable', 'string', 'max:255', 'no_french_accents'],
                ];

                $messages = [
                    'shop.in' => "Row " . ($index + $headerRowIdx + 2) . ": The selected shop is not valid for inhouse records.",
                ];

                $validator = Validator::make($data, $rules, $messages);

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $err) {
                        $errors[] = "Row " . ($index + $headerRowIdx + 2) . ": " . $err;
                    }
                    continue;
                }

                $validRecords[] = $data;
            }

            if (!empty($errors)) {
                Log::error('Bulk upload validation failed', ['errors' => $errors, 'input' => $request->all()]);
                return redirect()->route('ssnitdetailsinhouse.index')->with('error', implode(' | ', array_slice($errors, 0, 15)));
            }

            foreach ($validRecords as $record) {
                Ssnit::updateOrCreate(['staffid' => $record['staffid']], $record);
            }

            return redirect()->route('ssnitdetailsinhouse.index')->with('success', 'Successfully processed ' . count($validRecords) . ' inhouse SSNIT record(s).');
        } catch (\Exception $e) {
            Log::error('Error in SsnitInhouseController@bulkUpload', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('ssnitdetailsinhouse.index')->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }
}
