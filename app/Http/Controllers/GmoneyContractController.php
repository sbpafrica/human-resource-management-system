<?php

namespace App\Http\Controllers;

use App\Models\GmoneyCon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class GmoneyContractController extends Controller
{
    public function index(Request $request)
    {
        $query        = GmoneyCon::query();
        $totalRecords = GmoneyCon::count();
        $searchCount  = null;

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('employeename', 'like', "%{$search}%")
                  ->orWhere('client', 'like', "%{$search}%");
            });
            $searchCount = $query->count();
        }

        $sort  = $request->input('sort', 'employeename');
        $order = $request->input('order', 'asc');
        $query->orderBy($sort, $order);

        $contracts = $query->paginate(10)->withQueryString();

        return Inertia::render('Gmoney/Contracts/Index', [
            'contracts'    => $contracts,
            'filters'      => ['search' => $search, 'sort' => $sort, 'order' => $order],
            'totalRecords' => $totalRecords,
            'searchCount'  => $searchCount,
            'flash'        => session()->only(['success', 'error']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id'           => ['required', 'string', 'max:20', 'unique:gmoneycon,id'],
            'employeename' => ['required', 'string', 'max:100'],
            'ghanacard'    => ['nullable', 'string', 'max:100'],
            'digiaddress'  => ['nullable', 'string', 'max:255'],
            'position1'    => ['required', 'string', 'max:100'],
            'client'       => ['nullable', 'string', 'max:100'],
            'location'     => ['required', 'string', 'max:100'],
            'startdate'    => ['required', 'date'],
            'issuedate'    => ['required', 'date'],
            'basic'        => ['required', 'numeric', 'min:0'],
            'transport'    => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['activate'] = 0;

        GmoneyCon::create($validated);

        return redirect()->route('gmoney.contracts.index')
            ->with('success', 'G-Money contract created successfully.');
    }

    public function update(Request $request, $id)
    {
        $contract = GmoneyCon::findOrFail($id);

        $validated = $request->validate([
            'id'           => ['required', 'string', 'max:20', \Illuminate\Validation\Rule::unique('gmoneycon')->ignore($contract->id)],
            'employeename' => ['required', 'string', 'max:100'],
            'ghanacard'    => ['nullable', 'string', 'max:100'],
            'digiaddress'  => ['nullable', 'string', 'max:255'],
            'position1'    => ['required', 'string', 'max:100'],
            'client'       => ['nullable', 'string', 'max:100'],
            'location'     => ['required', 'string', 'max:100'],
            'startdate'    => ['required', 'date'],
            'issuedate'    => ['required', 'date'],
            'basic'        => ['required', 'numeric', 'min:0'],
            'transport'    => ['nullable', 'numeric', 'min:0'],
        ]);

        $contract->update($validated);

        return redirect()->route('gmoney.contracts.index')
            ->with('success', 'G-Money contract updated successfully.');
    }

    public function destroy($id)
    {
        GmoneyCon::findOrFail($id)->delete();

        return redirect()->route('gmoney.contracts.index')
            ->with('success', 'Contract deleted successfully.');
    }

    public function show($id)
    {
        return response()->json(GmoneyCon::findOrFail($id));
    }

    public function download($id, Request $request)
    {
        $contract = GmoneyCon::findOrFail($id);

        if ($contract->activate != 1 && !$request->has('no_signature')) {
            return redirect()->route('gmoney.contracts.index')
                ->with('error', 'Contract not activated for download.');
        }

        $data = [
            'employeename' => strtoupper($contract->employeename),
            'client'       => strtoupper($contract->client ?? 'G-MONEY FINANCIAL SERVICES LIMITED'),
            'location'     => strtoupper($contract->location),
            'position'     => strtoupper($contract->position1),
            'startdate'    => $contract->startdate->format('Y-m-d'),
            'issuedate'    => $contract->issuedate->format('Y-m-d'),
            'basic'        => number_format($contract->basic, 2),
            'transport'    => $contract->transport ? number_format($contract->transport, 2) : '—',
            'ghanacard'    => $contract->ghanacard ?? '—',
            'digiaddress'  => $contract->digiaddress ?? '—',
            'signature'    => $request->has('no_signature') ? null : $contract->signature,
            'created_at'   => $contract->created_at,
        ];

        $pdf = Pdf::loadView('pdf.gmoney-contract', $data)
            ->setPaper('a4', 'portrait');

        if ($request->has('no_signature')) {
            return response($pdf->output(), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="gmoney_preview_' . $id . '.pdf"',
            ]);
        }

        return $pdf->download('gmoney_contract_' . $id . '.pdf');
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $headers = [
            'Staff ID',        // A
            'Employee Name',   // B
            'Ghana Card',      // C
            'Digital Address', // D
            'Position',        // E
            'Client',          // F
            'Location',        // G
            'Start Date',      // H
            'Issue Date',      // I
            'Basic Salary',    // J
            'Transport',       // K
        ];

        $sheet->fromArray($headers, null, 'A1');

        $sample = [
            'GM001',
            'Kofi Mensah',
            'GHA-123456789-0',
            'GA-123-4567',
            'Field Agent',
            'G-Money Financial Services Limited',
            'Accra',
            '2026-03-01',
            '2026-02-15',
            '2500.00',
            '400.00',
        ];

        $sheet->fromArray($sample, null, 'A2');

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="gmoney_contract_template.xlsx"');
        $writer->save('php://output');
        exit;
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|extensions:xlsx,xls,csv,ods|max:10240',
        ]);

        try {
            $file        = $request->file('file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

            array_shift($rows); // remove header row

            $existingIds  = GmoneyCon::pluck('id')->toArray();
            $errors       = [];
            $validRecords = [];

            foreach ($rows as $index => $row) {
                if (empty(trim($row['A'] ?? ''))) continue;

                $rowErrors = [];

                $data = [
                    'id'           => trim($row['A'] ?? ''),
                    'employeename' => trim($row['B'] ?? ''),
                    'ghanacard'    => trim($row['C'] ?? '') ?: null,
                    'digiaddress'  => trim($row['D'] ?? '') ?: null,
                    'position1'    => trim($row['E'] ?? ''),
                    'client'       => trim($row['F'] ?? '') ?: null,
                    'location'     => trim($row['G'] ?? ''),
                    'startdate'    => $this->parseDate($row['H'] ?? '', $index, 'Start Date', $rowErrors),
                    'issuedate'    => $this->parseDate($row['I'] ?? '', $index, 'Issue Date', $rowErrors),
                    'basic'        => $this->parseNumeric($row['J'] ?? '', $index, 'Basic Salary', $rowErrors),
                    'transport'    => $this->parseNumeric($row['K'] ?? '', $index, 'Transport', $rowErrors),
                    'activate'     => 0,
                ];

                if (in_array(null, [$data['startdate'], $data['issuedate']], true)) {
                    $errors = array_merge($errors, $rowErrors);
                    continue;
                }

                if (in_array($data['id'], $existingIds)) {
                    $errors[] = "Row " . ($index + 1) . ": Staff ID '{$data['id']}' already exists.";
                    continue;
                }

                $validator = Validator::make($data, [
                    'id'           => 'required|string|max:20',
                    'employeename' => 'required|string|max:100',
                    'ghanacard'    => 'nullable|string|max:100',
                    'digiaddress'  => 'nullable|string|max:255',
                    'position1'    => 'required|string|max:100',
                    'client'       => 'nullable|string|max:100',
                    'location'     => 'required|string|max:100',
                    'startdate'    => 'required|date',
                    'issuedate'    => 'required|date',
                    'basic'        => 'required|numeric|min:0',
                    'transport'    => 'nullable|numeric|min:0',
                ]);

                if ($validator->fails()) {
                    $errors = array_merge($errors, array_map(
                        fn($e) => "Row " . ($index + 1) . ": $e",
                        $validator->errors()->all()
                    ));
                    continue;
                }

                $validRecords[] = $data;
                $existingIds[]  = $data['id'];
            }

            if (!empty($errors)) {
                return redirect()->back()->with('error', implode(' | ', $errors));
            }

            foreach ($validRecords as $record) {
                GmoneyCon::create($record);
            }

            $count = count($validRecords);
            return redirect()->route('gmoney.contracts.index')
                ->with('success', "Successfully uploaded {$count} G-Money contract(s).");

        } catch (\Exception $e) {
            Log::error('G-Money bulk upload failed', ['error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'An unexpected error occurred. Please check the file format and try again.');
        }
    }

    private function parseDate($value, int $rowIndex, string $fieldName, array &$errors)
    {
        if (empty($value)) {
            $errors[] = "Row " . ($rowIndex + 1) . ": $fieldName is empty or missing.";
            return null;
        }
        if (is_numeric($value) && $value > 0) {
            try {
                return ExcelDate::excelToDateTimeObject(floatval($value))->format('Y-m-d');
            } catch (\Exception $e) {
                $errors[] = "Row " . ($rowIndex + 1) . ": Invalid Excel date in $fieldName (value: $value).";
                return null;
            }
        }
        try {
            return Carbon::parse(trim($value))->format('Y-m-d');
        } catch (\Exception $e) {
            $errors[] = "Row " . ($rowIndex + 1) . ": Invalid date in $fieldName (value: '$value'). Use YYYY-MM-DD.";
            return null;
        }
    }

    private function parseNumeric($value, int $rowIndex, string $fieldName, array &$errors)
    {
        if ($value === '' || $value === null) return null;
        $cleaned = str_replace(',', '', trim($value));
        if (!is_numeric($cleaned)) {
            $errors[] = "Row " . ($rowIndex + 1) . ": Invalid number in $fieldName (value: '$value').";
            return null;
        }
        return floatval($cleaned);
    }
}
