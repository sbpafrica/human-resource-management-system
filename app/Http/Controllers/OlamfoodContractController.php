<?php

namespace App\Http\Controllers;

use App\Models\OlamfoodContract;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class OlamfoodContractController extends Controller
{
    public function index(Request $request)
    {
        $query = OlamfoodContract::query();
        $totalRecords = OlamfoodContract::count();
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

        return Inertia::render('Olamfood/Contracts/Index', [
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
            'id'                => ['required', 'string', 'max:20', 'unique:olamfoodcon2,id'],
            'employeename'      => ['required', 'string', 'max:100'],
            'client'            => ['nullable', 'string', 'max:100'],
            'location'          => ['required', 'string', 'max:100'],
            'position1'         => ['required', 'string', 'max:100'],
            'duration'          => ['required', 'integer', 'min:1'],
            'startdate'         => ['required', 'date'],
            'enddate'           => ['required', 'date', 'after_or_equal:startdate'],
            'basic'             => ['required', 'string', 'max:100'],
            'employername'      => ['required', 'string', 'max:100'],
            'issuedate'         => ['required', 'date'],
            'hremail'           => ['required', 'email', 'max:255'],
            'ghanacard'         => ['nullable', 'string', 'max:100'],
            'digiaddress'       => ['nullable', 'string', 'max:255'],
        ]);

        $validated['activate'] = '0';

        OlamfoodContract::create($validated);

        return redirect()->route('olamfood.contracts.index')
            ->with('success', 'Olam Food contract created successfully.');
    }

    public function update(Request $request, $id)
    {
        $contract = OlamfoodContract::findOrFail($id);

        $validated = $request->validate([
            'id'                => ['required', 'string', 'max:20', \Illuminate\Validation\Rule::unique('olamfoodcon2')->ignore($contract->id)],
            'employeename'      => ['required', 'string', 'max:100'],
            'client'            => ['nullable', 'string', 'max:100'],
            'location'          => ['required', 'string', 'max:100'],
            'position1'         => ['required', 'string', 'max:100'],
            'duration'          => ['required', 'integer', 'min:1'],
            'startdate'         => ['required', 'date'],
            'enddate'           => ['required', 'date', 'after_or_equal:startdate'],
            'basic'             => ['required', 'string', 'max:100'],
            'employername'      => ['required', 'string', 'max:100'],
            'issuedate'         => ['required', 'date'],
            'hremail'           => ['required', 'email', 'max:255'],
            'ghanacard'         => ['nullable', 'string', 'max:100'],
            'digiaddress'       => ['nullable', 'string', 'max:255'],
        ]);

        $contract->update($validated);

        return redirect()->route('olamfood.contracts.index')
            ->with('success', 'Olam Food contract updated successfully.');
    }

    public function destroy($id)
    {
        $contract = OlamfoodContract::findOrFail($id);
        $contract->delete();

        return redirect()->route('olamfood.contracts.index')
            ->with('success', 'Contract deleted successfully.');
    }

    public function show($id)
{
    $contract = OlamfoodContract::findOrFail($id);

    // Prepare base64 images for preview
    $headerPath = public_path('images/sbphead.png');
    $footerPath = public_path('images/footer.png');
    $employerSigPath = public_path('images/Annsign.png');
    $employeeSigPath = $contract->signature
        ? public_path('images/olamfoodcon/signatures/' . $contract->signature)
        : null;

    $headerImg = $this->imageToBase64($headerPath, 'HEADER MISSING');
    $footerImg = $this->imageToBase64($footerPath, 'FOOTER MISSING');
    $employerSigImg = $this->imageToBase64($employerSigPath, 'EMPLOYER SIGN MISSING');
    $employeeSigImg = $this->imageToBase64($employeeSigPath, 'EMPLOYEE SIGN MISSING');

    // Return JSON with contract + base64 images
    return response()->json([
        'contract' => $contract,
        'headerImg' => $headerImg,
        'footerImg' => $footerImg,
        'employerSigImg' => $employerSigImg,
        'employeeSigImg' => $employeeSigImg,
    ]);
}

    public function download($id, Request $request)
{
    $contract = OlamfoodContract::findOrFail($id);

    if ($contract->activate !== '1' && !$request->has('no_signature')) {
        return redirect()->route('olamfood.contracts.index')
            ->with('error', 'Contract not activated for download.');
    }

    // Prepare base64 images
    $headerPath = public_path('images/sbphead.png');
    $footerPath = public_path('images/footer.png');
    $employerSigPath = public_path('images/Annsign.png');
    $employeeSigPath = $contract->signature
        ? public_path('images/olamfoodcon/signatures/' . $contract->signature)
        : null;

    $headerImg = $this->imageToBase64($headerPath, 'HEADER MISSING');
    $footerImg = $this->imageToBase64($footerPath, 'FOOTER MISSING');
    $employerSigImg = $this->imageToBase64($employerSigPath, 'EMPLOYER SIGN MISSING');

    // Old employee signature logic
    $employeeSigImg = ($contract->signature && file_exists($employeeSigPath))
        ? '<img src="' . $employeeSigPath . '" class="signature-img-employee">'
        : '<div style="width:150px; height:50px; border:1px dashed #999; margin:0 auto;"></div>';

    $data = [
        'employeename'     => $contract->employeename,
        'startdate'        => $contract->startdate,
        'enddate'          => $contract->enddate,
        'issuedate'        => $contract->issuedate,
        'updated_at'       => $contract->updated_at,
        'signature'        => $contract->signature,
        'headerImg'        => $headerImg,
        'footerImg'        => $footerImg,
        'employerSigImg'   => $employerSigImg,
        'employeeSigImg'   => $employeeSigImg,
    ];

    $pdf = Pdf::loadView('pdf.olamfood-contract', $data)
        ->setPaper('a4', 'portrait');

    if ($request->has('no_signature')) {
        // Preview: stream inline (no signature in most cases)
        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="olamfood_preview_' . $id . '.pdf"',
        ]);
    }

    // Normal download
    return $pdf->download('olamfood_contract_' . $id . '.pdf');
}

/**
 * Helper: Convert image to base64
 */
private function imageToBase64($path, $fallback = '')
{
    if (file_exists($path)) {
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
    return $fallback; // Return empty or fallback message
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
            'Employer Name',      // J
            'Issue Date',         // K
            'HR Email',           // L
            'Ghana Card',         // M
            'Digital Address'     // N
        ];

        $sheet->fromArray($headers, null, 'A1');

        $sample = [
            'OLM001', 'Fatima Mensah', 'Olam Food', 'Tema', 'Logistics Officer',
            '12', '2026-02-01', '2027-01-31', '2800.00',
            'Ann', '2026-01-20', 'hr@olamfood.com',
            'GHA-567890123-4', 'TG-456-7890'
        ];

        $sheet->fromArray($sample, null, 'A2');

        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="olamfood_contract_template.xlsx"');
        $writer->save('php://output');
        exit;
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|extensions:xlsx,xls,csv,ods|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

            array_shift($rows); // Skip header

            $existingIds = OlamfoodContract::pluck('id')->toArray();
            $errors = [];
            $validRecords = [];

            foreach ($rows as $index => $row) {
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
                    'basic'          => trim($row['I'] ?? ''),
                    'employername'   => trim($row['J'] ?? ''),
                    'issuedate'      => $this->parseDate($row['K'] ?? '', $index, 'Issue Date', $rowErrors),
                    'hremail'        => trim($row['L'] ?? ''),
                    'ghanacard'      => trim($row['M'] ?? ''),
                    'digiaddress'    => trim($row['N'] ?? ''),
                    'activate'       => '0',
                ];

                if (in_array(null, [$data['startdate'], $data['enddate'], $data['issuedate']], true)) {
                    $errors = array_merge($errors, $rowErrors);
                    continue;
                }

                if (in_array($data['id'], $existingIds)) {
                    $errors[] = "Row " . ($index + 1) . ": Staff ID '{$data['id']}' already exists.";
                    continue;
                }

                $validator = Validator::make($data, [
                    'id'                => 'required|string|max:20|regex:/^[A-Za-z0-9\-_]+$/',
                    'employeename'      => 'required|string|max:100',
                    'client'            => 'nullable|string|max:100',
                    'location'          => 'required|string|max:100',
                    'position1'         => 'required|string|max:100',
                    'duration'          => 'required|integer|min:1',
                    'startdate'         => 'required|date',
                    'enddate'           => 'required|date|after_or_equal:startdate',
                    'basic'             => 'required|string|max:100',
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

                $validRecords[] = $data;
                $existingIds[] = $data['id'];
            }

            if (!empty($errors)) {
                return redirect()->back()->with('error', implode(' | ', $errors));
            }

            foreach ($validRecords as $record) {
                OlamfoodContract::create($record);
            }

            $count = count($validRecords);
            $message = $count === 1
                ? 'Successfully uploaded 1 Olam Food contract.'
                : "Successfully uploaded {$count} Olam Food contracts.";

            return redirect()->route('olamfood.contracts.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Olam Food bulk upload failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'An unexpected error occurred while processing the file.');
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
                $errors[] = "Row " . ($rowIndex + 1) . ": Invalid Excel date in $fieldName.";
                return null;
            }
        }

        try {
            $date = Carbon::parse(trim($value));
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            $errors[] = "Row " . ($rowIndex + 1) . ": Invalid date format in $fieldName.";
            return null;
        }
    }
}
