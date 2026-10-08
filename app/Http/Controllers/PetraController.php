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

class PetraController extends Controller
{
    public function index(Request $request)
    {
        // No shop exclusions — ALL staff including inhouse (SBP AFRICA) are shown here.
        // The separate PetraDetailsInhouse page is kept for inhouse-only management.
        $query = Petra::query();
        $totalRecords = Petra::count();
        $searchCount = null;

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('staffid',   'like', "%{$search}%")
                  ->orWhere('shop',      'like', "%{$search}%")
                  ->orWhere('lastname',  'like', "%{$search}%")
                  ->orWhere('firstname', 'like', "%{$search}%")
                  ->orWhere('othername', 'like', "%{$search}%")
                  ->orWhere('ssnit1a',   'like', "%{$search}%")
                  ->orWhere('national',  'like', "%{$search}%");
            });
            $searchCount = $query->count();
        }

        $sort = $request->input('sort', 'staffid');
        $order = $request->input('order', 'asc');
        $query->orderBy($sort, $order);
        $petras = $query->paginate(10)->withQueryString();

        return Inertia::render('Petra/Index', [
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
        $rules = [
            'staffid' => [
                'required',
                'string',
                'max:20',
                'unique:petraorg,staffid',
                'no_french_accents',
            ],
            'shop' => ['required', 'string', 'max:150', 'no_french_accents'],
            'lastname' => ['required', 'string', 'max:150', 'no_french_accents'],
            'firstname' => ['required', 'string', 'max:150', 'no_french_accents'],
            'othername' => ['nullable', 'string', 'max:150', 'no_french_accents'],
            'ssnit1a' => ['required', 'string', 'max:20', 'no_french_accents'],
            'national' => ['nullable', 'string', 'max:255', 'no_french_accents'],
        ];

        $messages = [
            'staffid.unique' => 'The Staff ID already exists in the database.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            Log::error('Store validation failed', ['errors' => $validator->errors()]);
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to create Petra record: ' . implode(' | ', $validator->errors()->all()));
        }

        Petra::create($request->all());
        return redirect()->route('petradetails.index')->with('success', 'Petra record created successfully.');
    }

    public function update(Request $request, $staffid)
    {
        Log::info('Update called', ['staffid' => $staffid]);
        $petra = Petra::findOrFail($staffid);
        Log::info('Record found', ['petra' => $petra->toArray()]);

        $rules = [
            'staffid' => [
                'required',
                'string',
                'max:20',
                Rule::unique('petraorg')->ignore($petra->staffid, 'staffid'),
                'no_french_accents',
            ],
            'shop' => ['required', 'string', 'max:150', 'no_french_accents'],
            'lastname' => ['required', 'string', 'max:150', 'no_french_accents'],
            'firstname' => ['required', 'string', 'max:150', 'no_french_accents'],
            'othername' => ['nullable', 'string', 'max:150', 'no_french_accents'],
            'ssnit1a' => ['required', 'string', 'max:20', 'no_french_accents'],
            'national' => ['nullable', 'string', 'max:255', 'no_french_accents'],
        ];

        $messages = [
            'staffid.unique' => 'The Staff ID already exists in the database.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            Log::error('Update validation failed', ['errors' => $validator->errors()]);
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to update Petra record: ' . implode(' | ', $validator->errors()->all()));
        }

        $petra->update($request->all());
        return redirect()->route('petradetails.index')->with('success', 'Petra record updated successfully.');
    }

    public function destroy($staffid)
    {
        Log::info('Destroy called', ['staffid' => $staffid]);
        $petra = Petra::findOrFail($staffid);
        $petra->delete();
        return redirect()->route('petradetails.index')->with('success', 'Petra record deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'staffids' => ['required', 'array', 'min:1'],
            'staffids.*' => ['required', 'string', 'exists:petraorg,staffid'],
        ]);

        $staffids = $request->input('staffids');
        Log::info('Bulk destroy called', ['staffids' => $staffids]);

        $deletedCount = Petra::whereIn('staffid', $staffids)->delete();

        if ($deletedCount > 0) {
            return redirect()->route('petradetails.index')->with('success', "Successfully deleted $deletedCount Petra record(s).");
        }

        return redirect()->route('petradetails.index')->with('error', 'No Petra records were deleted.');
    }

    public function export(Request $request)
    {
        // No shop exclusions — export all records including inhouse
        $search = $request->input('search');
        $sort = $request->input('sort', 'staffid');
        $order = $request->input('order', 'asc');

        $query = Petra::query();
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('staffid',   'like', "%{$search}%")
                  ->orWhere('shop',      'like', "%{$search}%")
                  ->orWhere('lastname',  'like', "%{$search}%")
                  ->orWhere('firstname', 'like', "%{$search}%")
                  ->orWhere('othername', 'like', "%{$search}%")
                  ->orWhere('ssnit1a',   'like', "%{$search}%")
                  ->orWhere('national',  'like', "%{$search}%");
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
            $sheet->setCellValue('A' . $row, $petra->staffid);
            $sheet->setCellValue('B' . $row, $petra->shop);
            $sheet->setCellValue('C' . $row, $petra->lastname);
            $sheet->setCellValue('D' . $row, $petra->firstname);
            $sheet->setCellValue('E' . $row, $petra->othername ?? 'N/A');
            $sheet->setCellValue('F' . $row, $petra->ssnit1a);
            $sheet->setCellValue('G' . $row, $petra->national ?? 'N/A');
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'petra_records.xlsx';
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
        $sheet->setCellValue('F1', 'Petra ID');
        $sheet->setCellValue('G1', 'Ghana Card ID');

        $sheet->setCellValue('A2', 'STF123');
        $sheet->setCellValue('B2', 'Main Shop');
        $sheet->setCellValue('C2', 'Doe');
        $sheet->setCellValue('D2', 'John');
        $sheet->setCellValue('E2', '');
        $sheet->setCellValue('F2', 'PTR123456789');
        $sheet->setCellValue('G2', 'GHA123456');
        $sheet->setCellValue('A3', 'STF124');
        $sheet->setCellValue('B3', 'Branch Shop');
        $sheet->setCellValue('C3', 'Smith');
        $sheet->setCellValue('D3', 'Jane');
        $sheet->setCellValue('E3', 'Ann');
        $sheet->setCellValue('F3', 'PTR987654321');
        $sheet->setCellValue('G3', '');

        $writer = new Xlsx($spreadsheet);
        $fileName = 'petra_template.xlsx';
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
            array_shift($rows);

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
                    'ssnit1a' => $row[5] ?? '',
                    'national' => !empty($row[6]) ? $row[6] : null,
                ];

                foreach ($data as $field => $value) {
                    if ($value && preg_match('/[ÂÊÎÔÛâêîôû]/u', $value)) {
                        $errors[] = "Row " . ($index + 2) . ": French accented characters are not allowed in $field.";
                        continue 2;
                    }
                }

                $rules = [
                    'staffid' => ['required', 'string', 'max:20', Rule::notIn($existingStaffIds), 'no_french_accents'],
                    'shop' => ['required', 'string', 'max:150', 'no_french_accents'],
                    'lastname' => ['required', 'string', 'max:150', 'no_french_accents'],
                    'firstname' => ['required', 'string', 'max:150', 'no_french_accents'],
                    'othername' => ['nullable', 'string', 'max:150', 'no_french_accents'],
                    'ssnit1a' => ['required', 'string', 'max:20', 'no_french_accents'],
                    'national' => ['nullable', 'string', 'max:255', 'no_french_accents'],
                ];

                $messages = [
                    'staffid.not_in' => "Row " . ($index + 2) . ": The Staff ID already exists in the database.",
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
                return redirect()->route('petradetails.index')->with('error', implode(' | ', $errors));
            }

            foreach ($validRecords as $record) {
                Petra::create($record);
            }

            return redirect()->route('petradetails.index')->with('success', 'Successfully uploaded ' . count($validRecords) . ' Petra record(s).');
        } catch (\Exception $e) {
            Log::error('Bulk upload failed', ['error' => $e->getMessage()]);
            return redirect()->route('petradetails.index')->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }
}
