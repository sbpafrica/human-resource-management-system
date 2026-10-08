<?php

namespace App\Services;

use App\Models\ArchiveRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ArchiveService
{
    // ── Staff ID column map (preserved exactly from original command) ──────
    public function getStaffIdColumn(string $table): string
    {
        if (str_contains($table, 'transsnet'))                                return 'staffid';
        if (str_contains($table, 'transsion'))                                return 'staffida';
        if (str_contains($table, 'tecno'))                                    return 'staffida';
        if (str_contains($table, 'xiaomi'))                                   return 'staffida';
        if (str_contains($table, 'melcom'))                                   return 'employ';
        if (str_contains($table, 'centurypay'))                               return 'employ';
        if (str_contains($table, 'driverssnit'))                              return 'employ';
        if (str_contains($table, 'twelliumaccra'))                            return 'staff_id';
        if (str_contains($table, 'twellium'))                                 return 'employ';
        if (str_contains($table, 'honda'))                                    return 'STAFFID';
        if (str_contains($table, 'bunge'))                                    return 'STAFF_ID';
        if (str_contains($table, 'hygieneexternal'))                          return 'StaffID';
        if (str_contains($table, 'offload'))                                  return 'StaffID';
        if (str_contains($table, 'olamfood'))                                 return 'StaffID';
        if (str_contains($table, 'olam') || str_contains($table, 'hygiene')) return 'id';
        if (str_contains($table, 'inhouse'))                                  return 'id';
        if (str_contains($table, 'nyonkopa'))                                 return 'staff_id';
        if (str_contains($table, 'jkay'))                                     return 'staff_id';
        if (str_contains($table, 'shefer'))                                   return 'staff_id';
        if (str_contains($table, 'fonpackage'))                               return 'staff_id';
        if (str_contains($table, 'a2feed'))                                   return 'staff_id';
        if (str_contains($table, 'a2poultry'))                                return 'staff_id';
        if (str_contains($table, 'a2soya'))                                   return 'staff_id';
        if (str_contains($table, 'pz'))                                       return 'staffid';
        if (str_contains($table, 'avara'))                                    return 'STAFFID';
        return 'staffid';
    }

    public function getNameColumn(string $table): string
    {
        if (str_contains($table, 'transsnet'))       return 'name';
        if (str_contains($table, 'transsion'))       return 'name';
        if (str_contains($table, 'tecno'))           return 'name';
        if (str_contains($table, 'xiaomi'))          return 'name';
        if (str_contains($table, 'nyonkopa'))        return 'employee_name';
        if (str_contains($table, 'jkay'))            return 'name';
        if (str_contains($table, 'pz'))              return 'name';
        if (str_contains($table, 'twelliumaccra'))   return 'name';
        if (str_contains($table, 'shefer'))          return 'name1';
        if (str_contains($table, 'fonpackage'))      return 'name';
        if (str_contains($table, 'melcom'))          return 'empoyeename';
        if (str_contains($table, 'driverssnit'))     return 'name';
        if (str_contains($table, 'centurypay'))      return 'empoyeename';
        if (str_contains($table, 'a2feed'))          return 'name';
        if (str_contains($table, 'a2poultry'))       return 'name';
        if (str_contains($table, 'a2soya'))          return 'name';
        if (str_contains($table, 'olambwh'))         return 'name';
        if (str_contains($table, 'offload'))         return 'employeename';
        if (str_contains($table, 'hygieneexternal')) return 'employeename';
        if (str_contains($table, 'olamhygiene'))     return 'names';
        if (str_contains($table, 'olamtem'))         return 'names';
        if (str_contains($table, 'olamfood'))        return 'name';
        if (str_contains($table, 'honda'))           return 'NAME';
        if (str_contains($table, 'bunge'))           return 'NAME';
        if (str_contains($table, 'twellium'))        return 'name';
        if (str_contains($table, 'inhouse'))         return 'name';
        if (str_contains($table, 'avara'))           return 'NAME';
        return 'name';
    }

    public function getPayrollTable(object $client, string $month, string $year): string
    {
        $base    = "{$client->client_dbname}{$month}{$year}";
        $withPay = "{$base}pay";
        return Schema::hasTable($withPay) ? $withPay : $base;
    }

    // ────────────────────────────────────────────────────────────────────────
    // run() — the main logic
    //
    // $month      : lowercase month name e.g. "march"
    // $year       : 2-digit year e.g. "26"
    // $isDryRun   : true = preview only, false = actually move records
    //
    // Returns a structured array consumed by both the controller and the CLI
    // ────────────────────────────────────────────────────────────────────────
    public function run(string $month, string $year, bool $isDryRun = true): array
    {
        // Parse month name + 2-digit year into a Carbon instance
        // e.g. "april" + "26" → "April 2026" → Carbon
        $fullYear      = '20' . $year;  // "26" → "2026"
        $carbonDate    = Carbon::createFromFormat('F Y', ucfirst($month) . ' ' . $fullYear);

        $futureCutoff    = (clone $carbonDate)->addMonth()->startOfMonth();
        $archiveDate     = (clone $carbonDate)->endOfMonth()->toDateString();
        $formattedPeriod = $carbonDate->format('F Y');

        // ── Step 1: Build payroll ID master list ─────────────────────────
        $clients = DB::table('client')->where('is_active', 1)->get();
        $payrollIds        = [];
        $clientTableStatus = []; // tracks which clients have/don't have tables

        foreach ($clients as $client) {
            $table      = $this->getPayrollTable($client, $month, $year);
            $staffIdCol = $this->getStaffIdColumn($table);

            // Table does not exist for this month
            if (!Schema::hasTable($table)) {
                $clientTableStatus[] = [
                    'client'  => $client->client_name,
                    'table'   => $table,
                    'status'  => 'missing',
                    'message' => "Payroll table for {$client->client_name} ({$formattedPeriod}) does not exist.",
                    'count'   => 0,
                ];
                continue;
            }

            // Table exists but is empty
            if (DB::table($table)->count() === 0) {
                $clientTableStatus[] = [
                    'client'  => $client->client_name,
                    'table'   => $table,
                    'status'  => 'empty',
                    'message' => "Payroll table for {$client->client_name} exists but has no records.",
                    'count'   => 0,
                ];
                continue;
            }

            // Staff ID column not found
            if (!Schema::hasColumn($table, $staffIdCol)) {
                $clientTableStatus[] = [
                    'client'  => $client->client_name,
                    'table'   => $table,
                    'status'  => 'column_missing',
                    'message' => "Staff ID column [{$staffIdCol}] not found in [{$table}].",
                    'count'   => 0,
                ];
                continue;
            }

            $ids = DB::table($table)
                ->pluck($staffIdCol)
                ->map(fn($id) => strtoupper(trim((string) $id)))
                ->filter()
                ->toArray();

            foreach ($ids as $id) {
                $payrollIds[$id] = true;
            }

            $clientTableStatus[] = [
                'client'  => $client->client_name,
                'table'   => $table,
                'status'  => 'loaded',
                'message' => null,
                'count'   => count($ids),
            ];
        }

        // ── Step 2: Categorise employees ─────────────────────────────────
        $allEmployees     = DB::table('newemployeedata')->get();
        $activeRows       = [];
        $archivedRows     = [];
        $heldRows         = [];
        $duplicateRows    = [];
        $errorRows        = [];

        foreach ($allEmployees as $emp) {
            $staffId   = strtoupper(trim((string) $emp->staffid));
            $inPayroll = isset($payrollIds[$staffId]);

            if ($inPayroll) {
                $activeRows[] = [
                    'staffid'      => $emp->staffid,
                    'employeename' => $emp->employeename ?? '',
                    'company'      => $emp->company ?? '',
                    'shop'         => $emp->shop ?? '',
                    'startdate'    => $emp->startdate ?? '',
                ];
                continue;
            }

            $startDate = null;
            if (!empty($emp->startdate)) {
                try { $startDate = Carbon::parse($emp->startdate); } catch (\Exception $e) {}
            }

            // On hold — future start date
            if ($startDate && $startDate->gte($futureCutoff)) {
                $heldRows[] = [
                    'staffid'      => $emp->staffid,
                    'employeename' => $emp->employeename ?? '',
                    'company'      => $emp->company ?? '',
                    'shop'         => $emp->shop ?? '',
                    'startdate'    => $emp->startdate ?? '',
                ];
                continue;
            }

            // Already in archive
            if (DB::table('newemployeedataarchieve')->where('staffid', $emp->staffid)->exists()) {
                $duplicateRows[] = [
                    'staffid'      => $emp->staffid,
                    'employeename' => $emp->employeename ?? '',
                    'company'      => $emp->company ?? '',
                    'shop'         => $emp->shop ?? '',
                    'startdate'    => $emp->startdate ?? '',
                ];
                continue;
            }

            // Will be archived
            $archivedRows[] = [
                'staffid'      => $emp->staffid,
                'employeename' => $emp->employeename ?? '',
                'company'      => $emp->company ?? '',
                'shop'         => $emp->shop ?? '',
                'startdate'    => $emp->startdate ?? '',
                'archived_at'  => $archiveDate,
            ];

            // If live run — actually move the record
            if (!$isDryRun) {
                try {
                    DB::transaction(function () use ($emp, $archiveDate) {
                        $archiveCols = DB::getSchemaBuilder()->getColumnListing('newemployeedataarchieve');
                        $data = [];
                        foreach ($archiveCols as $col) {
                            if ($col === 'archived_at') continue;
                            if (isset($emp->{$col})) {
                                $data[$col] = $emp->{$col};
                            }
                        }
                        $data['archived_at'] = $archiveDate;
                        DB::statement("SET SESSION sql_mode = ''");
                        DB::table('newemployeedataarchieve')->insert($data);
                        DB::statement("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
                        DB::table('newemployeedata')->where('staffid', $emp->staffid)->delete();
                    });
                } catch (\Exception $e) {
                    Log::error("Archive failed for {$emp->staffid}", ['error' => $e->getMessage()]);
                    $errorRows[] = [
                        'staffid'      => $emp->staffid,
                        'employeename' => $emp->employeename ?? '',
                        'company'      => $emp->company ?? '',
                        'error'        => $e->getMessage(),
                    ];
                    // Remove from archivedRows since it actually failed
                    array_pop($archivedRows);
                }
            }
        }

        // ── Step 3: Find unregistered new hires ───────────────────────────
        $existingIds = DB::table('newemployeedata')
            ->pluck('staffid')
            ->map(fn($id) => strtoupper(trim((string) $id)))
            ->toArray();

        $newHireRows = [];

        foreach ($clients as $client) {
            $table      = $this->getPayrollTable($client, $month, $year);
            $staffIdCol = $this->getStaffIdColumn($table);

            if (!Schema::hasTable($table) || DB::table($table)->count() === 0) continue;
            if (!Schema::hasColumn($table, $staffIdCol)) continue;

            $nameCol     = $this->getNameColumn($table);
            $payrollRows = DB::table($table)->get();

            foreach ($payrollRows as $row) {
                $payrollId = strtoupper(trim((string) ($row->{$staffIdCol} ?? '')));
                if ($payrollId && !in_array($payrollId, $existingIds)) {
                    $newHireRows[] = [
                        'staffid'      => $payrollId,
                        'employeename' => $row->{$nameCol} ?? 'Unknown',
                        'company'      => $client->client_name,
                        'shop'         => $row->shop ?? $row->department ?? '',
                    ];
                }
            }
        }

        // ── Step 4: Build result ──────────────────────────────────────────
        $result = [
            'isDryRun'         => $isDryRun,
            'month'            => $month,
            'year'             => $year,
            'formattedPeriod'  => $formattedPeriod,
            'archiveDate'      => $archiveDate,
            'clientStatuses'   => $clientTableStatus,
            'summary' => [
                'active'     => count($activeRows),
                'archived'   => count($archivedRows),
                'held'       => count($heldRows),
                'duplicates' => count($duplicateRows),
                'new_hires'  => count($newHireRows),
                'errors'     => count($errorRows),
            ],
            'active'     => $activeRows,
            'archived'   => $archivedRows,
            'held'       => $heldRows,
            'duplicates' => $duplicateRows,
            'new_hires'  => $newHireRows,
            'errors'     => $errorRows,
        ];

        // ── Step 5: Save audit log for live runs ──────────────────────────
        if (!$isDryRun) {
            $reportFile = $this->generateExcel($result);

            ArchiveRun::create([
                'payroll_month'    => $month,
                'payroll_year'     => $year,
                'formatted_period' => $formattedPeriod,
                'archived_count'   => count($archivedRows),
                'held_count'       => count($heldRows),
                'new_hire_count'   => count($newHireRows),
                'active_count'     => count($activeRows),
                'duplicate_count'  => count($duplicateRows),
                'error_count'      => count($errorRows),
                'report_filename'  => $reportFile,
                'run_by'           => Auth::id(),
                'run_by_name'      => Auth::user()?->name ?? 'System',
            ]);

            $result['reportFile'] = $reportFile;
        }

        return $result;
    }

    // ────────────────────────────────────────────────────────────────────────
    // generateExcel() — builds the 4-section Excel report
    // ────────────────────────────────────────────────────────────────────────
    public function generateExcel(array $result): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $sections = [
            ['title' => 'To Be Archived',       'color' => 'FFFFE0E0', 'data' => $result['archived'],   'cols' => ['Staff ID', 'Name', 'Company', 'Shop', 'Start Date', 'Archive Date'], 'keys' => ['staffid', 'employeename', 'company', 'shop', 'startdate', 'archived_at']],
            ['title' => 'On Hold',               'color' => 'FFFFF3CD', 'data' => $result['held'],       'cols' => ['Staff ID', 'Name', 'Company', 'Shop', 'Start Date'],                'keys' => ['staffid', 'employeename', 'company', 'shop', 'startdate']],
            ['title' => 'Unregistered New Hires','color' => 'FFD0EEFF', 'data' => $result['new_hires'],  'cols' => ['Staff ID', 'Name', 'Company', 'Shop'],                               'keys' => ['staffid', 'employeename', 'company', 'shop']],
            ['title' => 'Active (Untouched)',    'color' => 'FFD9F7D9', 'data' => $result['active'],     'cols' => ['Staff ID', 'Name', 'Company', 'Shop', 'Start Date'],                'keys' => ['staffid', 'employeename', 'company', 'shop', 'startdate']],
        ];

        foreach ($sections as $index => $section) {
            $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $section['title']);
            $spreadsheet->addSheet($sheet, $index);

            // Header row
            foreach ($section['cols'] as $i => $col) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1) . '1';
                $sheet->setCellValue($cell, $col);
            }
            $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($section['cols']));
            $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$lastCol}1")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($section['color']);

            // Data rows
            $row = 2;
            foreach ($section['data'] as $record) {
                foreach ($section['keys'] as $i => $key) {
                    $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1) . $row;
                    $sheet->setCellValue($cell, $record[$key] ?? '');
                }
                $row++;
            }

            if (empty($section['data'])) {
                $sheet->setCellValue('A2', 'No records in this category.');
            }

            for ($i = 1; $i <= count($section['cols']); $i++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $filename  = 'archive_report_' . $result['month'] . $result['year'] . '_' . now()->format('Ymd_His') . '.xlsx';
        $fullPath  = storage_path("app/archive_reports/{$filename}");

        if (!is_dir(storage_path('app/archive_reports'))) {
            mkdir(storage_path('app/archive_reports'), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($fullPath);

        return $filename;
    }
}
