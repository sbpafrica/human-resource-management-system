<?php
namespace App\Http\Controllers;
use App\Models\Contract;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $query = Contract::query();
        $totalRecords = Contract::count();
        $searchCount = null;

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('employeename', 'like', "%{$search}%")
                  ->orWhere('client', 'like', "%{$search}%");
            });
            $searchCount = $query->count();
        }

        $sort = $request->input('sort', 'employeename');
        $order = $request->input('order', 'asc');
        $query->orderBy($sort, $order);

        $contracts = $query->paginate(10)->withQueryString();

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
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
            'id' => ['required', 'string', 'max:20', 'unique:contractmelcomemp,id'],
            'employeename' => ['required', 'string', 'max:100'],
            'client' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:100'],
            'position1' => ['required', 'string', 'max:100'],
            'startdate' => ['required', 'date'],
            'enddate' => ['required', 'date'],
            'basic' => ['nullable', 'string', 'max:255'],
            'employername' => ['required', 'string', 'max:100'],
            'issuedate' => ['required', 'date'],
            'hremail' => ['required', 'email', 'max:255'],
            'email' => ['nullable', 'email', 'max:100'],
            'ghanacard' => ['nullable', 'string', 'max:100'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to create contract.');
        }

        $data = $request->all();
        $data['activate'] = '0'; // Set default
        Contract::create($data);
        return redirect()->route('contracts.index')->with('success', 'Contract created successfully.');
    }

    public function update(Request $request, $id)
    {
        $contract = Contract::findOrFail($id);

        $rules = [
            'id' => ['required', 'string', 'max:20', \Illuminate\Validation\Rule::unique('contractmelcomemp')->ignore($contract->id)],
            'employeename' => ['required', 'string', 'max:100'],
            'client' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:100'],
            'position1' => ['required', 'string', 'max:100'],
            'startdate' => ['required', 'date'],
            'enddate' => ['required', 'date'],
            'basic' => ['nullable', 'string', 'max:255'],
            'employername' => ['required', 'string', 'max:100'],
            'issuedate' => ['required', 'date'],
            'hremail' => ['required', 'email', 'max:255'],
            'email' => ['nullable', 'email', 'max:100'],
            'ghanacard' => ['nullable', 'string', 'max:100'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to update contract.');
        }

        $data = $request->all();
        $data['activate'] = $contract->activate; // Preserve existing activate value
        $contract->update($data);
        return redirect()->route('contracts.index')->with('success', 'Contract updated successfully.');
    }

    public function destroy($id)
    {
        $contract = Contract::findOrFail($id);
        $contract->delete();
        return redirect()->route('contracts.index')->with('success', 'Contract deleted successfully.');
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'], // 10MB max
        ]);

        try {
            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            array_shift($rows); // Remove header row

            $existingIds = Contract::pluck('id')->toArray();
            $errors = [];
            $validRecords = [];

            foreach ($rows as $index => $row) {
                if (empty($row['A'])) continue; // Skip empty rows

                $data = [
                    'id' => trim($row['A'] ?? ''),
                    'employeename' => trim($row['B'] ?? ''),
                    'client' => trim($row['C'] ?? ''),
                    'location' => trim($row['D'] ?? ''),
                    'position1' => trim($row['E'] ?? ''),
                    'startdate' => trim($row['F'] ?? ''),
                    'enddate' => trim($row['G'] ?? ''),
                    'basic' => !empty(trim($row['H'] ?? '')) ? trim($row['H']) : null,
                    'employername' => trim($row['I'] ?? ''),
                    'issuedate' => trim($row['J'] ?? ''),
                    'hremail' => trim($row['K'] ?? ''),
                    'email' => !empty(trim($row['L'] ?? '')) ? trim($row['L']) : null,
                    'ghanacard' => !empty(trim($row['M'] ?? '')) ? trim($row['M']) : null,
                    'activate' => '0', // Default value
                ];

                // Log raw data for debugging
                Log::debug("Raw row data for row " . ($index + 1), $data);

                // Handle date fields
                $dateFields = ['startdate' => 'F', 'enddate' => 'G', 'issuedate' => 'J'];
                foreach ($dateFields as $field => $column) {
                    if (!empty($data[$field])) {
                        $rawValue = $data[$field];
                        try {
                            // Check if value is an Excel date serial number
                            if (is_numeric($rawValue) && floatval($rawValue) > 1) {
                                $date = ExcelDate::excelToDateTimeObject(floatval($rawValue));
                                $data[$field] = $date->format('Y-m-d');
                            } else {
                                // Try parsing as string date
                                $date = Carbon::createFromFormat('Y-m-d', $rawValue) ??
                                        Carbon::createFromFormat('m/d/Y', $rawValue) ??
                                        Carbon::createFromFormat('m-d-Y', $rawValue) ??
                                        Carbon::createFromFormat('d/m/Y', $rawValue);
                                if ($date) {
                                    $data[$field] = $date->format('Y-m-d');
                                } else {
                                    throw new \Exception("Invalid date format: {$rawValue}");
                                }
                            }
                            Log::debug("Parsed {$field} for row " . ($index + 1) . ": {$data[$field]}");
                        } catch (\Exception $e) {
                            $errors[] = "Row " . ($index + 1) . ": Invalid date format for {$field}. Please use YYYY-MM-DD (e.g., 2025-08-23). Raw value: {$rawValue}";
                            continue 2; // Skip to next row
                        }
                    }
                }

                $rules = [
                    'id' => ['required', 'string', 'max:20', \Illuminate\Validation\Rule::notIn($existingIds)],
                    'employeename' => ['required', 'string', 'max:100'],
                    'client' => ['required', 'string', 'max:100'],
                    'location' => ['required', 'string', 'max:100'],
                    'position1' => ['required', 'string', 'max:100'],
                    'startdate' => ['required', 'date_format:Y-m-d'],
                    'enddate' => ['required', 'date_format:Y-m-d'],
                    'basic' => ['nullable', 'string', 'max:255'],
                    'employername' => ['required', 'string', 'max:100'],
                    'issuedate' => ['required', 'date_format:Y-m-d'],
                    'hremail' => ['required', 'email', 'max:255'],
                    'email' => ['nullable', 'email', 'max:100'],
                    'ghanacard' => ['nullable', 'string', 'max:100'],
                ];

                $validator = Validator::make($data, $rules);

                if ($validator->fails()) {
                    $errors = array_merge($errors, array_map(function ($error) use ($index) {
                        return "Row " . ($index + 1) . ": " . $error;
                    }, $validator->errors()->all()));
                    continue;
                }

                $validRecords[] = $data;
                $existingIds[] = $data['id'];
            }

            if (!empty($errors)) {
                Log::error("Bulk upload errors: " . implode(' | ', $errors));
                return redirect()->route('contracts.index')->with('error', implode(' | ', $errors));
            }

            foreach ($validRecords as $record) {
                Contract::create($record);
            }

            return redirect()->route('contracts.index')->with('success', 'Successfully uploaded ' . count($validRecords) . ' contract(s).');
        } catch (\Illuminate\Database\QueryException $e) {
            if (strpos($e->getMessage(), 'SQLSTATE[22007]') !== false) {
                Log::error('Bulk upload failed due to invalid date format: ' . $e->getMessage());
                return redirect()->route('contracts.index')->with('error', 'Invalid date format in uploaded file. Please use YYYY-MM-DD (e.g., 2025-08-23) for startdate, enddate, and issuedate.');
            }
            Log::error('Bulk upload failed: ' . $e->getMessage());
            return redirect()->route('contracts.index')->with('error', 'Failed to process the uploaded file.');
        } catch (\Exception $e) {
            Log::error('Bulk upload failed: ' . $e->getMessage());
            return redirect()->route('contracts.index')->with('error', 'Failed to process the uploaded file.');
        }
    }

    public function download($id, Request $request)
    {
        set_time_limit(120); // Prevent timeout
        $contract = Contract::findOrFail($id);

        if ($contract->activate !== '1' && !$request->has('no_signature')) {
            return redirect()->route('contracts.index')->with('error', 'Contract not activated for download.');
        }

        $data = [
            'employeename' => strtoupper($contract->employeename),
            'client' => strtoupper($contract->client),
            'location' => strtoupper($contract->location),
            'position' => strtoupper($contract->position1),
            'startdate' => date('F d, Y', strtotime($contract->startdate)),
            'enddate' => date('F d, Y', strtotime($contract->enddate)),
            'basic' => strtoupper($contract->basic ?? 'N/A'),
            'employername' => strtoupper($contract->employername),
            'issuedate' => date('F d, Y', strtotime($contract->id)),
            'hremail' => strtoupper($contract->hremail),
            'email' => strtoupper($contract->email ?? 'N/A'),
            'ghanacard' => strtoupper($contract->ghanacard ?? 'N/A'),
            'digiaddress' => strtoupper($contract->digiaddress ?? 'N/A'),
            'signature' => $request->has('no_signature') ? null : $contract->signature,
        ];

        $imagePaths = [
            'header' => public_path('images/sbphead.png'),
            'footer' => public_path('images/footer.png'),
            'employer_signature' => public_path('images/Annsign.png'),
            'employee_signature' => $data['signature'] ? public_path('images/melcomcon/' . $data['signature']) : null,
        ];

        foreach ($imagePaths as $key => $path) {
            if ($path && !file_exists($path)) {
                Log::warning("Image not found for PDF generation: {$key} at {$path}");
            }
        }

        $fontPaths = [
            'regular' => public_path('fonts/times.ttf'),
            'bold' => public_path('fonts/timesbd.ttf'),
            'italic' => public_path('fonts/timesi.ttf'),
            'bold_italic' => public_path('fonts/timesbi.ttf'),
        ];

        foreach ($fontPaths as $key => $path) {
            if (!file_exists($path)) {
                Log::warning("Font file not found for PDF generation: {$key} at {$path}");
            }
        }

        try {
            $fontCacheDir = storage_path('fonts');
            if (file_exists($fontCacheDir)) {
                foreach (glob($fontCacheDir . '/*.php') as $file) {
                    unlink($file);
                }
            }

            $pdf = Pdf::loadView('pdf.contract', $data)
                ->setOptions([
                    'defaultFont' => 'timesnewroman',
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                    'chroot' => public_path(),
                    'fontDir' => public_path('fonts'),
                    'fontdata' => [
                        'timesnewroman' => [
                            'R' => 'times.ttf',
                            'B' => 'timesbd.ttf',
                            'I' => 'timesi.ttf',
                            'BI' => 'timesbi.ttf',
                        ],
                    ],
                    'debug' => true,
                ])
                ->setPaper('a4', 'portrait');

            if ($request->has('no_signature')) {
                return response($pdf->output(), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="contract_preview_' . $contract->id . '.pdf"',
                ]);
            }

            return $pdf->download('contract_' . $contract->id . '.pdf');
        } catch (\Exception $e) {
            Log::error('PDF generation failed for contract ID ' . $id . ': ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
            ]);
            return redirect()->route('contracts.index')->with('error', 'Failed to generate PDF. Please try again or contact support.');
        }
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = [
            'Staff ID', 'Employee Name', 'Client', 'Location', 'Position',
            'Start Date', 'End Date', 'Basic Salary', 'Employer Name',
            'Issue Date', 'HR Email', 'Employee Email', 'Ghana Card'
        ];
        $sheet->fromArray($headers, null, 'A1');

        // Add sample row
        $sampleData = [
            'SBPR1',
            'Eric Koomson',
            'Melcom',
            'Accra',
            'Sales',
            '2025-08-23',
            '2026-08-22',
            '500',
            'Ann',
            '2025-08-07',
            'hr@example.com',
            'eric@example.com',
            'GHA-123456789-0'
        ];
        $sheet->fromArray($sampleData, null, 'A2');

        // Add instructions as a comment in date columns
        $dateColumns = ['F', 'G', 'J']; // Start Date, End Date, Issue Date
        foreach ($dateColumns as $col) {
            $sheet->getComment("{$col}1")->getText()->createTextRun('Use format YYYY-MM-DD (e.g., 2025-08-23)');
        }

        // Set column widths
        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Create writer
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = 'contract_template.xlsx';

        // Stream download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;
    }

    public function show($id)
    {
        $contract = Contract::findOrFail($id);
        return response()->json($contract);
    }
}
