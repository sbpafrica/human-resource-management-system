<?php

namespace App\Http\Controllers;

use App\Models\TranssionContract;
use App\Models\TranssionContractRenewal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;
use App\Mail\TranssionRenewalNotification;
use App\Mail\TranssionRenewalSigned;

class TranssionContractRenewalController extends Controller
{
    public function index(Request $request)
    {
        $query = TranssionContractRenewal::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%") // original contract id
                  ->orWhere('employeename', 'like', "%{$search}%")
                  ->orWhere('contract_id', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'issuedate');
        $order = $request->input('order', 'desc');

        $renewals = $query->orderBy($sort, $order)
            ->paginate(10)
            ->withQueryString();

        return inertia('Transsion/Renewals/Index', [
            'renewals' => $renewals,
            'filters'  => $request->only(['search', 'sort', 'order']),
            'flash'    => session()->only(['success', 'error']),
        ]);
    }

    public function create($originalId)
    {
        $original = TranssionContract::findOrFail($originalId);

        return inertia('Transsion/Renewals/Create', [
            'original' => $original,
            'defaultData' => [
                'id'           => $original->id,
                'employeename' => $original->employeename,
                'position1'    => $original->position1,
                'department'   => $original->department,
                'employername' => $original->employername,
                'hremail'      => $original->hremail,
                'email'        => $original->email,
                'copymail'     => $original->copymail ?? null,
            ]
        ]);
    }

    public function store(Request $request)
{
    $validated = $request->validate([
        'id' => 'required|string|max:20',
        'employeename'   => 'required|string|max:100',
        'position1'      => 'required|string|max:255',
        'department'     => 'required|string|max:255',
        'startdate'      => 'required|date',
        'enddate'        => 'required|date|after:startdate',
        'expireddate'    => 'required|date',
        'employername'   => 'required|string|max:100',
        'issuedate'      => 'required|date',
        'hremail'        => 'required|email|max:255',
        'email'          => 'nullable|email|max:100',
        'copymail'       => 'nullable|email|max:255',
    ]);

    $original = TranssionContract::findOrFail($validated['id']);

    // Business rules
    if ($validated['startdate'] === $original->startdate && $validated['enddate'] === $original->enddate) {
        return back()->withErrors(['startdate' => 'Renewal period cannot match original contract period'])->withInput();
    }

    if (strtotime($validated['startdate']) <= strtotime($original->enddate)) {
        return back()->withErrors(['startdate' => 'Renewal must start after original end date'])->withInput();
    }

    DB::beginTransaction();

    try {
        $renewal = TranssionContractRenewal::create([
            'id'             => $validated['id'],
            'employeename'   => $validated['employeename'],
            'position1'      => $validated['position1'],
            'department'     => $validated['department'],
            'startdate'      => $validated['startdate'],
            'enddate'        => $validated['enddate'],
            'expireddate'    => $validated['expireddate'],
            'employername'   => $validated['employername'],
            'issuedate'      => $validated['issuedate'],
            'hremail'        => $validated['hremail'],
            'email'          => $validated['email'],
            'copymail'       => $validated['copymail'],
            'activate'       => '1',
        ]);

        // Generate PDF in memory
        $pdfData = [
            'contract'      => $renewal,
            'employeename'  => strtoupper($renewal->employeename),
            'position'      => strtoupper($renewal->position1),
            'startdate'     => date('F d, Y', strtotime($renewal->startdate)),
            'enddate'       => date('F d, Y', strtotime($renewal->enddate)),
            'expireddate'   => date('F d, Y', strtotime($renewal->expireddate)),
            'issuedate'     => date('F d, Y', strtotime($renewal->issuedate)),
            'employername'  => strtoupper($renewal->employername),
            'signature'     => null, // No signature yet
            'datecreated'   => $renewal->created_at->format('F d, Y'),
        ];

        $pdf = Pdf::loadView('pdf.transsion-contract-addendum', $pdfData)
            ->setPaper('a4', 'portrait');

        // Temporary path for attachment
        $tempPdfPath = storage_path('app/temp/renewal_' . $renewal->id . '.pdf');
        $pdf->save($tempPdfPath);

        // Prepare email data
        $emailData = [
            'employeename' => $renewal->employeename,
            'staffid'      => $renewal->id, // original contract ID
            'startdate'    => date('F d, Y', strtotime($renewal->startdate)),
            'enddate'      => date('F d, Y', strtotime($renewal->enddate)),
        ];

        // Send email with PDF attachment
        if ($renewal->email) {
            try {
                Mail::to($renewal->email)
                    ->cc($renewal->copymail ?: $renewal->hremail)
                    ->send(new TranssionRenewalNotification($emailData, $tempPdfPath));

                Log::info('Renewal notification with PDF sent', ['id' => $renewal->id]);
            } catch (\Exception $e) {
                Log::error('Failed to send renewal email with PDF', [
                    'id' => $renewal->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Clean up temp file
        if (file_exists($tempPdfPath)) {
            unlink($tempPdfPath);
        }

        DB::commit();

        return redirect()->route('transsion.contract-renewals.index')
            ->with('success', 'Renewal addendum created and notification sent.');

    } catch (\Exception $e) {
        DB::rollBack();
        if (isset($tempPdfPath) && file_exists($tempPdfPath)) {
            unlink($tempPdfPath);
        }
        Log::error('Renewal creation failed', ['error' => $e->getMessage()]);
        return back()->with('error', 'Failed to create renewal.')->withInput();
    }
}

    public function edit($contract_id)
    {
        $renewal = TranssionContractRenewal::findOrFail($contract_id);

        return inertia('Transsion/Renewals/Edit', [
            'renewal' => $renewal,
        ]);
    }

    public function update(Request $request, $contract_id)
    {
        $renewal = TranssionContractRenewal::findOrFail($contract_id);

        $validator = Validator::make($request->all(), [
            'employeename'   => 'required|string|max:100',
            'position1'      => 'required|string|max:255',
            'department'     => 'required|string|max:255',
            'startdate'      => 'required|date',
            'enddate'        => 'required|date|after:startdate',
            'expireddate'    => 'required|date',
            'employername'   => 'required|string|max:100',
            'issuedate'      => 'required|date',
            'hremail'        => 'required|email|max:255',
            'email'          => 'nullable|email|max:100',
            'copymail'       => 'nullable|email|max:255',
            'activate'       => 'required|in:0,1', // Toggle for active status
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        /*
         * Update the renewal record itself.
         *
         * Bulk-uploaded renewals may not have a matching record in
         * TranssionContract. In that case, do not call findOrFail()
         * on the original contract. This is especially important when
         * an HR user only changes Activate from No to Yes.
         *
         * If an original Transsion contract exists, keep the original
         * business-rule checks for renewal dates.
         */
        $original = TranssionContract::find($renewal->id);

        if ($original) {
            if (
                $data['startdate'] === $original->startdate &&
                $data['enddate'] === $original->enddate
            ) {
                return back()
                    ->withErrors(['startdate' => 'Renewal period cannot be identical to the original contract'])
                    ->withInput();
            }

            if (strtotime($data['startdate']) <= strtotime($original->enddate)) {
                return back()
                    ->withErrors(['startdate' => 'Renewal must start after original contract end date'])
                    ->withInput();
            }
        }

        $renewal->update($data);

        return redirect()
            ->route('transsion.contract-renewals.index')
            ->with('success', 'Renewal addendum updated successfully.');
    }

    public function show($contract_id)
    {
        $renewal = TranssionContractRenewal::findOrFail($contract_id);

        return response()->json($renewal);
    }

    public function download($contract_id, Request $request)
    {
        // Try finding by primary key contract_id first, or fallback to staff ID 'id'
        $renewal = TranssionContractRenewal::find($contract_id);
        if (!$renewal) {
            $renewal = TranssionContractRenewal::where('id', $contract_id)->latest('contract_id')->first();
        }

        if (!$renewal) {
            return redirect()
                ->route('transsion.contract-renewals.index')
                ->with('error', 'Renewal document not found.');
        }

        // Auto-activate if not already activated
        if ($renewal->activate !== '1') {
            $renewal->update(['activate' => '1']);
        }

        // Resolve signature: use renewal signature if present, or fallback to original contract signature
        $signature = $request->has('no_signature') ? null : $renewal->signature;
        if (!$signature && !$request->has('no_signature')) {
            $originalContract = TranssionContract::where('id', $renewal->id)->first();
            if ($originalContract && !empty($originalContract->signature)) {
                $signature = $originalContract->signature;
            }
        }

        $data = [
            'contract'      => $renewal,
            'employeename'  => strtoupper($renewal->employeename),
            'position'      => strtoupper($renewal->position1),
            'department'    => strtoupper($renewal->department),
            'startdate'     => date('F d, Y', strtotime($renewal->startdate)),
            'enddate'       => date('F d, Y', strtotime($renewal->enddate)),
            'expireddate'   => date('F d, Y', strtotime($renewal->expireddate)),
            'issuedate'     => date('F d, Y', strtotime($renewal->issuedate)),
            'employername'  => strtoupper($renewal->employername),
            'signature'     => $signature,
            'datecreated'   => $renewal->created_at ? $renewal->created_at->format('F d, Y') : date('F d, Y'),
        ];

        $pdf = Pdf::loadView('pdf.transsion-contract-addendum', $data)
            ->setPaper('a4', 'portrait');

        $filename = "addendum_{$renewal->employeename}_{$renewal->id}.pdf";

        if ($request->has('no_signature')) {
            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"{$filename}\"",
            ]);
        }

        // Bundle CV with renewal document if CV exists for employee
        $cvPath = $this->findEmployeeCv($renewal->id, $renewal->employeename);

        if ($cvPath && file_exists($cvPath) && !$request->has('pdf_only')) {
            $zipPath = storage_path('app/temp/renewal_package_' . $renewal->contract_id . '.zip');
            $zipDir = dirname($zipPath);
            if (!is_dir($zipDir)) {
                mkdir($zipDir, 0755, true);
            }

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $zip->addFromString($filename, $pdf->output());
                $cvExt = pathinfo($cvPath, PATHINFO_EXTENSION) ?: 'pdf';
                $cvFilename = "cv_{$renewal->employeename}_{$renewal->id}.{$cvExt}";
                $zip->addFile($cvPath, $cvFilename);
                $zip->close();

                return response()->download($zipPath, "renewal_package_{$renewal->employeename}_{$renewal->id}.zip")->deleteFileAfterSend(true);
            }
        }

        return $pdf->download($filename);
    }

    private function findEmployeeCv($staffId, $employeeName)
    {
        $resumeFilename = null;

        // 1. Check recruit table
        $recruit = DB::table('recruit')
            ->where('id', $staffId)
            ->orWhere('fullname', 'like', "%{$employeeName}%")
            ->first();
        if ($recruit && !empty($recruit->resume)) {
            $resumeFilename = $recruit->resume;
        }

        // 2. Check sbpltd table
        if (!$resumeFilename) {
            $sbpltd = DB::table('sbpltd')
                ->where('employnum', $staffId)
                ->orWhere('fullname', 'like', "%{$employeeName}%")
                ->first();
            if ($sbpltd && !empty($sbpltd->resume)) {
                $resumeFilename = $sbpltd->resume;
            }
        }

        $candidatePaths = [];

        if ($resumeFilename) {
            $cleanName = basename($resumeFilename);
            $candidatePaths = array_merge($candidatePaths, [
                public_path('resumes/' . $cleanName),
                public_path('resumes/' . $resumeFilename),
                public_path('storage/resumes/' . $cleanName),
                storage_path('app/public/resumes/' . $cleanName),
                storage_path('app/resumes/' . $cleanName),
                storage_path('app/public/' . $cleanName),
                public_path('uploads/cv/' . $cleanName),
                storage_path('app/public/cv/' . $cleanName),
                '/home/u1710176/hrs.sbpafrica.group/public/resumes/' . $cleanName,
                '/home/u1710176/hrs.sbpafrica.group/storage/app/public/resumes/' . $cleanName,
            ]);
        }

        $candidatePaths = array_merge($candidatePaths, [
            public_path('resumes/' . $staffId . '.pdf'),
            public_path('resumes/' . $staffId . '.docx'),
            storage_path('app/public/resumes/' . $staffId . '.pdf'),
            storage_path('app/public/resumes/' . $staffId . '.docx'),
            storage_path('app/public/cv/' . $staffId . '.pdf'),
        ]);

        foreach ($candidatePaths as $path) {
            if (!empty($path) && file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    public function destroy($contract_id)
    {
        $renewal = TranssionContractRenewal::find($contract_id);
        if (!$renewal) {
            $renewal = TranssionContractRenewal::where('id', $contract_id)->firstOrFail();
        }
        $renewal->delete();

        return redirect()
            ->route('transsion.contract-renewals.index')
            ->with('success', 'Renewal addendum deleted successfully.');
    }

    public function activate($contract_id)
    {
        $renewal = TranssionContractRenewal::find($contract_id);
        if (!$renewal) {
            $renewal = TranssionContractRenewal::where('id', $contract_id)->latest('contract_id')->firstOrFail();
        }

        $wasAlreadyActivated = ($renewal->activate === '1');

        $updateData = ['activate' => '1'];

        // If renewal doesn't have a signature, check if original contract has one
        if (empty($renewal->signature)) {
            $originalContract = TranssionContract::where('id', $renewal->id)->first();
            if ($originalContract && !empty($originalContract->signature)) {
                $updateData['signature'] = $originalContract->signature;
                $renewal->signature = $originalContract->signature;
            }
        }

        $renewal->update($updateData);

        // Send confirmation email to Issuer / HR if newly activated / signed
        if (!$wasAlreadyActivated) {
            $this->sendSignedNotificationToIssuer($renewal);
        }

        return redirect()->back()->with('success', 'Renewal document activated and confirmation email sent to HR.');
    }

    private function sendSignedNotificationToIssuer(TranssionContractRenewal $renewal)
    {
        $recipientEmail = $renewal->hremail ?: $renewal->copymail;
        if (!$recipientEmail) return;

        // Resolve signature with fallback to original contract
        $signature = $renewal->signature;
        if (empty($signature)) {
            $originalContract = TranssionContract::where('id', $renewal->id)->first();
            if ($originalContract && !empty($originalContract->signature)) {
                $signature = $originalContract->signature;
            }
        }

        try {
            $pdfData = [
                'contract'      => $renewal,
                'employeename'  => strtoupper($renewal->employeename),
                'position'      => strtoupper($renewal->position1),
                'department'    => strtoupper($renewal->department),
                'startdate'     => date('F d, Y', strtotime($renewal->startdate)),
                'enddate'       => date('F d, Y', strtotime($renewal->enddate)),
                'expireddate'   => date('F d, Y', strtotime($renewal->expireddate)),
                'issuedate'     => date('F d, Y', strtotime($renewal->issuedate)),
                'employername'  => strtoupper($renewal->employername),
                'signature'     => $signature,
                'datecreated'   => $renewal->created_at ? $renewal->created_at->format('F d, Y') : date('F d, Y'),
            ];

            $pdf = Pdf::loadView('pdf.transsion-contract-addendum', $pdfData)
                ->setPaper('a4', 'portrait');

            $tempPdfPath = storage_path('app/temp/signed_renewal_' . $renewal->id . '_' . $renewal->contract_id . '.pdf');
            if (!file_exists(dirname($tempPdfPath))) {
                mkdir(dirname($tempPdfPath), 0755, true);
            }
            $pdf->save($tempPdfPath);

            $emailData = [
                'employeename' => $renewal->employeename,
                'staffid'      => $renewal->id,
                'position'     => $renewal->position1,
                'department'   => $renewal->department,
                'startdate'    => date('F d, Y', strtotime($renewal->startdate)),
                'enddate'      => date('F d, Y', strtotime($renewal->enddate)),
            ];

            $mail = Mail::to($recipientEmail);
            if ($renewal->copymail && $renewal->hremail && $renewal->copymail !== $renewal->hremail) {
                $mail->cc($renewal->copymail);
            }
            $mail->send(new TranssionRenewalSigned($emailData, $tempPdfPath));

            Log::info('Signed renewal notification sent to issuer', [
                'contract_id' => $renewal->contract_id,
                'staffid'     => $renewal->id,
                'to'          => $recipientEmail
            ]);

            if (file_exists($tempPdfPath)) {
                unlink($tempPdfPath);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send signed renewal notification to issuer', [
                'contract_id' => $renewal->contract_id,
                'staffid'     => $renewal->id,
                'error'       => $e->getMessage()
            ]);
        }
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Original ID', 'Employee Name', 'Position', 'Department',
            'Start Date', 'End Date', 'Expired Date', 'Employer Name',
            'Issue Date', 'HR Email', 'Employee Email', 'Copy Email'
        ];
        $sheet->fromArray($headers, null, 'A1');

        $sample = [
            'TR001', 'John Doe', 'Sales Rep', 'Sales',
            '2025-01-01', '2025-12-31', '2024-12-31', 'Ann',
            '2025-01-01', 'hr@sbpafrica.group', 'john@example.com', 'it@sbpafrica.group'
        ];
        $sheet->fromArray($sample, null, 'A2');

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="transsion_renewal_template.xlsx"');
        $writer->save('php://output');
        exit;
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

            if (empty($rows)) {
                return redirect()->back()
                    ->with('error', 'The uploaded Excel file is empty.');
            }

            array_shift($rows); // Skip header row

            $errors = [];
            $validRecords = [];
            $seenInFile = [];

            foreach ($rows as $index => $row) {
                if (empty(trim($row['A'] ?? ''))) {
                    continue;
                }

                $rowNumber = $index + 1;

                $data = [
                    'id'             => trim($row['A'] ?? ''),
                    'employeename'   => trim($row['B'] ?? ''),
                    'position1'      => trim($row['C'] ?? ''),
                    'department'     => trim($row['D'] ?? ''),
                    'startdate'      => $this->parseDate($row['E'] ?? '', $rowNumber, 'Start Date', $errors),
                    'enddate'        => $this->parseDate($row['F'] ?? '', $rowNumber, 'End Date', $errors),
                    'expireddate'    => $this->parseDate($row['G'] ?? '', $rowNumber, 'Expired Date', $errors),
                    'employername'   => trim($row['H'] ?? ''),
                    'issuedate'      => $this->parseDate($row['I'] ?? '', $rowNumber, 'Issue Date', $errors),
                    'hremail'        => trim($row['J'] ?? ''),
                    'email'          => trim($row['K'] ?? ''),
                    'copymail'       => trim($row['L'] ?? ''),
                    'activate'       => '1',
                ];

                if (in_array(null, [
                    $data['startdate'],
                    $data['enddate'],
                    $data['expireddate'],
                    $data['issuedate']
                ], true)) {
                    continue;
                }

                $validator = Validator::make($data, [
                    'id'             => 'required|string|max:20',
                    'employeename'   => 'required|string|max:100',
                    'position1'      => 'required|string|max:255',
                    'department'     => 'required|string|max:255',
                    'startdate'      => 'required|date',
                    'enddate'        => 'required|date|after:startdate',
                    'expireddate'    => 'required|date',
                    'employername'   => 'required|string|max:100',
                    'issuedate'      => 'required|date',
                    'hremail'        => 'required|email|max:255',
                    'email'          => 'nullable|email|max:100',
                    'copymail'       => 'nullable|email|max:255',
                ]);

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $error) {
                        $errors[] = "Row {$rowNumber}: {$error}";
                    }
                    continue;
                }

                // Prevent duplicate rows inside the same Excel file.
                $fileKey = $data['id'] . '|' . $data['startdate'] . '|' . $data['enddate'];

                if (isset($seenInFile[$fileKey])) {
                    $errors[] = "Row {$rowNumber}: Duplicate renewal in this Excel file for Staff ID {$data['id']}.";
                    continue;
                }

                $seenInFile[$fileKey] = true;
                $validRecords[] = $data;
            }

            // Validation errors mean the affected rows are not imported.
            // We do not throw a generic "file format" error.
            if (empty($validRecords) && !empty($errors)) {
                return redirect()->back()
                    ->with('error', implode(' | ', $errors));
            }

            $uploadedCount = 0;
            $skippedCount = 0;
            $emailSentCount = 0;
            $emailFailedCount = 0;
            $pdfFailedCount = 0;
            $duplicateMessages = [];
            $emailFailureMessages = [];
            $pdfFailureMessages = [];

            foreach ($validRecords as $record) {

                // Prevent accidental duplicate imports if the same Excel file is uploaded again.
                $duplicateExists = TranssionContractRenewal::where('id', $record['id'])
                    ->where('startdate', $record['startdate'])
                    ->where('enddate', $record['enddate'])
                    ->exists();

                if ($duplicateExists) {
                    $skippedCount++;
                    $duplicateMessages[] =
                        "Staff ID {$record['id']} ({$record['employeename']}) already has this renewal period.";
                    continue;
                }

                // Save the renewal first. PDF/email failures must NOT make a successful
                // database import appear to have failed.
                try {
                    $renewal = TranssionContractRenewal::create($record);
                    $uploadedCount++;
                } catch (\Exception $e) {
                    Log::error('Bulk renewal database insert failed', [
                        'staff_id' => $record['id'],
                        'employee' => $record['employeename'],
                        'error' => $e->getMessage(),
                    ]);

                    $errors[] =
                        "Staff ID {$record['id']} ({$record['employeename']}): database save failed.";
                    continue;
                }

                // Generate PDF separately. A PDF failure does not undo the database record.
                $tempPath = null;

                try {
                    $pdfData = [
                        'contract'      => $renewal,
                        'employeename'  => strtoupper($renewal->employeename),
                        'position'      => strtoupper($renewal->position1),
                        'startdate'     => date('F d, Y', strtotime($renewal->startdate)),
                        'enddate'       => date('F d, Y', strtotime($renewal->enddate)),
                        'expireddate'   => date('F d, Y', strtotime($renewal->expireddate)),
                        'issuedate'     => date('F d, Y', strtotime($renewal->issuedate)),
                        'employername'  => strtoupper($renewal->employername),
                        'signature'     => null,
                        'datecreated'   => $renewal->created_at->format('F d, Y'),
                    ];

                    $pdf = Pdf::loadView('pdf.transsion-contract-addendum', $pdfData)
                        ->setPaper('a4', 'portrait');

                    $tempPath = storage_path('app/temp/renewal_bulk_' . $renewal->contract_id . '.pdf');

                    $tempDirectory = dirname($tempPath);
                    if (!is_dir($tempDirectory)) {
                        mkdir($tempDirectory, 0755, true);
                    }

                    $pdf->save($tempPath);
                } catch (\Exception $e) {
                    $pdfFailedCount++;

                    Log::error('Bulk renewal PDF generation failed', [
                        'contract_id' => $renewal->contract_id,
                        'staff_id' => $renewal->id,
                        'employee' => $renewal->employeename,
                        'error' => $e->getMessage(),
                    ]);

                    $pdfFailureMessages[] =
                        "Staff ID {$renewal->id} ({$renewal->employeename}): PDF generation failed.";

                    if ($tempPath && file_exists($tempPath)) {
                        unlink($tempPath);
                    }

                    // Continue processing the remaining Excel rows.
                    continue;
                }

                // Email is optional. If email is blank, the renewal remains successfully uploaded.
                if ($renewal->email) {
                    try {
                        $emailData = [
                            'employeename' => $renewal->employeename,
                            'staffid'      => $renewal->id,
                            'startdate'    => date('F d, Y', strtotime($renewal->startdate)),
                            'enddate'      => date('F d, Y', strtotime($renewal->enddate)),
                        ];

                        Mail::to($renewal->email)
                            ->cc($renewal->copymail ?: $renewal->hremail)
                            ->send(new TranssionRenewalNotification($emailData, $tempPath));

                        $emailSentCount++;

                        Log::info('Bulk renewal email sent with PDF', [
                            'contract_id' => $renewal->contract_id,
                            'staff_id' => $renewal->id,
                        ]);
                    } catch (\Exception $e) {
                        $emailFailedCount++;

                        Log::error('Bulk renewal email failed', [
                            'contract_id' => $renewal->contract_id,
                            'staff_id' => $renewal->id,
                            'employee' => $renewal->employeename,
                            'error' => $e->getMessage(),
                        ]);

                        $emailFailureMessages[] =
                            "Staff ID {$renewal->id} ({$renewal->employeename}): email failed.";
                    }
                }

                // Clean up temporary PDF after email processing.
                if ($tempPath && file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }

            $messageParts = [];

            if ($uploadedCount > 0) {
                $messageParts[] = "{$uploadedCount} renewal " .
                    ($uploadedCount === 1 ? 'record' : 'records') . " uploaded successfully";
            }

            if ($skippedCount > 0) {
                $messageParts[] = "{$skippedCount} duplicate " .
                    ($skippedCount === 1 ? 'record' : 'records') . " skipped";
            }

            if ($emailSentCount > 0) {
                $messageParts[] = "{$emailSentCount} email" .
                    ($emailSentCount === 1 ? '' : 's') . " sent";
            }

            if ($emailFailedCount > 0) {
                $messageParts[] = "{$emailFailedCount} email" .
                    ($emailFailedCount === 1 ? '' : 's') . " failed";
            }

            if ($pdfFailedCount > 0) {
                $messageParts[] = "{$pdfFailedCount} PDF" .
                    ($pdfFailedCount === 1 ? '' : 's') . " failed";
            }

            if (empty($messageParts)) {
                $messageParts[] = 'No new renewal records were imported.';
            }

            $allProblems = array_merge(
                $errors,
                $duplicateMessages,
                $pdfFailureMessages,
                $emailFailureMessages
            );

            $message = implode('. ', $messageParts) . '.';

            if (!empty($allProblems)) {
                $message .= ' Details: ' . implode(' | ', $allProblems);
            }

            return redirect()
                ->route('transsion.contract-renewals.index')
                ->with($uploadedCount > 0 ? 'success' : 'error', $message);

        } catch (\Exception $e) {
            Log::error('Bulk upload file processing failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'The Excel file could not be processed. No new records were imported from this processing attempt.');
        }
    }

    private function parseDate($value, int $rowIndex, string $fieldName, array &$errors)
    {
        if (empty($value)) {
            $errors[] = "Row " . ($rowIndex + 1) . ": $fieldName is empty.";
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
            $date = Carbon::parse(trim($value));
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            $errors[] = "Row " . ($rowIndex + 1) . ": Invalid date in $fieldName (value: '$value'). Use YYYY-MM-DD.";
            return null;
        }
    }
}