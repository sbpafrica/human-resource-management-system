<?php

namespace App\Http\Controllers;
use App\Models\TranssionContract;
use App\Models\TranssionContractRenewal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
     
        $user         = $request->user();
        $isSuperAdmin = (bool) ($user->is_super_admin ?? false);

        // ====================================================================
        // ALLOWED CLIENTS — active only.
        // Super-admin  → every active client in the system.
        // Regular user → only their own assigned active clients.
        // Inactive clients are excluded everywhere on this dashboard.
        // ====================================================================
        if ($isSuperAdmin) {
            $allowedClients = \App\Models\Client::where('is_active', true)->get();
        } else {
            $allowedClients = $user->clients()->where('is_active', true)->get();
        }

        if ($allowedClients->isEmpty()) {
            $userPermissions = $user ? $user->getAllPermissions()->pluck('name')->toArray() : [];
            $userRoles = $user ? $user->getRoleNames()->toArray() : [];
            $hasDashboardPerm = in_array('view dashboard', $userPermissions);
            $isReceiptUser = in_array('Receipt Manager', $userRoles) || 
                             in_array('Receipt Officer', $userRoles) || 
                             in_array('view receipts', $userPermissions) || 
                             in_array('manage receipts', $userPermissions);

            if (!$isSuperAdmin && !$hasDashboardPerm && $isReceiptUser) {
                return redirect()->route('receipts.index');
            }

            return Inertia::render('dashboard', [
                'complianceData'     => $this->getEmptyComplianceData(),
                'clientSummary'      => [],
                'allClientHeadcount' => [],
                'flash' => $this->getFlash($request),
            ]);
        }

        // ====================================================================
        // ALL-CLIENT HEADCOUNT — newemployeedata scoped to allowed active clients.
        // "No Clientele" bucket is only appended for super-admins.
        // ====================================================================
        $allClientHeadcount = $this->getAllClientHeadcount($allowedClients, $isSuperAdmin);

        // ====================================================================
        // CONTRACT REPORTS & RENEWAL REPORTS (TRANSSION / TECNO GROUP)
        // ====================================================================
        $hasContractAccess = $allowedClients->contains(function ($client) {
            $name = strtoupper($client->client_name ?? '');
            return str_contains($name, 'TRANSSION') || str_contains($name, 'TECNO');
        });

        $renewedIdsArray   = [0];
        $expiringContracts = collect();
        $expiredContracts  = collect();
        $unsignedContracts = collect();
        $renewalExpiring   = collect();
        $renewalExpired    = collect();
        $renewalUnsigned   = collect();

        if ($hasContractAccess) {
            $renewedIds      = TranssionContractRenewal::distinct()->pluck('id');
            $renewedIdsArray = $renewedIds->isNotEmpty() ? $renewedIds->toArray() : [0];

            $expiringContracts = TranssionContract::query()
                ->where('activate', 1)
                ->whereDate('enddate', '>=', now())
                ->whereDate('enddate', '<=', now()->addMonths(2))
                ->whereNotIn('id', $renewedIdsArray)
                ->orderBy('enddate', 'asc')
                ->select('id as staffid', 'employeename', 'client as company', 'startdate', 'enddate')
                ->get();

            $expiredContracts = TranssionContract::query()
                ->where('activate', 1)
                ->whereDate('enddate', '<', now())
                ->whereNotIn('id', $renewedIdsArray)
                ->orderBy('enddate', 'desc')
                ->select('id as staffid', 'employeename', 'client as company', 'startdate', 'enddate')
                ->get();

            $unsignedContracts = TranssionContract::query()
                ->where('activate', 0)
                ->select('id as staffid', 'employeename', 'client as company', 'startdate', 'enddate')
                ->get();

            $baseRenewal = TranssionContractRenewal::leftJoin('transsioncon as original', 'contracttranssionemprenew.id', '=', 'original.id')
                ->select(
                    'contracttranssionemprenew.id as staffid',
                    'contracttranssionemprenew.employeename',
                    DB::raw('COALESCE(original.client, "TRANSSION TECHNOLOGY GH LIMITED.") as company'),
                    'contracttranssionemprenew.startdate',
                    'contracttranssionemprenew.enddate'
                );

            $renewalExpiring = $baseRenewal->clone()
                ->where('contracttranssionemprenew.activate', 1)
                ->whereDate('contracttranssionemprenew.enddate', '>=', now())
                ->whereDate('contracttranssionemprenew.enddate', '<=', now()->addMonths(2))
                ->orderBy('contracttranssionemprenew.enddate', 'asc')
                ->get();

            $renewalExpired = $baseRenewal->clone()
                ->where('contracttranssionemprenew.activate', 1)
                ->whereDate('contracttranssionemprenew.enddate', '<', now())
                ->orderBy('contracttranssionemprenew.enddate', 'desc')
                ->get();

            $renewalUnsigned = $baseRenewal->clone()
                ->where('contracttranssionemprenew.activate', 0)
                ->get();
        }

        // ====================================================================
        // BUILD CURRENT & PREVIOUS TABLE LISTS
        // ====================================================================
        $tableToClientMap = [];
        $currentTables    = [];
        $previousTables   = [];

        $months = collect();
        for ($i = 0; $i < 12; $i++) {
            $date = Carbon::now()->subMonths($i);
            $months->push([
                'name' => strtolower($date->format('F')),
                'year' => $date->format('y'),
                'date' => $date->copy(),
            ]);
        }

        foreach ($allowedClients as $client) {
            $dbName = strtolower(trim($client->client_dbname ?? ''));
            if (!$dbName) continue;

            $clientTables = [];

            foreach ($months as $m) {
                $date       = $m['date'];
                $fullMonth  = strtolower($date->format('F'));
                $shortMonth = strtolower($date->format('M'));
                $year       = $date->format('y');

                $candidates = [
                    "{$dbName}{$fullMonth}{$year}pay",
                    "{$dbName}{$fullMonth}{$year}",
                    "{$dbName}{$shortMonth}{$year}pay",
                    "{$dbName}{$shortMonth}{$year}",
                    "{$dbName}sept{$year}pay",
                    "{$dbName}sept{$year}",
                ];

                foreach ($candidates as $cand) {
                    if (Schema::hasTable($cand) && DB::table($cand)->count() > 0) {
                        $clientTables[] = ['table' => $cand, 'date' => $m['date']];
                        $tableToClientMap[$cand] = $client;
                        break;
                    }
                }
            }

            if (empty($clientTables)) continue;

            usort($clientTables, fn($a, $b) => $b['date'] <=> $a['date']);

            $currentTables[] = $clientTables[0]['table'];
            if (count($clientTables) > 1) {
                $previousTables[] = $clientTables[1]['table'];
            }
        }

        $currentTables  = array_unique($currentTables);
        $previousTables = array_unique($previousTables);

        // ====================================================================
        // TOTAL EMPLOYEES — scoped to the user's allowed active clients.
        // We reuse the already-computed $allClientHeadcount to avoid a second
        // full scan of newemployeedata.
        // Super-admin: full count from newemployeedata.
        // Regular user: sum of counts across their allowed-client buckets only
        //   (excludes "No Clientele" and employees belonging to other clients).
        // ====================================================================
        if ($isSuperAdmin) {
            $totalEmployeesCount = DB::table('newemployeedata')->count();
            $totalEmployeesList  = DB::table('newemployeedata')
                ->select('staffid', 'employeename', 'company')
                ->get()
                ->map(fn($e) => (object)[
                    'staffid'      => $e->staffid,
                    'employeename' => $e->employeename ?? 'Unknown',
                    'company'      => $e->company ?? 'N/A',
                ]);
        } else {
            // Sum counts from the already-scoped allClientHeadcount buckets
            // (No Clientele is never in this array for non-super-admin)
            $totalEmployeesCount = (int) array_sum(array_column($allClientHeadcount, 'count'));

            $totalEmployeesList = collect(
                array_merge(...array_map(fn($g) => $g['employees'], $allClientHeadcount))
            )->map(fn($e) => (object) $e)->values();
        }

        if (empty($currentTables)) {
            $emptyData = $this->getEmptyComplianceData();
            $emptyData['totalEmployees'] = [
                'count'  => $totalEmployeesCount,
                'change' => 0,
                'list'   => $totalEmployeesList,
            ];

            return Inertia::render('dashboard', [
                'complianceData'     => $emptyData,
                'clientSummary'      => [],
                'allClientHeadcount' => $allClientHeadcount,
                'flash' => $this->getFlash($request),
            ]);
        }

        // ====================================================================
        // CLIENT SUMMARY — per-client headcount for the current payroll month
        // ====================================================================
        $clientSummary = [];
        foreach ($currentTables as $table) {
            $client = $tableToClientMap[$table] ?? null;
            if (!$client) continue;
            try {
                $count = DB::table($table)->count();
                $staffIdCol = $this->getStaffIdColumn($table);
                $nameCol    = $this->getNameColumn($table);

                $emps = DB::table($table)
                    ->select("{$staffIdCol} as staffid", DB::raw("COALESCE({$nameCol}, 'Unknown') as employeename"))
                    ->get()
                    ->map(fn($e) => [
                        'staffid'      => $e->staffid,
                        'employeename' => $e->employeename ?? 'Unknown',
                        'company'      => $client->client_name,
                    ])->toArray();

                $clientSummary[] = [
                    'client_name' => $client->client_name,
                    'count'       => $count,
                    'table'       => $table,
                    'employees'   => $emps,
                ];
            } catch (\Exception $e) {
                Log::warning("Client summary count failed for {$table}", ['error' => $e->getMessage()]);
            }
        }
        usort($clientSummary, fn($a, $b) => $b['count'] <=> $a['count']);

        // ====================================================================
        // CURRENT MONTH STAFF IDS & STAFF ON PAYROLL
        // ====================================================================
        $currentStaffIds = [];
        foreach ($currentTables as $table) {
            try {
                $col = $this->getStaffIdColumn($table);
                $ids = DB::table($table)->pluck($col)->toArray();
                $currentStaffIds = array_merge($currentStaffIds, $ids);
            } catch (\Exception $e) {
                Log::warning("Failed reading current table: {$table}", ['error' => $e->getMessage()]);
            }
        }
        $currentStaffIds = array_unique($currentStaffIds);
        $staffOnPayrollList  = $this->getEmployeeSample($currentTables, $currentStaffIds, $tableToClientMap);
        $staffOnPayrollCount = count($currentStaffIds);

        // ====================================================================
        // NO CONTACT DETAILS
        // ====================================================================
        $noContactIds = [];
        foreach ($currentTables as $table) {
            try {
                $staffIdCol = $this->getStaffIdColumn($table);
                $missing = DB::table($table)
                    ->leftJoin('employeecontact', "{$table}.{$staffIdCol}", '=', 'employeecontact.staffid')
                    ->whereNull('employeecontact.staffid')
                    ->pluck("{$table}.{$staffIdCol}")
                    ->toArray();
                $noContactIds = array_merge($noContactIds, $missing);
            } catch (\Exception $e) {
                Log::warning("No Contact Details check failed on {$table}", ['error' => $e->getMessage()]);
            }
        }
        $noContactIds  = array_unique($noContactIds);
        $noContactList = $this->getEmployeeSample($currentTables, $noContactIds, $tableToClientMap);

        // ====================================================================
        // PREVIOUS MONTH STAFF IDS
        // ====================================================================
        $previousStaffIds = [];
        foreach ($previousTables as $table) {
            try {
                $col = $this->getStaffIdColumn($table);
                $ids = DB::table($table)->pluck($col)->toArray();
                $previousStaffIds = array_merge($previousStaffIds, $ids);
            } catch (\Exception $e) {}
        }
        $previousStaffIds = array_unique($previousStaffIds);

        $staffOnPayrollChange = count($previousStaffIds) > 0
            ? round(((count($currentStaffIds) - count($previousStaffIds)) / count($previousStaffIds)) * 100, 2)
            : 0;

        // ====================================================================
        // NEW HIRES & RESIGNED
        // ====================================================================
        $newHiresIds = array_diff($currentStaffIds, $previousStaffIds);
        $resignedIds = array_diff($previousStaffIds, $currentStaffIds);

        $newHiresCount = count($newHiresIds);
        $resignedCount = count($resignedIds);

        $totalChange    = $totalEmployeesCount > 0 ? round((($newHiresCount - $resignedCount) / $totalEmployeesCount) * 100, 2) : 0;
        $newHiresChange = $totalEmployeesCount > 0 ? round(($newHiresCount / $totalEmployeesCount) * 100, 2) : 0;
        $resignedChange = $resignedCount > 0 ? -round(($resignedCount / $totalEmployeesCount) * 100, 2) : 0;

        // ====================================================================
        // NEW EMPLOYEES YET TO BE ONBOARDED
        // ====================================================================
        $notOnboardedIds  = $this->getIncompleteProfileIds($currentTables);
        $notOnboardedList = $this->getEmployeeSample($currentTables, $notOnboardedIds, $tableToClientMap);

        // ====================================================================
        // SAMPLE LISTS
        // ====================================================================
        $newHiresList = $this->getEmployeeSample($currentTables, $newHiresIds, $tableToClientMap);
        $resignedList = $this->getEmployeeSample($previousTables, $resignedIds, $tableToClientMap);

        // ====================================================================
        // COMPLIANCE CHECKS
        // ====================================================================
        $withoutBank  = $this->getWithoutDetails($currentTables, 'bankdetails', 'employid', $tableToClientMap);
        $withoutSsnit = $this->getWithoutDetails($currentTables, 'ssnit', 'staffid', $tableToClientMap);
        $withoutPetra = $this->getWithoutDetails($currentTables, 'petraorg', 'staffid', $tableToClientMap);

        $withoutGhanaCardIds  = $this->getWithoutGhanaCardFromSsnit($currentTables);
        $withoutGhanaCardList = $this->getEmployeeSample($currentTables, $withoutGhanaCardIds, $tableToClientMap);

        $trendData = $this->getLastThreeMonthsTrend($allowedClients, $tableToClientMap);

        // ====================================================================
        // FINAL RESPONSE
        // ====================================================================
        $complianceData = [
            'totalEmployees' => [
                'count'  => count($totalEmployeesList),
                'change' => $totalChange,
                'list'   => $totalEmployeesList,
            ],
            'staffOnPayroll' => [
                'count'  => count($staffOnPayrollList),
                'change' => $staffOnPayrollChange,
                'list'   => $staffOnPayrollList,
            ],
            'newHires' => [
                'count'  => count($newHiresList),
                'change' => $newHiresChange,
                'list'   => $newHiresList,
            ],
            'resignedTerminated' => [
                'count'  => count($resignedList),
                'change' => $resignedChange,
                'list'   => $resignedList,
            ],
            'notOnboarded' => [
                'count' => count($notOnboardedList),
                'list'  => $notOnboardedList,
            ],
            'noContactDetails' => [
                'count' => count($noContactList),
                'list'  => $noContactList,
            ],
            'expiringContracts' => [
                'count' => $expiringContracts->count(),
                'list'  => $expiringContracts->values(),
            ],
            'expiredContracts' => [
                'count' => $expiredContracts->count(),
                'list'  => $expiredContracts->values(),
            ],
            'unsignedContracts' => [
                'count' => $unsignedContracts->count(),
                'list'  => $unsignedContracts->values(),
            ],
            'renewalExpiringContracts' => [
                'count' => $renewalExpiring->count(),
                'list'  => $renewalExpiring->values(),
            ],
            'renewalExpiredContracts' => [
                'count' => $renewalExpired->count(),
                'list'  => $renewalExpired->values(),
            ],
            'renewalUnsignedContracts' => [
                'count' => $renewalUnsigned->count(),
                'list'  => $renewalUnsigned->values(),
            ],
            'withoutBank'        => ['count' => count($withoutBank['list']),        'list' => $withoutBank['list']],
            'withoutSsnit'       => ['count' => count($withoutSsnit['list']),       'list' => $withoutSsnit['list']],
            'withoutPetra'       => ['count' => count($withoutPetra['list']),       'list' => $withoutPetra['list']],
            'withoutGhanaCard'   => ['count' => count($withoutGhanaCardList),  'list' => $withoutGhanaCardList],
            'incompleteProfiles' => ['count' => count($notOnboardedList),      'list' => $notOnboardedList],
            'pendingDocuments'   => ['count' => 0, 'list' => collect()],
            'upcomingBirthdays'  => ['count' => 0, 'list' => collect()],
            'employeesOnLeave'   => ['count' => 0, 'list' => collect()],
            'pendingLeaveRequests' => ['count' => 0, 'list' => collect()],
            'trend' => $trendData,
        ];

        return Inertia::render('dashboard', [
            'complianceData'     => $complianceData,
            'clientSummary'      => $clientSummary,
            'allClientHeadcount' => $allClientHeadcount,
            'flash'              => $this->getFlash($request),
        ]);
    }


    // ========================================================================
    // ALL-CLIENT HEADCOUNT
    // Groups employees in newemployeedata by company column.
    // Only counts employees whose company matches one of the $allowedClients.
    // $showNoClientele = true (super-admin only): also collects employees
    //   whose company doesn't match any known client into a "No Clientele" bucket.
    // Zero-count client groups are excluded from the returned array.
    // ========================================================================
    private function getAllClientHeadcount($allowedClients, bool $showNoClientele = false): array
    {
        // Lookup keyed by lowercase client_name
        $knownClients = $allowedClients
            ->keyBy(fn($c) => strtolower(trim($c->client_name)));

        // Pre-seed one group per allowed client
        $groups = [];
        foreach ($allowedClients as $client) {
            $groups[$client->client_name] = [
                'client_name' => $client->client_name,
                'count'       => 0,
                'employees'   => [],
            ];
        }

        $noClientele = ['client_name' => 'No Clientele', 'count' => 0, 'employees' => []];

        $employees = DB::table('newemployeedata')
            ->select('staffid', 'employeename', 'company', 'shop')
            ->get();

        foreach ($employees as $emp) {
            $companyRaw = trim($emp->company ?? $emp->shop ?? '');
            $matched    = $this->matchClientName($companyRaw, $knownClients);

            $row = [
                'staffid'      => $emp->staffid,
                'employeename' => $emp->employeename ?? 'Unknown',
                'company'      => $companyRaw ?: 'N/A',
            ];

            if ($matched && isset($groups[$matched])) {
                $groups[$matched]['count']++;
                $groups[$matched]['employees'][] = $row;
            } elseif (!$matched && $showNoClientele) {
                // Only super-admin sees employees with no matching client
                $noClientele['count']++;
                $noClientele['employees'][] = $row;
            }
            // Employees belonging to inaccessible / inactive clients → silently skipped
        }

        // Exclude zero-count groups, sort descending
        $result = array_values(array_filter($groups, fn($g) => $g['count'] > 0));
        usort($result, fn($a, $b) => $b['count'] <=> $a['count']);

        // Append No Clientele last — super-admin only, only when non-empty
        if ($showNoClientele && $noClientele['count'] > 0) {
            $result[] = $noClientele;
        }

        return $result;
    }

    /**
     * Match a raw company string to a known client_name (case-insensitive substring).
     * Returns the canonical client_name or null.
     */
    private function matchClientName(string $company, $knownClients): ?string
    {
        if (trim($company) === '') return null;

        $companyLower = strtolower(trim($company));
        $cleanCompany = preg_replace('/[^a-z0-9]/', '', $companyLower);
        if ($cleanCompany === '') return null;

        if (isset($knownClients[$companyLower])) {
            return $knownClients[$companyLower]->client_name;
        }

        foreach ($knownClients as $clientNameLower => $clientRow) {
            $cleanClientName = preg_replace('/[^a-z0-9]/', '', $clientNameLower);
            $cleanShortName  = preg_replace('/[^a-z0-9]/', '', strtolower($clientRow->short_name ?? ''));
            $cleanDbName     = preg_replace('/[^a-z0-9]/', '', strtolower($clientRow->client_dbname ?? ''));

            if ($cleanCompany === $cleanClientName ||
                ($cleanShortName !== '' && $cleanCompany === $cleanShortName) ||
                ($cleanDbName !== '' && $cleanCompany === $cleanDbName)) {
                return $clientRow->client_name;
            }

            if (($cleanClientName !== '' && (str_contains($cleanCompany, $cleanClientName) || str_contains($cleanClientName, $cleanCompany))) ||
                ($cleanShortName !== '' && (str_contains($cleanCompany, $cleanShortName) || str_contains($cleanShortName, $cleanCompany))) ||
                ($cleanDbName !== '' && (str_contains($cleanCompany, $cleanDbName) || str_contains($cleanDbName, $cleanCompany)))) {
                return $clientRow->client_name;
            }
        }

        return null;
    }

    // ========================================================================
    // getStaffIdColumn
    // ========================================================================
    private function getStaffIdColumn($table)
    {
        try {
            $columns = Schema::getColumnListing($table);
            $lowerMap = [];
            foreach ($columns as $col) {
                $lowerMap[strtolower($col)] = $col;
            }

            $candidates = [
                'staffida', 'staffid', 'staff_id', 'staffno', 'staff_no',
                'employ', 'employid', 'employee_id', 'id', 'matricule'
            ];
            foreach ($candidates as $cand) {
                if (isset($lowerMap[$cand])) {
                    return $lowerMap[$cand];
                }
            }
        } catch (\Exception $e) {}

        if (str_contains($table, 'transsnet'))                                    return 'staffid';
        if (str_contains($table, 'transsion'))                                    return 'staffida';
        if (str_contains($table, 'tecno'))                                        return 'staffida';
        if (str_contains($table, 'xiaomi'))                                       return 'staffida';
        if (str_contains($table, 'melcom'))                                       return 'employ';
        if (str_contains($table, 'centurypay'))                                   return 'employ';
        if (str_contains($table, 'driverssnit'))                                  return 'employ';
        if (str_contains($table, 'twelliumaccra'))                                return 'staff_id';
        if (str_contains($table, 'twellium'))                                     return 'employ';
        if (str_contains($table, 'honda'))                                        return 'STAFFID';
        if (str_contains($table, 'togobunge'))                                    return 'staffid';
        if (str_contains($table, 'civ'))                                          return 'id';
        if (str_contains($table, 'bunge'))                                        return 'STAFF_ID';
        if (str_contains($table, 'hygieneexternal'))                              return 'StaffID';
        if (str_contains($table, 'offload'))                                      return 'StaffID';
        if (str_contains($table, 'olamfood'))                                     return 'StaffID';
        if (str_contains($table, 'olam') || str_contains($table, 'hygiene'))     return 'id';
        if (str_contains($table, 'inhouse'))                                      return 'id';
        if (str_contains($table, 'nyonkopa'))                                     return 'staff_id';
        if (str_contains($table, 'jkay'))                                         return 'staff_id';
        if (str_contains($table, 'shefer'))                                       return 'staff_id';
        if (str_contains($table, 'fonpackage'))                                   return 'staff_id';
        if (str_contains($table, 'a2feed'))                                       return 'staff_id';
        if (str_contains($table, 'a2poultry'))                                    return 'staff_id';
        if (str_contains($table, 'a2soya'))                                       return 'staff_id';
        if (str_contains($table, 'pz'))                                           return 'staffid';
        if (str_contains($table, 'transburkina'))                                 return 'staffid';
        return 'staffid';
    }

    private function getNameColumn($table)
    {
        try {
            $columns = Schema::getColumnListing($table);
            $lowerMap = [];
            foreach ($columns as $col) {
                $lowerMap[strtolower($col)] = $col;
            }

            $candidates = [
                'name', 'fullname', 'full_name', 'employeename', 'employee_name', 'empoyeename',
                'name1', 'names', 'nom'
            ];
            foreach ($candidates as $cand) {
                if (isset($lowerMap[$cand])) {
                    return $lowerMap[$cand];
                }
            }
        } catch (\Exception $e) {}

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
        if (str_contains($table, 'civ'))             return 'fullname';
        if (str_contains($table, 'togobunge'))       return 'nom';
        if (str_contains($table, 'bunge'))           return 'NAME';
        if (str_contains($table, 'twellium'))        return 'name';
        if (str_contains($table, 'inhouse'))         return 'name';
        if (str_contains($table, 'transburkina'))    return 'fullname';
        return 'name';
    }

    private function getWithoutDetails($tables, $detailTable, $joinColumn, $tableToClientMap)
    {
        $ids = [];
        foreach ($tables as $table) {
            try {
                $staffIdCol = $this->getStaffIdColumn($table);
                $missing = DB::table($table)
                    ->leftJoin($detailTable, "{$table}.{$staffIdCol}", '=', "{$detailTable}.{$joinColumn}")
                    ->whereNull("{$detailTable}.{$joinColumn}")
                    ->pluck("{$table}.{$staffIdCol}")
                    ->toArray();
                $ids = array_merge($ids, $missing);
            } catch (\Exception $e) {
                Log::warning("Compliance check failed for {$detailTable} on {$table}", ['error' => $e->getMessage()]);
            }
        }
        $ids = array_unique($ids);
        return [
            'count' => count($ids),
            'list'  => $this->getEmployeeSample($tables, $ids, $tableToClientMap),
        ];
    }

    private function getWithoutGhanaCardFromSsnit($tables)
    {
        $ids = [];
        foreach ($tables as $table) {
            try {
                $staffIdCol = $this->getStaffIdColumn($table);
                $missing = DB::table($table)
                    ->leftJoin('ssnit', "{$table}.{$staffIdCol}", '=', 'ssnit.staffid')
                    ->where(function ($q) {
                        $q->whereNull('ssnit.national')
                          ->orWhere('ssnit.national', '')
                          ->orWhere('ssnit.national', 'NULL');
                    })
                    ->pluck("{$table}.{$staffIdCol}")
                    ->toArray();
                $ids = array_merge($ids, $missing);
            } catch (\Exception $e) {
                Log::warning("Ghana Card check failed on {$table}", ['error' => $e->getMessage()]);
            }
        }
        return array_unique($ids);
    }

    private function getIncompleteProfileIds($tables)
    {
        $ids = [];
        foreach ($tables as $table) {
            try {
                $staffIdCol = $this->getStaffIdColumn($table);
                $missing = DB::table($table)
                    ->leftJoin('newemployeedata', "{$table}.{$staffIdCol}", '=', 'newemployeedata.staffid')
                    ->whereNull('newemployeedata.staffid')
                    ->pluck("{$table}.{$staffIdCol}")
                    ->toArray();
                $ids = array_merge($ids, $missing);
            } catch (\Exception $e) {}
        }
        return array_unique($ids);
    }

    private function getEmployeeSample($tables, $ids, $tableToClientMap)
    {
        $sample = collect();
        if (empty($ids)) return $sample;

        foreach ($tables as $table) {
            try {
                $client = $tableToClientMap[$table] ?? null;
                if (!$client) continue;

                $staffIdCol = $this->getStaffIdColumn($table);
                $nameCol    = $this->getNameColumn($table);

                $emps = DB::table($table)
                    ->whereIn($staffIdCol, $ids)
                    ->select("{$staffIdCol} as staffid", DB::raw("COALESCE({$nameCol}, 'Unknown') as employeename"))
                    ->get();

                foreach ($emps as $emp) {
                    $sample->push((object)[
                        'staffid'      => $emp->staffid,
                        'employeename' => $emp->employeename,
                        'company'      => $client->client_name,
                    ]);
                }
            } catch (\Exception $e) {}
        }

        return $sample->unique('staffid')->values();
    }

    private function getLastThreeMonthsTrend($clients, $tableToClientMap)
    {
        $trend = ['labels' => [], 'totalEmployees' => [], 'newHires' => [], 'resigned' => []];
        $now   = Carbon::now();

        foreach ([3, 2, 1] as $i) {
            $monthDate  = $now->copy()->subMonths($i);
            $monthName  = $monthDate->format('F Y');
            $monthShort = strtolower($monthDate->format('F'));
            $yearShort  = $monthDate->format('y');

            $prevDate  = $monthDate->copy()->subMonth();
            $prevShort = strtolower($prevDate->format('F'));
            $prevYear  = $prevDate->format('y');

            $monthTables = [];
            $prevTables  = [];

            foreach ($clients as $client) {
                $dbName = $client->client_dbname;

                $ct = Schema::hasTable("{$dbName}{$monthShort}{$yearShort}pay")
                    ? "{$dbName}{$monthShort}{$yearShort}pay"
                    : "{$dbName}{$monthShort}{$yearShort}";
                $pt = Schema::hasTable("{$dbName}{$prevShort}{$prevYear}pay")
                    ? "{$dbName}{$prevShort}{$prevYear}pay"
                    : "{$dbName}{$prevShort}{$prevYear}";

                if (Schema::hasTable($ct) && DB::table($ct)->count() > 0) $monthTables[] = $ct;
                if (Schema::hasTable($pt) && DB::table($pt)->count() > 0) $prevTables[]  = $pt;
            }

            $currentIds = $this->getAllStaffIds($monthTables);
            $prevIds    = $this->getAllStaffIds($prevTables);
            $total      = array_sum(array_map(fn($t) => DB::table($t)->count(), $monthTables));

            $trend['labels'][]         = $monthName;
            $trend['totalEmployees'][] = $total;
            $trend['newHires'][]       = count(array_diff($currentIds, $prevIds));
            $trend['resigned'][]       = count(array_diff($prevIds, $currentIds));
        }

        return $trend;
    }

    private function getAllStaffIds($tables)
    {
        $ids = [];
        foreach ($tables as $table) {
            try {
                $col = $this->getStaffIdColumn($table);
                $ids = array_merge($ids, DB::table($table)->pluck($col)->toArray());
            } catch (\Exception $e) {}
        }
        return array_unique($ids);
    }

    private function getEmptyComplianceData(): array
    {
        return [
            'totalEmployees'          => ['count' => 0, 'change' => 0, 'list' => collect()],
            'staffOnPayroll'          => ['count' => 0, 'change' => 0, 'list' => collect()],
            'newHires'                => ['count' => 0, 'change' => 0, 'list' => collect()],
            'resignedTerminated'      => ['count' => 0, 'change' => 0, 'list' => collect()],
            'notOnboarded'            => ['count' => 0, 'list' => collect()],
            'withoutBank'             => ['count' => 0, 'list' => collect()],
            'withoutSsnit'            => ['count' => 0, 'list' => collect()],
            'withoutPetra'            => ['count' => 0, 'list' => collect()],
            'withoutGhanaCard'        => ['count' => 0, 'list' => collect()],
            'incompleteProfiles'      => ['count' => 0, 'list' => collect()],
            'noContactDetails'        => ['count' => 0, 'list' => collect()],
            'pendingDocuments'        => ['count' => 0, 'list' => collect()],
            'expiringContracts'       => ['count' => 0, 'list' => collect()],
            'expiredContracts'        => ['count' => 0, 'list' => collect()],
            'unsignedContracts'       => ['count' => 0, 'list' => collect()],
            'renewalExpiringContracts'=> ['count' => 0, 'list' => collect()],
            'renewalExpiredContracts' => ['count' => 0, 'list' => collect()],
            'renewalUnsignedContracts'=> ['count' => 0, 'list' => collect()],
            'upcomingBirthdays'       => ['count' => 0, 'list' => collect()],
            'employeesOnLeave'        => ['count' => 0, 'list' => collect()],
            'pendingLeaveRequests'    => ['count' => 0, 'list' => collect()],
            'trend' => ['labels' => [], 'totalEmployees' => [], 'newHires' => [], 'resigned' => []],
        ];
    }

    private function getFlash(Request $request): array
    {
        return [
            'success' => $request->hasSession() ? $request->session()->get('success') : null,
            'error'   => $request->hasSession() ? $request->session()->get('error') : null,
        ];
    }
}
