<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use ZipArchive;

class PayslipController extends Controller
{
    private function getFormalSlug(string $routeSlug): string
    {
        $map = [
            'transsion'     => 'transsion-tecnology-gh-ltd',
            'tecno'         => 'tecno-mobile-ltd',
            'melcom'        => 'melcom',
            'driver'        => 'melcom-drivers',
            'century'       => 'century',
            'nyonkopa'      => 'nyonkopa-cocoa-buying-ltd',
            'jaykay'        => 'jay-kay',
            'pz'            => 'pz-cussons-ghana-ltd',
            'twellium'      => 'twellium-industrial-company-ghana',
            'twelliumaccra' => 'twellium-industrial-company-accra',
            'shefer'        => 'shefer-plastics-ltd',
            'fon'           => 'fon-packaging',
            'a2feed'        => 'a2-group-of-companies-a2-feed',
            'a2soya'        => 'a2-group-of-companies-a2-poultry-farm',
            'ofibwh'        => 'ofi-bean-warehouse',
            'ofioffload'    => 'ofi-offloads',
            'ofiexternal'   => 'ofi-hygiene-external',
            'ofihygiene'    => 'ofi-hygiene',
            'ofitemp'       => 'ofi-tempering',
            'honda'         => 'honda-manufacturing-gh-ltd',
            'bunge'         => 'bunge',
            'inhouse'       => 'inhouse',
        ];

        return $map[$routeSlug] ?? $routeSlug;
    }

    private function resolveClient(string $routeSlug)
    {
        $formalSlug = $this->getFormalSlug($routeSlug);

        return Client::where('slug', $formalSlug)
            ->where('is_active', 1)
            ->firstOrFail();
    }



    private function getTableName($client, Carbon $date): string
{
    $base = $client->client_dbname;

    // Default: regular monthly table (e.g., inhousejanuary2025)
    $defaultTable = $base . strtolower($date->format('Fy'));

    // Special handling ONLY for Inhouse client
    if ($base === 'inhouse') {
        // Check if the special "pay" table exists (e.g., inhousenovember25pay, inhousedecember26pay)
        $specialTable = 'inhouse' . strtolower($date->format('Fy')) . 'pay';

        // Use the special table if it exists in the database
        if (\Schema::hasTable($specialTable)) {
            return $specialTable;
        }
    }

    // Fallback to default table if no special table exists
    return $defaultTable;
}

    private function resolvePayslipView($client): string
    {
        $dbName    = strtolower((string)($client->client_dbname ?? ''));
        $slug      = strtolower((string)($client->slug ?? ''));
        $shortName = strtolower((string)($client->short_name ?? ''));

        if (str_contains($dbName, 'avara') || str_contains($slug, 'avara') || str_contains($shortName, 'avara')) {
            return 'payslip.avara-pdf';
        }
        if (str_contains($dbName, 'driver') || str_contains($slug, 'driver') || str_contains($shortName, 'driver')) {
            return 'payslip.drivers-pdf';
        }
        if (str_contains($dbName, 'melcom') || str_contains($slug, 'melcom')) {
            return 'payslip.melcom-pdf';
        }
        if (str_contains($dbName, 'honda') || str_contains($slug, 'honda')) {
            return 'payslip.honda-pdf';
        }
        if (str_contains($dbName, 'jkay') || str_contains($dbName, 'jaykay') || str_contains($slug, 'jaykay')) {
            return 'payslip.jaykay-pdf';
        }
        if (str_contains($dbName, 'pz') || str_contains($slug, 'pz')) {
            return 'payslip.pz-pdf';
        }
        if (str_contains($dbName, 'twelliumaccra') || str_contains($slug, 'twelliumaccra')) {
            return 'payslip.twelliumaccra-pdf';
        }
        if (str_contains($dbName, 'twellium') || str_contains($slug, 'twellium')) {
            return 'payslip.twellium-pdf';
        }
        if (str_contains($dbName, 'shefer') || str_contains($slug, 'shefer')) {
            return 'payslip.shefer-pdf';
        }
        if (str_contains($dbName, 'inhouse') || str_contains($slug, 'inhouse')) {
            return 'payslip.inhouse-pdf';
        }
        if (str_contains($dbName, 'civ') || str_contains($slug, 'civ')) {
            return 'payslip.civ-pdf';
        }

        return 'payslip.pdf';
    }

    /**
     * Convert CIV Bunge's payroll column names to the common payslip
     * fields expected by resources/views/payslip.pdf.
     */
    private function normalizeCivPayslip($payslip, $client, $ssnit = '-')
    {
        if ($client->client_dbname !== 'civ' || !$payslip) {
            return $payslip;
        }

        $payslip->staffida       = $payslip->id ?? '-';
        $payslip->name           = $payslip->fullname ?? '-';
        $payslip->position       = $payslip->jobtitle ?? '-';
        $payslip->brandname      = $client->short_name ?? 'CIV Bunge';
        $payslip->ssnit          = $ssnit;
        $payslip->department     = $payslip->{'1stlevel'} ?? ($payslip->city ?? '-');
        $payslip->workdays       = $payslip->actualwork ?? 0;
        $payslip->basic          = $payslip->basic ?? ($payslip->actualbasic ?? 0);
        $payslip->coefficient    = $payslip->coefficient ?? 0;
        $payslip->performstand   = $payslip->monthlykpi ?? 0;
        $payslip->allowance      = (float)($payslip->transport ?? 0)
                                  + (float)($payslip->callallowance ?? 0)
                                  + (float)($payslip->leaveallowance ?? 0);
        $payslip->otworkingday   = $payslip->overtime ?? 0;
        $payslip->otholiday      = $payslip->overtimeallowance ?? 0;
        $payslip->other          = 0;
        $payslip->commission     = 0;
        $payslip->grosspay       = $payslip->grosssalary ?? ($payslip->grosssalary1 ?? 0);
        $payslip->annaulbonus    = 0;
        $payslip->paye           = $payslip->itsalaries ?? 0;
        $payslip->leavedays      = 0;
        $payslip->leavededuction = 0;
        $payslip->otherdeduction = $payslip->otherdeductions ?? 0;
        $payslip->loan           = 0;
        $payslip->annaultax      = 0;
        $payslip->netpay         = $payslip->netpay ?? 0;

        return $payslip;
    }

    public function index(Request $request, string $clientSlug)
    {
        $client = $this->resolveClient($clientSlug);

        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $formattedMonth = $date->format('F Y');
        $tableName = $this->getTableName($client, $date);

        if (!Schema::hasTable($tableName)) {
            return Inertia::render('Payslips/Index', [
                'clientSlug'     => $clientSlug,
                'client'         => $client,
                'month'          => $month,
                'formattedMonth' => $formattedMonth,
                'employees'      => collect(),
                'flash'          => ['error' => "No payslip data available for {$formattedMonth}"],
            ]);
        }

        $staffIdField = match ($client->client_dbname) {
            'melcom' => 'employ',
            'honda'  => 'STAFFID',
            'jkay'   => 'staff_id',
            'pz'     => 'staffid',
            'twellium' => 'employ',
            'twelliumaccra'=> 'staff_id',
            'shefer'        => 'staff_id',
            'inhouse'       => 'id',
            'civ'            => 'id',
            'driverssnit'   => 'employ',
            default => 'staffida',
        };

        $nameField = match ($client->client_dbname) {
            'melcom' => 'empoyeename',
            'honda'  => 'NAME',
            'jkay'   => 'name',
            'pz'     => 'name',
            'twellium' => 'name',
            'twelliumaccra'=> 'name',
            'shefer' => 'name1',
            'inhouse'       => 'name',
            'civ'            => 'fullname',
            'driverssnit'   => 'name',
            default => 'name',
        };

        $employees = DB::table($tableName)
            ->select(
                DB::raw("`{$staffIdField}` as staff_id"),
                DB::raw("`{$nameField}` as name")
            )
            ->orderBy('name')
            ->get();

        return Inertia::render('Payslips/Index', [
            'clientSlug'     => $clientSlug,
            'client'         => $client,
            'month'          => $month,
            'formattedMonth' => $formattedMonth,
            'employees'      => $employees,
        ]);
    }

    public function view(Request $request, string $clientSlug, string $month, string $staffId)
    {
        $client = $this->resolveClient($clientSlug);
        $date = Carbon::createFromFormat('Y-m', $month);
        $formattedMonth = $date->format('F Y');
        $tableName = $this->getTableName($client, $date);

        if (!Schema::hasTable($tableName)) {
            return Inertia::render('Payslips/Index', [
                'clientSlug'     => $clientSlug,
                'client'         => $client,
                'month'          => $month,
                'formattedMonth' => $formattedMonth,
                'employees'      => collect(),
                'flash'          => ['error' => "No data for {$formattedMonth}"],
            ]);
        }

        $staffIdField = match ($client->client_dbname) {
            'melcom' => 'employ',
            'honda'  => 'STAFFID',
            'jkay'   => 'staff_id',
            'pz'     => 'staffid',
            'twellium' => 'employ',
            'twelliumaccra'=> 'staff_id',
            'shefer'        => 'staff_id',
            'inhouse'       => 'id',
            'civ'            => 'id',
            'driverssnit'   => 'employ',
            default => 'staffida',
        };

        $nameField = match ($client->client_dbname) {
            'melcom' => 'empoyeename',
            'honda'  => 'NAME',
            'jkay'   => 'name',
            'pz'     => 'name',
            'twellium' => 'name',
            'twelliumaccra'=> 'name',
            'shefer'        => 'name1',
            'inhouse'       => 'name',
            'civ'            => 'fullname',
            'driverssnit'   => 'name',
            default => 'name',
        };

        $payslip = DB::table($tableName)
            ->where($staffIdField, $staffId)
            ->first();

        if (!$payslip) {
            $employees = DB::table($tableName)
                ->select(
                    DB::raw("`{$staffIdField}` as staff_id"),
                    DB::raw("`{$nameField}` as name")
                )
                ->orderBy('name')
                ->get();

            return Inertia::render('Payslips/Index', [
                'clientSlug'     => $clientSlug,
                'client'         => $client,
                'month'          => $month,
                'formattedMonth' => $formattedMonth,
                'employees'      => $employees,
                'flash'          => ['error' => "No payslip found for Staff ID: {$staffId}"],
            ]);
        }

        $employees = DB::table($tableName)
            ->select(
                DB::raw("`{$staffIdField}` as staff_id"),
                DB::raw("`{$nameField}` as name")
            )
            ->orderBy('name')
            ->get();

        return Inertia::render('Payslips/Index', [
            'clientSlug'     => $clientSlug,
            'client'         => $client,
            'payslip'        => $payslip,
            'month'          => $month,
            'formattedMonth' => $formattedMonth,
            'employees'      => $employees,
        ]);
    }

    public function download(Request $request, string $clientSlug, string $month, string $staffId)
    {
        $client = $this->resolveClient($clientSlug);
        $date = Carbon::createFromFormat('Y-m', $month);
        $formattedMonth = $date->format('F Y');
        $tableName = $this->getTableName($client, $date);

        $staffIdField = match ($client->client_dbname) {
            'melcom' => 'employ',
            'honda'  => 'STAFFID',
            'jkay'   => 'staff_id',
            'pz'     => 'staffid',
            'twellium' => 'employ',
            'twelliumaccra'=> 'staff_id',
            'shefer'        => 'staff_id',
            'inhouse'       => 'id',
            'civ'            => 'id',
            'driverssnit'   => 'employ',
            default => 'staffida',
        };

        $payslip = DB::table($tableName)
            ->where($staffIdField, $staffId)
            ->first();

        if (!$payslip) {
            return response()->json(['error' => 'Payslip not found'], 404);
        }

        $ssnit = DB::table('ssnit')->where('staffid', $staffId)->value('ssnit1a') ?? '-';

        $payslip = $this->normalizeCivPayslip($payslip, $client, $ssnit);

        $bankRecord = DB::table('bankdetails')->where('employid', $staffId)->first();

$bankData = [
    'bankname' => data_get($bankRecord, 'bank', '-'),
    'name'     => data_get($bankRecord, 'name', '-'),
    'alcnum'   => data_get($bankRecord, 'account', '-'),
    'branch'   => data_get($bankRecord, 'branch', '-'),
];

        $view = $this->resolvePayslipView($client);

        $pdf = Pdf::loadView($view, [
            'payslip'        => $payslip,
            'formattedMonth' => $formattedMonth,
            'ssnit'          => $ssnit,
            'bank'           => $bankData,
        ]);

        $filename = "{$client->short_name}_Payslip_{$staffId}_{$month}.pdf";

        return $pdf->download($filename);
    }

    public function sendEmail(string $clientSlug, Request $request)
{
    set_time_limit(0);
    ini_set('memory_limit', '512M');

    // Force this endpoint's logs to always be captured, regardless of
    // .env LOG_LEVEL (which may be filtering out ->info()/->warning()).
    $diagLog = function (string $msg) {
        Log::channel('single')->error('[PAYSLIP-EMAIL] ' . $msg);
        // 'error' level almost always passes any LOG_LEVEL filter.
        // The [PAYSLIP-EMAIL] prefix makes it easy to grep separately.
    };

    try {
        $client = $this->resolveClient($clientSlug);

        $month    = $request->input('month');
        $staffIds = $request->input('staff_ids', []);

        if (empty($staffIds)) {
            $diagLog("No staff_ids provided. Payload: " . json_encode($request->all()));
            return response()->json([
                'success' => false,
                'sent' => 0,
                'failed' => 0,
                'message' => 'No employees selected',
            ], 200); // 200, not 400 — let the JSON body carry the failure
        }

        $date           = Carbon::createFromFormat('Y-m', $month);
        $formattedMonth = $date->format('F Y');
        $tableName      = $this->getTableName($client, $date);

        if (!Schema::hasTable($tableName)) {
            $diagLog("Table {$tableName} does not exist for client {$clientSlug}, month {$month}");
            return response()->json([
                'success' => false,
                'sent' => 0,
                'failed' => count($staffIds),
                'message' => "No data for {$formattedMonth}",
            ], 200);
        }

        $staffIdField = match ($client->client_dbname) {
            'melcom'        => 'employ',
            'honda'         => 'STAFFID',
            'jkay'          => 'staff_id',
            'pz'            => 'staffid',
            'twellium'      => 'employ',
            'twelliumaccra' => 'staff_id',
            'shefer'        => 'staff_id',
            'inhouse'       => 'id',
            'civ'           => 'id',
            default         => 'staffida',
        };

        $view = $this->resolvePayslipView($client);

        $payslips = DB::table($tableName)
            ->whereIn($staffIdField, $staffIds)
            ->get()
            ->keyBy($staffIdField);

        $employees = DB::table('newemployeedata')
            ->whereIn('staffid', $staffIds)
            ->select('staffid', 'employeename', 'email')
            ->get()
            ->keyBy('staffid');

        $ssnits = DB::table('ssnit')
            ->whereIn('staffid', $staffIds)
            ->pluck('ssnit1a', 'staffid');

        $banks = DB::table('bankdetails')
            ->whereIn('employid', $staffIds)
            ->get()
            ->keyBy('employid');

        $sentCount    = 0;
        $failedCount  = 0;
        $noEmailCount = 0;
        $details      = []; // per-staff diagnostics returned to the frontend/logs

        foreach ($staffIds as $id) {
            $payslip  = $payslips->get($id);
            $employee = $employees->get($id);

            if (!$payslip) {
                $diagLog("No payslip row for staff ID {$id} in table {$tableName}");
                $details[] = ['id' => $id, 'status' => 'failed', 'reason' => 'no_payslip'];
                $failedCount++;
                continue;
            }

            $email = $employee?->email ?? null;
            if (!$email) {
                $diagLog("No email on file for staff ID {$id} (employee record " . ($employee ? 'found, email blank' : 'not found in newemployeedata') . ")");
                $details[] = ['id' => $id, 'status' => 'no_email', 'reason' => $employee ? 'blank_email' : 'no_employee_record'];
                $noEmailCount++;
                continue;
            }

            $name       = $employee?->employeename ?? 'Employee';
            $bankRecord = $banks->get($id);

            $bankData = [
                'bankname' => data_get($bankRecord, 'bank',    '-'),
                'name'     => data_get($bankRecord, 'name',    '-'),
                'alcnum'   => data_get($bankRecord, 'account', '-'),
                'branch'   => data_get($bankRecord, 'branch',  '-'),
            ];

            $payslip = $this->normalizeCivPayslip($payslip, $client, $ssnits->get($id) ?? '-');

            try {
                $pdf = Pdf::loadView($view, [
                    'payslip'        => $payslip,
                    'formattedMonth' => $formattedMonth,
                    'ssnit'          => $ssnits->get($id) ?? '-',
                    'bank'           => $bankData,
                ]);

                $pdfContent = $pdf->output();

                Mail::raw(
                    "Dear {$name},\n\nPlease find your {$client->short_name} payslip for {$formattedMonth} attached.\n\nThank you.",
                    function ($message) use ($email, $name, $client, $formattedMonth, $pdfContent, $id, $date) {
                        $message->to($email)
                            ->subject("{$client->short_name} Payslip - {$formattedMonth}")
                            ->attachData(
                                $pdfContent,
                                "{$client->short_name}_Payslip_{$id}_{$date->format('Y-m')}.pdf",
                                ['mime' => 'application/pdf']
                            );
                    }
                );

                $sentCount++;
                $details[] = ['id' => $id, 'status' => 'sent'];

                unset($pdf, $pdfContent);
                gc_collect_cycles();

            } catch (\Throwable $e) {
                // \Throwable, not just \Exception — catches fatal errors too
                $diagLog("Email FAILED for {$email} (Staff ID {$id}): " . $e->getMessage() . " | " . $e->getFile() . ':' . $e->getLine());
                $details[] = ['id' => $id, 'status' => 'failed', 'reason' => $e->getMessage()];
                $failedCount++;
            }
        }

        $diagLog("Batch complete for {$clientSlug} {$month}: sent={$sentCount} noEmail={$noEmailCount} failed={$failedCount} staffIds=" . implode(',', $staffIds));

        // ALWAYS return 200 with a JSON body — this is what makes
        // response.ok reliable on the frontend, since it can no longer
        // be fooled by redirects or unrelated page-render failures.
        return response()->json([
            'success'      => $failedCount === 0,
            'sent'         => $sentCount,
            'no_email'     => $noEmailCount,
            'failed'       => $failedCount,
            'message'      => "Sent {$sentCount}, {$noEmailCount} skipped (no email), {$failedCount} failed.",
            'details'      => $details,
        ], 200);

    } catch (\Throwable $e) {
        // Catches anything above the per-employee loop: bad client slug,
        // bad month format, DB connection issue, etc.
        Log::channel('single')->error('[PAYSLIP-EMAIL] FATAL: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

        // Still return 200 so the frontend doesn't just show a generic
        // "failed" toast — instead it'll show totalFailed = batch size,
        // but at least your log now has the real reason.
        return response()->json([
            'success' => false,
            'sent'    => 0,
            'failed'  => count($request->input('staff_ids', [])),
            'message' => 'Server error: ' . $e->getMessage(),
        ], 200);
    }
}
    public function downloadMultiple(string $clientSlug, Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        try {
            $client = $this->resolveClient($clientSlug);
            $month = $request->input('month');
            $staffIds = array_values(array_filter((array) $request->input('staff_ids', [])));
            $mode = $request->input('mode', 'batch');
            $batchId = trim((string) $request->input('batch_id', ''));

            if (!$month) {
                return response()->json(['error' => 'Month is required.'], 400);
            }

            if (empty($staffIds) && $mode !== 'finalize') {
                return response()->json(['error' => 'No employees selected.'], 400);
            }

            if (!preg_match('/^[A-Za-z0-9_-]{8,80}$/', $batchId)) {
                return response()->json(['error' => 'Invalid bulk download session.'], 400);
            }

            $date = Carbon::createFromFormat('Y-m', $month);
            $formattedMonth = $date->format('F Y');
            $tableName = $this->getTableName($client, $date);

            if (!Schema::hasTable($tableName)) {
                return response()->json(['error' => "No payroll data found for {$formattedMonth}"], 404);
            }

            $staffIdField = match ($client->client_dbname) {
                'melcom'        => 'employ',
                'honda'         => 'STAFFID',
                'jkay'          => 'staff_id',
                'pz'            => 'staffid',
                'twellium'      => 'employ',
                'twelliumaccra' => 'staff_id',
                'shefer'        => 'staff_id',
                'inhouse'       => 'id',
                'civ'           => 'id',
                'driverssnit'   => 'employ',
                default         => 'staffida',
            };

            $view = $this->resolvePayslipView($client);

            $baseDirectory = storage_path('app/payslip-bulk/' . $batchId);

            if (!is_dir($baseDirectory) && !mkdir($baseDirectory, 0755, true) && !is_dir($baseDirectory)) {
                return response()->json(['error' => 'Unable to create bulk download workspace.'], 500);
            }

            /*
             * FINALIZE: only packages already-generated PDF files.
             * No PDF rendering happens in this request, so even 100+ employees
             * do not hit the 20-PDF server limit in a single request.
             */
            if ($mode === 'finalize') {
                $pdfFiles = glob($baseDirectory . DIRECTORY_SEPARATOR . '*.pdf') ?: [];

                if (empty($pdfFiles)) {
                    @rmdir($baseDirectory);
                    return response()->json(['error' => 'No generated payslips were found to package.'], 404);
                }

                $zipFileName = "{$client->short_name}_Payslips_{$month}.zip";
                $zipPath = $baseDirectory . DIRECTORY_SEPARATOR . $zipFileName;
                $zip = new ZipArchive();

                if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    return response()->json(['error' => 'Unable to create the final ZIP file.'], 500);
                }

                foreach ($pdfFiles as $pdfFile) {
                    $zip->addFile($pdfFile, basename($pdfFile));
                }

                if (!$zip->close() || !file_exists($zipPath) || filesize($zipPath) === 0) {
                    @unlink($zipPath);
                    return response()->json(['error' => 'The final ZIP file could not be created.'], 500);
                }

                return response()
                    ->download($zipPath, $zipFileName, ['Content-Type' => 'application/zip'])
                    ->deleteFileAfterSend(true);
            }

            /*
             * BATCH: the frontend sends at most 20 staff IDs here.
             * Generate PDFs and keep them on disk for the finalize request.
             */
            if ($mode !== 'batch') {
                return response()->json(['error' => 'Invalid bulk download mode.'], 400);
            }

            if (count($staffIds) > 20) {
                return response()->json([
                    'error' => 'A bulk batch cannot contain more than 20 employees.'
                ], 422);
            }

            $payslips = DB::table($tableName)
                ->whereIn($staffIdField, $staffIds)
                ->get()
                ->keyBy($staffIdField);

            if ($payslips->isEmpty()) {
                return response()->json(['error' => 'No payslips found for this batch.'], 404);
            }

            $ssnits = Schema::hasTable('ssnit')
                ? DB::table('ssnit')->whereIn('staffid', $staffIds)->pluck('ssnit1a', 'staffid')
                : collect();

            $banks = Schema::hasTable('bankdetails')
                ? DB::table('bankdetails')->whereIn('employid', $staffIds)->get()->keyBy('employid')
                : collect();

            $generated = 0;
            $failed = [];

            foreach ($staffIds as $requestedStaffId) {
                try {
                    $payslip = $payslips->get($requestedStaffId);

                    if (!$payslip) {
                        $failed[] = $requestedStaffId;
                        continue;
                    }

                    $staffId = $payslip->$staffIdField;
                    $ssnit = $ssnits->get($staffId) ?? '-';
                    $payslip = $this->normalizeCivPayslip($payslip, $client, $ssnit);
                    $bankRecord = $banks->get($staffId);

                    $bankData = [
                        'bankname' => data_get($bankRecord, 'bank', '-'),
                        'name'     => data_get($bankRecord, 'name', '-'),
                        'alcnum'   => data_get($bankRecord, 'account', '-'),
                        'branch'   => data_get($bankRecord, 'branch', '-'),
                    ];

                    $pdf = Pdf::loadView($view, [
                        'payslip'        => $payslip,
                        'formattedMonth' => $formattedMonth,
                        'ssnit'          => $ssnit,
                        'bank'           => $bankData,
                    ]);

                    $filename = "{$client->short_name}_Payslip_{$staffId}_{$month}.pdf";
                    $pdfPath = $baseDirectory . DIRECTORY_SEPARATOR . $filename;
                    file_put_contents($pdfPath, $pdf->output());

                    if (!file_exists($pdfPath) || filesize($pdfPath) === 0) {
                        throw new \Exception('Generated PDF is empty.');
                    }

                    $generated++;
                    unset($pdf);
                    gc_collect_cycles();
                } catch (\Throwable $e) {
                    Log::error(
                        "[PAYSLIP-BULK-BATCH] Failed Staff ID {$requestedStaffId}: " .
                        $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
                    );
                    $failed[] = $requestedStaffId;
                }
            }

            if ($generated === 0) {
                return response()->json([
                    'error' => 'No PDF payslips could be generated in this batch.',
                    'failed' => $failed,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'generated' => $generated,
                'failed' => $failed,
            ]);

        } catch (\Throwable $e) {
            Log::error(
                '[PAYSLIP-BULK] FATAL ERROR: ' .
                $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
            );

            return response()->json([
                'error' => 'Bulk payslip download failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
