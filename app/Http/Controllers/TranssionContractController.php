<?php

namespace App\Http\Controllers;

use App\Models\TranssionContract;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\TranssionContractUploaded;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class TranssionContractController extends Controller
{
    public function index(Request $request)
    {
        $query = TranssionContract::query();
        $totalRecords = TranssionContract::count();
        $searchCount = null;

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('employeename', 'like', "%{$search}%")
                  ->orWhere('client', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
            $searchCount = $query->count();
        }

        $sort = $request->input('sort', 'employeename');
        $order = $request->input('order', 'asc');
        $query->orderBy($sort, $order);

        $contracts = $query->paginate(10)->withQueryString();

        return Inertia::render('Transsion/Contracts/Index', [
            'contracts' => $contracts,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'order' => $order,
            ],
            'totalRecords' => $totalRecords,
            'searchCount' => $searchCount,
            'flash' => session()->only(['success', 'error']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id'                => ['required', 'string', 'max:20', 'unique:transsioncon,id'],
            'employeename'      => ['required', 'string', 'max:100'],
            'client'            => ['required', 'string', 'max:100'],
            'location'          => ['required', 'string', 'max:100'],
            'position1'         => ['required', 'string', 'max:100'],
            'department'        => ['required', 'string', 'max:255'],
            'duration'          => ['required', 'integer', 'min:1'],
            'startdate'         => ['required', 'date'],
            'enddate'           => ['required', 'date'],
            'basic'             => ['required', 'string', 'max:100'],
            'allowance'         => ['nullable', 'string', 'max:255'],
            'kpi'               => ['nullable', 'string', 'max:255'],
            'employername'      => ['required', 'string', 'max:100'],
            'issuedate'         => ['required', 'date'],
            'hremail'           => ['required', 'email', 'max:255'],
            'email'             => ['required', 'email', 'max:100'],
            'copymail'          => ['nullable', 'email', 'max:255'],
            'ghanacard'         => ['nullable', 'string', 'max:100'],
            'digiaddress'       => ['nullable', 'string', 'max:255'],
        ]);

        $validated['activate'] = '0';

        $contract = TranssionContract::create($validated);

        // Send email notification
        try {
            Mail::to($contract->email)
                ->cc($contract->copymail ?: 'it@sbpafrica.group')
                ->send(new TranssionContractUploaded([
                    'staffid'       => $contract->id,
                    'employeename'  => $contract->employeename,
                ]));
        } catch (\Exception $e) {
            Log::error('Failed to send contract email for single creation', [
                'id' => $contract->id,
                'error' => $e->getMessage(),
            ]);
            // We don't fail the creation if email fails
        }

        return redirect()->route('transsion.contracts.index')
            ->with('success', 'Contract created successfully.');
    }

    public function update(Request $request, $id)
    {
        $contract = TranssionContract::findOrFail($id);

        $validated = $request->validate([
            'id'                => ['required', 'string', 'max:20', \Illuminate\Validation\Rule::unique('transsioncon')->ignore($contract->id)],
            'employeename'      => ['required', 'string', 'max:100'],
            'client'            => ['required', 'string', 'max:100'],
            'location'          => ['required', 'string', 'max:100'],
            'position1'         => ['required', 'string', 'max:100'],
            'department'        => ['required', 'string', 'max:255'],
            'duration'          => ['required', 'integer', 'min:1'],
            'startdate'         => ['required', 'date'],
            'enddate'           => ['required', 'date'],
            'basic'             => ['required', 'string', 'max:100'],
            'allowance'         => ['nullable', 'string', 'max:255'],
            'kpi'               => ['nullable', 'string', 'max:255'],
            'employername'      => ['required', 'string', 'max:100'],
            'issuedate'         => ['required', 'date'],
            'hremail'           => ['required', 'email', 'max:255'],
            'email'             => ['required', 'email', 'max:100'],
            'copymail'          => ['nullable', 'email', 'max:255'],
            'ghanacard'         => ['nullable', 'string', 'max:100'],
            'digiaddress'       => ['nullable', 'string', 'max:255'],
        ]);

        $contract->update($validated);

        return redirect()->route('transsion.contracts.index')
            ->with('success', 'Contract updated successfully.');
    }

    public function destroy($id)
    {
        $contract = TranssionContract::findOrFail($id);
        $contract->delete();

        return redirect()->route('transsion.contracts.index')
            ->with('success', 'Contract deleted successfully.');
    }

    public function show($id)
    {
        return response()->json(TranssionContract::findOrFail($id));
    }

    public function download($id, Request $request)
    {
        $contract = TranssionContract::findOrFail($id);

        if ($contract->activate !== '1' && !$request->has('no_signature')) {
            return redirect()->route('transsion.contracts.index')
                ->with('error', 'Contract not activated for download.');
        }

        $data = [
            'employeename'   => strtoupper($contract->employeename),
            'client'         => strtoupper($contract->client),
            'location'       => strtoupper($contract->location),
            'position'       => strtoupper($contract->position1),
            'department'     => strtoupper($contract->department),
            'duration'       => $contract->duration,
            'startdate'      => date('F d, Y', strtotime($contract->startdate)),
            'enddate'        => date('F d, Y', strtotime($contract->enddate)),
            'basic'          => $contract->basic,
            'allowance'      => $contract->allowance ?? 'N/A',
            'kpi'            => $contract->kpi ?? 'N/A',
            'employername'   => strtoupper($contract->employername),
            'issuedate'      => date('F d, Y', strtotime($contract->issuedate)),
            'hremail'        => $contract->hremail,
            'email'          => $contract->email,
            'signature'      => $request->has('no_signature') ? null : $contract->signature,
            'created_at'     => $contract->created_at,
        ];

        $pdf = Pdf::loadView('pdf.transsion-contract', $data)
            ->setPaper('a4', 'portrait');

        if ($request->has('no_signature')) {
            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="transsion_preview_' . $id . '.pdf"',
            ]);
        }

        return $pdf->download('transsion_contract_' . $id . '.pdf');
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Staff ID', 'Employee Name', 'Client', 'Location', 'Position',
            'Department', 'Duration (Months)', 'Start Date', 'End Date',
            'Basic Salary', 'Allowance','Kpi', 'Employer Name', 'Issue Date',
            'HR Email', 'Employee Email', 'Copy Email'
        ];
        $sheet->fromArray($headers, null, 'A1');

        $sample = [
            'TR001', 'John Doe', 'Transsion', 'Accra Central', 'Sales Rep',
            'Sales', '12', '2025-01-01', '2025-12-31', '2500', '500','500',
            'Ann', '2025-01-01', 'hr@sbpafrica.group', 'john@example.com', 'it@sbpafrica.group'
        ];
        $sheet->fromArray($sample, null, 'A2');

        foreach (range('A', 'Q') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="transsion_contract_template.xlsx"');
        $writer->save('php://output');
        exit;
    }

    public function bulkUpload(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:xlsx,xls|max:10240', // 10MB max
    ]);

    try {
        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        // Skip header row
        array_shift($rows);

        $existingIds = TranssionContract::pluck('id')->toArray();
        $errors = [];
        $validRecords = [];
        $emailQueue = [];

        foreach ($rows as $index => $row) {
            // Skip completely empty rows
            if (empty(trim($row['A'] ?? ''))) {
                continue;
            }

            $rowErrors = []; // Collect errors for this row only

            $data = [
                'id'           => trim($row['A'] ?? ''),
                'employeename' => trim($row['B'] ?? ''),
                'client'       => trim($row['C'] ?? ''),
                'location'     => trim($row['D'] ?? ''),
                'position1'    => trim($row['E'] ?? ''),
                'department'   => trim($row['F'] ?? ''),
                'duration'     => (int)($row['G'] ?? 0),
                'startdate'    => $this->parseDate($row['H'] ?? '', $index, 'Start Date', $rowErrors),
                'enddate'      => $this->parseDate($row['I'] ?? '', $index, 'End Date', $rowErrors),
                'basic'        => trim($row['J'] ?? ''),
                'allowance'    => trim($row['K'] ?? ''),
                'kpi'          => trim($row['L'] ?? ''),
                'employername' => trim($row['M'] ?? ''),
                'issuedate'    => $this->parseDate($row['N'] ?? '', $index, 'Issue Date', $rowErrors),
                'hremail'      => trim($row['O'] ?? ''),
                'email'        => trim($row['P'] ?? ''),
                'copymail'     => trim($row['Q'] ?? ''),
                'activate'     => '0',
            ];

            // If any required date is invalid/missing, skip this row
            if (in_array(null, [$data['startdate'], $data['enddate'], $data['issuedate']], true)) {
                $errors = array_merge($errors, $rowErrors);
                continue;
            }

            // Check for duplicate ID early
            if (in_array($data['id'], $existingIds)) {
                $errors[] = "Row " . ($index + 1) . ": Staff ID '{$data['id']}' already exists.";
                continue;
            }

            // Full validation
            $validator = Validator::make($data, [
                'id'           => 'required|string|max:20|regex:/^[A-Za-z0-9\-_]+$/',
                'employeename' => 'required|string|max:100',
                'client'       => 'required|string|max:100',
                'location'     => 'required|string|max:100',
                'position1'    => 'required|string|max:100',
                'department'   => 'required|string|max:255',
                'duration'     => 'required|integer|min:1',
                'startdate'    => 'required|date',
                'enddate'      => 'required|date|after_or_equal:startdate',
                'basic'        => 'required|string|max:100',
                'employername' => 'required|string|max:100',
                'issuedate'    => 'required|date',
                'hremail'      => 'required|email|max:255',
                'email'        => 'required|email|max:100',
            ]);

            if ($validator->fails()) {
                $errors = array_merge($errors, array_map(
                    fn($e) => "Row " . ($index + 1) . ": " . $e,
                    $validator->errors()->all()
                ));
                continue;
            }

            // Row is valid → store it
            $validRecords[] = $data;
            $existingIds[] = $data['id'];

            // Queue email
            $emailQueue[] = [
                'staffid'      => $data['id'],
                'employeename' => $data['employeename'],
                'email'        => $data['email'],
                'copymail'     => $data['copymail'] ?: 'it@sbpafrica.group',
            ];
        }

        // If there were any errors, return them to user
        if (!empty($errors)) {
            return redirect()->back()->with('error', implode(' | ', $errors));
        }

        // Save all valid records
        foreach ($validRecords as $record) {
            TranssionContract::create($record);
        }

        // Send emails
        foreach ($emailQueue as $emp) {
            try {
                Mail::to($emp['email'])
                    ->cc($emp['copymail'])
                    ->send(new TranssionContractUploaded([
                        'staffid'      => $emp['staffid'],
                        'employeename' => $emp['employeename'],
                    ]));
            } catch (\Exception $e) {
                Log::error('Failed to send contract email during bulk upload', [
                    'staffid' => $emp['staffid'],
                    'error'   => $e->getMessage(),
                ]);
                // Continue even if email fails - don't block the upload
            }
        }

        $count = count($validRecords);
        $message = $count === 1
            ? 'Successfully uploaded 1 Transsion contract and sent email.'
            : "Successfully uploaded {$count} Transsion contracts and sent emails.";

        return redirect()->route('transsion.contracts.index')
            ->with('success', $message);

    } catch (\Exception $e) {
        Log::error('Bulk upload failed', ['error' => $e->getMessage()]);
        return redirect()->back()->with('error', 'An unexpected error occurred while processing the file. Please check the file format and try again.');
    }
}

    private function parseDate($value, int $rowIndex, string $fieldName, array &$errors)
{
    if (empty($value)) {
        $errors[] = "Row " . ($rowIndex + 1) . ": $fieldName is empty or missing.";
        return null;
    }

    // Handle Excel numeric dates (serial numbers)
    if (is_numeric($value) && $value > 0) {
        try {
            return ExcelDate::excelToDateTimeObject(floatval($value))->format('Y-m-d');
        } catch (\Exception $e) {
            $errors[] = "Row " . ($rowIndex + 1) . ": Invalid Excel date format in $fieldName (value: $value).";
            return null;
        }
    }

    // Handle string dates
    try {
        $date = Carbon::parse(trim($value));
        return $date->format('Y-m-d');
    } catch (\Exception $e) {
        $errors[] = "Row " . ($rowIndex + 1) . ": Invalid date format in $fieldName (value: '$value'). Use YYYY-MM-DD (e.g., 2025-12-25).";
        return null;
    }
}
}
