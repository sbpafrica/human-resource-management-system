<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class PayrollColumn extends Model
{
    protected $table = 'payroll_columns';

    protected $fillable = [
        'client_id',
        'client_dbname',
        'is_custom',
        'column_name',
        'display_label',
        'header_aliases',
        'data_type',
        'position_after',
        'show_in_payslip',
        'payslip_section',
        'is_active',
    ];

    protected $casts = [
        'is_custom'       => 'boolean',
        'show_in_payslip' => 'boolean',
        'is_active'       => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_dbname', 'client_dbname');
    }

    /**
     * Get all active custom columns for a specific client (and any global columns)
     */
    public static function getColumnsForClient(string $clientDbname)
    {
        return static::where('is_active', true)
            ->where('is_custom', true)
            ->where(function ($q) use ($clientDbname) {
                $q->where('client_dbname', $clientDbname)
                  ->orWhere('client_dbname', 'all');
            })
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Get columns that should appear on the payslip
     */
    public static function getPayslipColumns(string $clientDbname)
    {
        return static::where('is_active', true)
            ->where('show_in_payslip', true)
            ->where(function ($q) use ($clientDbname) {
                $q->where('client_dbname', $clientDbname)
                  ->orWhere('client_dbname', 'all');
            })
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Add column definition to a single table safely
     */
    public static function addColumnToTable(string $tableName, PayrollColumn $col): bool
    {
        if (!Schema::hasTable($tableName)) {
            return false;
        }

        if (Schema::hasColumn($tableName, $col->column_name)) {
            return false; // Already exists
        }

        try {
            $typeSql = self::resolveSqlType($col->data_type);
            $afterSql = '';

            if (!empty($col->position_after) && Schema::hasColumn($tableName, $col->position_after)) {
                $afterSql = " AFTER `{$col->position_after}`";
            }

            DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `{$col->column_name}` {$typeSql} NULL DEFAULT NULL{$afterSql}");
            return true;
        } catch (\Throwable $e) {
            // If AFTER clause failed or any positional error occurred, try adding at the end
            try {
                DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `{$col->column_name}` {$typeSql} NULL DEFAULT NULL");
                return true;
            } catch (\Throwable $e2) {
                Log::error("Failed adding column {$col->column_name} to table {$tableName}: " . $e2->getMessage());
                return false;
            }
        }
    }

    /**
     * Ensure all custom columns for this client exist on the given table.
     * Can be invoked dynamically whenever a monthly table is created or uploaded.
     * If $clientDbname is omitted, it auto-detects from the table name.
     */
    public static function ensureColumnsForTable(string $tableName, ?string $clientDbname = null): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        if (empty($clientDbname)) {
            $clientDbname = self::detectClientFromTable($tableName);
        }

        $customCols = static::getColumnsForClient($clientDbname ?? 'all');
        foreach ($customCols as $col) {
            self::addColumnToTable($tableName, $col);
        }
    }

    /**
     * Get all possible table prefixes / aliases for a given client db name.
     */
    public static function getTablePrefixesForClient(string $clientDb): array
    {
        $clientDb = strtolower(trim($clientDb));
        $prefixes = [$clientDb];

        $aliases = [
            'transburkina'   => ['transburkina', 'transbfa', 'tbfa'],
            'togobunge'      => ['togobunge', 'togo'],
            'bungebenin'     => ['bungebenin', 'beninbunge', 'benin'],
            'bungegh'        => ['bungegh', 'bunge'],
            'driverssnit'    => ['driverssnit', 'driver', 'drivers'],
            'jkay'           => ['jkay', 'jaykay'],
            'fonpackage'     => ['fonpackage', 'fon'],
            'olambwh'        => ['olambwh', 'ofibwh'],
            'olamhygiene'    => ['olamhygiene', 'ofihygiene'],
            'hygieneexternal'=> ['hygieneexternal', 'ofiexternal'],
            'olamtem'        => ['olamtem', 'ofitemp'],
            'offload'        => ['offload', 'ofioffload'],
            'transsion'      => ['transsion', 'trans'],
            'transsiontec'   => ['transsiontec', 'tecno'],
        ];

        if (isset($aliases[$clientDb])) {
            $prefixes = array_unique(array_merge($prefixes, $aliases[$clientDb]));
        }

        return $prefixes;
    }

    /**
     * Detect the client db name from a monthly table name (e.g. 'transburkinaoctober26' -> 'transburkina').
     */
    public static function detectClientFromTable(string $tableName): ?string
    {
        $table = strtolower(trim($tableName));
        $months = 'january|february|march|april|may|june|july|august|september|october|november|december';
        $stripped = preg_replace('/(' . $months . ')\d{2,4}$/i', '', $table);

        $allClients = Client::select('client_dbname')->get()->pluck('client_dbname')->toArray();
        usort($allClients, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($allClients as $cdb) {
            $prefixes = self::getTablePrefixesForClient($cdb);
            foreach ($prefixes as $p) {
                if ($stripped === $p || str_starts_with($table, $p) || str_starts_with($stripped, $p)) {
                    return $cdb;
                }
            }
        }

        return null;
    }

    /**
     * Apply a custom column to ALL existing tables belonging to that client
     * (or ALL monthly payroll tables of all clients if client_dbname is 'all')
     */
    public static function applyToAllClientTables(PayrollColumn $col): int
    {
        $prefix = strtolower(trim($col->client_dbname));

        $excludeClause = "
            AND TABLE_NAME NOT LIKE '%_sign'
            AND TABLE_NAME NOT LIKE '%_contracts%'
            AND TABLE_NAME NOT LIKE '%attendance%'
            AND TABLE_NAME NOT LIKE '%sms%'
            AND TABLE_NAME NOT LIKE '%data'
            AND TABLE_NAME NOT LIKE '%con'
        ";

        // If 'all', apply across ALL monthly payroll tables in the entire database!
        if ($prefix === 'all') {
            $systemTables = [
                'users', 'password_resets', 'password_reset_tokens', 'failed_jobs',
                'personal_access_tokens', 'migrations', 'roles', 'permissions',
                'model_has_permissions', 'model_has_roles', 'role_has_permissions',
                'client', 'clients', 'bankdetails', 'ssnit', 'newemployeedata',
                'payroll_columns', 'sessions', 'cache', 'jobs', 'job_batches'
            ];

            $tables = DB::select("
                SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = DATABASE()
                  {$excludeClause}
            ");

            $appliedCount = 0;
            foreach ($tables as $t) {
                $tableName = $t->TABLE_NAME;
                if (!in_array(strtolower($tableName), $systemTables)) {
                    if (self::addColumnToTable($tableName, $col)) {
                        $appliedCount++;
                    }
                }
            }
            return $appliedCount;
        }

        // Find all matching tables for this client and its aliases
        $prefixes = self::getTablePrefixesForClient($prefix);
        $likeClauses = [];
        $bindings = [];

        foreach ($prefixes as $p) {
            $likeClauses[] = "TABLE_NAME = ? OR TABLE_NAME LIKE ?";
            $bindings[] = $p;
            $bindings[] = "{$p}%";
        }

        $whereLikes = implode(' OR ', $likeClauses);

        $tables = DB::select("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND ({$whereLikes})
              {$excludeClause}
        ", $bindings);

        $appliedCount = 0;
        foreach ($tables as $t) {
            $tableName = $t->TABLE_NAME;

            // If Shefer, ensure base columns exist too
            if (str_starts_with(strtolower($tableName), 'shefer')) {
                if (!Schema::hasColumn($tableName, 'total_cash_emolument')) {
                    try {
                        DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `total_cash_emolument` DECIMAL(15,2) NULL DEFAULT NULL AFTER `gross`");
                    } catch (\Throwable $e) {
                        try {
                            DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `total_cash_emolument` DECIMAL(15,2) NULL DEFAULT NULL");
                        } catch (\Throwable $e2) {}
                    }
                }
                if (!Schema::hasColumn($tableName, 'total_assessable_income')) {
                    try {
                        DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `total_assessable_income` DECIMAL(15,2) NULL DEFAULT NULL AFTER `total_cash_emolument`");
                    } catch (\Throwable $e) {
                        try {
                            DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `total_assessable_income` DECIMAL(15,2) NULL DEFAULT NULL");
                        } catch (\Throwable $e2) {}
                    }
                }
            }

            if (self::addColumnToTable($tableName, $col)) {
                $appliedCount++;
            }
        }

        return $appliedCount;
    }

    /**
     * Map friendly data type options to MySQL column definitions
     */
    public static function resolveSqlType(string $type): string
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'int', 'integer'         => 'INT',
            'bigint'                 => 'BIGINT',
            'decimal(10,2)', 'currency' => 'DECIMAL(10,2)',
            'decimal(15,2)', 'amount'   => 'DECIMAL(15,2)',
            'double', 'float'        => 'DOUBLE',
            'text'                   => 'TEXT',
            'date'                   => 'DATE',
            'boolean', 'bool'        => 'TINYINT(1)',
            default                  => str_starts_with($type, 'decimal') ? strtoupper($type) : 'VARCHAR(255)',
        };
    }

    /**
     * Normalize header string for resilient, fault-tolerant matching.
     * Lowercases, removes parenthetical currency/units, and strips punctuation.
     */
    public static function normalizeHeader(string $str): string
    {
        // Remove common parenthetical units like (GHS), (Ghc), (%), (Reference), etc.
        $cleaned = preg_replace('/\s*\((ghs|ghc|usd|eur|cfa|\$|%|cedis?|reference|memo)\)\s*/i', '', (string)$str);
        // Strip non-alphanumeric
        $norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$cleaned));
        // Strip trailing currency suffix if present
        if (strlen($norm) > 6 && (str_ends_with($norm, 'ghs') || str_ends_with($norm, 'ghc'))) {
            $norm = substr($norm, 0, -3);
        }
        return $norm;
    }

    /**
     * Get all recognized alias strings (normalized) for a given column.
     * Includes current display_label, column_name, header_aliases, plus singular/plural variants.
     */
    public static function getAllAliasesForColumn(PayrollColumn $col): array
    {
        $rawAliases = [$col->display_label, $col->column_name];

        if (!empty($col->header_aliases)) {
            // Split by comma, semicolon, pipe, or newline
            $parts = preg_split('/[,;|\n\r]+/', (string)$col->header_aliases);
            foreach ($parts as $p) {
                $trimmed = trim($p);
                if ($trimmed !== '') {
                    $rawAliases[] = $trimmed;
                }
            }
        }

        $normalized = [];
        foreach ($rawAliases as $alias) {
            $norm = self::normalizeHeader($alias);
            if ($norm === '') {
                continue;
            }
            $normalized[] = $norm;

            // Singular/plural variance
            if (str_ends_with($norm, 's') && strlen($norm) > 3) {
                $normalized[] = rtrim($norm, 's');
            } else {
                $normalized[] = $norm . 's';
            }

            // Strip optional currency/unit suffixes like "ghs", "ghc", "amount", "rate", "total"
            $cleaned = preg_replace('/(ghs|ghc|amount|rate|total|tax|income)$/', '', $norm);
            if ($cleaned !== '' && $cleaned !== $norm) {
                $normalized[] = $cleaned;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Find the column index in a given header row that matches a custom column or its aliases.
     */
    public static function findHeaderIndex(PayrollColumn|string $col, array $headerRow, ?string $clientDb = null): ?int
    {
        if (is_string($col)) {
            $colModel = static::where(function ($q) use ($col) {
                $q->where('column_name', $col)->orWhere('display_label', $col);
            });
            if (!empty($clientDb)) {
                $colModel->where(function ($q) use ($clientDb) {
                    $q->where('client_dbname', $clientDb)->orWhere('client_dbname', 'all');
                });
            }
            $resolved = $colModel->first();
            if ($resolved) {
                $col = $resolved;
            } else {
                // Direct normalization comparison against string $col
                $targetNorm = self::normalizeHeader($col);
                foreach ($headerRow as $idx => $h) {
                    if (self::normalizeHeader((string)$h) === $targetNorm) {
                        return $idx;
                    }
                }
                return null;
            }
        }

        $allAliases = self::getAllAliasesForColumn($col);
        foreach ($headerRow as $idx => $h) {
            $normH = self::normalizeHeader((string)$h);
            if ($normH !== '' && in_array($normH, $allAliases, true)) {
                return $idx;
            }
        }

        return null;
    }

    /**
     * Check if a column exists anywhere in the uploaded header row.
     */
    public static function hasColumnInHeaders(PayrollColumn|string $col, array $headerRow, ?string $clientDb = null): bool
    {
        return self::findHeaderIndex($col, $headerRow, $clientDb) !== null;
    }

    /**
     * Dynamically inject or update registered custom columns in template headers & example data.
     * If an existing header matches an old name or alias of the column, it replaces it with the
     * latest display_label so the new name appears while preserving column positions.
     */
    public static function augmentTemplate(array &$headers, ?array &$exampleRow, string $clientDb): void
    {
        $labelsMap = self::getColumnLabelsMap($clientDb);
        $aliasMap  = self::getAllHeaderAliasesMap($clientDb);

        // 1. Update any existing header in the template to its latest display label!
        foreach ($headers as $idx => $h) {
            $norm = self::normalizeHeader((string)$h);
            if (isset($aliasMap[$norm])) {
                $dbCol = $aliasMap[$norm];
                if (isset($labelsMap[$dbCol])) {
                    $headers[$idx] = $labelsMap[$dbCol];
                }
            }
        }

        $customCols = static::getColumnsForClient($clientDb);
        if ($customCols->isEmpty()) {
            return;
        }

        foreach ($customCols as $col) {
            $allAliases = self::getAllAliasesForColumn($col);

            // Check if column is already present in headers (either under current label, column_name, or old alias)
            $existingIdx = null;
            foreach ($headers as $idx => $h) {
                $normH = self::normalizeHeader((string)$h);
                if ($normH !== '' && in_array($normH, $allAliases, true)) {
                    $existingIdx = $idx;
                    break;
                }
            }

            if ($existingIdx !== null) {
                // Update the header cell with the latest display_label so new names appear!
                $headers[$existingIdx] = $col->display_label;
                continue;
            }

            // Determine insert position if position_after is configured
            $insertIdx = null;
            if (!empty($col->position_after)) {
                $cleanPos = self::normalizeHeader($col->position_after);
                foreach ($headers as $idx => $h) {
                    $normH = self::normalizeHeader((string)$h);
                    if ($normH === $cleanPos || str_contains($normH, $cleanPos)) {
                        $insertIdx = $idx + 1;
                        break;
                    }
                }
            }

            $sampleVal = match(strtolower($col->data_type)) {
                'int', 'integer' => 0,
                'text', 'varchar', 'string' => 'Sample',
                'date' => date('Y-m-d'),
                default => 0.00,
            };

            if ($insertIdx !== null) {
                array_splice($headers, $insertIdx, 0, [$col->display_label]);
                if ($exampleRow !== null) {
                    array_splice($exampleRow, $insertIdx, 0, [$sampleVal]);
                }
            } else {
                $headers[] = $col->display_label;
                if ($exampleRow !== null) {
                    $exampleRow[] = $sampleVal;
                }
            }
        }
    }

    /**
     * Map excel headers to database column names for custom columns.
     */
    public static function getCustomHeaderToDbMap(string $clientDb): array
    {
        $customCols = static::getColumnsForClient($clientDb);
        $map = [];
        foreach ($customCols as $col) {
            $map[$col->display_label] = $col->column_name;
            $map[$col->column_name]   = $col->column_name;
            if (!empty($col->header_aliases)) {
                $parts = preg_split('/[,;|\n\r]+/', (string)$col->header_aliases);
                foreach ($parts as $p) {
                    $trimmed = trim($p);
                    if ($trimmed !== '') {
                        $map[$trimmed] = $col->column_name;
                    }
                }
            }
        }
        return $map;
    }

    /**
     * Helper to extract custom column values securely from an excel row given header row.
     * Matches new changed header names, underlying db column names, and old format aliases!
     */
    public static function extractCustomColumnValues(array $row, array $headerRow, string $clientDb): array
    {
        $customCols = static::getColumnsForClient($clientDb);
        if ($customCols->isEmpty()) {
            return [];
        }

        // Index the header row by normalized name
        $headerMap = [];
        foreach ($headerRow as $idx => $h) {
            $norm = self::normalizeHeader((string)$h);
            if ($norm !== '') {
                $headerMap[$norm] = $idx;
            }
        }

        $extracted = [];
        foreach ($customCols as $col) {
            $targetIdx = null;
            $allAliases = self::getAllAliasesForColumn($col);

            foreach ($allAliases as $alias) {
                if (isset($headerMap[$alias])) {
                    $targetIdx = $headerMap[$alias];
                    break;
                }
            }

            if ($targetIdx !== null && array_key_exists($targetIdx, $row)) {
                $rawVal = $row[$targetIdx];
                if (is_numeric($rawVal)) {
                    $extracted[$col->column_name] = (float)$rawVal;
                } elseif ($rawVal === null || trim((string)$rawVal) === '' || trim((string)$rawVal) === '-') {
                    $extracted[$col->column_name] = null;
                } else {
                    $cleaned = preg_replace('/[^\d.-]/', '', str_replace(',', '', trim((string)$rawVal)));
                    $extracted[$col->column_name] = is_numeric($cleaned) ? (float)$cleaned : trim((string)$rawVal);
                }
            }
        }

        return $extracted;
    }

    /**
     * Canonical registry of all built-in base financial columns across client payroll schemas.
     * Core identifier (staff_id) is deliberately excluded from renaming as per business rules.
     */
    public static function getCanonicalStandardColumns(): array
    {
        return [
            'staff_id' => [
                'display_label'   => 'Staff ID',
                'header_aliases'  => 'Staff ID, StaffID, Staff No, Employee ID, EmployID, ID Number',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(32)',
                'description'     => 'Primary Employee Identification (Immutable Core System Key - Locked).',
                'locked'          => true,
            ],
            'basic' => [
                'display_label'   => 'Basic Salary',
                'header_aliases'  => 'Basic Salary, Basic, Base Salary, Base Pay, Basic Rate, Rate, Basic Earned',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard monthly base salary before allowances or deductions.',
            ],
            'basic_earned' => [
                'display_label'   => 'Basic Earned',
                'header_aliases'  => 'Basic Earned, Earned Basic, Basic Pay Earned, Earned Pay',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Actual base salary earned based on days worked.',
            ],
            'gross' => [
                'display_label'   => 'Gross Salary',
                'header_aliases'  => 'Gross Salary, Gross, Total Gross, Gross Pay, Total Earned, Total Earnings, Total Gross Pay',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total earnings before statutory and voluntary deductions.',
            ],
            'net_pay' => [
                'display_label'   => 'Net Pay',
                'header_aliases'  => 'Net Pay, Net, Net Salary, Take Home, Net Amount, Netpay, Take Home Pay',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Final take-home pay credited to the employee.',
            ],
            'total_allowance' => [
                'display_label'   => 'Total Allowance',
                'header_aliases'  => 'Total Allowance, Total Allowances, Allowance Total, All Allowances',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Sum of all taxable and non-taxable allowances.',
            ],
            'total_overtime' => [
                'display_label'   => 'Total Overtime',
                'header_aliases'  => 'Total Overtime, Total OT, Overtime Total, OT Total, Total Overtime Amount',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total monetary amount earned from overtime work.',
            ],
            'weekdayOT_amount' => [
                'display_label'   => 'Weekday OT Amount',
                'header_aliases'  => 'Weekday OT Amount, Weekday Overtime, Weekday OT, OT Weekday Amount, Weekday OT Pay',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Overtime pay earned on normal working days.',
            ],
            'weekendOT_amount' => [
                'display_label'   => 'Weekend OT Amount',
                'header_aliases'  => 'Weekend OT Amount, Weekend Overtime, Weekend OT, Saturday Overtime, Saturday OT',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Overtime pay earned on Saturdays or non-work days.',
            ],
            'sunOT_amount' => [
                'display_label'   => 'Sunday OT Amount',
                'header_aliases'  => 'Sunday OT Amount, Sunday Overtime, Sunday OT, Holiday OT, Sun OT Amount, Special Day OT',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Overtime pay earned on Sundays and public holidays.',
            ],
            'fixedot' => [
                'display_label'   => 'Fixed OT',
                'header_aliases'  => 'Fixed OT, Fixed Overtime, Fixed OT Amount, Fixed Overtime Pay',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Fixed contractual overtime allowance.',
            ],
            'food' => [
                'display_label'   => 'Food Allowance',
                'header_aliases'  => 'Food Allowance, Food, Lunch Allowance, Canteen Allowance, Meal Allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Allowance for lunch or canteen meals.',
            ],
            'transport' => [
                'display_label'   => 'Transport Allowance',
                'header_aliases'  => 'Transport Allowance, Transport, Travel Allowance, Commute Allowance, T&T',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Travel or commuting allowance.',
            ],
            'airtime' => [
                'display_label'   => 'Airtime Allowance',
                'header_aliases'  => 'Airtime Allowance, Airtime, Communication Allowance, Phone Allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Phone, data, or communication allowance.',
            ],
            'nightallo' => [
                'display_label'   => 'Night Allowance',
                'header_aliases'  => 'Night Allowance, Night Shift, Night Duty, Night Premium, Night Shift Allowance, Night Shift Differential',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Allowance for overnight shifts.',
            ],
            'responseallo' => [
                'display_label'   => 'Responsibility Allowance',
                'header_aliases'  => 'Responsibility Allowance, Response Allowance, Response, Acting Allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Supervisory or acting responsibility allowance.',
            ],
            'medical' => [
                'display_label'   => 'Medical Allowance',
                'header_aliases'  => 'Medical Allowance, Medical, Health Allowance, Clinic Allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Healthcare or medical benefit allowance.',
            ],
            'emp_ssnit_13_percent' => [
                'display_label'   => 'Employer SSNIT (13%)',
                'header_aliases'  => 'Employer SSNIT (13%), Employer SSNIT, SSNIT 13%, SSNIT 13 Percent, Tier 1 Employer, Employer Pension',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Statutory 13% employer pension contribution.',
            ],
            'ssnit_5_5_percent' => [
                'display_label'   => 'Employee SSNIT (5.5%)',
                'header_aliases'  => 'Employee SSNIT (5.5%), Employee SSNIT, SSNIT 5.5%, SSNIT 5.5 Percent, Tier 1 Employee, Employee Pension, SSNIT',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Statutory 5.5% employee pension deduction.',
            ],
            'petra' => [
                'display_label'   => 'PETRA',
                'header_aliases'  => 'PETRA, Tier 2, Tier 2 Pension, Petra Trust, Second Tier, Provident Fund, Petra 5%',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Mandatory Tier 2 occupational pension scheme.',
            ],
            'taxable_income' => [
                'display_label'   => 'Taxable Income',
                'header_aliases'  => 'Taxable Income, Taxable Base, Total Taxable, Net Taxable',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Income base subject to PAYE tax deduction.',
            ],
            'paye' => [
                'display_label'   => 'PAYE',
                'header_aliases'  => 'PAYE, PAYE Tax, Income Tax, Tax Deducted, Total PAYE, GRA Tax, Normal Tax-Regular',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Pay As You Earn statutory income tax.',
            ],
            'ot_tax' => [
                'display_label'   => 'OT Tax',
                'header_aliases'  => 'OT Tax, Overtime Tax, OT Tax Deducted, Tax on OT',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Separate tax assessed on overtime earnings.',
            ],
            'total_tax' => [
                'display_label'   => 'Total Tax',
                'header_aliases'  => 'Total Tax, Total Tax Deducted, Combined Tax, Tax Total',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Sum of PAYE tax and overtime tax.',
            ],
            'absent_without_pay' => [
                'display_label'   => 'Absent Without Pay',
                'header_aliases'  => 'Absent Without Pay, AWOP, Absence Deduction, Unpaid Absence, Absent Deduction',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Deduction for unauthorized or unpaid absences.',
            ],
            'salaryloan' => [
                'display_label'   => 'Salary Loan',
                'header_aliases'  => 'Salary Loan, Staff Loan, Loan, Loan Deduction, Advance, Salary Advance',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Loan repayment or salary advance deduction.',
            ],
            'total_deductions' => [
                'display_label'   => 'Total Deductions',
                'header_aliases'  => 'Total Deductions, Total Deduction, Deductions Total, All Deductions',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Sum of all statutory and voluntary payroll deductions.',
            ],
            'refunds' => [
                'display_label'   => 'Refunds',
                'header_aliases'  => 'Refunds, Refund, Reimbursement, Reimbursements, Arrears',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Reimbursements, expense refunds, or salary arrears.',
            ],
            'leavepay' => [
                'display_label'   => 'Leave Pay',
                'header_aliases'  => 'Leave Pay, Annual Leave Pay, Leave Allowance, Paid Leave',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Payment for accrued annual or earned leave.',
            ],
            'no_of_days_worked' => [
                'display_label'   => 'Days Worked',
                'header_aliases'  => 'Days Worked, No of Days Worked, Number of Days Worked, Work Days, Days',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Number of days the employee worked in the month.',
            ],
            'absent_days' => [
                'display_label'   => 'Absent Days',
                'header_aliases'  => 'Absent Days, Days Absent, Number of Absent Days, Unpaid Days',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Number of days absent.',
            ],
            'total_weekdays' => [
                'display_label'   => 'Total Weekdays',
                'header_aliases'  => 'Total Weekdays, Weekdays, Working Days in Month',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard working days in the payroll cycle.',
            ],
        ];
    }

    /**
     * Get all standard columns with any client or global overrides merged in.
     */
    public static function getStandardColumnsWithOverrides(string $clientDb): array
    {
        $canonical = self::getCanonicalStandardColumns();

        // Fetch overrides stored in database for this client (and 'all')
        $overrides = static::where('is_custom', false)
            ->where('is_active', true)
            ->where(function ($q) use ($clientDb) {
                $q->where('client_dbname', $clientDb)->orWhere('client_dbname', 'all');
            })
            ->get()
            ->keyBy('column_name');

        $result = [];
        foreach ($canonical as $colName => $def) {
            $override = $overrides->get($colName);
            $result[] = [
                'id'              => $override?->id ?? null,
                'client_dbname'   => $override?->client_dbname ?? $clientDb,
                'is_custom'       => false,
                'column_name'     => $colName,
                'display_label'   => $override?->display_label ?? $def['display_label'],
                'default_label'   => $def['display_label'],
                'header_aliases'  => $override?->header_aliases ?? $def['header_aliases'],
                'default_aliases' => $def['header_aliases'],
                'data_type'       => $def['data_type'],
                'position_after'  => $override?->position_after ?? null,
                'show_in_payslip' => $override?->show_in_payslip ?? true,
                'payslip_section' => $override?->payslip_section ?? $def['payslip_section'],
                'description'     => $def['description'] ?? '',
                'has_override'    => $override !== null,
            ];
        }

        return $result;
    }

    /**
     * Get comprehensive map of ['column_name' => 'display_label'] for all standard and custom columns.
     */
    public static function getColumnLabelsMap(string $clientDb): array
    {
        // 1. Defaults from canonical standard columns
        $map = [];
        foreach (self::getCanonicalStandardColumns() as $colName => $def) {
            $map[$colName] = $def['display_label'];
        }

        // 2. Overrides & custom columns from database
        $registered = static::where('is_active', true)
            ->where(function ($q) use ($clientDb) {
                $q->where('client_dbname', $clientDb)->orWhere('client_dbname', 'all');
            })
            ->orderBy('id', 'asc')
            ->get();

        foreach ($registered as $col) {
            $map[$col->column_name] = $col->display_label;
        }

        return $map;
    }

    /**
     * Get unified map of ['normalized_alias' => 'target_db_col'] covering ALL columns (standard + custom).
     */
    public static function getAllHeaderAliasesMap(string $clientDb): array
    {
        $aliasMap = [];

        // Core identity columns: robust matching for staff_id & name so upload never fails
        $idAliases = ['staffid', 'staff_id', 'staff id', 'employee id', 'employid', 'staffno', 'staff no', 'id number'];
        foreach ($idAliases as $a) {
            $aliasMap[self::normalizeHeader($a)] = 'staff_id';
        }

        $nameAliases = ['name', 'name1', 'full name', 'employeename', 'employee name', 'staff name'];
        foreach ($nameAliases as $a) {
            $aliasMap[self::normalizeHeader($a)] = 'name1';
        }

        // Canonical standard columns
        foreach (self::getCanonicalStandardColumns() as $colName => $def) {
            $aliasMap[self::normalizeHeader($def['display_label'])] = $colName;
            $aliasMap[self::normalizeHeader($colName)] = $colName;
            if (!empty($def['header_aliases'])) {
                foreach (preg_split('/[,;|\n\r]+/', $def['header_aliases']) as $p) {
                    $norm = self::normalizeHeader($p);
                    if ($norm !== '') {
                        $aliasMap[$norm] = $colName;
                    }
                }
            }
        }

        // Database overrides & custom columns
        $registered = static::where('is_active', true)
            ->where(function ($q) use ($clientDb) {
                $q->where('client_dbname', $clientDb)->orWhere('client_dbname', 'all');
            })
            ->get();

        foreach ($registered as $col) {
            foreach (self::getAllAliasesForColumn($col) as $alias) {
                $aliasMap[$alias] = $col->column_name;
            }
        }

        return $aliasMap;
    }

    /**
     * Universal mapper to extract all mapped column values from an excel row given header row.
     */
    public static function mapRowHeadersToDb(array $row, array $headerRow, string $clientDb): array
    {
        $aliasMap = self::getAllHeaderAliasesMap($clientDb);
        $extracted = [];

        foreach ($headerRow as $idx => $rawHeader) {
            $norm = self::normalizeHeader((string)$rawHeader);
            if ($norm === '' || !isset($aliasMap[$norm])) {
                continue;
            }

            $targetDbCol = $aliasMap[$norm];
            if (!array_key_exists($idx, $row)) {
                continue;
            }

            $rawVal = $row[$idx];
            if (is_numeric($rawVal)) {
                $extracted[$targetDbCol] = (float)$rawVal;
            } elseif ($rawVal === null || trim((string)$rawVal) === '' || trim((string)$rawVal) === '-') {
                $extracted[$targetDbCol] = null;
            } else {
                $cleaned = preg_replace('/[^\d.-]/', '', str_replace(',', '', trim((string)$rawVal)));
                $extracted[$targetDbCol] = is_numeric($cleaned) ? (float)$cleaned : trim((string)$rawVal);
            }
        }

        return $extracted;
    }
}
