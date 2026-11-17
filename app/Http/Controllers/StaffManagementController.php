<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StaffManagementController extends Controller
{
    /**
     * All known client-specific contract tables in HRMS.
     */
    const CONTRACT_TABLES = [
        'olamfoodcon2',
        'transsioncon',
        'xiaomicon',
        'gmoneycon',
        'shefercon',
        'contractmelcomemp',
        'contracttranssionemprenew',
        'contractmelcomemprenew',
    ];

    /**
     * Authorize user: Super Admin, HR Manager, or HR Officer.
     */
    private function authorizeStaffManager(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $isAllowed = $user->is_super_admin
            || (method_exists($user, 'hasRole') && $user->hasRole(['Super Admin', 'HR Manager', 'HR Officer']))
            || (method_exists($user, 'can') && ($user->can('manage users') || $user->can('edit staff')));

        if (!$isAllowed) {
            abort(403, 'Unauthorized. Super Admin, HR Manager, or HR Officer permissions required.');
        }

        return $user;
    }

    /**
     * Get allowed client names for non-superadmin HR users.
     */
    private function getAllowedClients($user): ?array
    {
        if ($user->is_super_admin || (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) || !method_exists($user, 'clients') || !$user->clients()->exists()) {
            return null; // Null means global access to all clients
        }

        $clients = $user->clients()->get();
        $names = [];
        foreach ($clients as $c) {
            $names[] = $c->client_name;
            if (!empty($c->short_name)) $names[] = $c->short_name;
            if (!empty($c->client_dbname)) $names[] = $c->client_dbname;
        }

        return array_values(array_unique(array_filter($names)));
    }

    /**
     * Auto-generate or suggest the next sequential Staff ID for a client.
     * GET /staff-management/next-id?client={client}&prefix={prefix}
     */
    public function getNextStaffId(Request $request): JsonResponse
    {
        $this->authorizeStaffManager($request);

        $clientQuery = trim($request->input('client', ''));
        $prefix = trim($request->input('prefix', ''));

        // If prefix is not explicitly provided, deduce from client
        if (empty($prefix)) {
            $prefix = $this->guessPrefixForClient($clientQuery);
        }

        $prefix = strtoupper($prefix);
        $escapedPrefix = str_replace(['%', '_'], ['\\%', '\\_'], $prefix);

        // Find all staff IDs starting with this prefix in newemployeedata and contract tables
        $existingIds = collect();

        if (Schema::hasTable('newemployeedata')) {
            $dirIds = DB::table('newemployeedata')
                ->where('staffid', 'like', $escapedPrefix . '%')
                ->pluck('staffid');
            $existingIds = $existingIds->merge($dirIds);
        }

        foreach (self::CONTRACT_TABLES as $cTable) {
            if (Schema::hasTable($cTable)) {
                $idCol = Schema::hasColumn($cTable, 'id') ? 'id' : (Schema::hasColumn($cTable, 'staffid') ? 'staffid' : null);
                if ($idCol) {
                    $cIds = DB::table($cTable)->where($idCol, 'like', $escapedPrefix . '%')->pluck($idCol);
                    $existingIds = $existingIds->merge($cIds);
                }
            }
        }

        $existingIds = $existingIds->unique()->filter();

        $maxNumber = 0;
        $highestExisting = null;
        $samplePadLength = 0;

        foreach ($existingIds as $idStr) {
            $idStr = trim((string) $idStr);
            if (str_starts_with(strtoupper($idStr), $prefix)) {
                $suffix = substr($idStr, strlen($prefix));
                if (preg_match('/^(\d+)$/', $suffix, $matches)) {
                    $num = (int) $matches[1];
                    if ($num > $maxNumber) {
                        $maxNumber = $num;
                        $highestExisting = $idStr;
                        $samplePadLength = strlen($matches[1]);
                    }
                }
            }
        }

        $nextNumber = $maxNumber + 1;

        // Preserve zero-padding if original format used padding (e.g. 00005 -> 00006)
        if ($samplePadLength > 1 && strlen((string) $maxNumber) < $samplePadLength) {
            $paddedNumber = str_pad((string) $nextNumber, $samplePadLength, '0', STR_PAD_LEFT);
        } else {
            $paddedNumber = (string) $nextNumber;
        }

        $nextId = $prefix . $paddedNumber;

        return response()->json([
            'success'          => true,
            'client'           => $clientQuery,
            'prefix'           => $prefix,
            'highest_existing' => $highestExisting ?: ($prefix . '0'),
            'next_id'          => $nextId,
        ]);
    }

    /**
     * Preview matching staff for a given ID range or list before deletion.
     * POST /staff-management/preview-range
     */
    public function previewRange(Request $request): JsonResponse
    {
        $user = $this->authorizeStaffManager($request);
        $allowedClients = $this->getAllowedClients($user);

        $mode = $request->input('mode', 'range'); // 'range' | 'list'
        $client = trim($request->input('client', ''));
        $targetIds = [];

        if ($mode === 'range') {
            $prefix = strtoupper(trim($request->input('prefix', '')));
            $startNum = (int) $request->input('start_num', 1);
            $endNum = (int) $request->input('end_num', 1);
            $padLength = (int) $request->input('pad_length', 0);

            if ($endNum < $startNum) {
                return response()->json([
                    'success' => false,
                    'message' => 'End number cannot be smaller than start number.',
                ], 422);
            }

            if (($endNum - $startNum) > 1000) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum range limit is 1,000 staff at a time for safety.',
                ], 422);
            }

            for ($n = $startNum; $n <= $endNum; $n++) {
                $numStr = $padLength > 0 ? str_pad((string)$n, $padLength, '0', STR_PAD_LEFT) : (string)$n;
                $targetIds[] = $prefix . $numStr;
            }
        } else {
            $rawIds = $request->input('ids', []);
            if (is_string($rawIds)) {
                // Split by commas, newlines, or spaces
                $rawIds = preg_split('/[\s,]+/', $rawIds, -1, PREG_SPLIT_NO_EMPTY);
            }
            $targetIds = array_values(array_unique(array_filter(array_map('trim', (array) $rawIds))));
        }

        if (empty($targetIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid staff IDs provided.',
            ], 422);
        }

        // Query newemployeedata
        $dirQuery = DB::table('newemployeedata')->whereIn('staffid', $targetIds);
        if ($allowedClients !== null) {
            $dirQuery->where(function ($q) use ($allowedClients) {
                $q->whereIn('company', $allowedClients);
                foreach ($allowedClients as $ac) {
                    $q->orWhere('company', 'like', "%{$ac}%");
                }
            });
        }
        if (!empty($client)) {
            $dirQuery->where(function ($q) use ($client) {
                $q->where('company', 'like', "%{$client}%")
                  ->orWhere('shop', 'like', "%{$client}%");
            });
        }
        $dirRecords = $dirQuery->select('staffid', 'employeename', 'company', 'shop', 'position')->get()->keyBy('staffid');

        // Query active contract tables
        $contractHits = [];
        foreach (self::CONTRACT_TABLES as $cTable) {
            if (!Schema::hasTable($cTable)) continue;
            $idCol = Schema::hasColumn($cTable, 'id') ? 'id' : (Schema::hasColumn($cTable, 'staffid') ? 'staffid' : null);
            if (!$idCol) continue;

            $nameCol = Schema::hasColumn($cTable, 'employeename') ? 'employeename' : (Schema::hasColumn($cTable, 'name') ? 'name' : null);
            $clientCol = Schema::hasColumn($cTable, 'client') ? 'client' : (Schema::hasColumn($cTable, 'employername') ? 'employername' : null);

            $selects = [$idCol . ' as id'];
            if ($nameCol) $selects[] = $nameCol . ' as name';
            if ($clientCol) $selects[] = $clientCol . ' as client';

            $cRows = DB::table($cTable)->whereIn($idCol, $targetIds)->select($selects)->get();
            foreach ($cRows as $r) {
                $contractHits[$r->id][] = [
                    'table'  => $cTable,
                    'name'   => $r->name ?? '',
                    'client' => $r->client ?? '',
                ];
            }
        }

        $records = [];
        foreach ($targetIds as $id) {
            $dir = $dirRecords->get($id);
            $cHits = $contractHits[$id] ?? [];

            $name = $dir?->employeename ?? (!empty($cHits) ? ($cHits[0]['name'] ?: 'Unknown') : 'N/A');
            $company = $dir?->company ?? (!empty($cHits) ? ($cHits[0]['client'] ?: 'N/A') : 'N/A');
            $position = $dir?->position ?? 'N/A';

            $records[] = [
                'staff_id'       => $id,
                'name'           => $name,
                'company'        => $company,
                'position'       => $position,
                'in_directory'   => (bool) $dir,
                'in_contracts'   => !empty($cHits),
                'contract_tables'=> array_column($cHits, 'table'),
                'exists_in_db'   => (bool) $dir || !empty($cHits),
            ];
        }

        $foundCount = collect($records)->where('exists_in_db', true)->count();

        return response()->json([
            'success'            => true,
            'total_target_ids'   => count($targetIds),
            'found_in_system'    => $foundCount,
            'found_in_directory' => $dirRecords->count(),
            'found_in_contracts' => count($contractHits),
            'records'            => $records,
        ]);
    }

    /**
     * Execute bulk deletion by range or ID list across selected scope.
     * POST /staff-management/bulk-delete
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $user = $this->authorizeStaffManager($request);
        $allowedClients = $this->getAllowedClients($user);

        $request->validate([
            'staff_ids' => ['required', 'array', 'min:1'],
            'scope'     => ['required', 'string', 'in:contracts,directory,complete'],
            'client'    => ['nullable', 'string', 'max:150'],
        ]);

        $staffIds = array_values(array_unique(array_filter(array_map('trim', $request->input('staff_ids')))));
        $scope = $request->input('scope');

        if (empty($staffIds)) {
            return response()->json(['success' => false, 'message' => 'No staff IDs provided.'], 422);
        }

        // Permission check: If HR Manager / Officer has restricted clients, ensure staff belongs to their clients
        if ($allowedClients !== null) {
            $unauthorizedStaff = DB::table('newemployeedata')
                ->whereIn('staffid', $staffIds)
                ->where(function ($q) use ($allowedClients) {
                    $q->whereNotIn('company', $allowedClients);
                    foreach ($allowedClients as $ac) {
                        $q->where('company', 'not like', "%{$ac}%");
                    }
                })
                ->pluck('staffid');

            if ($unauthorizedStaff->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission denied. Some staff IDs belong to companies outside your assigned client scope.',
                    'unauthorized_ids' => $unauthorizedStaff,
                ], 403);
            }
        }

        $deletedCounts = [
            'contracts'    => 0,
            'directory'    => 0,
            'contact'      => 0,
            'bank'         => 0,
            'pincode'      => 0,
            'guarantor'    => 0,
            'leaves'       => 0,
        ];

        try {
            DB::beginTransaction();

            // 1. Delete from Contracts
            if ($scope === 'contracts' || $scope === 'complete') {
                foreach (self::CONTRACT_TABLES as $cTable) {
                    if (Schema::hasTable($cTable)) {
                        $idCol = Schema::hasColumn($cTable, 'id') ? 'id' : (Schema::hasColumn($cTable, 'staffid') ? 'staffid' : null);
                        if ($idCol) {
                            $cnt = DB::table($cTable)->whereIn($idCol, $staffIds)->delete();
                            $deletedCounts['contracts'] += $cnt;
                        }
                    }
                }
            }

            // 2. Delete from Employee Directory
            if ($scope === 'directory' || $scope === 'complete') {
                if (Schema::hasTable('newemployeedata')) {
                    $deletedCounts['directory'] = DB::table('newemployeedata')->whereIn('staffid', $staffIds)->delete();
                }
            }

            // 3. Complete operational purge
            if ($scope === 'complete') {
                if (Schema::hasTable('employeecontact')) {
                    $deletedCounts['contact'] = DB::table('employeecontact')->whereIn('staffid', $staffIds)->delete();
                }
                if (Schema::hasTable('bankdetails')) {
                    $deletedCounts['bank'] = DB::table('bankdetails')->whereIn('employid', $staffIds)->delete();
                }
                if (Schema::hasTable('employeepin')) {
                    $deletedCounts['pincode'] = DB::table('employeepin')->whereIn('staff_id', $staffIds)->delete();
                }
                if (Schema::hasTable('guarantordata')) {
                    $deletedCounts['guarantor'] = DB::table('guarantordata')->whereIn('id', $staffIds)->delete();
                }
                if (Schema::hasTable('leaveapplications')) {
                    $deletedCounts['leaves'] = DB::table('leaveapplications')
                        ->where(function ($q) use ($staffIds) {
                            $q->whereIn('ida', $staffIds)->orWhereIn('staffid', $staffIds);
                        })
                        ->delete();
                }
            }

            DB::commit();

            Log::info("StaffManagementController: Bulk deleted staff by user {$user->id} ({$user->name})", [
                'scope'         => $scope,
                'staff_count'   => count($staffIds),
                'deletedCounts' => $deletedCounts,
            ]);

            return response()->json([
                'success'        => true,
                'message'        => 'Successfully deleted selected staff records.',
                'scope'          => $scope,
                'staff_count'    => count($staffIds),
                'deleted_counts' => $deletedCounts,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('StaffManagementController: bulkDelete failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete staff: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rename an existing Staff ID and cascade update across all related tables.
     * POST /staff-management/rename-id
     */
    public function renameStaffId(Request $request): JsonResponse
    {
        $user = $this->authorizeStaffManager($request);
        $allowedClients = $this->getAllowedClients($user);

        $request->validate([
            'old_staff_id' => ['required', 'string', 'max:50'],
            'new_staff_id' => ['required', 'string', 'max:50'],
        ]);

        $oldId = trim($request->input('old_staff_id'));
        $newId = trim($request->input('new_staff_id'));

        if ($oldId === $newId) {
            return response()->json(['success' => false, 'message' => 'New Staff ID cannot be identical to the old Staff ID.'], 422);
        }

        // Verify old ID exists
        $emp = DB::table('newemployeedata')->where('staffid', $oldId)->first();
        if (!$emp) {
            // Check contracts if not in directory
            $foundInContracts = false;
            foreach (self::CONTRACT_TABLES as $cTable) {
                if (Schema::hasTable($cTable)) {
                    $idCol = Schema::hasColumn($cTable, 'id') ? 'id' : (Schema::hasColumn($cTable, 'staffid') ? 'staffid' : null);
                    if ($idCol && DB::table($cTable)->where($idCol, $oldId)->exists()) {
                        $foundInContracts = true;
                        break;
                    }
                }
            }
            if (!$foundInContracts) {
                return response()->json(['success' => false, 'message' => "Staff ID '{$oldId}' was not found in the system."], 404);
            }
        }

        // Check client permissions
        if ($emp && $allowedClients !== null) {
            $company = $emp->company ?? '';
            $hasAccess = in_array($company, $allowedClients, true);
            if (!$hasAccess) {
                foreach ($allowedClients as $ac) {
                    if (stripos($company, $ac) !== false) {
                        $hasAccess = true;
                        break;
                    }
                }
            }
            if (!$hasAccess) {
                return response()->json(['success' => false, 'message' => 'Permission denied. This staff belongs to a company outside your assigned clients.'], 403);
            }
        }

        // Verify new ID does not already exist
        if (Schema::hasTable('newemployeedata') && DB::table('newemployeedata')->where('staffid', $newId)->exists()) {
            return response()->json(['success' => false, 'message' => "New Staff ID '{$newId}' is already in use by another employee."], 422);
        }
        foreach (self::CONTRACT_TABLES as $cTable) {
            if (Schema::hasTable($cTable)) {
                $idCol = Schema::hasColumn($cTable, 'id') ? 'id' : (Schema::hasColumn($cTable, 'staffid') ? 'staffid' : null);
                if ($idCol && DB::table($cTable)->where($idCol, $newId)->exists()) {
                    return response()->json(['success' => false, 'message' => "New Staff ID '{$newId}' is already in use in contract table '{$cTable}'."], 422);
                }
            }
        }

        try {
            DB::beginTransaction();

            $updatedTables = [];

            // 1. newemployeedata
            if (Schema::hasTable('newemployeedata')) {
                $affected = DB::table('newemployeedata')->where('staffid', $oldId)->update(['staffid' => $newId]);
                if ($affected) $updatedTables[] = 'newemployeedata';
            }

            // 2. Contract tables
            foreach (self::CONTRACT_TABLES as $cTable) {
                if (Schema::hasTable($cTable)) {
                    $idCol = Schema::hasColumn($cTable, 'id') ? 'id' : (Schema::hasColumn($cTable, 'staffid') ? 'staffid' : null);
                    if ($idCol) {
                        $affected = DB::table($cTable)->where($idCol, $oldId)->update([$idCol => $newId]);
                        if ($affected) $updatedTables[] = $cTable;
                    }
                }
            }

            // 3. employeecontact
            if (Schema::hasTable('employeecontact')) {
                $affected = DB::table('employeecontact')->where('staffid', $oldId)->update(['staffid' => $newId]);
                if ($affected) $updatedTables[] = 'employeecontact';
            }

            // 4. bankdetails
            if (Schema::hasTable('bankdetails')) {
                $affected = DB::table('bankdetails')->where('employid', $oldId)->update(['employid' => $newId]);
                if ($affected) $updatedTables[] = 'bankdetails';
            }

            // 5. employeepin
            if (Schema::hasTable('employeepin')) {
                $affected = DB::table('employeepin')->where('staff_id', $oldId)->update(['staff_id' => $newId]);
                if ($affected) $updatedTables[] = 'employeepin';
            }

            // 6. guarantordata
            if (Schema::hasTable('guarantordata')) {
                $affected = DB::table('guarantordata')->where('id', $oldId)->update(['id' => $newId]);
                if ($affected) $updatedTables[] = 'guarantordata';
            }

            // 7. leaveapplications
            if (Schema::hasTable('leaveapplications')) {
                $affected = DB::table('leaveapplications')->where('ida', $oldId)->update(['ida' => $newId]);
                DB::table('leaveapplications')->where('staffid', $oldId)->update(['staffid' => $newId]);
                if ($affected) $updatedTables[] = 'leaveapplications';
            }

            DB::commit();

            Log::info("StaffManagementController: Renamed Staff ID '{$oldId}' -> '{$newId}' by user {$user->id}", [
                'updatedTables' => $updatedTables,
            ]);

            return response()->json([
                'success'        => true,
                'message'        => "Staff ID successfully updated from '{$oldId}' to '{$newId}' across all related records.",
                'old_staff_id'   => $oldId,
                'new_staff_id'   => $newId,
                'updated_tables' => $updatedTables,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('StaffManagementController: renameStaffId failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to rename Staff ID: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guess standard client prefix.
     */
    private function guessPrefixForClient(string $client): string
    {
        $c = strtolower(trim($client));

        if (str_contains($c, 'olam') || str_contains($c, 'ofi')) return 'SBPENM';
        if (str_contains($c, 'transsion') || str_contains($c, 'tecno')) return 'TRN-';
        if (str_contains($c, 'civ') || str_contains($c, 'ivory')) return 'BUNGCI';
        if (str_contains($c, 'benin')) return 'BEN-';
        if (str_contains($c, 'togo')) return 'TG-';
        if (str_contains($c, 'xiaomi')) return 'XIA-';
        if (str_contains($c, 'gmoney') || str_contains($c, 'g-money')) return 'GM-';
        if (str_contains($c, 'shefer')) return 'SHF-';
        if (str_contains($c, 'twellium')) return 'TW-';
        if (str_contains($c, 'melcom')) return 'MEL-';
        if (str_contains($c, 'jaykay')) return 'JK-';

        // Check DB Client short_name
        $dbClient = Client::where('client_name', 'like', "%{$client}%")
            ->orWhere('client_dbname', $client)
            ->first();

        if ($dbClient && !empty($dbClient->short_name)) {
            $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $dbClient->short_name));
            return substr($prefix, 0, 4) . '-';
        }

        // Fallback: first 3 letters of client
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $client);
        return strtoupper(substr($clean, 0, 3) ?: 'EMP') . '-';
    }
}
