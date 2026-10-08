<?php
namespace App\Http\Controllers;

use App\Models\Petra;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Illuminate\Support\Facades\Response;

class PetraDetailsInhouseController extends Controller
{
    public function index(Request $request)
    {
        $includedShops = [
            'SBP AFRICA',
            'SBP AFRICA LTD',
            'SBP AFRICA LTD.',
            'SBP SOLUTIONS',
            'SBP SOLUTIONS LTD',
            'SBP SOLUTIONS LTD.'
        ];

        $query = Petra::query()->whereIn('shop', $includedShops);
        $totalRecords = Petra::whereIn('shop', $includedShops)->count();
        $searchCount = null;

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('staffid', 'like', "%{$search}%")
                  ->orWhere('shop', 'like', "%{$search}%")
                  ->orWhere('lastname', 'like', "%{$search}%")
                  ->orWhere('firstname', 'like', "%{$search}%");
            });
            $searchCount = $query->count();
        }

        $sort = $request->input('sort', 'staffid');
        $order = $request->input('order', 'asc');
        $query->orderBy($sort, $order);
        $petras = $query->paginate(10)->withQueryString();

        return Inertia::render('PetraDetailsInhouse/Index', [
            'petras' => $petras,
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
        try {
            Log::info('PetraDetailsInhouseController@store called', ['input' => $request->all()]);

            $includedShops = [
                'SBP AFRICA',
                'SBP AFRICA LTD',
                'SBP AFRICA LTD.',
                'SBP SOLUTIONS',
                'SBP SOLUTIONS LTD',
                'SBP SOLUTIONS LTD.'
            ];

            $rules = [
                'staffid' => [
                    'required',
                    'string',
                    'max:20',
                    'unique:petraorg,staffid',
                    'no_french_accents',
                ],
                'shop' => ['required', 'string', 'max:150', Rule::in($includedShops), 'no_french_accents'],
                'lastname' => ['required', 'string', 'max:150', 'no_french_accents'],
                'firstname' => ['required', 'string', 'max:150', 'no_french_accents'],
                'othername' => ['nullable', 'string', 'max:150', 'no_french_accents'],
                'petraId' => ['required', 'string', 'max:20', 'no_french_accents'],
                'ghanacard' => ['nullable', 'string', 'max:255', 'no_french_accents'],
            ];

            $messages = [
                'staffid.unique' => 'The Staff ID already exists in the database.',
                'shop.in' => 'The selected shop is not valid for inhouse records.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                Log::error('Store validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'input' => $request->all(),
                ]);
                return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to create Petra record: ' . implode(' | ', $validator->errors()->all()));
            }

            Petra::create($request->all());
            return redirect()->route('petradetailsinhouse.index')->with('success', 'Petra record created successfully.');
        } catch (\Exception $e) {
            Log::error('Error in PetraDetailsInhouseController@store', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'An error occurred while creating the Petra record.');
        }
    }

    public function update(Request $request, $staffid)
    {
        try {
            Log::info('PetraDetailsInhouseController@update called', [
                'staffid' => $staffid,
                'input' => $request->all(),
            ]);

            $petra = Petra::findOrFail($staffid);

            $includedShops = [
                'SBP AFRICA',
                'SBP AFRICA LTD',
                'SBP AFRICA LTD.',
                'SBP SOLUTIONS',
                'SBP SOLUTIONS LTD',
                'SBP SOLUTIONS LTD.'
            ];

            $rules = [
                'staffid' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('petraorg')->ignore($petra->staffid, 'staffid'),
                    'no_french_accents',
                ],
                'shop' => ['required', 'string', 'max:150', Rule::in($includedShops), 'no_french_accents'],
                'lastname' => ['required', 'string', 'max:150', 'no_french_accents'],
                'firstname' => ['required', 'string', 'max:150', 'no_french_accents'],
                'othername' => ['nullable', 'string', 'max:150', 'no_french_accents'],
                'petraId' => ['required', 'string', 'max:20', 'no_french_accents'],
                'ghanacard' => ['nullable', 'string', 'max:255', 'no_french_accents'],
            ];

            $messages = [
                'staffid.unique' => 'The Staff ID already exists in the database.',
                'shop.in' => 'The selected shop is not valid for inhouse records.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                Log::error('Update validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'input' => $request->all(),
                ]);
                return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to update Petra record: ' . implode(' | ', $validator->errors()->all()));
            }

            $petra->update($request->all());
            return redirect()->route('petradetailsinhouse.index')->with('success', 'Petra record updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error in PetraDetailsInhouseController@update', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'An error occurred while updating the Petra record.');
        }
    }

    public function destroy($staffid)
    {
        try {
            Log::info('PetraDetailsInhouseController@destroy called', ['staffid' => $staffid]);
            $petra = Petra::findOrFail($staffid);
            $petra->delete();
            return redirect()->route('petradetailsinhouse.index')->with('success', 'Petra record deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error in PetraDetailsInhouseController@destroy', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'An error occurred while deleting the Petra record.');
        }
    }

    public function bulkDestroy(Request $request)
    {
        try {
            Log::info('PetraDetailsInhouseController@bulkDestroy called', ['staffids' => $request->input('staffids')]);

            $request->validate([
                'staffids' => ['required', 'array', 'min:1'],
                'staffids.*' => ['required', 'string', 'exists:petraorg,staffid'],
            ]);

            $staffids = $request->input('staffids');
            $deletedCount = Petra::whereIn('staffid', $staffids)->delete();

            if ($deletedCount > 0) {
                return redirect()->route('petradetailsinhouse.index')->with('success', "Successfully deleted $deletedCount Petra record(s).");
            }

            return redirect()->route('petradetailsinhouse.index')->with('error', 'No Petra records were deleted.');
        } catch (\Exception $e) {
            Log::error('Error in PetraDetailsInhouseController@bulkDestroy', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'An error occurred while deleting the Petra records.');
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
                'SBP SOLUTIONS LTD',
                'SBP SOLUTIONS LTD.'
            ];

            $search = $request->input('search');
            $sort = $request->input('sort', 'staffid');
            $order = $request->input('order', 'asc');

            $query = Petra::query()->whereIn('shop', $includedShops);
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('staffid', 'like', "%{$search}%")
                      ->orWhere('shop', 'like', "%{$search}%")
                      ->orWhere('lastname', 'like', "%{$search}%")
                      ->orWhere('firstname', 'like', "%{$search}%");
                });
            }
            $query->orderBy($sort, $order);
            $petras = $query->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', 'Staff ID');
            $sheet->setCellValue('B1', 'Shop');
            $sheet->setCellValue('C1', 'Last Name');
            $sheet->setCellValue('D1', 'First Name');
            $sheet->setCellValue('E1', 'Other Name');
            $sheet->setCellValue('F1', 'Petra ID');
            $sheet->setCellValue('G1', 'Ghana Card ID');

            $row = 2;
            foreach ($petras as $petra) {
                $sheet->setCellValue('A' . $row, $petra->stackffid);
                $sheet->setCellValue('B' . $row, $petra->shop);
                $sheet->setCellValue('C' . $row, $petra->lastname);
                $sheet->setCellValue('D' . $row, $petra->firstname);
                $sheet->setCellValue('E' . $row, $petra->othername ?? 'N/A');
                $sheet->setCellValue('F' . $row, $petra->petraId);
                $sheet->setCellValue('G' . $row, $petra->ghanacard ?? 'N/A');
                $row++;
            }

            $writer = new Xlsx($spreadsheet);
            $fileName = 'petra_inhouse_records.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            $writer->save($tempFile);

            return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Error in PetraDetailsInhouseController@export', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'An error occurred while exporting the Petra records.');
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
            $sheet->setCellValue('F1', 'Petra ID');
            $sheet->setCellValue('G1', 'Ghana Card ID');

            $sheet->setCellValue('A2', 'STF123');
            $sheet->setCellValue('B2', 'SBP AFRICA');
            $sheet->setCellValue('C2', 'Doe');
            $sheet->setCellValue('D2', 'John');
            $sheet->setCellValue('E2', '');
            $sheet->setCellValue('F2', 'PTR123456789');
            $sheet->setCellValue('G2', 'GHA123456');
            $sheet->setCellValue('A3', 'STF124');
            $sheet->setCellValue('B3', 'SBP SOLUTIONS');
            $sheet->setCellValue('C3', 'Smith');
            $sheet->setCellValue('D3', 'Jane');
            $sheet->setCellValue('E3', 'Ann');
            $sheet->setCellValue('F3', 'PTR987654321');
            $sheet->setCellValue('G3', '');

            $writer = new Xlsx($spreadsheet);
            $fileName = 'petra_inhouse_template.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            $writer->save($tempFile);

            return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Error in PetraDetailsInhouseController@downloadTemplate', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'An error occurred while downloading the template.');
        }
    }

    public function bulkUpload(Request $request)
    {
        try {
            Log::info('PetraDetailsInhouseController@bulkUpload called', ['file' => $request->file('file') ? $request->file('file')->getClientOriginalName() : null]);

            $request->validate([
                'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:2048'],
            ]);

            $includedShops = [
                'SBP AFRICA',
                'SBP AFRICA LTD',
                'SBP AFRICA LTD.',
                'SBP SOLUTIONS',
                'SBP SOLUTIONS LTD',
                'SBP SOLUTIONS LTD.'
            ];

            $file = $request->file('file');
            $reader = new XlsxReader();
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            array_shift($rows); // Remove header row

            $existingStaffIds = Petra::pluck('staffid')->toArray();
            $errors = [];
            $validRecords = [];

            foreach ($rows as $index => $row) {
                $data = [
                    'staffid' => $row[0] ?? '',
                    'shop' => $row[1] ?? '',
                    'lastname' => $row[2] ?? '',
                    'firstname' => $row[3] ?? '',
                    'othername' => !empty($row[4]) ? $row[4] : null,
                    'petraId' => $row[5] ?? '',
                    'ghanacard' => !empty($row[6]) ? $row[6] : null,
                ];

                foreach ($data as $field => $value) {
                    if ($value && preg_match('/[ÂÊÎÔÛâêîôû]/u', $value)) {
                        $errors[] = "Row " . ($index + 2) . ": French accented characters (Â, Ê, Î, Ô, Û, â, ê, î, ô, û) are not allowed in $field.";
                        continue 2;
                    }
                }

                $rules = [
                    'staffid' => [
                        'required',
                        'string',
                        'max:20',
                        Rule::notIn($existingStaffIds),
                        'no_french_accents',
                    ],
                    'shop' => ['required', 'string', 'max:150', Rule::in($includedShops), 'no_french_accents'],
                    'lastname' => ['required', 'string', 'max:150', 'no_french_accents'],
                    'firstname' => ['required', 'string', 'max:150', 'no_french_accents'],
                    'othername' => ['nullable', 'string', 'max:150', 'no_french_accents'],
                    'petraId' => ['required', 'string', 'max:20', 'no_french_accents'],
                    'ghanacard' => ['nullable', 'string', 'max:255', 'no_french_accents'],
                ];

                $messages = [
                    'staffid.not_in' => "Row " . ($index + 2) . ": The Staff ID already exists in the database.",
                    'shop.in' => "Row " . ($index + 2) . ": The selected shop is not valid for inhouse records.",
                ];

                $validator = Validator::make($data, $rules, $messages);

                if ($validator->fails()) {
                    $errors = array_merge($errors, array_map(function ($error) use ($index) {
                        return "Row " . ($index + 2) . ": " . $error;
                    }, $validator->errors()->all()));
                    continue;
                }

                $validRecords[] = $data;
                $existingStaffIds[] = $data['staffid'];
            }

            if (!empty($errors)) {
                Log::error('Bulk upload validation failed', ['errors' => $errors]);
                return redirect()->route('petradetailsinhouse.index')->with('error', implode(' | ', $errors));
            }

            foreach ($validRecords as $record) {
                Petra::create($record);
            }

            return redirect()->route('petradetailsinhouse.index')->with('success', 'Successfully uploaded ' . count($validRecords) . ' Petra record(s).');
        } catch (\Exception $e) {
            Log::error('Error in PetraDetailsInhouseController@bulkUpload', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('petradetailsinhouse.index')->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }
}
