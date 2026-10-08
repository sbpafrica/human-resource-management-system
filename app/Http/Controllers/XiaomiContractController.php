<?php

namespace App\Http\Controllers;

use App\Models\XiaomiContract;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class XiaomiContractController extends Controller
{
    public function index(Request $request)
    {
        $query = XiaomiContract::query();
        $totalRecords = XiaomiContract::count();
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

        return Inertia::render('Xiaomi/Contracts/Index', [
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
            'id'                => ['required', 'string', 'max:20', 'unique:xiaomicon,id'],
            'employeename'      => ['required', 'string', 'max:100'],
            'client'            => ['nullable', 'string', 'max:100'],
            'location'          => ['required', 'string', 'max:100'],
            'position1'         => ['required', 'string', 'max:100'],
            'duration'          => ['required', 'integer', 'min:1'],
            'startdate'         => ['required', 'date'],
            'enddate'           => ['required', 'date', 'after_or_equal:startdate'],
            'basic'             => ['required', 'numeric', 'min:0'],
            'kpi'               => ['nullable', 'numeric', 'min:0'],
            'transport'         => ['nullable', 'numeric', 'min:0'],
            'basicafter'        => ['nullable', 'numeric', 'min:0'],
            'kpiafter'          => ['nullable', 'numeric', 'min:0'],
            'transportafter'    => ['nullable', 'numeric', 'min:0'],
            'employername'      => ['required', 'string', 'max:100'],
            'issuedate'         => ['required', 'date'],
            'hremail'           => ['required', 'email', 'max:255'],
            'ghanacard'         => ['nullable', 'string', 'max:100'],
            'digiaddress'       => ['nullable', 'string', 'max:255'],
        ]);

        $validated['activate'] = '0';

        XiaomiContract::create($validated);

        return redirect()->route('xiaomi.contracts.index')
            ->with('success', 'Xiaomi contract created successfully.');
    }

    public function update(Request $request, $id)
    {
        $contract = XiaomiContract::findOrFail($id);

        $validated = $request->validate([
            'id'                => ['required', 'string', 'max:20', \Illuminate\Validation\Rule::unique('xiaomicon')->ignore($contract->id)],
            'employeename'      => ['required', 'string', 'max:100'],
            'client'            => ['nullable', 'string', 'max:100'],
            'location'          => ['required', 'string', 'max:100'],
            'position1'         => ['required', 'string', 'max:100'],
            'duration'          => ['required', 'integer', 'min:1'],
            'startdate'         => ['required', 'date'],
            'enddate'           => ['required', 'date', 'after_or_equal:startdate'],
            'basic'             => ['required', 'numeric', 'min:0'],
            'kpi'               => ['nullable', 'numeric', 'min:0'],
            'transport'         => ['nullable', 'numeric', 'min:0'],
            'basicafter'        => ['nullable', 'numeric', 'min:0'],
            'kpiafter'          => ['nullable', 'numeric', 'min:0'],
            'transportafter'    => ['nullable', 'numeric', 'min:0'],
            'employername'      => ['required', 'string', 'max:100'],
            'issuedate'         => ['required', 'date'],
            'hremail'           => ['required', 'email', 'max:255'],
            'ghanacard'         => ['nullable', 'string', 'max:100'],
            'digiaddress'       => ['nullable', 'string', 'max:255'],
        ]);

        $contract->update($validated);

        return redirect()->route('xiaomi.contracts.index')
            ->with('success', 'Xiaomi contract updated successfully.');
    }

    public function destroy($id)
    {
        $contract = XiaomiContract::findOrFail($id);
        $contract->delete();

        return redirect()->route('xiaomi.contracts.index')
            ->with('success', 'Contract deleted successfully.');
    }

    public function show($id)
    {
        return response()->json(XiaomiContract::findOrFail($id));
    }

    public function download($id, Request $request)
    {
        $contract = XiaomiContract::findOrFail($id);

        if ($contract->activate !== '1' && !$request->has('no_signature')) {
            return redirect()->route('xiaomi.contracts.index')
                ->with('error', 'Contract not activated for download.');
        }

        $data = [
            'employeename'      => strtoupper($contract->employeename),
            'client'            => strtoupper($contract->client ?? 'Xiaomi'),
            'location'          => strtoupper($contract->location),
            'position'          => strtoupper($contract->position1),
            'duration'          => $contract->duration,
            'startdate'         => date('F d, Y', strtotime($contract->startdate)),
            'enddate'           => date('F d, Y', strtotime($contract->enddate)),
            'basic'             => number_format($contract->basic, 2),
            'kpi'               => $contract->kpi ? number_format($contract->kpi, 2) : '—',
            'transport'         => $contract->transport ? number_format($contract->transport, 2) : '—',
            'basicafter'        => $contract->basicafter ? number_format($contract->basicafter, 2) : '—',
            'kpiafter'          => $contract->kpiafter ? number_format($contract->kpiafter, 2) : '—',
            'transportafter'    => $contract->transportafter ? number_format($contract->transportafter, 2) : '—',
            'employername'      => strtoupper($contract->employername),
            'issuedate'         => date('F d, Y', strtotime($contract->issuedate)),
            'hremail'           => $contract->hremail,
            'signature'         => $request->has('no_signature') ? null : $contract->signature,
            'created_at'        => $contract->created_at,
            'ghanacard'         => $contract->ghanacard ?? '—',
            'digiaddress'       => $contract->digiaddress ?? '—',
        ];

        $pdf = Pdf::loadView('pdf.xiaomi-contract', $data)
            ->setPaper('a4', 'portrait');

        if ($request->has('no_signature')) {
            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="xiaomi_preview_' . $id . '.pdf"',
            ]);
        }

        return $pdf->download('xiaomi_contract_' . $id . '.pdf');
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Staff ID',           // A
            'Employee Name',      // B
            'Client',             // C
            'Location',           // D
            'Position',           // E
            'Duration (Months)',  // F
            'Start Date',         // G
            'End Date',           // H
            'Basic Salary',       // I
            'KPI',                // J
            'Transport',          // K
            'Basic After',        // L
            'KPI After',          // M
            'Transport After',    // N
            'Employer Name',      // O
            'Issue Date',         // P
            'HR Email',           // Q
            'Ghana Card',         // R
            'Digital Address'     // S
        ];

        $sheet->fromArray($headers, null, 'A1');

        $sample = [
            'XM002',                    // Staff ID
            'Kwame Asante',             // Employee Name
            'Xiaomi Ghana',             // Client
            'Spintex',                  // Location
            'Sales Executive',          // Position
            '12',                       // Duration
            '2026-02-01',               // Start Date
            '2027-01-31',               // End Date
            '3500.00',                  // Basic Salary
            '900.00',                   // KPI
            '500.00',                   // Transport
            '3800.00',                  // Basic After
            '1000.00',                  // KPI After
            '550.00',                   // Transport After
            'Annabelle',                // Employer Name
            '2026-01-15',               // Issue Date
            'hr@xiaomi.example.com',    // HR Email
            'GHA-987654321-0',          // Ghana Card
            'GA-987-6543'               // Digital Address
        ];

        $sheet->fromArray($sample, null, 'A2');

        foreach (range('A', 'S') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="xiaomi_contract_template.xlsx"');
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

            $existingIds = XiaomiContract::pluck('id')->toArray();
            $errors = [];
            $validRecords = [];

            foreach ($rows as $index => $row) {
                // Skip completely empty rows
                if (empty(trim($row['A'] ?? ''))) {
                    continue;
                }

                $rowErrors = [];

                $data = [
                    'id'             => trim($row['A'] ?? ''),
                    'employeename'   => trim($row['B'] ?? ''),
                    'client'         => trim($row['C'] ?? ''),
                    'location'       => trim($row['D'] ?? ''),
                    'position1'      => trim($row['E'] ?? ''),
                    'duration'       => (int)($row['F'] ?? 0),
                    'startdate'      => $this->parseDate($row['G'] ?? '', $index, 'Start Date', $rowErrors),
                    'enddate'        => $this->parseDate($row['H'] ?? '', $index, 'End Date', $rowErrors),
                    'basic'          => $this->parseNumeric($row['I'] ?? '', $index, 'Basic Salary', $rowErrors),
                    'kpi'            => $this->parseNumeric($row['J'] ?? '', $index, 'KPI', $rowErrors),
                    'transport'      => $this->parseNumeric($row['K'] ?? '', $index, 'Transport', $rowErrors),
                    'basicafter'     => $this->parseNumeric($row['L'] ?? '', $index, 'Basic After', $rowErrors),
                    'kpiafter'       => $this->parseNumeric($row['M'] ?? '', $index, 'KPI After', $rowErrors),
                    'transportafter' => $this->parseNumeric($row['N'] ?? '', $index, 'Transport After', $rowErrors),
                    'employername'   => trim($row['O'] ?? ''),
                    'issuedate'      => $this->parseDate($row['P'] ?? '', $index, 'Issue Date', $rowErrors),
                    'hremail'        => trim($row['Q'] ?? ''),
                    'ghanacard'      => trim($row['R'] ?? ''),
                    'digiaddress'    => trim($row['S'] ?? ''),
                    'activate'       => '0',
                ];

                // Required date checks
                if (in_array(null, [$data['startdate'], $data['enddate'], $data['issuedate']], true)) {
                    $errors = array_merge($errors, $rowErrors);
                    continue;
                }

                // Early duplicate check
                if (in_array($data['id'], $existingIds)) {
                    $errors[] = "Row " . ($index + 1) . ": Staff ID '{$data['id']}' already exists.";
                    continue;
                }

                // Full validation
                $validator = Validator::make($data, [
                    'id'                => 'required|string|max:20|regex:/^[A-Za-z0-9\-_]+$/',
                    'employeename'      => 'required|string|max:100',
                    'client'            => 'nullable|string|max:100',
                    'location'          => 'required|string|max:100',
                    'position1'         => 'required|string|max:100',
                    'duration'          => 'required|integer|min:1',
                    'startdate'         => 'required|date',
                    'enddate'           => 'required|date|after_or_equal:startdate',
                    'basic'             => 'required|numeric|min:0',
                    'kpi'               => 'nullable|numeric|min:0',
                    'transport'         => 'nullable|numeric|min:0',
                    'basicafter'        => 'nullable|numeric|min:0',
                    'kpiafter'          => 'nullable|numeric|min:0',
                    'transportafter'    => 'nullable|numeric|min:0',
                    'employername'      => 'required|string|max:100',
                    'issuedate'         => 'required|date',
                    'hremail'           => 'required|email|max:255',
                    'ghanacard'         => 'nullable|string|max:100',
                    'digiaddress'       => 'nullable|string|max:255',
                ]);

                if ($validator->fails()) {
                    $errors = array_merge($errors, array_map(
                        fn($e) => "Row " . ($index + 1) . ": " . $e,
                        $validator->errors()->all()
                    ));
                    continue;
                }

                // Valid record
                $validRecords[] = $data;
                $existingIds[] = $data['id'];
            }

            if (!empty($errors)) {
                return redirect()->back()->with('error', implode(' | ', $errors));
            }

            // Save all valid records
            foreach ($validRecords as $record) {
                XiaomiContract::create($record);
            }

            $count = count($validRecords);
            $message = $count === 1
                ? 'Successfully uploaded 1 Xiaomi contract.'
                : "Successfully uploaded {$count} Xiaomi contracts.";

            return redirect()->route('xiaomi.contracts.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Xiaomi bulk upload failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'An unexpected error occurred while processing the file. Please check the file format and try again.');
        }
    }

    private function parseDate($value, int $rowIndex, string $fieldName, array &$errors)
    {
        if (empty($value)) {
            $errors[] = "Row " . ($rowIndex + 1) . ": $fieldName is empty or missing.";
            return null;
        }

        // Handle Excel numeric dates
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
            $errors[] = "Row " . ($rowIndex + 1) . ": Invalid date format in $fieldName (value: '$value'). Use YYYY-MM-DD.";
            return null;
        }
    }

    private function parseNumeric($value, int $rowIndex, string $fieldName, array &$errors)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        $cleaned = str_replace(',', '', trim($value));
        if (!is_numeric($cleaned)) {
            $errors[] = "Row " . ($rowIndex + 1) . ": Invalid number format in $fieldName (value: '$value').";
            return null;
        }

        return floatval($cleaned);
    }
}
