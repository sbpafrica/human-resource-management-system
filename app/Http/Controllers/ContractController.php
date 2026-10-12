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
            'id' => ['required', 'string', 'max:50', 'unique:contractmelcomemp,id'],
            'employeename' => ['required', 'string', 'max:100'],
            'client' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:100'],
            'position1' => ['required', 'string', 'max:100'],
            'startdate' => ['required', 'date'],
            'enddate' => ['required', 'date'],
            'basic' => ['nullable', 'string', 'max:255'],
            'employername' => ['required', 'string', 'max:100'],
            'issuedate' => ['required', 'date'],
            'hremail' => ['nullable', 'email', 'max:255'],
            'email' => ['nullable', 'email', 'max:100'],
            'ghanacard' => ['nullable', 'string', 'max:100'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to create contract.');
        }

        $data = $request->all();
        $data['activate'] = '0'; // Set default
        if (empty($data['hremail'])) {
            $data['hremail'] = auth()->user()?->email ?: 'hr@sbpafrica.com';
        }
        Contract::create($data);
        return redirect()->route('contracts.index')->with('success', 'Contract created successfully.');
    }

    public function update(Request $request, $id)
    {
        $contract = Contract::findOrFail($id);

        $rules = [
            'id' => ['required', 'string', 'max:50', \Illuminate\Validation\Rule::unique('contractmelcomemp')->ignore($contract->id)],
            'employeename' => ['required', 'string', 'max:100'],
            'client' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:100'],
            'position1' => ['required', 'string', 'max:100'],
            'startdate' => ['required', 'date'],
            'enddate' => ['required', 'date'],
            'basic' => ['nullable', 'string', 'max:255'],
            'employername' => ['required', 'string', 'max:100'],
            'issuedate' => ['required', 'date'],
            'hremail' => ['nullable', 'email', 'max:255'],
            'email' => ['nullable', 'email', 'max:100'],
            'ghanacard' => ['nullable', 'string', 'max:100'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to update contract.');
        }

        $data = $request->all();
        $data['activate'] = $contract->activate; // Preserve existing activate value
        if (empty($data['hremail'])) {
            $data['hremail'] = !empty($contract->hremail) ? $contract->hremail : (auth()->user()?->email ?: 'hr@sbpafrica.com');
        }
        $contract->update($data);
        return redirect()->route('contracts.index')->with('success', 'Contract updated successfully.');
    }

    public function destroy($id)
    {
        $contract = Contract::findOrFail($id);
        $contract->delete();
        return redirect()->route('contracts.index')->with('success', 'Contract deleted successfully.');
    }

    /**
     * Parse date value from Excel serial or various string formats to Y-m-d.
     */
    private function parseDateValue($rawValue): ?string
    {
        if (empty($rawValue)) {
            return null;
        }

        if ($rawValue instanceof \DateTimeInterface) {
            return $rawValue->format('Y-m-d');
        }

        $strVal = trim((string)$rawValue);
        if ($strVal === '') {
            return null;
        }

        // Check if value is an Excel date serial number (e.g. 45892)
        if (is_numeric($strVal) && floatval($strVal) > 1000) {
            try {
                $date = ExcelDate::excelToDateTimeObject(floatval($strVal));
                return $date->format('Y-m-d');
            } catch (\Throwable $e) {
                // fall through to string parsing
            }
        }

        // List of candidate formats
        $formats = [
            'Y-m-d',
            'd/m/Y',
            'm/d/Y',
            'Y/m/d',
            'd-m-Y',
            'm-d-Y',
            'd.m.Y',
            'Y.m.d',
            'j/n/Y',
            'n/j/Y',
            'j-n-Y',
            'n-j-Y',
            'd M Y',
            'd-M-Y',
            'd M, Y',
            'M d, Y',
            'F d, Y',
            'd F Y',
        ];

        foreach ($formats as $fmt) {
            try {
                $parsed = Carbon::createFromFormat($fmt, $strVal);
                if ($parsed !== false) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                // try next format
            }
        }

        try {
            $parsed = Carbon::parse($strVal);
            if ($parsed !== false) {
                return $parsed->format('Y-m-d');
            }
        } catch (\Throwable $e) {
            // failed to parse
        }

        return null;
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'], // 10MB max
        ]);

        try {
            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (empty($rows)) {
                return redirect()->route('contracts.index')->with('error', 'The uploaded file contains no data.');
            }

            // Find header row and build column mapping
            $headerRowKey = null;
            $columnMap = []; // field_name => column_letter ('A', 'B', ...)

            // Aliases for matching columns
            $fieldAliases = [
                'id' => ['staff id', 'employee id', 'staff no', 'staff number', 'emp id', 'empid', 'staffid', 'id'],
                'employeename' => ['employee name', 'employeename', 'staff name', 'fullname', 'full name', 'name of employee', 'name'],
                'client' => ['client', 'client name', 'company', 'organization'],
                'location' => ['location', 'branch', 'site', 'work station', 'station', 'place of work'],
                'position1' => ['position', 'position1', 'job title', 'designation', 'role', 'job'],
                'startdate' => ['start date', 'startdate', 'commencement date', 'contract start', 'effective date', 'from date', 'commencement'],
                'enddate' => ['end date', 'enddate', 'expiry date', 'contract end', 'to date', 'termination date', 'expiry'],
                'basic' => ['basic salary', 'basic', 'salary', 'basic pay', 'wage', 'rate', 'gross'],
                'employername' => ['employer name', 'employername', 'employer', 'entity', 'company name'],
                'issuedate' => ['issue date', 'issuedate', 'date of issue', 'contract date', 'signed date', 'agreement date', 'date'],
                'hremail' => ['hr email', 'hremail', 'hr\'s email', 'hr mail', 'human resource email', 'hr contact'],
                'email' => ['employee email', 'staff email', 'personal email', 'worker email', 'email address', 'email'],
                'ghanacard' => ['ghana card', 'ghanacard', 'ghana card no', 'ghana card number', 'national id', 'nia number', 'id number', 'nia'],
            ];

            // Scan first 10 rows to detect header row
            $scanLimit = 10;
            $scanned = 0;
            foreach ($rows as $rKey => $row) {
                if ($scanned++ >= $scanLimit) break;

                $matchedFields = [];
                $tempColMap = [];

                foreach ($row as $colLetter => $cellVal) {
                    if (is_null($cellVal)) continue;
                    $cleanVal = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', ' ', (string)$cellVal)));
                    if ($cleanVal === '') continue;

                    foreach ($fieldAliases as $field => $aliases) {
                        if (isset($tempColMap[$field])) continue; // already mapped

                        foreach ($aliases as $alias) {
                            $cleanAlias = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', ' ', $alias)));
                            if ($cleanVal === $cleanAlias || str_contains($cleanVal, $cleanAlias)) {
                                $tempColMap[$field] = $colLetter;
                                $matchedFields[] = $field;
                                break 2;
                            }
                        }
                    }
                }

                // If at least 3 recognizable fields are found (e.g. id, name, position or start date)
                if (count($matchedFields) >= 3) {
                    $headerRowKey = $rKey;
                    $columnMap = $tempColMap;
                    break;
                }
            }

            // Fallback default column mapping if no header row was detected
            $defaultMapping = [
                'id' => 'A',
                'employeename' => 'B',
                'client' => 'C',
                'location' => 'D',
                'position1' => 'E',
                'startdate' => 'F',
                'enddate' => 'G',
                'basic' => 'H',
                'employername' => 'I',
                'issuedate' => 'J',
                'hremail' => 'K',
                'email' => 'L',
                'ghanacard' => 'M',
            ];

            foreach ($defaultMapping as $field => $defaultCol) {
                if (!isset($columnMap[$field])) {
                    $columnMap[$field] = $defaultCol;
                }
            }

            // Default HR email from logged-in user or standard fallback
            $defaultHrEmail = auth()->user()?->email ?: 'hr@sbpafrica.com';

            $errors = [];
            $validRecords = [];
            $displayRowNumber = 0;

            foreach ($rows as $rKey => $row) {
                // If we detected a header row, skip rows up to and including the header row
                if ($headerRowKey !== null && $rKey <= $headerRowKey) {
                    continue;
                } elseif ($headerRowKey === null && $rKey === 1) {
                    // Check if row 1 looks like a header (e.g. contains 'staff' or 'id')
                    $firstCell = strtolower(trim((string)($row['A'] ?? '')));
                    if (str_contains($firstCell, 'id') || str_contains($firstCell, 'staff') || str_contains($firstCell, 'name')) {
                        continue;
                    }
                }

                $displayRowNumber++;

                // Helper to extract value using mapped column
                $getVal = function ($field) use ($row, $columnMap) {
                    $col = $columnMap[$field] ?? null;
                    if ($col && isset($row[$col])) {
                        return trim((string)$row[$col]);
                    }
                    return '';
                };

                $id = $getVal('id');
                $name = $getVal('employeename');

                // Skip entirely empty row
                if (empty($id) && empty($name)) {
                    continue;
                }

                if (empty($id)) {
                    $errors[] = "Row {$displayRowNumber}: Staff ID / ID is required.";
                    continue;
                }

                // HR email resolution: Use provided value if valid email; otherwise use default fallback
                $hrEmailVal = $getVal('hremail');
                if (empty($hrEmailVal) || !filter_var($hrEmailVal, FILTER_VALIDATE_EMAIL)) {
                    $hrEmailVal = $defaultHrEmail;
                }

                // Employee email
                $empEmailVal = $getVal('email');
                if (!empty($empEmailVal) && !filter_var($empEmailVal, FILTER_VALIDATE_EMAIL)) {
                    $empEmailVal = null;
                }

                // Dates parsing
                $startDate = $this->parseDateValue($getVal('startdate'));
                $endDate = $this->parseDateValue($getVal('enddate'));
                $issueDate = $this->parseDateValue($getVal('issuedate')) ?: ($startDate ?: date('Y-m-d'));

                if (empty($startDate)) {
                    $raw = $getVal('startdate');
                    $errors[] = "Row {$displayRowNumber}: Invalid or missing Start Date" . ($raw ? " ('{$raw}')" : "") . ". Please use YYYY-MM-DD.";
                    continue;
                }

                if (empty($endDate)) {
                    $raw = $getVal('enddate');
                    $errors[] = "Row {$displayRowNumber}: Invalid or missing End Date" . ($raw ? " ('{$raw}')" : "") . ". Please use YYYY-MM-DD.";
                    continue;
                }

                $data = [
                    'id' => $id,
                    'employeename' => $name ?: 'Employee',
                    'client' => $getVal('client') ?: 'Melcom',
                    'location' => $getVal('location') ?: 'Accra',
                    'position1' => $getVal('position1') ?: 'Staff',
                    'startdate' => $startDate,
                    'enddate' => $endDate,
                    'basic' => !empty($getVal('basic')) ? $getVal('basic') : null,
                    'employername' => $getVal('employername') ?: 'Strategic Business Partnership',
                    'issuedate' => $issueDate,
                    'hremail' => $hrEmailVal,
                    'email' => $empEmailVal,
                    'ghanacard' => !empty($getVal('ghanacard')) ? $getVal('ghanacard') : null,
                    'activate' => '0',
                ];

                $rules = [
                    'id' => ['required', 'string', 'max:50'],
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
                    foreach ($validator->errors()->all() as $err) {
                        $errors[] = "Row {$displayRowNumber}: {$err}";
                    }
                    continue;
                }

                $validRecords[] = $data;
            }

            if (!empty($errors)) {
                Log::error("Bulk upload errors: " . implode(' | ', array_slice($errors, 0, 20)));
                return redirect()->route('contracts.index')->with('error', implode(' | ', array_slice($errors, 0, 10)));
            }

            if (empty($validRecords)) {
                return redirect()->route('contracts.index')->with('error', 'No valid contract records found in the uploaded file.');
            }

            // Save/upsert records
            $savedCount = 0;
            foreach ($validRecords as $record) {
                $existing = Contract::find($record['id']);
                if ($existing) {
                    $record['activate'] = $existing->activate; // Preserve activation/signature status
                    if (!empty($existing->signature)) {
                        $record['signature'] = $existing->signature;
                    }
                }
                Contract::updateOrCreate(['id' => $record['id']], $record);
                $savedCount++;
            }

            return redirect()->route('contracts.index')->with('success', "Successfully processed and uploaded {$savedCount} contract(s).");
        } catch (\Exception $e) {
            Log::error('Bulk upload failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('contracts.index')->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
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
            'issuedate' => !empty($contract->issuedate) ? date('F d, Y', strtotime($contract->issuedate)) : date('F d, Y'),
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
