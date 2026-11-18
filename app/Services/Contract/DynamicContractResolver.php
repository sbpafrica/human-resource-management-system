<?php

namespace App\Services\Contract;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\GmoneyContract;
use App\Models\MelcomContractRenewal;
use App\Models\OlamfoodContract;
use App\Models\TranssionContract;
use App\Models\TranssionContractRenewal;
use App\Models\XiaomiContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DynamicContractResolver
{
    /**
     * Resolve contract or renewal for a given staff ID across all client tables.
     */
    public function resolve(string $staffId, ?string $preferredType = null): ?array
    {
        $staffId = trim($staffId);
        if ($staffId === '') {
            return null;
        }

        // 1. If preferredType is 'renewal' or unset, check for pending renewals first
        if ($preferredType === 'renewal' || $preferredType === null) {
            $renewal = $this->checkRenewals($staffId);
            if ($renewal) {
                // If it was explicitly requested, or if it is unsigned, return the renewal
                if ($preferredType === 'renewal' || !$renewal['is_signed']) {
                    return $renewal;
                }
                // Save it as fallback if initial contract is not found
                $fallbackRenewal = $renewal;
            }
        }

        // 2. Check client-specific initial contract tables
        $contract = $this->checkContracts($staffId);
        if ($contract) {
            return $contract;
        }

        // 3. Fallback to renewal if contract wasn't found but renewal exists
        if (isset($fallbackRenewal)) {
            return $fallbackRenewal;
        }

        // 4. Fallback to master directory (newemployeedata)
        return $this->fallbackFromDirectory($staffId);
    }

    /**
     * Check renewal tables: contracttranssionemprenew and contractmelcomemprenew.
     */
    private function checkRenewals(string $staffId): ?array
    {
        // A. Transsion Contract Renewal
        if (Schema::hasTable('contracttranssionemprenew')) {
            $record = DB::table('contracttranssionemprenew')
                ->where('id', $staffId)
                ->orderByDesc('created_at')
                ->first();

            if ($record) {
                $isSigned = ($record->activate === '1' && !empty($record->signature));
                $signatureFile = $record->signature;
                if (!$signatureFile && $isSigned) {
                    $orig = DB::table('transsioncon')->where('id', $staffId)->value('signature');
                    if ($orig) $signatureFile = $orig;
                }

                $viewData = [
                    'contract'      => $record,
                    'employeename'  => strtoupper($record->employeename ?? 'Staff'),
                    'position'      => strtoupper($record->position1 ?? 'Staff'),
                    'department'    => strtoupper($record->department ?? 'Operations'),
                    'startdate'     => !empty($record->startdate) ? date('F d, Y', strtotime($record->startdate)) : 'N/A',
                    'enddate'       => !empty($record->enddate) ? date('F d, Y', strtotime($record->enddate)) : 'N/A',
                    'expireddate'   => !empty($record->expireddate) ? date('F d, Y', strtotime($record->expireddate)) : 'N/A',
                    'issuedate'     => !empty($record->issuedate) ? date('F d, Y', strtotime($record->issuedate)) : date('F d, Y'),
                    'employername'  => strtoupper($record->employername ?? 'SBP Africa Limited'),
                    'signature'     => $signatureFile,
                    'datecreated'   => !empty($record->created_at) ? date('F d, Y', strtotime($record->created_at)) : date('F d, Y'),
                ];

                return [
                    'found'          => true,
                    'staff_id'       => $staffId,
                    'employee_name'  => $record->employeename ?? 'Staff',
                    'client'         => 'Transsion',
                    'contract_type'  => 'renewal',
                    'table'          => 'contracttranssionemprenew',
                    'template'       => 'pdf.transsion-contract-addendum',
                    'is_signed'      => $isSigned,
                    'signature'      => $signatureFile,
                    'signature_date' => $record->updated_at ?? $record->created_at,
                    'raw_record'     => (array)$record,
                    'view_data'      => $viewData,
                ];
            }
        }

        // B. Melcom / General Contract Renewal
        if (Schema::hasTable('contractmelcomemprenew')) {
            $record = DB::table('contractmelcomemprenew')
                ->where('id', $staffId)
                ->orderByDesc('id')
                ->first();

            if ($record) {
                $isSigned = ($record->activate === '1' && !empty($record->signature));
                $firstname = ucfirst(strtolower(explode(' ', trim($record->employeename ?? 'Staff'))[0]));

                $viewData = [
                    'employeename' => $record->employeename ?? 'Staff',
                    'firstname'    => $firstname,
                    'position'     => $record->position1 ?? 'Staff',
                    'startdate'    => !empty($record->startdate) ? Carbon::parse($record->startdate)->format('F d, Y') : 'N/A',
                    'enddate'      => !empty($record->enddate) ? Carbon::parse($record->enddate)->format('F d, Y') : 'N/A',
                    'issuedate'    => !empty($record->issuedate) ? Carbon::parse($record->issuedate)->format('F d, Y') : date('F d, Y'),
                    'expireddate'  => !empty($record->expireddate) ? Carbon::parse($record->expireddate)->format('F d, Y') : 'N/A',
                    'location'     => $record->location ?? 'Accra',
                    'adjusted'     => $record->adjusted ?? 'N/A',
                    'basic'        => $record->basic ?? 'N/A',
                    'signature'    => $record->signature,
                ];

                return [
                    'found'          => true,
                    'staff_id'       => $staffId,
                    'employee_name'  => $record->employeename ?? 'Staff',
                    'client'         => $record->client ?? 'Melcom',
                    'contract_type'  => 'renewal',
                    'table'          => 'contractmelcomemprenew',
                    'template'       => 'pdf.contract_renewal',
                    'is_signed'      => $isSigned,
                    'signature'      => $record->signature,
                    'signature_date' => $record->updated_at ?? null,
                    'raw_record'     => (array)$record,
                    'view_data'      => $viewData,
                ];
            }
        }

        return null;
    }

    /**
     * Check client-specific initial contract tables.
     */
    private function checkContracts(string $staffId): ?array
    {
        // 1. Olam Food Ingredients (olamfoodcon2)
        if (Schema::hasTable('olamfoodcon2')) {
            $record = DB::table('olamfoodcon2')->where('id', $staffId)->first();
            if ($record) {
                return $this->formatOlamContract($record, $staffId);
            }
        }

        // 2. Transsion (transsioncon)
        if (Schema::hasTable('transsioncon')) {
            $record = DB::table('transsioncon')->where('id', $staffId)->first();
            if ($record) {
                return $this->formatTranssionContract($record, $staffId);
            }
        }

        // 3. Xiaomi (xiaomicon)
        if (Schema::hasTable('xiaomicon')) {
            $record = DB::table('xiaomicon')->where('id', $staffId)->first();
            if ($record) {
                return $this->formatXiaomiContract($record, $staffId);
            }
        }

        // 4. G-Money (gmoneycon)
        if (Schema::hasTable('gmoneycon')) {
            $record = DB::table('gmoneycon')->where('id', $staffId)->first();
            if ($record) {
                return $this->formatGmoneyContract($record, $staffId);
            }
        }

        // 5. Shefer (shefercon)
        if (Schema::hasTable('shefercon')) {
            $record = DB::table('shefercon')->where('id', $staffId)->first();
            if ($record) {
                return $this->formatGeneralContract($record, $staffId, 'shefercon', 'Shefer');
            }
        }

        // 6. Melcom and General Contracts (contractmelcomemp)
        if (Schema::hasTable('contractmelcomemp')) {
            $record = DB::table('contractmelcomemp')->where('id', $staffId)->first();
            if ($record) {
                return $this->formatGeneralContract($record, $staffId, 'contractmelcomemp', $record->client ?? 'Melcom');
            }
        }

        return null;
    }

    /**
     * Format Olam Food contract view data.
     */
    private function formatOlamContract($record, string $staffId): array
    {
        $isSigned = ($record->activate === '1' && !empty($record->signature));
        $headerPath = public_path('images/sbphead.png');
        $footerPath = public_path('images/footer.png');
        $employerSigPath = public_path('images/Annsign.png');
        $employeeSigPath = $this->resolveSignaturePath($record->signature, 'olamfoodcon');

        $headerImg = $this->imageToBase64($headerPath);
        $footerImg = $this->imageToBase64($footerPath);
        $employerSigImg = $this->imageToBase64($employerSigPath);

        $employeeSigImg = ($record->signature && file_exists($employeeSigPath))
            ? '<img src="' . $this->imageToBase64($employeeSigPath) . '" class="signature-img-employee" style="max-height: 50px;">'
            : '<div style="width:150px; height:50px; border:1px dashed #999; margin:0 auto;"></div>';

        $viewData = [
            'employeename'   => $record->employeename ?? 'Staff',
            'startdate'      => $record->startdate ?? 'N/A',
            'enddate'        => $record->enddate ?? 'N/A',
            'issuedate'      => $record->issuedate ?? date('Y-m-d'),
            'updated_at'     => $record->updated_at ?? null,
            'signature'      => $record->signature,
            'headerImg'      => $headerImg,
            'footerImg'      => $footerImg,
            'employerSigImg' => $employerSigImg,
            'employeeSigImg' => $employeeSigImg,
        ];

        return [
            'found'          => true,
            'staff_id'       => $staffId,
            'employee_name'  => $record->employeename ?? 'Staff',
            'client'         => 'Olam Food Ingredients',
            'contract_type'  => 'contract',
            'table'          => 'olamfoodcon2',
            'template'       => 'pdf.olamfood-contract',
            'is_signed'      => $isSigned,
            'signature'      => $record->signature,
            'signature_date' => $record->updated_at ?? null,
            'raw_record'     => (array)$record,
            'view_data'      => $viewData,
        ];
    }

    /**
     * Format Transsion contract view data.
     */
    private function formatTranssionContract($record, string $staffId): array
    {
        $isSigned = ($record->activate === '1' && !empty($record->signature));
        $viewData = [
            'employeename' => strtoupper($record->employeename ?? 'Staff'),
            'client'       => strtoupper($record->client ?? 'Transsion'),
            'location'     => strtoupper($record->location ?? 'Accra'),
            'position'     => strtoupper($record->position1 ?? 'Staff'),
            'department'   => strtoupper($record->department ?? 'Operations'),
            'duration'     => $record->duration ?? '12 Months',
            'startdate'    => !empty($record->startdate) ? date('F d, Y', strtotime($record->startdate)) : 'N/A',
            'enddate'      => !empty($record->enddate) ? date('F d, Y', strtotime($record->enddate)) : 'N/A',
            'basic'        => $record->basic ?? '0.00',
            'allowance'    => $record->allowance ?? 'N/A',
            'kpi'          => $record->kpi ?? 'N/A',
            'employername' => strtoupper($record->employername ?? 'SBP Africa Limited'),
            'issuedate'    => !empty($record->issuedate) ? date('F d, Y', strtotime($record->issuedate)) : date('F d, Y'),
            'hremail'      => $record->hremail ?? 'hr@sbpafrica.group',
            'email'        => $record->email ?? 'N/A',
            'signature'    => $record->signature,
            'created_at'   => $record->created_at ?? null,
        ];

        return [
            'found'          => true,
            'staff_id'       => $staffId,
            'employee_name'  => $record->employeename ?? 'Staff',
            'client'         => 'Transsion',
            'contract_type'  => 'contract',
            'table'          => 'transsioncon',
            'template'       => 'pdf.transsion-contract',
            'is_signed'      => $isSigned,
            'signature'      => $record->signature,
            'signature_date' => $record->updated_at ?? null,
            'raw_record'     => (array)$record,
            'view_data'      => $viewData,
        ];
    }

    /**
     * Format Xiaomi contract view data.
     */
    private function formatXiaomiContract($record, string $staffId): array
    {
        $isSigned = ($record->activate === '1' && !empty($record->signature));
        $viewData = [
            'employeename'   => strtoupper($record->employeename ?? 'Staff'),
            'client'         => strtoupper($record->client ?? 'Xiaomi'),
            'location'       => strtoupper($record->location ?? 'Accra'),
            'position'       => strtoupper($record->position1 ?? 'Staff'),
            'duration'       => $record->duration ?? '12 Months',
            'startdate'      => !empty($record->startdate) ? date('F d, Y', strtotime($record->startdate)) : 'N/A',
            'enddate'        => !empty($record->enddate) ? date('F d, Y', strtotime($record->enddate)) : 'N/A',
            'basic'          => is_numeric($record->basic) ? number_format((float)$record->basic, 2) : ($record->basic ?? '0.00'),
            'kpi'            => $record->kpi ? number_format((float)$record->kpi, 2) : '—',
            'transport'      => $record->transport ? number_format((float)$record->transport, 2) : '—',
            'basicafter'     => $record->basicafter ? number_format((float)$record->basicafter, 2) : '—',
            'kpiafter'       => $record->kpiafter ? number_format((float)$record->kpiafter, 2) : '—',
            'transportafter' => $record->transportafter ? number_format((float)$record->transportafter, 2) : '—',
            'employername'   => strtoupper($record->employername ?? 'SBP Africa Limited'),
            'issuedate'      => !empty($record->issuedate) ? date('F d, Y', strtotime($record->issuedate)) : date('F d, Y'),
            'hremail'        => $record->hremail ?? 'hr@sbpafrica.group',
            'signature'      => $record->signature,
            'created_at'     => $record->created_at ?? null,
            'ghanacard'      => $record->ghanacard ?? '—',
            'digiaddress'    => $record->digiaddress ?? '—',
        ];

        return [
            'found'          => true,
            'staff_id'       => $staffId,
            'employee_name'  => $record->employeename ?? 'Staff',
            'client'         => 'Xiaomi',
            'contract_type'  => 'contract',
            'table'          => 'xiaomicon',
            'template'       => 'pdf.xiaomi-contract',
            'is_signed'      => $isSigned,
            'signature'      => $record->signature,
            'signature_date' => $record->updated_at ?? null,
            'raw_record'     => (array)$record,
            'view_data'      => $viewData,
        ];
    }

    /**
     * Format G-Money contract view data.
     */
    private function formatGmoneyContract($record, string $staffId): array
    {
        $isSigned = ($record->activate === '1' && !empty($record->signature));
        $viewData = [
            'employeename' => strtoupper($record->employeename ?? 'Staff'),
            'client'       => strtoupper($record->client ?? 'G-MONEY FINANCIAL SERVICES LIMITED'),
            'location'     => strtoupper($record->location ?? 'Accra'),
            'position'     => strtoupper($record->position1 ?? 'Staff'),
            'startdate'    => !empty($record->startdate) ? date('Y-m-d', strtotime($record->startdate)) : 'N/A',
            'issuedate'    => !empty($record->issuedate) ? date('Y-m-d', strtotime($record->issuedate)) : date('Y-m-d'),
            'basic'        => is_numeric($record->basic) ? number_format((float)$record->basic, 2) : ($record->basic ?? '0.00'),
            'transport'    => $record->transport ? number_format((float)$record->transport, 2) : '—',
            'ghanacard'    => $record->ghanacard ?? '—',
            'digiaddress'  => $record->digiaddress ?? '—',
            'signature'    => $record->signature,
            'created_at'   => $record->created_at ?? null,
        ];

        return [
            'found'          => true,
            'staff_id'       => $staffId,
            'employee_name'  => $record->employeename ?? 'Staff',
            'client'         => 'G-Money',
            'contract_type'  => 'contract',
            'table'          => 'gmoneycon',
            'template'       => 'pdf.gmoney-contract',
            'is_signed'      => $isSigned,
            'signature'      => $record->signature,
            'signature_date' => $record->updated_at ?? null,
            'raw_record'     => (array)$record,
            'view_data'      => $viewData,
        ];
    }

    /**
     * Format general / Melcom contract view data.
     */
    private function formatGeneralContract($record, string $staffId, string $table, string $clientName): array
    {
        $isSigned = ($record->activate === '1' && !empty($record->signature));

        $headerPath = public_path('images/sbphead.png');
        $footerPath = public_path('images/footer.png');
        $employerSigPath = public_path('images/Annsign.png');
        $employeeSigPath = $this->resolveSignaturePath($record->signature, 'melcomcon');

        $viewData = [
            'employeename'           => strtoupper($record->employeename ?? 'Staff'),
            'client'                 => strtoupper($record->client ?? $clientName),
            'location'               => strtoupper($record->location ?? 'Accra'),
            'position'               => strtoupper($record->position1 ?? 'Staff'),
            'startdate'              => !empty($record->startdate) ? date('F d, Y', strtotime($record->startdate)) : 'N/A',
            'enddate'                => !empty($record->enddate) ? date('F d, Y', strtotime($record->enddate)) : 'N/A',
            'basic'                  => strtoupper($record->basic ?? 'N/A'),
            'employername'           => strtoupper($record->employername ?? 'SBP Africa Limited'),
            'issuedate'              => !empty($record->issuedate) ? date('F d, Y', strtotime($record->issuedate)) : date('F d, Y'),
            'hremail'                => strtoupper($record->hremail ?? 'hr@sbpafrica.group'),
            'email'                  => strtoupper($record->email ?? 'N/A'),
            'ghanacard'              => strtoupper($record->ghanacard ?? 'N/A'),
            'digiaddress'            => strtoupper($record->digiaddress ?? 'N/A'),
            'signature'              => $record->signature,
            'headerPath'             => $headerPath,
            'footerPath'             => $footerPath,
            'employerSignaturePath'  => $employerSigPath,
            'employeeSignaturePath'  => $employeeSigPath,
        ];

        return [
            'found'          => true,
            'staff_id'       => $staffId,
            'employee_name'  => $record->employeename ?? 'Staff',
            'client'         => $clientName,
            'contract_type'  => 'contract',
            'table'          => $table,
            'template'       => 'pdf.contract',
            'is_signed'      => $isSigned,
            'signature'      => $record->signature,
            'signature_date' => $record->updated_at ?? null,
            'raw_record'     => (array)$record,
            'view_data'      => $viewData,
        ];
    }

    /**
     * Fallback from newemployeedata if employee has no contract table entry yet.
     */
    private function fallbackFromDirectory(string $staffId): ?array
    {
        if (!Schema::hasTable('newemployeedata')) {
            return null;
        }

        $emp = DB::table('newemployeedata')->where('staffid', $staffId)->first();
        if (!$emp) {
            return null;
        }

        $isSigned = (!empty($emp->signature));
        $startDate = !empty($emp->startdate) ? date('F d, Y', strtotime($emp->startdate)) : date('F d, Y');
        $endDate = !empty($emp->startdate) ? date('F d, Y', strtotime($emp->startdate . ' + 1 year')) : date('F d, Y', strtotime('+1 year'));

        $headerPath = public_path('images/sbphead.png');
        $footerPath = public_path('images/footer.png');
        $employerSigPath = public_path('images/Annsign.png');
        $employeeSigPath = $this->resolveSignaturePath($emp->signature);

        $viewData = [
            'employeename'          => strtoupper($emp->employeename ?? 'Staff'),
            'client'                => strtoupper($emp->company ?? 'SBP Africa'),
            'location'              => strtoupper($emp->shop ?? 'Accra'),
            'position'              => strtoupper($emp->position ?? 'Staff Member'),
            'startdate'             => $startDate,
            'enddate'               => $endDate,
            'basic'                 => 'CONFIDENTIAL',
            'employername'          => 'STRATEGIC BUSINESS PARTNERSHIP',
            'issuedate'             => date('F d, Y'),
            'hremail'               => 'HR@SBPAFRICA.GROUP',
            'email'                 => strtoupper($emp->email ?? 'N/A'),
            'ghanacard'             => strtoupper($emp->ghcard ?? 'N/A'),
            'digiaddress'           => strtoupper($emp->gpsaddress ?? 'N/A'),
            'signature'             => $emp->signature,
            'headerPath'            => $headerPath,
            'footerPath'            => $footerPath,
            'employerSignaturePath' => $employerSigPath,
            'employeeSignaturePath' => $employeeSigPath,
        ];

        return [
            'found'          => true,
            'staff_id'       => $staffId,
            'employee_name'  => $emp->employeename ?? 'Staff',
            'client'         => $emp->company ?? 'SBP Africa',
            'contract_type'  => 'contract',
            'table'          => 'newemployeedata',
            'template'       => 'pdf.contract',
            'is_signed'      => $isSigned,
            'signature'      => $emp->signature,
            'signature_date' => $emp->signature_date ?? null,
            'raw_record'     => (array)$emp,
            'view_data'      => $viewData,
        ];
    }

    /**
     * Save base64 signature image, activate the contract, and synchronize across tables.
     */
    public function saveSignature(string $staffId, string $base64Data, ?string $type = null): array
    {
        $contractInfo = $this->resolve($staffId, $type);
        if (!$contractInfo) {
            throw new \Exception("No contract found for Staff ID: {$staffId}");
        }

        // Clean & extract base64 data
        if (str_contains($base64Data, ';base64,')) {
            [, $base64Data] = explode(';base64,', $base64Data);
        }

        $imageBytes = base64_decode($base64Data);
        if (!$imageBytes) {
            throw new \Exception('Invalid signature image data.');
        }
        

        // Ensure directories exist
        $sigDir = public_path('images/signatures');
        if (!is_dir($sigDir)) {
            mkdir($sigDir, 0755, true);
        }

        $cleanClient = Str::slug($contractInfo['client']);
        $cleanId = preg_replace('/[^A-Za-z0-9_-]/', '', $staffId);
        $filename = "sig_{$cleanClient}_{$cleanId}_" . time() . '.png';
        $fullPath = $sigDir . '/' . $filename;

        file_put_contents($fullPath, $imageBytes);

        // Also copy into subdirectories if needed by specific contract templates
        if ($contractInfo['table'] === 'olamfoodcon2') {
            $olamDir = public_path('images/olamfoodcon/signatures');
            if (!is_dir($olamDir)) mkdir($olamDir, 0755, true);
            @copy($fullPath, $olamDir . '/' . $filename);
        } elseif (str_contains($contractInfo['table'], 'melcom')) {
            $melcomDir = public_path('images/melcomcon');
            if (!is_dir($melcomDir)) mkdir($melcomDir, 0755, true);
            @copy($fullPath, $melcomDir . '/' . $filename);
        }

        // Update the contract table
        $table = $contractInfo['table'];
        $now = now();

        DB::transaction(function () use ($table, $staffId, $filename, $now) {
            if ($table !== 'newemployeedata') {
                $updateData = [
                    'signature'  => $filename,
                    'activate'   => '1',
                ];
                if (Schema::hasColumn($table, 'updated_at')) {
                    $updateData['updated_at'] = $now;
                }
                if (Schema::hasColumn($table, 'signature_date')) {
                    $updateData['signature_date'] = $now;
                }
                DB::table($table)->where('id', $staffId)->update($updateData);
            }

            // Sync with newemployeedata master directory
            if (Schema::hasTable('newemployeedata')) {
                $dirUpdate = [
                    'signature'      => $filename,
                    'signature_date' => $now,
                ];
                if (Schema::hasColumn('newemployeedata', 'contract_step')) {
                    $dirUpdate['contract_step'] = 'signed';
                }
                DB::table('newemployeedata')->where('staffid', $staffId)->update($dirUpdate);
            }
        });

        // Re-resolve updated contract info
        return $this->resolve($staffId, $type);
    }

    /**
     * Generate PDF instance using DomPDF.
     */
    public function generatePdf(array $contractInfo)
    {
        return Pdf::loadView($contractInfo['template'], $contractInfo['view_data'])
            ->setPaper('a4', 'portrait');
    }

    /**
     * Resolve image file path to base64.
     */
    private function imageToBase64($path, $fallback = '')
    {
        if (file_exists($path)) {
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);
            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
        return $fallback;
    }

    /**
     * Resolve absolute signature path.
     */
    private function resolveSignaturePath(?string $filename, ?string $subFolder = null): ?string
    {
        if (empty($filename)) return null;

        $candidates = [
            public_path('images/signatures/' . $filename),
            public_path('images/signatures/' . basename($filename)),
        ];

        if ($subFolder) {
            $candidates[] = public_path('images/' . $subFolder . '/signatures/' . $filename);
            $candidates[] = public_path('images/' . $subFolder . '/' . $filename);
        }

        foreach ($candidates as $c) {
            if (file_exists($c)) return $c;
        }

        return $candidates[0];
    }
}
