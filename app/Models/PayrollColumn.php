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
        'column_name',
        'display_label',
        'data_type',
        'position_after',
        'show_in_payslip',
        'payslip_section',
        'is_active',
    ];

    protected $casts = [
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
            Log::error("Failed adding column {$col->column_name} to table {$tableName}: " . $e->getMessage());
            return false;
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
        $dbName = config('database.connections.mysql.database', env('DB_DATABASE', 'sbpafricahr'));
        $prefix = strtolower(trim($col->client_dbname));

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
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME NOT LIKE '%_sign'
                  AND TABLE_NAME NOT LIKE '%_contracts%'
                  AND TABLE_NAME NOT LIKE '%attendance%'
            ", [$dbName]);

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
        $bindings = [$dbName];

        foreach ($prefixes as $p) {
            $likeClauses[] = "TABLE_NAME = ? OR TABLE_NAME LIKE ?";
            $bindings[] = $p;
            $bindings[] = "{$p}%";
        }

        $whereLikes = implode(' OR ', $likeClauses);

        $tables = DB::select("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = ? 
              AND ({$whereLikes})
              AND TABLE_NAME NOT LIKE '%_sign'
              AND TABLE_NAME NOT LIKE '%_contracts%'
              AND TABLE_NAME NOT LIKE '%attendance%'
        ", $bindings);

        $appliedCount = 0;
        foreach ($tables as $t) {
            $tableName = $t->TABLE_NAME;
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
}
