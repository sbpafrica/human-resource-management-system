<?php

namespace App\Console\Commands;

use App\Models\Complaint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateLegacyComplaints extends Command
{
    protected $signature = 'complaints:migrate-legacy {--force : Truncate previously imported legacy complaints and re-import}';
    protected $description = 'Migrate historical complaints from legacy commel... tables into the unified complaints table';

    public function handle(): int
    {
        $this->info('Starting migration of legacy complaints...');

        if ($this->option('force')) {
            $this->warn('Truncating previously imported legacy complaints...');
        }

        $totalImported = self::syncLegacy((bool)$this->option('force'));

        $this->info("Legacy complaints migration completed! Total records imported: {$totalImported}");
        return Command::SUCCESS;
    }

    /**
     * Programmatically sync legacy complaint tables into the unified complaints table.
     */
    public static function syncLegacy(bool $force = false): int
    {
        if ($force) {
            Complaint::where('submission_source', 'legacy_import')->delete();
        }

        $tables = DB::select('SHOW TABLES LIKE "commel%"');
        $tableNames = array_map(fn($t) => array_values((array)$t)[0], $tables);

        $totalImported = 0;

        foreach ($tableNames as $tableName) {
            $monthPeriod = self::resolveMonthYear($tableName);
            $rows = DB::table($tableName)->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $ticketPrefix = 'LEG-MEL-' . preg_replace('/[^a-zA-Z0-9]/', '', $tableName) . '-';
            $insertData = [];

            foreach ($rows as $index => $row) {
                $staffId = trim($row->staffid ?? '');
                $name = trim($row->name ?? '');
                $shopName = trim($row->shopname ?? '');
                $complaintText = trim($row->complain ?? '');

                if (empty($staffId) && empty($complaintText)) {
                    continue;
                }

                $ticketNumber = $ticketPrefix . str_pad((string)($index + 1), 4, '0', STR_PAD_LEFT);

                // Avoid duplicate ticket numbers
                if (Complaint::where('ticket_number', $ticketNumber)->exists()) {
                    continue;
                }

                $insertData[] = [
                    'ticket_number'     => $ticketNumber,
                    'client_name'       => 'MELCOM LTD.',
                    'staff_id'          => $staffId ?: 'N/A',
                    'employee_name'     => $name ?: 'Unknown Staff',
                    'shop_name'         => $shopName ?: null,
                    'phone'             => null,
                    'email'             => null,
                    'month_period'      => $monthPeriod,
                    'category'          => 'Salary Discrepancy',
                    'complaint_text'    => $complaintText ?: 'Complaint logged without description',
                    'status'            => 'resolved',
                    'priority'          => 'medium',
                    'submission_source' => 'legacy_import',
                    'resolution_notes'  => 'Historical record imported from legacy system (' . $tableName . ')',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
            }

            if (!empty($insertData)) {
                foreach (array_chunk($insertData, 500) as $chunk) {
                    DB::table('complaints')->insert($chunk);
                }
                $totalImported += count($insertData);
            }
        }

        // Also normalize any existing legacy records that were stored as 'Melcom'
        Complaint::where('client_name', 'Melcom')->update(['client_name' => 'MELCOM LTD.']);

        return $totalImported;
    }

    public static function resolveMonthYear(string $name): string
    {
        if (preg_match('/^commel([a-z]+)(\d{2})$/i', $name, $m)) {
            $monthPart = $m[1];
            $yearPart = '20' . $m[2];
            $timestamp = strtotime($monthPart . ' ' . $yearPart);
            if ($timestamp) {
                return date('F Y', $timestamp);
            }
            return ucfirst($monthPart) . ' ' . $yearPart;
        }
        return ucfirst(str_replace('commel', '', $name));
    }
}
