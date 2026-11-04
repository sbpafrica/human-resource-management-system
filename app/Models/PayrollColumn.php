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
            'name' => [
                'display_label'   => 'Employee Name',
                'header_aliases'  => 'Name, Employee Name, Staff Name, Full Name, Emp Name, Names, Fullname',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(150)',
                'description'     => 'Full name of employee.',
            ],
            'totalabsent' => [
                'display_label'   => 'Total Absent Days',
                'header_aliases'  => 'Total Absent, Total Absent Days, Days Absent Total, Total Absenteeism',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Total number of absent days recorded.',
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
                'header_aliases'  => 'Total Weekdays, Weekdays, Working Days in Month, Legal Work Days, Legal Workdays',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard working days in the payroll cycle.',
            ],

            // =====================================================
            // OVERTIME BREAKDOWN COLUMNS
            // =====================================================
            'overtime_hours' => [
                'display_label'   => 'Overtime Hours',
                'header_aliases'  => 'Overtime Hours, OT Hours, OT Hrs, Total OT Hours, Overtime Hrs',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Total overtime hours worked in the period.',
            ],
            'overtime_rate' => [
                'display_label'   => 'Overtime Rate',
                'header_aliases'  => 'Overtime Rate, OT Rate, OT Hourly Rate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Hourly rate applied to overtime work.',
            ],
            'overtime_amount' => [
                'display_label'   => 'Overtime Amount',
                'header_aliases'  => 'Overtime Amount, OT Amount, Overtime Pay, OT Pay, Normal Overtime',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Monetary overtime amount for normal weekday OT.',
            ],
            'overtime_tax' => [
                'display_label'   => 'Overtime Tax',
                'header_aliases'  => 'Overtime Tax, OT Tax, Tax on Overtime, Overtime Tax Deduction',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Tax deducted specifically on overtime earnings.',
            ],
            'totalovertime' => [
                'display_label'   => 'Total Overtime',
                'header_aliases'  => 'Total Overtime, Total OT Amount, Total Overtime Amount, totalovertime',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Combined total of all overtime payments.',
            ],
            'weekday_overtime_hours' => [
                'display_label'   => 'Weekday OT Hours',
                'header_aliases'  => 'Weekday OT Hours, Weekday Overtime Hours, Weekday Overtime Hrs, Weekdays OT Hours',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Hours of overtime worked on weekdays.',
            ],
            'weekday_overtime_rate' => [
                'display_label'   => 'Weekday OT Rate',
                'header_aliases'  => 'Weekday OT Rate, Weekday Overtime Rate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Hourly rate applied to weekday overtime.',
            ],
            'weekday_overtime_amount' => [
                'display_label'   => 'Weekday OT Amount',
                'header_aliases'  => 'Weekday OT Amount, Weekday Overtime Amount, Weekday Overtime, Weekday OT Amt',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Monetary amount for weekday overtime.',
            ],
            'weekend_overtime_hours' => [
                'display_label'   => 'Weekend OT Hours',
                'header_aliases'  => 'Weekend OT Hours, Weekend Overtime Hours, Weekend Overtime Hrs, Saturday OT Hours',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Hours of overtime worked on weekends.',
            ],
            'weekend_overtime_rate' => [
                'display_label'   => 'Weekend OT Rate',
                'header_aliases'  => 'Weekend OT Rate, Weekend Overtime Rate, Saturday OT Rate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Hourly rate applied to weekend overtime.',
            ],
            'weekend_overtime_amount' => [
                'display_label'   => 'Weekend OT Amount',
                'header_aliases'  => 'Weekend OT Amount, Weekend Overtime Amount, Weekend Overtime, Saturday Overtime Amount',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Monetary amount for weekend/Saturday overtime.',
            ],
            'holiday_overtime_hours' => [
                'display_label'   => 'Holiday OT Hours',
                'header_aliases'  => 'Holiday OT Hours, Holiday Overtime Hours, Holiday OT Hrs, Sunday Holiday OT Hours',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Hours of overtime worked on holidays/Sundays.',
            ],
            'holiday_overtime_rate' => [
                'display_label'   => 'Holiday OT Rate',
                'header_aliases'  => 'Holiday OT Rate, Holiday Overtime Rate, Sunday OT Rate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Hourly rate applied to holiday/Sunday overtime.',
            ],
            'holiday_overtime_amount' => [
                'display_label'   => 'Holiday OT Amount',
                'header_aliases'  => 'Holiday OT Amount, Holiday Overtime Amount, Holiday OT Amt, Holiday Overtime, Sunday Holiday OT Amount',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Monetary amount for holiday/Sunday overtime.',
            ],
            'saturdays_worked' => [
                'display_label'   => 'Saturdays Worked',
                'header_aliases'  => 'Saturdays Worked, Saturday Days, Sat Days Worked, Saturday Work',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Number of Saturdays worked in the period.',
            ],
            'saturday_rate' => [
                'display_label'   => 'Saturday Rate',
                'header_aliases'  => 'Saturday Rate, Sat Rate, Saturday Hourly Rate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Hourly or daily rate for Saturday work.',
            ],
            'saturday_amount' => [
                'display_label'   => 'Saturday Amount',
                'header_aliases'  => 'Saturday Amount, Saturday Pay, Saturday OT Amount, Sat Amount',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Monetary amount earned for Saturday work.',
            ],

            // =====================================================
            // BASIC SALARY VARIANTS & WORK DAYS
            // =====================================================
            'basic_salary' => [
                'display_label'   => 'Basic Salary',
                'header_aliases'  => 'Basic Salary, Basic Pay, Monthly Salary, Base Salary, Basic',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard monthly base salary (Jay Kay / Avara / Bunge style).',
            ],
            'earned_basic_salary' => [
                'display_label'   => 'Earned Basic Salary',
                'header_aliases'  => 'Earned Basic Salary, Basic Salary Earned, Basic Earned, Earned Basic',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Salary earned after attendance adjustments.',
            ],
            'daily_rate' => [
                'display_label'   => 'Daily Rate',
                'header_aliases'  => 'Daily Rate, Day Rate, Rate Per Day, Daily Wage',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Pay rate per working day.',
            ],
            'legalworkdays' => [
                'display_label'   => 'Legal Work Days',
                'header_aliases'  => 'Legal Work Days, Legal Workdays, Standard Work Days, Expected Work Days, Working Days',
                'payslip_section' => 'info',
                'data_type'       => 'int',
                'description'     => 'Statutory or standard working days in the period.',
            ],
            'days_worked' => [
                'display_label'   => 'Days Worked',
                'header_aliases'  => 'Days Worked, Actual Days Worked, Work Days, Number of Days Worked',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Actual number of days worked by employee.',
            ],
            'absentee_days' => [
                'display_label'   => 'Absentee Days',
                'header_aliases'  => 'Absentee Days, Absent Days, Days Absent, Absenteeism Days',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Number of days absent from work.',
            ],
            'absent_deductions' => [
                'display_label'   => 'Absent Deductions',
                'header_aliases'  => 'Absent Deductions, Absent Deduction, Absent Days Deduction, Absentee Deduction',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Salary deducted for absences.',
            ],
            'total_days_amount' => [
                'display_label'   => 'Total Days Amount',
                'header_aliases'  => 'Total Days Amount, Total Days Pay, Days Total Amount',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total earnings from days worked including Saturdays.',
            ],
            'leave_days' => [
                'display_label'   => 'Leave Days',
                'header_aliases'  => 'Leave Days, Paid Leave Days, Annual Leave Days, Leave',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Number of leave days taken.',
            ],

            // =====================================================
            // NIGHT ALLOWANCE (Jay Kay / Avara variant)
            // =====================================================
            'night_allowance' => [
                'display_label'   => 'Night Allowance',
                'header_aliases'  => 'Night Allowance, Night Shift Allowance, Night Pay, Night Duty Allowance, Night Premium',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Allowance for overnight or night shift work.',
            ],

            // =====================================================
            // STATUTORY DEDUCTIONS & EMPLOYER COST
            // =====================================================
            'management_fee' => [
                'display_label'   => 'Management Fee',
                'header_aliases'  => 'Management Fee, Mgmt Fee, Service Fee, Admin Fee, Management Fee 8%',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Outsourcing or staffing management fee.',
            ],
            'getfund' => [
                'display_label'   => 'GETFund',
                'header_aliases'  => 'GETFund, GET Fund, GETFund 2.5%, GETFund Levy, GETFUND 2.5 Percent',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Ghana Education Trust Fund levy (2.5%).',
            ],
            'nhil' => [
                'display_label'   => 'NHIL',
                'header_aliases'  => 'NHIL, National Health Insurance Levy, NHIL 2.5%, Health Levy',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'National Health Insurance Levy (2.5%).',
            ],
            'vat' => [
                'display_label'   => 'VAT',
                'header_aliases'  => 'VAT, Value Added Tax, VAT 15%, VAT Levy, COVID VAT',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Value Added Tax applied on management fee.',
            ],
            'ssf' => [
                'display_label'   => 'SSF',
                'header_aliases'  => 'SSF, Social Security Fund, SSF 13%, SSF 13 Percent, Employer SSF',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Social Security Fund employer contribution.',
            ],
            'ssnit_ee' => [
                'display_label'   => 'SSNIT EE',
                'header_aliases'  => 'SSNIT EE, SSNIT Employee, SSNIT EE 5.5%, Employee SSNIT, SSNIT 5.5, SSNIT EE 0.5%',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Employee SSNIT contribution.',
            ],
            'ssnit_er' => [
                'display_label'   => 'SSNIT ER',
                'header_aliases'  => 'SSNIT ER, SSNIT Employer, SSNIT ER 13%, Employer SSNIT, SSNIT 13',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Employer SSNIT contribution.',
            ],
            'cost_to_jkay' => [
                'display_label'   => 'Cost to JKAY',
                'header_aliases'  => 'Cost to JKAY, Cost to Jay Kay, Staff Cost JKAY',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total employer cost for Jay Kay Enterprise.',
            ],
            'cost_to_shefer' => [
                'display_label'   => 'Cost to Shefer',
                'header_aliases'  => 'Cost to Shefer, Staff Cost Shefer, Shefer Cost',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total employer cost for Shefer Plastics.',
            ],
            'cost_to_nyonkopa' => [
                'display_label'   => 'Cost to Nyonkopa',
                'header_aliases'  => 'Cost to Nyonkopa, Staff Cost Nyonkopa, Nyonkopa Cost',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total employer cost for Nyonkopa.',
            ],
            'surcharge_or_student_loan' => [
                'display_label'   => 'Surcharge / Student Loan',
                'header_aliases'  => 'Surcharge, Student Loan, Surcharge/Student Loan, Surcharge or Student Loan, Loan Surcharge',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Surcharge or student loan deductions.',
            ],
            'take_home_pay' => [
                'display_label'   => 'Take Home Pay',
                'header_aliases'  => 'Take Home Pay, Net Pay, Take Home, Net Salary, Net Amount, Bank Transfer',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Final amount credited to employee bank account.',
            ],
            'gross_salary' => [
                'display_label'   => 'Gross Salary',
                'header_aliases'  => 'Gross Salary, Total Gross Salary, Gross, Gross Pay',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total earnings before deductions (Jay Kay / Avara style).',
            ],

            // =====================================================
            // EMPLOYEE INFO & POSITION COLUMNS
            // =====================================================
            'position' => [
                'display_label'   => 'Position',
                'header_aliases'  => 'Position, Designation, Job Title, Role, Job Position',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Employee job title or designation.',
            ],
            'department' => [
                'display_label'   => 'Department',
                'header_aliases'  => 'Department, Dept, Division, Section, Unit',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Employee department or division.',
            ],
            'shop' => [
                'display_label'   => 'Shop / Location',
                'header_aliases'  => 'Shop, Location, Work Place, Work Location, Branch, Site, Workplace, Shop Name',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Employee work site or shop location.',
            ],
            'join_date' => [
                'display_label'   => 'Join Date',
                'header_aliases'  => 'Join Date, Date of Employment, Hire Date, Date Employed, Date Joined, Entry Date',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Date the employee joined the company.',
            ],

            // =====================================================
            // ADDITIONAL ALLOWANCES
            // =====================================================
            'bonus' => [
                'display_label'   => 'Bonus',
                'header_aliases'  => 'Bonus, Performance Bonus, Staff Bonus, Monthly Bonus, Annual Bonus',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Performance or discretionary bonus payment.',
            ],
            'allowance' => [
                'display_label'   => 'Allowance',
                'header_aliases'  => 'Allowance, General Allowance, Monthly Allowance, Staff Allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'General or miscellaneous allowance.',
            ],
            'commission' => [
                'display_label'   => 'Commission',
                'header_aliases'  => 'Commission, Sales Commission, Incentive, Commission Earned',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Sales commission or incentive payment.',
            ],
            'welfare' => [
                'display_label'   => 'Welfare',
                'header_aliases'  => 'Welfare, Staff Welfare, Trust, Welfare Deduction, Welfare Fund',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Welfare fund or trust contribution.',
            ],
            'backpay' => [
                'display_label'   => 'Back Pay',
                'header_aliases'  => 'Back Pay, Backpay, Salary Arrears, Pay Adjustment, Underpay',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Retroactive salary or wage adjustment.',
            ],
            'fuel_and_rent_allowance' => [
                'display_label'   => 'Fuel & Rent Allowance',
                'header_aliases'  => 'Fuel and Rent Allowance, Fuel Allowance, Rent Allowance, Fuel & Rent',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Combined fuel and rent allowance.',
            ],

            // =====================================================
            // TOTAL ASSESSABLE / EMOLUMENTS (Shefer / Melcom)
            // =====================================================
            'total_cash_emolument' => [
                'display_label'   => 'Total Cash Emolument',
                'header_aliases'  => 'Total Cash Emolument, Total Cash Emoluments, Cash Emolument, Total Emoluments',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total cash emoluments before statutory deductions.',
            ],
            'total_assessable_income' => [
                'display_label'   => 'Total Assessable Income',
                'header_aliases'  => 'Total Assessable Income, Assessable Income, Total Assessable, Charge Income',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'GRA assessable income after exemptions.',
            ],

            // =====================================================
            // BANKING & IDENTIFIERS
            // =====================================================
            'bank' => [
                'display_label'   => 'Bank Name',
                'header_aliases'  => 'Bank Name, Bank, Financial Institution',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Employee bank name.',
            ],
            'account_number' => [
                'display_label'   => 'Account Number',
                'header_aliases'  => 'Account Number, Account No, Account, Bank Account, Bank Account Number',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Employee bank account number.',
            ],
            'branch_code' => [
                'display_label'   => 'Branch Code',
                'header_aliases'  => 'Branch Code, Code, Bank Code, Sort Code',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(20)',
                'description'     => 'Bank branch or sort code.',
            ],
            'ssnit_number' => [
                'display_label'   => 'SSNIT Number',
                'header_aliases'  => 'SSNIT Number, SSNIT No, SSNIT, Social Security Number, SSN',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Employee SSNIT registration number.',
            ],
            'gh_card' => [
                'display_label'   => 'Ghana Card',
                'header_aliases'  => 'Ghana Card, GH Card, Ghana Card Number, National ID',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Ghana national ID card number.',
            ],
            'tin' => [
                'display_label'   => 'TIN',
                'header_aliases'  => 'TIN, Tax Identification Number, Tax ID',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(30)',
                'description'     => 'Employee Tax Identification Number.',
            ],

            // =====================================================
            // TRANSSION / TECNO SPECIFIC
            // =====================================================
            'performbonus' => [
                'display_label'   => 'Performance Bonus',
                'header_aliases'  => 'Performance Bonus, Perform Bonus, KPI Bonus, Performance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'KPI or performance-based bonus.',
            ],
            'performstand' => [
                'display_label'   => 'Performance Standard',
                'header_aliases'  => 'Performance Standard, Perform Stand, KPI Standard',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'KPI performance standard target.',
            ],
            'coefficient' => [
                'display_label'   => 'Coefficient',
                'header_aliases'  => 'Coefficient, KPI Coefficient, Performance Coefficient',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,4)',
                'description'     => 'Performance evaluation coefficient.',
            ],
            'totalemolu' => [
                'display_label'   => 'Total Emoluments',
                'header_aliases'  => 'Total Emoluments, Total Emolu, Emoluments, Total Cash Emolument',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total emoluments for tax computation.',
            ],
            'accommodation' => [
                'display_label'   => 'Accommodation',
                'header_aliases'  => 'Accommodation, Housing, Housing Allowance, Accommodation Allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Housing or accommodation benefit.',
            ],
            'chargeincome' => [
                'display_label'   => 'Chargeable Income',
                'header_aliases'  => 'Chargeable Income, Charge Income, Assessable, Taxable Amount',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Income chargeable to tax after exemptions.',
            ],
            'ssnitamt' => [
                'display_label'   => 'SSNIT Amount',
                'header_aliases'  => 'SSNIT Amount, SSNIT Amt, SSNIT Contribution, SSNIT Pay',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'SSNIT contribution amount.',
            ],
            'nssf' => [
                'display_label'   => 'NSSF',
                'header_aliases'  => 'NSSF, National Social Security, Second Tier, Tier 2',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'National Social Security Fund (Tier 2) contribution.',
            ],
            'annaultax' => [
                'display_label'   => 'Annual Tax',
                'header_aliases'  => 'Annual Tax, Annualized Tax, Yearly Tax, Annual PAYE',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Annualized tax computation reference.',
            ],
            'hrcost' => [
                'display_label'   => 'HR Cost',
                'header_aliases'  => 'HR Cost, Human Resource Cost, Total HR Cost, Staff Cost',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total human resource cost to employer.',
            ],
            'annaulbonus' => [
                'display_label'   => 'Annual Bonus',
                'header_aliases'  => 'Annual Bonus, Annaul Bonus, Yearly Bonus, 13th Month Bonus',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Annual or 13th month bonus payment.',
            ],
            'leavededuction' => [
                'display_label'   => 'Leave Deduction',
                'header_aliases'  => 'Leave Deduction, Leave Deduct, Leave Absence Deduction, Unpaid Leave',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Salary deduction for unpaid leave.',
            ],
            'loan' => [
                'display_label'   => 'Loan',
                'header_aliases'  => 'Loan, Staff Loan, Employee Loan, Loan Deduction, Loan Repayment',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Loan repayment deduction.',
            ],
            'excess' => [
                'display_label'   => 'Excess',
                'header_aliases'  => 'Excess, Excess Pay, Salary Excess',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Excess amount for tax computation.',
            ],
            'grosspay' => [
                'display_label'   => 'Gross Pay',
                'header_aliases'  => 'Gross Pay, Total Gross, Gross Amount, Gross Total',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total gross pay before deductions (Transsion style).',
            ],
            'netpay' => [
                'display_label'   => 'Net Pay',
                'header_aliases'  => 'Net Pay, Net Salary, Take Home Pay, Final Net, Netpay',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Final net pay after all deductions (Transsion style).',
            ],
            'brandname' => [
                'display_label'   => 'Brand Name',
                'header_aliases'  => 'Brand Name, Brand, Business Line, Product Line',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Brand or business line assignment.',
            ],
            'grade' => [
                'display_label'   => 'Grade',
                'header_aliases'  => 'Grade, Job Grade, Salary Grade, Pay Grade, Level',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Employee pay grade or level.',
            ],
            'categories' => [
                'display_label'   => 'Category',
                'header_aliases'  => 'Category, Categories, Staff Category, Employee Category, Type',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Employee classification or category.',
            ],

            // =====================================================
            // AVARA / BUNGE SPECIFIC
            // =====================================================
            'cost_to_avara' => [
                'display_label'   => 'Cost to Avara',
                'header_aliases'  => 'Cost to Avara, Avara Cost, Cost to Avara GHC',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total employer cost for Avara RC.',
            ],
            'cost_to_bunge' => [
                'display_label'   => 'Cost to Bunge',
                'header_aliases'  => 'Cost to Bunge, Bunge Cost, Cost to Bunge GHC',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total employer cost for Bunge.',
            ],
            'cost_to_honda' => [
                'display_label'   => 'Cost to Honda',
                'header_aliases'  => 'Cost to Honda, Honda Cost, CostToHonda GHC',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total employer cost for Honda Manufacturing.',
            ],
            'cost_to_twellium' => [
                'display_label'   => 'Cost to Twellium',
                'header_aliases'  => 'Cost to Twellium, Twellium Cost, CostToTwe',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total employer cost for Twellium.',
            ],
            'cost_to_ofi' => [
                'display_label'   => 'Cost to OFI',
                'header_aliases'  => 'Cost to OFI, OFI Cost, Cost to Olam, CostToOlam',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total employer cost for OFI/Olam.',
            ],
            'workmen_compensation' => [
                'display_label'   => 'Workmen Compensation',
                'header_aliases'  => 'Workmen Compensation, Workers Comp, Workman Comp, WC',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Workers compensation insurance cost.',
            ],

            // =====================================================
            // BUNGE INTERNATIONAL / CIV / TOGO / BENIN
            // =====================================================
            'sursalaire' => [
                'display_label'   => 'Sursalaire',
                'header_aliases'  => 'Sursalaire, Sur Salaire, Salary Supplement, Additional Salary',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Salary supplement (Francophone payroll).',
            ],
            'cnss' => [
                'display_label'   => 'CNSS',
                'header_aliases'  => 'CNSS, CNSS Contribution, Social Security CNSS, CNPS',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Caisse Nationale de Sécurité Sociale contribution.',
            ],
            'tva' => [
                'display_label'   => 'TVA',
                'header_aliases'  => 'TVA, TVA 18%, VAT Francophone, Taxe sur la Valeur Ajoutée',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Francophone value-added tax.',
            ],

            // =====================================================
            // TRANSSION BFA SPECIFIC
            // =====================================================
            'constart' => [
                'display_label'   => 'Contract Start',
                'header_aliases'  => 'Contract Start, Con Start, Contract Start Date, Start Date',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Employment contract start date.',
            ],
            'conend' => [
                'display_label'   => 'Contract End',
                'header_aliases'  => 'Contract End, Con End, Contract End Date, End Date, Expiration Date',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Employment contract end date.',
            ],

            // =====================================================
            // MELCOM SPECIFIC
            // =====================================================
            'banktransfer' => [
                'display_label'   => 'Bank Transfer',
                'header_aliases'  => 'Bank Transfer, Net Pay, Take Home, Bank Amount',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Net pay amount for bank transfer (Melcom).',
            ],
            'trust' => [
                'display_label'   => 'Trust / Welfare',
                'header_aliases'  => 'Trust, Welfare, Staff Welfare, Trust Fund',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Trust fund or welfare deduction (Melcom).',
            ],
            'normalovertime' => [
                'display_label'   => 'Normal Overtime',
                'header_aliases'  => 'Normal Overtime, Normal OT, Weekday OT Amount',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Normal weekday overtime pay (Melcom).',
            ],
            'saturdayovertime' => [
                'display_label'   => 'Saturday Overtime',
                'header_aliases'  => 'Saturday Overtime, Saturday OT, Sat OT Amount, Saturday OT Amt',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Saturday overtime pay (Melcom).',
            ],
            'holidayotamt' => [
                'display_label'   => 'Holiday OT Amt',
                'header_aliases'  => 'Holiday OT Amt, Holiday OT Amount, Holiday Overtime Amount',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Holiday overtime amount (Melcom).',
            ],
            'totaldeduction' => [
                'display_label'   => 'Total Deduction',
                'header_aliases'  => 'Total Deduction, Total Deductions, Deductions Total',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Total of all deductions (Melcom).',
            ],
            'taxable' => [
                'display_label'   => 'Taxable',
                'header_aliases'  => 'Taxable, Taxable Income, Taxable Amount',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Taxable income amount (Melcom).',
            ],
            'totaltax' => [
                'display_label'   => 'Total Tax',
                'header_aliases'  => 'Total Tax, Tax Total, Combined Tax',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Combined total tax (Melcom).',
            ],

            // =====================================================
            // DRIVER / SHEFER SPECIFIC
            // =====================================================
            'service_charge' => [
                'display_label'   => 'Service Charge',
                'header_aliases'  => 'Service Charge, Service Fee, Service Charge 7%, Outsource Service Fee',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Outsourcing or service charge fee.',
            ],
            'covid' => [
                'display_label'   => 'COVID-19 Levy',
                'header_aliases'  => 'COVID, COVID-19, COVID 19, COVID Levy, COVID 1%, Covid 19 Levy',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'COVID-19 health recovery levy.',
            ],

            // =====================================================
            // ALL CLIENT-SPECIFIC & TEMPLATE COLUMNS (EXPANDED)
            // =====================================================
            '13month' => [
                'display_label'   => '13month',
                'header_aliases'  => '13month',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for 13month.',
            ],
            '13tax' => [
                'display_label'   => '13tax',
                'header_aliases'  => '13tax',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for 13tax.',
            ],
            'absent_days_deduction' => [
                'display_label'   => 'Absent Days Deduction',
                'header_aliases'  => 'absent_days_deduction, Absent Days Deduction, absent days deduction',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Absent Days Deduction.',
            ],
            'absent_days_deductions' => [
                'display_label'   => 'Absent Days Deductions',
                'header_aliases'  => 'absent_days_deductions, Absent Days Deductions, absent days deductions',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Absent Days Deductions.',
            ],
            'absent_deduction' => [
                'display_label'   => 'Absent Deduction',
                'header_aliases'  => 'absent_deduction, Absent Deduction, absent deduction',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Absent Deduction.',
            ],
            'absentdays' => [
                'display_label'   => 'Absentdays',
                'header_aliases'  => 'absentdays, Absentdays',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Absentdays.',
            ],
            'absenteeism_days' => [
                'display_label'   => 'Absenteeism Days',
                'header_aliases'  => 'absenteeism_days, Absenteeism Days, absenteeism days',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Absenteeism Days.',
            ],
            'absenthrs' => [
                'display_label'   => 'Absenthrs',
                'header_aliases'  => 'absenthrs, Absenthrs',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Absenthrs.',
            ],
            'absentwithoutpay' => [
                'display_label'   => 'Absentwithoutpay',
                'header_aliases'  => 'absentwithoutpay, Absentwithoutpay',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Absentwithoutpay.',
            ],
            'account1' => [
                'display_label'   => 'Account1',
                'header_aliases'  => 'account1, Account1',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Account1.',
            ],
            'account_name' => [
                'display_label'   => 'Account Name',
                'header_aliases'  => 'account_name, Account Name, account name',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Account Name.',
            ],
            'accountnumber' => [
                'display_label'   => 'Accountnumber',
                'header_aliases'  => 'ACCOUNTNUMBER, Accountnumber, accountnumber',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Accountnumber.',
            ],
            'actualdays' => [
                'display_label'   => 'Actualdays',
                'header_aliases'  => 'actualdays, Actualdays',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Actualdays.',
            ],
            'additional_allowance_ghc' => [
                'display_label'   => 'Additional Allowance',
                'header_aliases'  => 'ADDITIONAL_ALLOWANCE_GHC, Additional Allowance, additional_allowance_ghc, additional allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Additional Allowance.',
            ],
            'advance' => [
                'display_label'   => 'Advance',
                'header_aliases'  => 'advance, Advance',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Advance.',
            ],
            'after_tax_deductions' => [
                'display_label'   => 'After Tax Deductions',
                'header_aliases'  => 'after_tax_deductions, After Tax Deductions, after tax deductions',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for After Tax Deductions.',
            ],
            'age' => [
                'display_label'   => 'Age',
                'header_aliases'  => 'age, Age',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Age.',
            ],
            'airtimebasic' => [
                'display_label'   => 'Airtimebasic',
                'header_aliases'  => 'airtimebasic, Airtimebasic',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Airtimebasic.',
            ],
            'alcnum' => [
                'display_label'   => 'Alcnum',
                'header_aliases'  => 'alcnum, Alcnum',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Alcnum.',
            ],
            'amount' => [
                'display_label'   => 'Amount',
                'header_aliases'  => 'amount, Amount',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Amount.',
            ],
            'amt' => [
                'display_label'   => 'Amt',
                'header_aliases'  => 'amt, Amt',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Amt.',
            ],
            'arrears' => [
                'display_label'   => 'Arrears',
                'header_aliases'  => 'arrears, Arrears',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Arrears.',
            ],
            'bank1' => [
                'display_label'   => 'Bank1',
                'header_aliases'  => 'bank1, Bank1',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Bank1.',
            ],
            'bank_account_no' => [
                'display_label'   => 'Bank Account No',
                'header_aliases'  => 'bank_account_no, Bank Account No, bank account no',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Bank Account No.',
            ],
            'bank_name' => [
                'display_label'   => 'Bank Name',
                'header_aliases'  => 'bank_name, Bank Name, bank name',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Bank Name.',
            ],
            'bankname' => [
                'display_label'   => 'Bankname',
                'header_aliases'  => 'bankname, Bankname',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Bankname.',
            ],
            'base_for_paye' => [
                'display_label'   => 'Base For Paye',
                'header_aliases'  => 'base_for_paye, Base For Paye, base for paye',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Base For Paye.',
            ],
            'base_salary' => [
                'display_label'   => 'Base Salary',
                'header_aliases'  => 'base_salary, Base Salary, base salary',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Base Salary.',
            ],
            'base_salary_ghc' => [
                'display_label'   => 'Base Salary',
                'header_aliases'  => 'BASE_SALARY_GHC, Base Salary, base_salary_ghc, base salary',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Base Salary.',
            ],
            'basic_salary_earned' => [
                'display_label'   => 'Basic Salary Earned',
                'header_aliases'  => 'basic_salary_earned, Basic Salary Earned, basic salary earned',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Basic Salary Earned.',
            ],
            'basicentitlement' => [
                'display_label'   => 'Basicentitlement',
                'header_aliases'  => 'basicentitlement, Basicentitlement',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Basicentitlement.',
            ],
            'basicsalary' => [
                'display_label'   => 'Basicsalary',
                'header_aliases'  => 'basicsalary, Basicsalary',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Basicsalary.',
            ],
            'basicsalary_ghc' => [
                'display_label'   => 'Basicsalary',
                'header_aliases'  => 'BASICSALARY_GHC, Basicsalary, basicsalary_ghc, basicsalary',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Basicsalary.',
            ],
            'basicsalaryearned' => [
                'display_label'   => 'Basicsalaryearned',
                'header_aliases'  => 'basicsalaryearned, Basicsalaryearned',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Basicsalaryearned.',
            ],
            'basicsalaryearned_ghc' => [
                'display_label'   => 'Basicsalaryearned',
                'header_aliases'  => 'BASICSALARYEARNED_GHC, Basicsalaryearned, basicsalaryearned_ghc, basicsalaryearned',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Basicsalaryearned.',
            ],
            'bonus_earnings_allowance' => [
                'display_label'   => 'Bonus Earnings Allowance',
                'header_aliases'  => 'BONUS_EARNINGS_ALLOWANCE, Bonus Earnings Allowance, bonus_earnings_allowance, bonus earnings allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Bonus Earnings Allowance.',
            ],
            'bonus_tax' => [
                'display_label'   => 'Bonus Tax',
                'header_aliases'  => 'BONUS_TAX, Bonus Tax, bonus_tax, bonus tax',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Bonus Tax.',
            ],
            'branch' => [
                'display_label'   => 'Branch',
                'header_aliases'  => 'BRANCH, Branch, branch',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Branch.',
            ],
            'branch1' => [
                'display_label'   => 'Branch1',
                'header_aliases'  => 'branch1, Branch1',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Branch1.',
            ],
            'business_line' => [
                'display_label'   => 'Business Line',
                'header_aliases'  => 'business_line, Business Line, business line',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Business Line.',
            ],
            'c2c' => [
                'display_label'   => 'C2c',
                'header_aliases'  => 'c2c, C2c',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for C2c.',
            ],
            'call_allowance' => [
                'display_label'   => 'Call Allowance',
                'header_aliases'  => 'call_allowance, Call Allowance, call allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Call Allowance.',
            ],
            'category' => [
                'display_label'   => 'Category',
                'header_aliases'  => 'category, Category',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Category.',
            ],
            'col12' => [
                'display_label'   => 'Col12',
                'header_aliases'  => 'col12, Col12',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Col12.',
            ],
            'contracting_entity' => [
                'display_label'   => 'Contracting Entity',
                'header_aliases'  => 'contracting_entity, Contracting Entity, contracting entity',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Contracting Entity.',
            ],
            'cost_to_a2_agro' => [
                'display_label'   => 'Cost To A2 Agro',
                'header_aliases'  => 'cost_to_a2_agro, Cost To A2 Agro, cost to a2 agro',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Cost To A2 Agro.',
            ],
            'cost_to_bunge_ghc' => [
                'display_label'   => 'Cost To Bunge',
                'header_aliases'  => 'COST_TO_BUNGE_GHC, Cost To Bunge, cost_to_bunge_ghc, cost to bunge',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Cost To Bunge.',
            ],
            'cost_to_fon_packaging' => [
                'display_label'   => 'Cost To Fon Packaging',
                'header_aliases'  => 'cost_to_fon_packaging, Cost To Fon Packaging, cost to fon packaging',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Cost To Fon Packaging.',
            ],
            'costofolam' => [
                'display_label'   => 'Costofolam',
                'header_aliases'  => 'costofolam, Costofolam',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Costofolam.',
            ],
            'costtohonda_ghc' => [
                'display_label'   => 'Costtohonda',
                'header_aliases'  => 'COSTTOHONDA_GHC, Costtohonda, costtohonda_ghc, costtohonda',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Costtohonda.',
            ],
            'costtoolam' => [
                'display_label'   => 'Costtoolam',
                'header_aliases'  => 'costtoolam, Costtoolam',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Costtoolam.',
            ],
            'covid19' => [
                'display_label'   => 'Covid19',
                'header_aliases'  => 'covid19, Covid19',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Covid19.',
            ],
            'covid_1' => [
                'display_label'   => 'Covid 1',
                'header_aliases'  => 'covid_1, Covid 1, covid 1',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Covid 1.',
            ],
            'covid_19' => [
                'display_label'   => 'Covid 19',
                'header_aliases'  => 'covid_19, Covid 19, covid 19',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Covid 19.',
            ],
            'covid_19_1' => [
                'display_label'   => 'Covid 19 1',
                'header_aliases'  => 'COVID_19_1, Covid 19 1, covid_19_1, covid 19 1',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Covid 19 1.',
            ],
            'covid_19_1_percent' => [
                'display_label'   => 'Covid 19',
                'header_aliases'  => 'COVID_19_1_PERCENT, Covid 19, covid_19_1_percent, covid 19',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Covid 19.',
            ],
            'crane' => [
                'display_label'   => 'Crane',
                'header_aliases'  => 'crane, Crane',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Crane.',
            ],
            'date_of_employment' => [
                'display_label'   => 'Date Of Employment',
                'header_aliases'  => 'date_of_employment, Date Of Employment, date of employment',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Date Of Employment.',
            ],
            'dateemploy' => [
                'display_label'   => 'Dateemploy',
                'header_aliases'  => 'dateemploy, Dateemploy',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Dateemploy.',
            ],
            'dateofemployment' => [
                'display_label'   => 'Dateofemployment',
                'header_aliases'  => 'DATEOFEMPLOYMENT, Dateofemployment, dateofemployment',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Dateofemployment.',
            ],
            'deduction' => [
                'display_label'   => 'Deduction',
                'header_aliases'  => 'deduction, Deduction',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Deduction.',
            ],
            'deductions' => [
                'display_label'   => 'Deductions',
                'header_aliases'  => 'deductions, Deductions',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Deductions.',
            ],
            'dept' => [
                'display_label'   => 'Dept',
                'header_aliases'  => 'dept, Dept',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Dept.',
            ],
            'desig' => [
                'display_label'   => 'Desig',
                'header_aliases'  => 'desig, Desig',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Desig.',
            ],
            'designation' => [
                'display_label'   => 'Designation',
                'header_aliases'  => 'designation, Designation',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Designation.',
            ],
            'dob' => [
                'display_label'   => 'Dob',
                'header_aliases'  => 'dob, Dob',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Dob.',
            ],
            'ee_cost' => [
                'display_label'   => 'Ee Cost',
                'header_aliases'  => 'ee_cost, Ee Cost, ee cost',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Ee Cost.',
            ],
            'ee_paye' => [
                'display_label'   => 'Ee Paye',
                'header_aliases'  => 'ee_paye, Ee Paye, ee paye',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Ee Paye.',
            ],
            'employee_name' => [
                'display_label'   => 'Employee Name',
                'header_aliases'  => 'employee_name, Employee Name, employee name',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Employee Name.',
            ],
            'employer_provident_fund_5' => [
                'display_label'   => 'Employer Provident Fund 5',
                'header_aliases'  => 'employer_provident_fund_5, Employer Provident Fund 5, employer provident fund 5',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Employer Provident Fund 5.',
            ],
            'end_of_year' => [
                'display_label'   => 'End Of Year',
                'header_aliases'  => 'end_of_year, End Of Year, end of year',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for End Of Year.',
            ],
            'er_cost' => [
                'display_label'   => 'Er Cost',
                'header_aliases'  => 'er_cost, Er Cost, er cost',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Er Cost.',
            ],
            'expected_work_days' => [
                'display_label'   => 'Expected Work Days',
                'header_aliases'  => 'expected_work_days, Expected Work Days, expected work days',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Expected Work Days.',
            ],
            'expiration_date' => [
                'display_label'   => 'Expiration Date',
                'header_aliases'  => 'expiration_date, Expiration Date, expiration date',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Expiration Date.',
            ],
            'extraldays' => [
                'display_label'   => 'Extraldays',
                'header_aliases'  => 'extraldays, Extraldays',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Extraldays.',
            ],
            'f_tamount' => [
                'display_label'   => 'F Tamount',
                'header_aliases'  => 'f_tamount, F Tamount, f tamount',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for F Tamount.',
            ],
            'f_trate' => [
                'display_label'   => 'F Trate',
                'header_aliases'  => 'f_trate, F Trate, f trate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for F Trate.',
            ],
            'fixed_sunday_overtime' => [
                'display_label'   => 'Fixed Sunday Overtime',
                'header_aliases'  => 'fixed_sunday_overtime, Fixed Sunday Overtime, fixed sunday overtime',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Fixed Sunday Overtime.',
            ],
            'fixedotbasic' => [
                'display_label'   => 'Fixedotbasic',
                'header_aliases'  => 'fixedotbasic, Fixedotbasic',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Fixedotbasic.',
            ],
            'food_allowance' => [
                'display_label'   => 'Food Allowance',
                'header_aliases'  => 'food_allowance, Food Allowance, food allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Food Allowance.',
            ],
            'foodallowance' => [
                'display_label'   => 'Foodallowance',
                'header_aliases'  => 'foodallowance, Foodallowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Foodallowance.',
            ],
            'foodbasic' => [
                'display_label'   => 'Foodbasic',
                'header_aliases'  => 'foodbasic, Foodbasic',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Foodbasic.',
            ],
            'getfund_2_5' => [
                'display_label'   => 'Getfund 2 5',
                'header_aliases'  => 'getfund_2_5, Getfund 2 5, getfund 2 5',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Getfund 2 5.',
            ],
            'getfund_2_5_percent' => [
                'display_label'   => 'Getfund',
                'header_aliases'  => 'GETFUND_2_5_PERCENT, Getfund, getfund_2_5_percent, getfund',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Getfund.',
            ],
            'gfl' => [
                'display_label'   => 'Gfl',
                'header_aliases'  => 'gfl, Gfl',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Gfl.',
            ],
            'gh_card_number' => [
                'display_label'   => 'Gh Card Number',
                'header_aliases'  => 'GH_CARD_NUMBER, Gh Card Number, gh_card_number, gh card number',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Gh Card Number.',
            ],
            'ghcard' => [
                'display_label'   => 'Ghcard',
                'header_aliases'  => 'ghcard, Ghcard',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Ghcard.',
            ],
            'ghcardnumber' => [
                'display_label'   => 'Ghcardnumber',
                'header_aliases'  => 'GHCARDNUMBER, Ghcardnumber, ghcardnumber',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Ghcardnumber.',
            ],
            'gross2' => [
                'display_label'   => 'Gross2',
                'header_aliases'  => 'gross2, Gross2',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Gross2.',
            ],
            'gross_salary_ghc' => [
                'display_label'   => 'Gross Salary',
                'header_aliases'  => 'GROSS_SALARY_GHC, Gross Salary, gross_salary_ghc, gross salary',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Gross Salary.',
            ],
            'gross_salary_repeat' => [
                'display_label'   => 'Gross Salary',
                'header_aliases'  => 'gross_salary_repeat, Gross Salary, gross salary',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Gross Salary.',
            ],
            'grosssalary_ghc' => [
                'display_label'   => 'Grosssalary',
                'header_aliases'  => 'GROSSSALARY_GHC, Grosssalary, grosssalary_ghc, grosssalary',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Grosssalary.',
            ],
            'halfdays' => [
                'display_label'   => 'Halfdays',
                'header_aliases'  => 'halfdays, Halfdays',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Halfdays.',
            ],
            'hired_date' => [
                'display_label'   => 'Hired Date',
                'header_aliases'  => 'hired_date, Hired Date, hired date',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Hired Date.',
            ],
            'hiredate' => [
                'display_label'   => 'Hiredate',
                'header_aliases'  => 'hiredate, Hiredate',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Hiredate.',
            ],
            'holiday' => [
                'display_label'   => 'Holiday',
                'header_aliases'  => 'holiday, Holiday',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Holiday.',
            ],
            'holidayot' => [
                'display_label'   => 'Holidayot',
                'header_aliases'  => 'holidayot, Holidayot',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Holidayot.',
            ],
            'house_allowance' => [
                'display_label'   => 'House Allowance',
                'header_aliases'  => 'house_allowance, House Allowance, house allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for House Allowance.',
            ],
            'incentive' => [
                'display_label'   => 'Incentive',
                'header_aliases'  => 'incentive, Incentive',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Incentive.',
            ],
            'joindate' => [
                'display_label'   => 'Joindate',
                'header_aliases'  => 'joindate, Joindate',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Joindate.',
            ],
            'last_date' => [
                'display_label'   => 'Last Date',
                'header_aliases'  => 'last_date, Last Date, last date',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Last Date.',
            ],
            'leave1' => [
                'display_label'   => 'Leave1',
                'header_aliases'  => 'leave1, Leave1',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Leave1.',
            ],
            'leaveamount' => [
                'display_label'   => 'Leaveamount',
                'header_aliases'  => 'LEAVEAMOUNT, Leaveamount, leaveamount',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Leaveamount.',
            ],
            'leavedays' => [
                'display_label'   => 'Leavedays',
                'header_aliases'  => 'LEAVEDAYS, Leavedays, leavedays',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Leavedays.',
            ],
            'leavededuct' => [
                'display_label'   => 'Leavededuct',
                'header_aliases'  => 'leavededuct, Leavededuct',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Leavededuct.',
            ],
            'legal_work_days' => [
                'display_label'   => 'Legal Work Days',
                'header_aliases'  => 'legal_work_days, Legal Work Days, legal work days',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Legal Work Days.',
            ],
            'legalwork' => [
                'display_label'   => 'Legalwork',
                'header_aliases'  => 'legalwork, Legalwork',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Legalwork.',
            ],
            'management_fee_10' => [
                'display_label'   => 'Management Fee',
                'header_aliases'  => 'management_fee_10, Management Fee, management fee',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Management Fee.',
            ],
            'management_fee_5_percent' => [
                'display_label'   => 'Management Fee',
                'header_aliases'  => 'MANAGEMENT_FEE_5_PERCENT, Management Fee, management_fee_5_percent, management fee',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Management Fee.',
            ],
            'management_fee_8' => [
                'display_label'   => 'Management Fee',
                'header_aliases'  => 'management_fee_8, Management Fee, management fee',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Management Fee.',
            ],
            'managementfee_8' => [
                'display_label'   => 'Managementfee',
                'header_aliases'  => 'MANAGEMENTFEE_8, Managementfee, managementfee_8, managementfee',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Managementfee.',
            ],
            'medical_insurance' => [
                'display_label'   => 'Medical Insurance',
                'header_aliases'  => 'medical_insurance, Medical Insurance, medical insurance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Medical Insurance.',
            ],
            'medicalbasic' => [
                'display_label'   => 'Medicalbasic',
                'header_aliases'  => 'medicalbasic, Medicalbasic',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Medicalbasic.',
            ],
            'medicalinsurance' => [
                'display_label'   => 'Medicalinsurance',
                'header_aliases'  => 'medicalinsurance, Medicalinsurance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Medicalinsurance.',
            ],
            'mgt_fee' => [
                'display_label'   => 'Mgt Fee',
                'header_aliases'  => 'mgt_fee, Mgt Fee, mgt fee',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Mgt Fee.',
            ],
            'net_salary' => [
                'display_label'   => 'Net Salary',
                'header_aliases'  => 'net_salary, Net Salary, net salary',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Net Salary.',
            ],
            'nhiil' => [
                'display_label'   => 'Nhiil',
                'header_aliases'  => 'nhiil, Nhiil',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Nhiil.',
            ],
            'nhil_2_5' => [
                'display_label'   => 'Nhil 2 5',
                'header_aliases'  => 'nhil_2_5, Nhil 2 5, nhil 2 5',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Nhil 2 5.',
            ],
            'nhil_2_5_percent' => [
                'display_label'   => 'Nhil',
                'header_aliases'  => 'NHIL_2_5_PERCENT, Nhil, nhil_2_5_percent, nhil',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Nhil.',
            ],
            'night' => [
                'display_label'   => 'Night',
                'header_aliases'  => 'night, Night',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Night.',
            ],
            'night_duty_allowance' => [
                'display_label'   => 'Night Duty Allowance',
                'header_aliases'  => 'night_duty_allowance, Night Duty Allowance, night duty allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Night Duty Allowance.',
            ],
            'nightallowance' => [
                'display_label'   => 'Nightallowance',
                'header_aliases'  => 'nightallowance, Nightallowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Nightallowance.',
            ],
            'nightdays' => [
                'display_label'   => 'Nightdays',
                'header_aliases'  => 'nightdays, Nightdays',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Nightdays.',
            ],
            'no_of_absent_days' => [
                'display_label'   => 'No Of Absent Days',
                'header_aliases'  => 'no_of_absent_days, No Of Absent Days, no of absent days',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for No Of Absent Days.',
            ],
            'noofdays' => [
                'display_label'   => 'Noofdays',
                'header_aliases'  => 'noofdays, Noofdays',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Noofdays.',
            ],
            'noofdayspresent' => [
                'display_label'   => 'Noofdayspresent',
                'header_aliases'  => 'noofdayspresent, Noofdayspresent',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Noofdayspresent.',
            ],
            'normal_weekday_overtime_hours' => [
                'display_label'   => 'Normal Weekday Overtime Hours',
                'header_aliases'  => 'normal_weekday_overtime_hours, Normal Weekday Overtime Hours, normal weekday overtime hours',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Normal Weekday Overtime Hours.',
            ],
            'normal_weekday_overtime_rate' => [
                'display_label'   => 'Normal Weekday Overtime Rate',
                'header_aliases'  => 'normal_weekday_overtime_rate, Normal Weekday Overtime Rate, normal weekday overtime rate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Normal Weekday Overtime Rate.',
            ],
            'num_sundays_holidays' => [
                'display_label'   => 'Num Sundays Holidays',
                'header_aliases'  => 'num_sundays_holidays, Num Sundays Holidays, num sundays holidays',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Num Sundays Holidays.',
            ],
            'number_of_days_on_night_duty' => [
                'display_label'   => 'Number Of Days On Night Duty',
                'header_aliases'  => 'number_of_days_on_night_duty, Number Of Days On Night Duty, number of days on night duty',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Number Of Days On Night Duty.',
            ],
            'number_of_days_worked' => [
                'display_label'   => 'Number Of Days Worked',
                'header_aliases'  => 'number_of_days_worked, Number Of Days Worked, number of days worked',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Number Of Days Worked.',
            ],
            'numberofdaysworked' => [
                'display_label'   => 'Numberofdaysworked',
                'header_aliases'  => 'NUMBEROFDAYSWORKED, Numberofdaysworked, numberofdaysworked',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Numberofdaysworked.',
            ],
            'offday_holiday_overtime_hours' => [
                'display_label'   => 'Offday Holiday Overtime Hours',
                'header_aliases'  => 'offday_holiday_overtime_hours, Offday Holiday Overtime Hours, offday holiday overtime hours',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Offday Holiday Overtime Hours.',
            ],
            'offday_holiday_overtime_rate' => [
                'display_label'   => 'Offday Holiday Overtime Rate',
                'header_aliases'  => 'offday_holiday_overtime_rate, Offday Holiday Overtime Rate, offday holiday overtime rate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Offday Holiday Overtime Rate.',
            ],
            'offday_holiday_overtime_total' => [
                'display_label'   => 'Offday Holiday Overtime Total',
                'header_aliases'  => 'offday_holiday_overtime_total, Offday Holiday Overtime Total, offday holiday overtime total',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Offday Holiday Overtime Total.',
            ],
            'other' => [
                'display_label'   => 'Other',
                'header_aliases'  => 'other, Other',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Other.',
            ],
            'other_deduction' => [
                'display_label'   => 'Other Deduction',
                'header_aliases'  => 'other_deduction, Other Deduction, other deduction',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Other Deduction.',
            ],
            'other_income' => [
                'display_label'   => 'Other Income',
                'header_aliases'  => 'other_income, Other Income, other income',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Other Income.',
            ],
            'otherdeduction' => [
                'display_label'   => 'Otherdeduction',
                'header_aliases'  => 'otherdeduction, Otherdeduction',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Otherdeduction.',
            ],
            'otholiday' => [
                'display_label'   => 'Otholiday',
                'header_aliases'  => 'otholiday, Otholiday',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Otholiday.',
            ],
            'otweekend' => [
                'display_label'   => 'Otweekend',
                'header_aliases'  => 'otweekend, Otweekend',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Otweekend.',
            ],
            'otworkdays' => [
                'display_label'   => 'Otworkdays',
                'header_aliases'  => 'otworkdays, Otworkdays',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Otworkdays.',
            ],
            'otworkingday' => [
                'display_label'   => 'Otworkingday',
                'header_aliases'  => 'otworkingday, Otworkingday',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Otworkingday.',
            ],
            'outsourced' => [
                'display_label'   => 'Outsourced',
                'header_aliases'  => 'outsourced, Outsourced',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Outsourced.',
            ],
            'outsourced_service' => [
                'display_label'   => 'Outsourced Service',
                'header_aliases'  => 'outsourced_service, Outsourced Service, outsourced service',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Outsourced Service.',
            ],
            'outsourcename' => [
                'display_label'   => 'Outsourcename',
                'header_aliases'  => 'outsourcename, Outsourcename',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Outsourcename.',
            ],
            'overtime' => [
                'display_label'   => 'Overtime',
                'header_aliases'  => 'overtime, Overtime',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Overtime.',
            ],
            'overtime_ghc' => [
                'display_label'   => 'Overtime',
                'header_aliases'  => 'OVERTIME_GHC, Overtime, overtime_ghc, overtime',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Overtime.',
            ],
            'overtimehr' => [
                'display_label'   => 'Overtimehr',
                'header_aliases'  => 'overtimehr, Overtimehr',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Overtimehr.',
            ],
            'overtimerefund' => [
                'display_label'   => 'Overtimerefund',
                'header_aliases'  => 'overtimerefund, Overtimerefund',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Overtimerefund.',
            ],
            'overtimetax' => [
                'display_label'   => 'Overtimetax',
                'header_aliases'  => 'overtimetax, Overtimetax',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Overtimetax.',
            ],
            'overtimetaxt' => [
                'display_label'   => 'Overtimetaxt',
                'header_aliases'  => 'overtimetaxt, Overtimetaxt',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Overtimetaxt.',
            ],
            'overtimetotal' => [
                'display_label'   => 'Overtimetotal',
                'header_aliases'  => 'overtimetotal, Overtimetotal',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Overtimetotal.',
            ],
            'overtimetotal2' => [
                'display_label'   => 'Overtimetotal2',
                'header_aliases'  => 'overtimetotal2, Overtimetotal2',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Overtimetotal2.',
            ],
            'overtimeweekend' => [
                'display_label'   => 'Overtimeweekend',
                'header_aliases'  => 'overtimeweekend, Overtimeweekend',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Overtimeweekend.',
            ],
            'paye_ghc' => [
                'display_label'   => 'Paye',
                'header_aliases'  => 'PAYE_GHC, Paye, paye_ghc, paye',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Paye.',
            ],
            'performance_basis' => [
                'display_label'   => 'Performance Basis',
                'header_aliases'  => 'performance_basis, Performance Basis, performance basis',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Performance Basis.',
            ],
            'performance_final' => [
                'display_label'   => 'Performance Final',
                'header_aliases'  => 'performance_final, Performance Final, performance final',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Performance Final.',
            ],
            'performancebonus' => [
                'display_label'   => 'Performancebonus',
                'header_aliases'  => 'PERFORMANCEBONUS, Performancebonus, performancebonus',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Performancebonus.',
            ],
            'performancebonusstandard' => [
                'display_label'   => 'Performancebonusstandard',
                'header_aliases'  => 'PERFORMANCEBONUSSTANDARD, Performancebonusstandard, performancebonusstandard',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Performancebonusstandard.',
            ],
            'petra_5' => [
                'display_label'   => 'Petra 5',
                'header_aliases'  => 'PETRA_5, Petra 5, petra_5, petra 5',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Petra 5.',
            ],
            'petra_ee_5' => [
                'display_label'   => 'Petra 5',
                'header_aliases'  => 'petra_ee_5, Petra 5, petra 5',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Petra 5.',
            ],
            'pf' => [
                'display_label'   => 'Pf',
                'header_aliases'  => 'pf, Pf',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Pf.',
            ],
            'phonecredit' => [
                'display_label'   => 'Phonecredit',
                'header_aliases'  => 'phonecredit, Phonecredit',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Phonecredit.',
            ],
            'ppe' => [
                'display_label'   => 'Ppe',
                'header_aliases'  => 'ppe, Ppe',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Ppe.',
            ],
            'rate' => [
                'display_label'   => 'Rate',
                'header_aliases'  => 'rate, Rate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Rate.',
            ],
            'refund' => [
                'display_label'   => 'Refund',
                'header_aliases'  => 'refund, Refund',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Refund.',
            ],
            'remarks' => [
                'display_label'   => 'Remarks',
                'header_aliases'  => 'remarks, Remarks',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Remarks.',
            ],
            'rent_allowance' => [
                'display_label'   => 'Rent Allowance',
                'header_aliases'  => 'rent_allowance, Rent Allowance, rent allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Rent Allowance.',
            ],
            'report' => [
                'display_label'   => 'Report',
                'header_aliases'  => 'report, Report',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Report.',
            ],
            'responsebasic' => [
                'display_label'   => 'Responsebasic',
                'header_aliases'  => 'responsebasic, Responsebasic',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Responsebasic.',
            ],
            'risk_allowance' => [
                'display_label'   => 'Risk Allowance',
                'header_aliases'  => 'risk_allowance, Risk Allowance, risk allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Risk Allowance.',
            ],
            'salary' => [
                'display_label'   => 'Salary',
                'header_aliases'  => 'salary, Salary',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Salary.',
            ],
            'sat' => [
                'display_label'   => 'Sat',
                'header_aliases'  => 'sat, Sat',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Sat.',
            ],
            'satoffdays' => [
                'display_label'   => 'Satoffdays',
                'header_aliases'  => 'satoffdays, Satoffdays',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Satoffdays.',
            ],
            'satot' => [
                'display_label'   => 'Satot',
                'header_aliases'  => 'satOT, Satot, satot',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Satot.',
            ],
            'saturday_hours' => [
                'display_label'   => 'Saturday Hours',
                'header_aliases'  => 'saturday_hours, Saturday Hours, saturday hours',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Saturday Hours.',
            ],
            'saturday_overtime_amt' => [
                'display_label'   => 'Saturday Overtime Amt',
                'header_aliases'  => 'saturday_overtime_amt, Saturday Overtime Amt, saturday overtime amt',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Saturday Overtime Amt.',
            ],
            'saturday_overtime_hours' => [
                'display_label'   => 'Saturday Overtime Hours',
                'header_aliases'  => 'saturday_overtime_hours, Saturday Overtime Hours, saturday overtime hours',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Saturday Overtime Hours.',
            ],
            'saturday_overtime_rate' => [
                'display_label'   => 'Saturday Overtime Rate',
                'header_aliases'  => 'saturday_overtime_rate, Saturday Overtime Rate, saturday overtime rate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Saturday Overtime Rate.',
            ],
            'saturday_overtime_total' => [
                'display_label'   => 'Saturday Overtime Total',
                'header_aliases'  => 'saturday_overtime_total, Saturday Overtime Total, saturday overtime total',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Saturday Overtime Total.',
            ],
            'saturdayot' => [
                'display_label'   => 'Saturdayot',
                'header_aliases'  => 'saturdayot, Saturdayot',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Saturdayot.',
            ],
            'servicecharge' => [
                'display_label'   => 'Servicecharge',
                'header_aliases'  => 'servicecharge, Servicecharge',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Servicecharge.',
            ],
            'severance_pay' => [
                'display_label'   => 'Severance Pay',
                'header_aliases'  => 'severance_pay, Severance Pay, severance pay',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Severance Pay.',
            ],
            'shopname' => [
                'display_label'   => 'Shopname',
                'header_aliases'  => 'shopname, Shopname',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Shopname.',
            ],
            'special_day_overtime_amount' => [
                'display_label'   => 'Special Day Overtime Amount',
                'header_aliases'  => 'special_day_overtime_amount, Special Day Overtime Amount, special day overtime amount',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Special Day Overtime Amount.',
            ],
            'special_day_overtime_hours' => [
                'display_label'   => 'Special Day Overtime Hours',
                'header_aliases'  => 'special_day_overtime_hours, Special Day Overtime Hours, special day overtime hours',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Special Day Overtime Hours.',
            ],
            'special_day_overtime_rate' => [
                'display_label'   => 'Special Day Overtime Rate',
                'header_aliases'  => 'special_day_overtime_rate, Special Day Overtime Rate, special day overtime rate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Special Day Overtime Rate.',
            ],
            'ssf_13' => [
                'display_label'   => 'Ssf',
                'header_aliases'  => 'ssf_13, Ssf, ssf',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Ssf.',
            ],
            'ssfyee' => [
                'display_label'   => 'Ssfyee',
                'header_aliases'  => 'ssfyee, Ssfyee',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Ssfyee.',
            ],
            'ssnit' => [
                'display_label'   => 'Ssnit',
                'header_aliases'  => 'ssnit, Ssnit',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Ssnit.',
            ],
            'ssnit1' => [
                'display_label'   => 'Ssnit1',
                'header_aliases'  => 'ssnit1, Ssnit1',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Ssnit1.',
            ],
            'ssnit13' => [
                'display_label'   => 'Ssnit13',
                'header_aliases'  => 'ssnit13, Ssnit13',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Ssnit13.',
            ],
            'ssnit_ee_05' => [
                'display_label'   => 'Ssnit',
                'header_aliases'  => 'ssnit_ee_05, Ssnit, ssnit',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Ssnit.',
            ],
            'ssnit_ee_5_5' => [
                'display_label'   => 'Ssnit',
                'header_aliases'  => 'ssnit_ee_5_5, Ssnit, ssnit',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Ssnit.',
            ],
            'ssnit_er_13' => [
                'display_label'   => 'Ssnit',
                'header_aliases'  => 'ssnit_er_13, Ssnit, ssnit',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Ssnit.',
            ],
            'ssnit_er_13_percent' => [
                'display_label'   => 'Ssnit',
                'header_aliases'  => 'SSNIT_ER_13_PERCENT, Ssnit, ssnit_er_13_percent, ssnit',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Ssnit.',
            ],
            'ssnitee' => [
                'display_label'   => 'Ssnitee',
                'header_aliases'  => 'ssnitee, Ssnitee',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Ssnitee.',
            ],
            'ssnitee_0_5' => [
                'display_label'   => 'Ssnitee',
                'header_aliases'  => 'SSNITEE_0_5, Ssnitee, ssnitee_0_5, ssnitee',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Ssnitee.',
            ],
            'ssniter' => [
                'display_label'   => 'Ssniter',
                'header_aliases'  => 'ssniter, Ssniter',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Ssniter.',
            ],
            'ssnitnum' => [
                'display_label'   => 'Ssnitnum',
                'header_aliases'  => 'ssnitnum, Ssnitnum',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Ssnitnum.',
            ],
            'ssnitnumber' => [
                'display_label'   => 'Ssnitnumber',
                'header_aliases'  => 'SSNITNUMBER, Ssnitnumber, ssnitnumber',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Ssnitnumber.',
            ],
            'staff_category' => [
                'display_label'   => 'Staff Category',
                'header_aliases'  => 'staff_category, Staff Category, staff category',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Staff Category.',
            ],
            'staff_cost' => [
                'display_label'   => 'Staff Cost',
                'header_aliases'  => 'staff_cost, Staff Cost, staff cost',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Staff Cost.',
            ],
            'start_date' => [
                'display_label'   => 'Start Date',
                'header_aliases'  => 'start_date, Start Date, start date',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Start Date.',
            ],
            'startdate' => [
                'display_label'   => 'Startdate',
                'header_aliases'  => 'startdate, Startdate',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Startdate.',
            ],
            'sunday_holiday_overtime_amt' => [
                'display_label'   => 'Sunday Holiday Overtime Amt',
                'header_aliases'  => 'sunday_holiday_overtime_amt, Sunday Holiday Overtime Amt, sunday holiday overtime amt',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Sunday Holiday Overtime Amt.',
            ],
            'sunday_holiday_overtime_hours' => [
                'display_label'   => 'Sunday Holiday Overtime Hours',
                'header_aliases'  => 'sunday_holiday_overtime_hours, Sunday Holiday Overtime Hours, sunday holiday overtime hours',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Sunday Holiday Overtime Hours.',
            ],
            'sunday_holiday_overtime_rate' => [
                'display_label'   => 'Sunday Holiday Overtime Rate',
                'header_aliases'  => 'sunday_holiday_overtime_rate, Sunday Holiday Overtime Rate, sunday holiday overtime rate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Sunday Holiday Overtime Rate.',
            ],
            'sunday_holiday_overtime_total' => [
                'display_label'   => 'Sunday Holiday Overtime Total',
                'header_aliases'  => 'sunday_holiday_overtime_total, Sunday Holiday Overtime Total, sunday holiday overtime total',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Sunday Holiday Overtime Total.',
            ],
            'sunday_overtime_amount' => [
                'display_label'   => 'Sunday Overtime Amount',
                'header_aliases'  => 'sunday_overtime_amount, Sunday Overtime Amount, sunday overtime amount',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Sunday Overtime Amount.',
            ],
            'sunday_overtime_hours' => [
                'display_label'   => 'Sunday Overtime Hours',
                'header_aliases'  => 'sunday_overtime_hours, Sunday Overtime Hours, sunday overtime hours',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Sunday Overtime Hours.',
            ],
            'sunday_overtime_rate' => [
                'display_label'   => 'Sunday Overtime Rate',
                'header_aliases'  => 'sunday_overtime_rate, Sunday Overtime Rate, sunday overtime rate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Sunday Overtime Rate.',
            ],
            'sundays_worked' => [
                'display_label'   => 'Sundays Worked',
                'header_aliases'  => 'sundays_worked, Sundays Worked, sundays worked',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Sundays Worked.',
            ],
            'sunoffdays' => [
                'display_label'   => 'Sunoffdays',
                'header_aliases'  => 'sunoffdays, Sunoffdays',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Sunoffdays.',
            ],
            'sunot_hours' => [
                'display_label'   => 'Sunot Hours',
                'header_aliases'  => 'sunOT_hours, Sunot Hours, sunot_hours, sunot hours',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Sunot Hours.',
            ],
            'sunot_rate' => [
                'display_label'   => 'Sunot Rate',
                'header_aliases'  => 'sunOT_rate, Sunot Rate, sunot_rate, sunot rate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Sunot Rate.',
            ],
            'take_home_pay_ghc' => [
                'display_label'   => 'Take Home Pay',
                'header_aliases'  => 'TAKE_HOME_PAY_GHC, Take Home Pay, take_home_pay_ghc, take home pay',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Take Home Pay.',
            ],
            'takehomepay' => [
                'display_label'   => 'Takehomepay',
                'header_aliases'  => 'takehomepay, Takehomepay',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Takehomepay.',
            ],
            'takehomepay_ghc' => [
                'display_label'   => 'Takehomepay',
                'header_aliases'  => 'TAKEHOMEPAY_GHC, Takehomepay, takehomepay_ghc, takehomepay',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Takehomepay.',
            ],
            'taxable_income_ghc' => [
                'display_label'   => 'Taxable Income',
                'header_aliases'  => 'TAXABLE_INCOME_GHC, Taxable Income, taxable_income_ghc, taxable income',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Taxable Income.',
            ],
            'taxableincome_ghc' => [
                'display_label'   => 'Taxableincome',
                'header_aliases'  => 'TAXABLEINCOME_GHC, Taxableincome, taxableincome_ghc, taxableincome',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Taxableincome.',
            ],
            'taxbase' => [
                'display_label'   => 'Taxbase',
                'header_aliases'  => 'taxbase, Taxbase',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Taxbase.',
            ],
            'taxincome' => [
                'display_label'   => 'Taxincome',
                'header_aliases'  => 'taxincome, Taxincome',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Taxincome.',
            ],
            'thirteenth_month_bonus' => [
                'display_label'   => 'Thirteenth Month Bonus',
                'header_aliases'  => 'thirteenth_month_bonus, Thirteenth Month Bonus, thirteenth month bonus',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Thirteenth Month Bonus.',
            ],
            'thirteenth_month_tax' => [
                'display_label'   => 'Thirteenth Month Tax',
                'header_aliases'  => 'thirteenth_month_tax, Thirteenth Month Tax, thirteenth month tax',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Thirteenth Month Tax.',
            ],
            'total1' => [
                'display_label'   => 'Total1',
                'header_aliases'  => 'total1, Total1',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Total1.',
            ],
            'total2' => [
                'display_label'   => 'Total2',
                'header_aliases'  => 'total2, Total2',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Total2.',
            ],
            'total_cost_of_outsource_service' => [
                'display_label'   => 'Total Cost Of Outsource Service',
                'header_aliases'  => 'total_cost_of_outsource_service, Total Cost Of Outsource Service, total cost of outsource service',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Total Cost Of Outsource Service.',
            ],
            'total_hours_worked' => [
                'display_label'   => 'Total Hours Worked',
                'header_aliases'  => 'total_hours_worked, Total Hours Worked, total hours worked',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Total Hours Worked.',
            ],
            'total_hr_cost' => [
                'display_label'   => 'Total Hr Cost',
                'header_aliases'  => 'total_hr_cost, Total Hr Cost, total hr cost',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Total Hr Cost.',
            ],
            'total_overtime_amount' => [
                'display_label'   => 'Total Overtime Amount',
                'header_aliases'  => 'total_overtime_amount, Total Overtime Amount, total overtime amount',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Total Overtime Amount.',
            ],
            'total_overtime_amt' => [
                'display_label'   => 'Total Overtime Amt',
                'header_aliases'  => 'total_overtime_amt, Total Overtime Amt, total overtime amt',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Total Overtime Amt.',
            ],
            'total_taxation' => [
                'display_label'   => 'Total Taxation',
                'header_aliases'  => 'total_taxation, Total Taxation, total taxation',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Total Taxation.',
            ],
            'total_taxation_ghc' => [
                'display_label'   => 'Total Taxation',
                'header_aliases'  => 'TOTAL_TAXATION_GHC, Total Taxation, total_taxation_ghc, total taxation',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Total Taxation.',
            ],
            'totalamount' => [
                'display_label'   => 'Totalamount',
                'header_aliases'  => 'totalamount, Totalamount',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Totalamount.',
            ],
            'totaldeduct' => [
                'display_label'   => 'Totaldeduct',
                'header_aliases'  => 'totaldeduct, Totaldeduct',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Totaldeduct.',
            ],
            'totaldeductions_ghc' => [
                'display_label'   => 'Totaldeductions',
                'header_aliases'  => 'TOTALDEDUCTIONS_GHC, Totaldeductions, totaldeductions_ghc, totaldeductions',
                'payslip_section' => 'deductions',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Totaldeductions.',
            ],
            'totalearn' => [
                'display_label'   => 'Totalearn',
                'header_aliases'  => 'totalearn, Totalearn',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Totalearn.',
            ],
            'totaltaxdeduct' => [
                'display_label'   => 'Totaltaxdeduct',
                'header_aliases'  => 'totaltaxdeduct, Totaltaxdeduct',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Totaltaxdeduct.',
            ],
            'traffic_allowance' => [
                'display_label'   => 'Traffic Allowance',
                'header_aliases'  => 'traffic_allowance, Traffic Allowance, traffic allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Traffic Allowance.',
            ],
            'transbasic' => [
                'display_label'   => 'Transbasic',
                'header_aliases'  => 'transbasic, Transbasic',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Transbasic.',
            ],
            'transport_allowance_ghc' => [
                'display_label'   => 'Transport Allowance',
                'header_aliases'  => 'TRANSPORT_ALLOWANCE_GHC, Transport Allowance, transport_allowance_ghc, transport allowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Transport Allowance.',
            ],
            'transport_earned' => [
                'display_label'   => 'Transport Earned',
                'header_aliases'  => 'transport_earned, Transport Earned, transport earned',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Transport Earned.',
            ],
            'transport_standard' => [
                'display_label'   => 'Transport Standard',
                'header_aliases'  => 'transport_standard, Transport Standard, transport standard',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Transport Standard.',
            ],
            'transportallowance' => [
                'display_label'   => 'Transportallowance',
                'header_aliases'  => 'TRANSPORTALLOWANCE, Transportallowance, transportallowance',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Transportallowance.',
            ],
            'vat_15' => [
                'display_label'   => 'Vat 15',
                'header_aliases'  => 'vat_15, Vat 15, vat 15',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Vat 15.',
            ],
            'vat_15_percent' => [
                'display_label'   => 'Vat',
                'header_aliases'  => 'VAT_15_PERCENT, Vat, vat_15_percent, vat',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Vat.',
            ],
            'vat_for_music_ghana' => [
                'display_label'   => 'Vat For Music Ghana',
                'header_aliases'  => 'vat_for_music_ghana, Vat For Music Ghana, vat for music ghana',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Vat For Music Ghana.',
            ],
            'week' => [
                'display_label'   => 'Week',
                'header_aliases'  => 'week, Week',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Week.',
            ],
            'weekday_hours' => [
                'display_label'   => 'Weekday Hours',
                'header_aliases'  => 'weekday_hours, Weekday Hours, weekday hours',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Weekday Hours.',
            ],
            'weekday_overtime_amt' => [
                'display_label'   => 'Weekday Overtime Amt',
                'header_aliases'  => 'weekday_overtime_amt, Weekday Overtime Amt, weekday overtime amt',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Weekday Overtime Amt.',
            ],
            'weekday_overtime_total' => [
                'display_label'   => 'Weekday Overtime Total',
                'header_aliases'  => 'weekday_overtime_total, Weekday Overtime Total, weekday overtime total',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Weekday Overtime Total.',
            ],
            'weekdayhr' => [
                'display_label'   => 'Weekdayhr',
                'header_aliases'  => 'weekdayhr, Weekdayhr',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Weekdayhr.',
            ],
            'weekdayot_hours' => [
                'display_label'   => 'Weekdayot Hours',
                'header_aliases'  => 'weekdayOT_hours, Weekdayot Hours, weekdayot_hours, weekdayot hours',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Weekdayot Hours.',
            ],
            'weekdayot_rate' => [
                'display_label'   => 'Weekdayot Rate',
                'header_aliases'  => 'weekdayOT_rate, Weekdayot Rate, weekdayot_rate, weekdayot rate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Weekdayot Rate.',
            ],
            'weekdayotamt1' => [
                'display_label'   => 'Weekdayotamt1',
                'header_aliases'  => 'weekdayotamt1, Weekdayotamt1',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Weekdayotamt1.',
            ],
            'weekdayothr' => [
                'display_label'   => 'Weekdayothr',
                'header_aliases'  => 'weekdayothr, Weekdayothr',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Weekdayothr.',
            ],
            'weekdayotrate' => [
                'display_label'   => 'Weekdayotrate',
                'header_aliases'  => 'weekdayotrate, Weekdayotrate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Weekdayotrate.',
            ],
            'weekdayottonage' => [
                'display_label'   => 'Weekdayottonage',
                'header_aliases'  => 'weekdayottonage, Weekdayottonage',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Weekdayottonage.',
            ],
            'weekdayovertimehr' => [
                'display_label'   => 'Weekdayovertimehr',
                'header_aliases'  => 'weekdayovertimehr, Weekdayovertimehr',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Weekdayovertimehr.',
            ],
            'weekdayovertimetotal' => [
                'display_label'   => 'Weekdayovertimetotal',
                'header_aliases'  => 'weekdayovertimetotal, Weekdayovertimetotal',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Weekdayovertimetotal.',
            ],
            'weekdayrate' => [
                'display_label'   => 'Weekdayrate',
                'header_aliases'  => 'weekdayrate, Weekdayrate',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Weekdayrate.',
            ],
            'weekdaysot' => [
                'display_label'   => 'Weekdaysot',
                'header_aliases'  => 'weekdaysOT, Weekdaysot, weekdaysot',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Weekdaysot.',
            ],
            'weekdaytonage' => [
                'display_label'   => 'Weekdaytonage',
                'header_aliases'  => 'weekdaytonage, Weekdaytonage',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Weekdaytonage.',
            ],
            'weekendhr' => [
                'display_label'   => 'Weekendhr',
                'header_aliases'  => 'weekendhr, Weekendhr',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Weekendhr.',
            ],
            'weekendot' => [
                'display_label'   => 'Weekendot',
                'header_aliases'  => 'weekendOT, Weekendot, weekendot',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Weekendot.',
            ],
            'weekendot_hours' => [
                'display_label'   => 'Weekendot Hours',
                'header_aliases'  => 'weekendOT_hours, Weekendot Hours, weekendot_hours, weekendot hours',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Weekendot Hours.',
            ],
            'weekendot_rate' => [
                'display_label'   => 'Weekendot Rate',
                'header_aliases'  => 'weekendOT_rate, Weekendot Rate, weekendot_rate, weekendot rate',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Weekendot Rate.',
            ],
            'weekendovertimehr' => [
                'display_label'   => 'Weekendovertimehr',
                'header_aliases'  => 'weekendovertimehr, Weekendovertimehr',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Weekendovertimehr.',
            ],
            'weekendovertimetotal' => [
                'display_label'   => 'Weekendovertimetotal',
                'header_aliases'  => 'weekendovertimetotal, Weekendovertimetotal',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Weekendovertimetotal.',
            ],
            'weekendrate' => [
                'display_label'   => 'Weekendrate',
                'header_aliases'  => 'weekendrate, Weekendrate',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Weekendrate.',
            ],
            'weekendtonage' => [
                'display_label'   => 'Weekendtonage',
                'header_aliases'  => 'weekendtonage, Weekendtonage',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(50)',
                'description'     => 'Standard payroll column for Weekendtonage.',
            ],
            'work_days' => [
                'display_label'   => 'Work Days',
                'header_aliases'  => 'work_days, Work Days, work days',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Work Days.',
            ],
            'work_location' => [
                'display_label'   => 'Work Location',
                'header_aliases'  => 'work_location, Work Location, work location',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Work Location.',
            ],
            'work_place' => [
                'display_label'   => 'Work Place',
                'header_aliases'  => 'work_place, Work Place, work place',
                'payslip_section' => 'earnings',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Work Place.',
            ],
            'workarea' => [
                'display_label'   => 'Workarea',
                'header_aliases'  => 'workarea, Workarea',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Workarea.',
            ],
            'workdays' => [
                'display_label'   => 'Workdays',
                'header_aliases'  => 'workdays, Workdays',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Standard payroll column for Workdays.',
            ],
            'workmens_compensation' => [
                'display_label'   => 'Workmens Compensation',
                'header_aliases'  => 'workmens_compensation, Workmens Compensation, workmens compensation',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'Standard payroll column for Workmens Compensation.',
            ],
            'workplace' => [
                'display_label'   => 'Workplace',
                'header_aliases'  => 'workplace, Workplace',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(100)',
                'description'     => 'Standard payroll column for Workplace.',
            ],

        ];
    }

    /**
     * Get standard columns with any client or global overrides merged in.
     * When $filterByClient is true and $clientDb !== 'all', tailors the list
     * strictly to columns belonging to that client's schema / tables / templates.
     */
    public static function getStandardColumnsWithOverrides(string $clientDb, bool $filterByClient = true): array
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

        if ($clientDb === 'all' || empty($clientDb) || !$filterByClient) {
            $result = [];
            foreach ($canonical as $colName => $def) {
                $override = $overrides->get($colName);
                $isLocked = !empty($def['locked']) || in_array(strtolower($colName), ['staff_id', 'staffid', 'staffida', 'employ', 'id', 'employid']);
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
                    'locked'          => $isLocked,
                ];
            }
            return $result;
        }

        // --- Client-specific tailored filtering ---
        $clientCols = [];

        // 1. Physical table schema columns for this client
        $prefixes = self::getTablePrefixesForClient($clientDb);
        $whereParts = [];
        $bindings = [];
        foreach ($prefixes as $p) {
            $whereParts[] = "TABLE_NAME LIKE ?";
            $bindings[] = "{$p}%";
        }

        try {
            $tables = \Illuminate\Support\Facades\DB::select("
                SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND (" . implode(' OR ', $whereParts) . ")
                  AND TABLE_NAME NOT LIKE '%_sign'
                  AND TABLE_NAME NOT LIKE '%_contracts%'
                  AND TABLE_NAME NOT LIKE '%attendance%'
                  AND TABLE_NAME NOT LIKE '%sms%'
                  AND TABLE_NAME NOT LIKE '%data'
                  AND TABLE_NAME NOT LIKE '%con'
                ORDER BY TABLE_NAME DESC 
                LIMIT 20
            ", $bindings);

            foreach ($tables as $t) {
                $cols = \Illuminate\Support\Facades\Schema::getColumnListing($t->TABLE_NAME);
                foreach ($cols as $c) {
                    $norm = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $c));
                    $clientCols[$norm] = $c;
                }
            }
        } catch (\Exception $e) {
            // Gracefully ignore database query issues
        }

        // 2. Overrides already configured for this client
        foreach ($overrides as $ov) {
            $norm = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $ov->column_name));
            $clientCols[$norm] = $ov->column_name;
        }

        // 3. Static fallback template columns if tables not yet populated
        $staticTemplates = self::getStaticTemplateColumnsForClient($clientDb);
        foreach ($staticTemplates as $stCol) {
            $norm = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $stCol));
            if (!isset($clientCols[$norm])) {
                $clientCols[$norm] = $stCol;
            }
        }

        // 4. Always ensure primary staff_id is present
        $clientCols['staff_id'] = 'staff_id';

        $result = [];
        $processedKeys = [];

        // First pass: Match from canonical list (maintains polished descriptions, types, and sections)
        foreach ($canonical as $colName => $def) {
            $norm = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $colName));
            if (isset($clientCols[$norm]) || isset($clientCols[$colName])) {
                $override = $overrides->get($colName);
                $isLocked = !empty($def['locked']) || in_array(strtolower($colName), ['staff_id', 'staffid', 'staffida', 'employ', 'id', 'employid']);
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
                    'locked'          => $isLocked,
                ];
                $processedKeys[$norm] = true;
                $processedKeys[$colName] = true;
            }
        }

        // Second pass: Any extra physical columns in client's table not in canonical
        foreach ($clientCols as $norm => $origCol) {
            if (!isset($processedKeys[$norm]) && !isset($processedKeys[$origCol])) {
                if (in_array($norm, ['created_at', 'updated_at', 'deleted_at', 'remember_token', 'password', 'email_verified_at'])) {
                    continue;
                }

                $override = $overrides->get($origCol);
                $cleanLabel = ucwords(str_replace('_', ' ', $norm));
                $isId = in_array($norm, ['staff_id', 'staffid', 'staffida', 'employ', 'id', 'employid']);

                $result[] = [
                    'id'              => $override?->id ?? null,
                    'client_dbname'   => $override?->client_dbname ?? $clientDb,
                    'is_custom'       => false,
                    'column_name'     => $origCol,
                    'display_label'   => $override?->display_label ?? $cleanLabel,
                    'default_label'   => $cleanLabel,
                    'header_aliases'  => $override?->header_aliases ?? "{$origCol}, {$cleanLabel}",
                    'default_aliases' => "{$origCol}, {$cleanLabel}",
                    'data_type'       => 'varchar(100)',
                    'position_after'  => $override?->position_after ?? null,
                    'show_in_payslip' => $override?->show_in_payslip ?? true,
                    'payslip_section' => $isId ? 'info' : 'earnings',
                    'description'     => "Physical payroll column '{$origCol}' for {$clientDb}.",
                    'has_override'    => $override !== null,
                    'locked'          => $isId,
                ];
                $processedKeys[$norm] = true;
            }
        }

        return $result;
    }

    /**
     * Fallback template columns defined per client in their payroll controllers.
     */
    public static function getStaticTemplateColumnsForClient(string $clientDb): array
    {
        $templates = [
            'jkay' => [
                'staff_id', 'name', 'position', 'join_date', 'basic_salary', 'legalworkdays', 'daily_rate',
                'days_worked', 'absentee_days', 'absent_deductions', 'earned_basic_salary', 'saturdays_worked',
                'saturday_rate', 'saturday_amount', 'total_days_amount', 'overtime_hours', 'overtime_rate',
                'overtime_amount', 'night_allowance', 'totalovertime', 'gross_salary', 'management_fee',
                'getfund', 'nhil', 'covid19', 'vat', 'ssf', 'cost_to_jkay', 'ssnit_ee', 'petra',
                'taxable_income', 'paye', 'overtime_tax', 'surcharge_or_student_loan', 'total_deductions', 'take_home_pay'
            ],
            'melcom' => [
                'employ', 'empoyeename', 'outsourcename', 'designation', 'joindate', 'shopname', 'category',
                'saturdayot', 'actualdays', 'night', 'halfdays', 'satoffdays', 'sunoffdays', 'leavededuct',
                'absentdays', 'absenthrs', 'holiday', 'weekdaysOT', 'weekendOT', 'holidayot', 'basic',
                'absentwithoutpay', 'basicentitlement', 'transport', 'normalovertime', 'saturdayovertime',
                'holidayotamt', 'gross', 'total_cash_emolument', 'total_assessable_income', 'ssnit', 'taxable',
                'paye', 'totalovertime', 'overtimetax', 'totaltax', 'trust', 'totaldeduction', 'banktransfer'
            ],
            'transsion' => [
                'staffida', 'name', 'department', 'hiredate', 'constart', 'conend', 'position', 'grade',
                'categories', 'workplace', 'bankname', 'alcnum', 'branch', 'ssnit', 'col12', 'workdays',
                'leavedays', 'otworkdays', 'otweekend', 'basic', 'performbonus', 'coefficient', 'performstand',
                'allowance', 'commission', 'otworkingday', 'otholiday', 'other', 'annaulbonus', 'leavededuction',
                'otherdeduction', 'loan', 'grosspay', 'excess', 'totalemolu', 'accommodation', 'chargeincome',
                'petra', 'ssnitamt', 'nssf', 'taxbase', 'paye', 'annaultax', 'netpay', 'hrcost', 'brandname'
            ],
            'transsiontec' => [
                'staffida', 'name', 'department', 'hiredate', 'constart', 'conend', 'position', 'grade',
                'categories', 'workplace', 'bankname', 'alcnum', 'branch', 'ssnit', 'basic', 'performbonus',
                'coefficient', 'performstand', 'allowance', 'commission', 'gross', 'net_pay'
            ],
            'nyonkopa' => [
                'staff_id', 'employee_name', 'start_date', 'position', 'work_location', 'base_salary',
                'fuel_and_rent_allowance', 'gross_salary', 'management_fee_8', 'getfund_2_5', 'nhil_2_5',
                'covid_19_1', 'vat_15', 'medical_insurance', 'employer_provident_fund_5', 'thirteenth_month_bonus',
                'ssnit_er_13', 'cost_to_nyonkopa', 'ssnit_ee_5_5', 'taxable_income', 'paye', 'thirteenth_month_tax',
                'total_taxation', 'total_deductions', 'take_home_pay'
            ],
            'pz' => [
                'staffid', 'name', 'dept', 'basic', 'overtime', 'incentive', 'bonus', 'other', '13month',
                'totalearn', 'ssnitee', 'overtimetaxt', 'pf', 'taxable', 'ssniter', '13tax', 'totaltaxdeduct',
                'totaldeduct', 'netpay', 'bank1', 'account1', 'branch1', 'ssnit1', 'ghcard'
            ],
            'shefer' => [
                'staff_id', 'name1', 'position', 'department', 'startdate', 'basic', 'total_weekdays',
                'no_of_days_worked', 'leavepay', 'absent_days', 'basic_earned', 'nightdays', 'weekdayOT_hours',
                'weekdayOT_rate', 'weekdayOT_amount', 'weekendOT_hours', 'weekendOT_rate', 'weekendOT_amount',
                'sunOT_hours', 'sunOT_rate', 'sunOT_amount', 'fixedotbasic', 'fixedot', 'total_overtime',
                'foodbasic', 'food', 'airtimebasic', 'airtime', 'nightallo', 'responsebasic', 'responseallo',
                'transbasic', 'transport', 'medicalbasic', 'medical', 'total_allowance', 'gross',
                'emp_ssnit_13_percent', 'ssnit_5_5_percent', 'taxable_income', 'paye', 'ot_tax', 'total_tax',
                'absent_without_pay', 'salaryloan', 'total_deductions', 'refunds', 'net_pay'
            ],
            'honda' => [
                'STAFFID', 'NAME', 'DATEOFEMPLOYMENT', 'POSITION', 'BASICSALARY_GHC', 'LEGALWORKDAYS',
                'NUMBEROFDAYSWORKED', 'LEAVEDAYS', 'LEAVEAMOUNT', 'BASICSALARYEARNED_GHC', 'PERFORMANCEBONUSSTANDARD',
                'COEFFICIENT', 'PERFORMANCEBONUS', 'TRANSPORTALLOWANCE', 'foodallowance', 'medicalinsurance',
                'GROSSSALARY_GHC', 'MANAGEMENTFEE_8', 'GETFUND_2_5', 'NHIL_2_5', 'COVID_19_1', 'VAT_15',
                'SSF_13', 'COSTTOHONDA_GHC', 'SSNITEE_0_5', 'PETRA_5', 'TAXABLEINCOME_GHC', 'PAYE_GHC',
                'TOTALDEDUCTIONS_GHC', 'TAKEHOMEPAY_GHC', 'BANK', 'ACCOUNTNUMBER', 'BRANCH', 'SSNITNUMBER', 'GHCARDNUMBER'
            ],
            'bungegh' => [
                'STAFF_ID', 'NAME', 'POSITION', 'BASE_SALARY_GHC', 'TRANSPORT_ALLOWANCE_GHC', 'ADDITIONAL_ALLOWANCE_GHC',
                'OVERTIME_GHC', 'BONUS_EARNINGS_ALLOWANCE', 'GROSS_SALARY_GHC', 'MANAGEMENT_FEE_5_PERCENT',
                'SSNIT_ER_13_PERCENT', 'GETFUND_2_5_PERCENT', 'NHIL_2_5_PERCENT', 'COVID_19_1_PERCENT',
                'VAT_15_PERCENT', 'COST_TO_BUNGE_GHC', 'gross2', 'SSNIT_5_5_PERCENT', 'TAXABLE_INCOME_GHC',
                'PAYE_GHC', 'BONUS_TAX', 'TOTAL_TAXATION_GHC', 'TAKE_HOME_PAY_GHC', 'BANK', 'ACCOUNT_NUMBER',
                'BRANCH', 'SSNIT', 'GH_CARD_NUMBER'
            ],
        ];

        return $templates[strtolower(trim($clientDb))] ?? [];
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
