<?php

namespace App\Services;

use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PayrollHeadcountService
{
    private const MONTH_NAMES = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];

    private const MONTH_SHORTS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
    ];

    private const MONTH_MAP = [
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
        'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
        'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
        'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'sept' => 9,
        'oct' => 10, 'nov' => 11, 'dec' => 12
    ];

    /**
     * Get aggregate monthly and yearly payroll headcount metrics.
     */
    public function getHeadcountData($allowedClients, ?int $selectedYear = null, bool $forceRefresh = false): array
    {
        $clientIds = $allowedClients instanceof Collection
            ? $allowedClients->pluck('id')->sort()->values()->all()
            : collect($allowedClients)->pluck('id')->sort()->values()->all();

        $cacheKey = 'payroll_headcount_stats_' . md5(implode('-', $clientIds));

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        $allData = Cache::remember($cacheKey, 1800, function () use ($allowedClients) {
            return $this->computeHeadcountMetrics($allowedClients);
        });

        // Determine current/selected year
        $allYears = $allData['all_years'] ?? [];
        $currentYear = $selectedYear ?: ($allYears[0] ?? (int)date('Y'));

        $selectedYearData = $allData['yearly'][$currentYear] ?? null;

        return [
            'all_years'          => $allYears,
            'current_year'       => $currentYear,
            'selected_year_data' => $selectedYearData,
            'yearly_summary'     => array_values($allData['yearly'] ?? []),
            'monthly_trend'      => $allData['monthly_trend'] ?? [],
            'yearly_comparison'  => $allData['yearly_comparison'] ?? [],
        ];
    }

    /**
     * Compute the full headcount metrics by scanning tables.
     */
    private function computeHeadcountMetrics($allowedClients): array
    {
        $tables = DB::select('SHOW TABLES');
        $dbName = DB::getDatabaseName();
        $colName = 'Tables_in_' . $dbName;
        $allTables = array_map(fn($t) => $t->$colName, $tables);

        $clientLookup = [];
        foreach ($allowedClients as $c) {
            $prefix = strtolower(trim($c->client_dbname ?? ''));
            if ($prefix) {
                $clientLookup[$prefix] = $c;
            }
        }

        $monthlyData = []; // keyed by "YYYY-MM"

        foreach ($allTables as $tableName) {
            $tbl = strtolower($tableName);

            // Filter out foreign branches
            if (
                str_contains($tbl, 'benin') ||
                str_contains($tbl, 'togo') ||
                str_contains($tbl, 'civ') ||
                str_contains($tbl, 'transburkina')
            ) {
                continue;
            }

            foreach ($clientLookup as $dbPrefix => $client) {
                if (str_starts_with($tbl, $dbPrefix)) {
                    $rest = substr($tbl, strlen($dbPrefix));
                    if (preg_match('/^(january|february|march|april|may|june|july|august|september|sept|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|oct|nov|dec)(\d{2,4})(pay)?$/', $rest, $m)) {
                        $mName = $m[1];
                        $yPart = $m[2];
                        $year = (strlen($yPart) === 2) ? (2000 + (int)$yPart) : (int)$yPart;

                        if ($year < 2020 || $year > 2035) {
                            continue;
                        }

                        $mNum = self::MONTH_MAP[$mName] ?? null;
                        if (!$mNum) {
                            continue;
                        }

                        $monthKey = sprintf('%04d-%02d', $year, $mNum);

                        try {
                            $count = DB::table($tableName)->count();
                            if ($count > 0) {
                                if (!isset($monthlyData[$monthKey])) {
                                    $monthlyData[$monthKey] = [
                                        'year'         => $year,
                                        'month'        => $mNum,
                                        'month_name'   => self::MONTH_NAMES[$mNum],
                                        'month_short'  => self::MONTH_SHORTS[$mNum],
                                        'month_key'    => $monthKey,
                                        'total_count'  => 0,
                                        'clients_count'=> 0,
                                        'clients'      => [],
                                    ];
                                }

                                $clientName = $client->client_name;
                                // If both base and pay tables exist, keep the one with higher count
                                if (!isset($monthlyData[$monthKey]['clients'][$clientName]) || $count > $monthlyData[$monthKey]['clients'][$clientName]['count']) {
                                    $monthlyData[$monthKey]['clients'][$clientName] = [
                                        'client_id'   => $client->id,
                                        'client_name' => $client->client_name,
                                        'short_name'  => $client->short_name ?? $client->client_name,
                                        'count'       => $count,
                                        'table'       => $tableName,
                                    ];
                                }
                            }
                        } catch (\Throwable $e) {
                            // Table query failed or schema mismatch
                        }

                        break; // matched client prefix
                    }
                }
            }
        }

        ksort($monthlyData);

        // Calculate MoM changes and finalize client lists
        $prevMonthCount = null;
        foreach ($monthlyData as $mKey => &$mData) {
            $mData['total_count']   = (int)array_sum(array_column($mData['clients'], 'count'));
            $mData['clients_count'] = count($mData['clients']);
            $mData['clients']       = array_values($mData['clients']);
            usort($mData['clients'], fn($a, $b) => $b['count'] <=> $a['count']);

            if ($prevMonthCount !== null && $prevMonthCount > 0) {
                $diff = $mData['total_count'] - $prevMonthCount;
                $mData['change_count']   = $diff;
                $mData['change_percent'] = round(($diff / $prevMonthCount) * 100, 1);
            } else {
                $mData['change_count']   = 0;
                $mData['change_percent'] = 0.0;
            }
            $prevMonthCount = $mData['total_count'];
        }
        unset($mData);

        // Group into years with full 12-month calendar slots
        $yearly = [];
        $yearsFound = [];

        foreach ($monthlyData as $mKey => $mData) {
            $y = $mData['year'];
            $yearsFound[$y] = true;
        }

        $allYears = array_keys($yearsFound);
        rsort($allYears);

        foreach ($allYears as $year) {
            $yearMonths = [];
            $totalVolume = 0;
            $monthsWithData = 0;
            $maxMonthly = 0;
            $peakMonth = '';

            for ($m = 1; $m <= 12; $m++) {
                $key = sprintf('%04d-%02d', $year, $m);
                if (isset($monthlyData[$key])) {
                    $item = $monthlyData[$key];
                    $item['has_data'] = true;
                    $yearMonths[] = $item;

                    $totalVolume += $item['total_count'];
                    $monthsWithData++;

                    if ($item['total_count'] > $maxMonthly) {
                        $maxMonthly = $item['total_count'];
                        $peakMonth = $item['month_name'];
                    }
                } else {
                    $yearMonths[] = [
                        'year'           => $year,
                        'month'          => $m,
                        'month_name'     => self::MONTH_NAMES[$m],
                        'month_short'    => self::MONTH_SHORTS[$m],
                        'month_key'      => $key,
                        'total_count'    => 0,
                        'clients_count'  => 0,
                        'change_count'   => 0,
                        'change_percent' => 0.0,
                        'has_data'       => false,
                        'clients'        => [],
                    ];
                }
            }

            $yearly[$year] = [
                'year'           => $year,
                'total_volume'   => $totalVolume,
                'months_count'   => $monthsWithData,
                'avg_monthly'    => $monthsWithData > 0 ? (int)round($totalVolume / $monthsWithData) : 0,
                'max_monthly'    => $maxMonthly,
                'peak_month'     => $peakMonth ?: 'N/A',
                'months'         => $yearMonths,
            ];
        }

        // Continuous timeline for trend chart (last 24 months with data)
        $recentMonths = array_slice($monthlyData, -24, 24, true);
        $monthlyTrend = [
            'labels'  => [],
            'counts'  => [],
            'clients' => [],
        ];
        foreach ($recentMonths as $m) {
            $monthlyTrend['labels'][]  = $m['month_short'] . ' ' . $m['year'];
            $monthlyTrend['counts'][]  = $m['total_count'];
            $monthlyTrend['clients'][] = $m['clients_count'];
        }

        // Multi-year comparison datasets (last 3-4 years)
        $comparisonYears = array_slice($allYears, 0, 4);
        $yearlyComparison = [
            'labels'   => array_values(self::MONTH_SHORTS),
            'datasets' => [],
        ];

        $colors = [
            '#2563eb', // blue-600
            '#10b981', // emerald-500
            '#8b5cf6', // purple-500
            '#f59e0b', // amber-500
        ];

        foreach ($comparisonYears as $idx => $yr) {
            $dataPoints = [];
            $color = $colors[$idx % count($colors)];
            for ($m = 1; $m <= 12; $m++) {
                $key = sprintf('%04d-%02d', $yr, $m);
                $dataPoints[] = $monthlyData[$key]['total_count'] ?? 0;
            }
            $yearlyComparison['datasets'][] = [
                'year'            => $yr,
                'label'           => (string)$yr,
                'data'            => $dataPoints,
                'borderColor'     => $color,
                'backgroundColor' => $color . '20',
            ];
        }

        return [
            'all_years'         => $allYears,
            'yearly'            => $yearly,
            'monthly_trend'     => $monthlyTrend,
            'yearly_comparison' => $yearlyComparison,
        ];
    }

    /**
     * Fetch employee records from a specific payroll table for drilldown modal.
     */
    public function getEmployeesForTable(string $table, ?string $search = null): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $cols = Schema::getColumnListing($table);
        $colsLower = array_map('strtolower', $cols);

        // Find staff id column
        $staffIdCol = null;
        foreach (['staffid', 'staffida', 'employ', 'id', 'emp_id'] as $cand) {
            $idx = array_search($cand, $colsLower, true);
            if ($idx !== false) {
                $staffIdCol = $cols[$idx];
                break;
            }
        }
        $staffIdCol = $staffIdCol ?: ($cols[0] ?? 'staffid');

        // Find name column
        $nameCol = null;
        foreach (['name', 'empoyeename', 'employeename', 'fullname', 'employee_name'] as $cand) {
            $idx = array_search($cand, $colsLower, true);
            if ($idx !== false) {
                $nameCol = $cols[$idx];
                break;
            }
        }

        // Find netpay column
        $netPayCol = null;
        foreach (['netpay', 'takehomepay', 'net_salary', 'take_home_pay', 'net_pay'] as $cand) {
            $idx = array_search($cand, $colsLower, true);
            if ($idx !== false) {
                $netPayCol = $cols[$idx];
                break;
            }
        }

        // Find department / shop column
        $deptCol = null;
        foreach (['dept', 'department', 'shop', 'shopname', 'location'] as $cand) {
            $idx = array_search($cand, $colsLower, true);
            if ($idx !== false) {
                $deptCol = $cols[$idx];
                break;
            }
        }

        $query = DB::table($table);

        if ($search) {
            $query->where(function ($q) use ($search, $staffIdCol, $nameCol, $deptCol) {
                $q->where($staffIdCol, 'like', "%{$search}%");
                if ($nameCol) {
                    $q->orWhere($nameCol, 'like', "%{$search}%");
                }
                if ($deptCol) {
                    $q->orWhere($deptCol, 'like', "%{$search}%");
                }
            });
        }

        $selects = [
            "{$staffIdCol} as staffid",
            $nameCol ? "{$nameCol} as name" : DB::raw("'Unknown' as name"),
            $netPayCol ? "{$netPayCol} as netpay" : DB::raw('0 as netpay'),
            $deptCol ? "{$deptCol} as department" : DB::raw("'' as department"),
        ];

        return $query->select($selects)->limit(200)->get()->toArray();
    }

    /**
     * Compute Super Admin Financials (Admin/Principal fee, GETFund, NHIL, Net Salary, SSNIT, Petra, Tier 3)
     * for a given month across all clients or for a selected client.
     */
    public function getFinancialsForMonth($allowedClients, ?string $yearMonth = null): array
    {
        $date = Carbon::now();
        $availableMonths = [];

        // Find available months over the last 24 months
        for ($i = 0; $i < 24; $i++) {
            $checkDate = $date->copy()->subMonths($i);
            $ym = $checkDate->format('Y-m');
            $availableMonths[$ym] = [
                'key'   => $ym,
                'label' => $checkDate->format('F Y'),
                'year'  => (int)$checkDate->format('Y'),
                'month' => (int)$checkDate->format('m'),
            ];
        }

        // Default to August 2026 if no month specified and current month is sparse, or user-selected month
        if (!$yearMonth) {
            $yearMonth = '2026-08';
        }

        $selectedDate = Carbon::createFromFormat('Y-m', $yearMonth);
        $fullMonth = strtolower($selectedDate->format('F'));
        $shortMonth = strtolower($selectedDate->format('M'));
        $yy = $selectedDate->format('y');

        $findCol = function ($cols, $candidates) {
            $colsLower = array_map('strtolower', $cols);
            foreach ($candidates as $cand) {
                $idx = array_search(strtolower($cand), $colsLower, true);
                if ($idx !== false) {
                    return $cols[$idx];
                }
            }
            return null;
        };

        $grand = [
            'count'         => 0,
            'admin_fee'     => 0.0,
            'getfund'       => 0.0,
            'nhil'          => 0.0,
            'net_salary'    => 0.0,
            'ssnit_ee'      => 0.0,
            'ssnit_er'      => 0.0,
            'ssnit_total'   => 0.0,
            'petra'         => 0.0,
            'tier_3'        => 0.0,
            'total_cost'    => 0.0,
            'clients_count' => 0,
        ];

        $byClient = [];

        foreach ($allowedClients as $client) {
            $prefix = strtolower(trim($client->client_dbname ?? ''));
            if (!$prefix) {
                continue;
            }
            if (
                str_contains($prefix, 'benin') ||
                str_contains($prefix, 'togo') ||
                str_contains($prefix, 'civ') ||
                str_contains($prefix, 'transburkina')
            ) {
                continue;
            }

            $candidates = [
                "{$prefix}{$fullMonth}{$yy}pay",
                "{$prefix}{$fullMonth}{$yy}",
                "{$prefix}{$shortMonth}{$yy}pay",
                "{$prefix}{$shortMonth}{$yy}",
                "{$prefix}sept{$yy}pay",
                "{$prefix}sept{$yy}",
            ];

            foreach ($candidates as $tbl) {
                if (Schema::hasTable($tbl)) {
                    $cols = Schema::getColumnListing($tbl);
                    $adminFeeCol = $findCol($cols, ['management_fee', 'management_fee_8', 'management_fee_10', 'managementfee_8', 'management_fee_5_percent', 'management_fee_8_percent', 'mgt_fee', 'admin_fee', 'adminfee']);
                    $getfundCol  = $findCol($cols, ['getfund', 'getfund_2_5', 'getfund_2_5_percent']);
                    $nhilCol     = $findCol($cols, ['nhil', 'nhil_2_5', 'nhil_2_5_percent']);
                    $netSalaryCol= $findCol($cols, ['netpay', 'net_pay', 'net_salary', 'takehomepay', 'takehomepay_ghc', 'banktransfer']);
                    $ssnitEeCol  = $findCol($cols, ['ssnitee', 'ssnit_ee', 'ssnit_ee_5_5', 'ssnitee_0_5', 'ssnit_5_5_percent', 'ssnitamt']);
                    $ssnitErCol  = $findCol($cols, ['ssniter', 'ssnit_er', 'ssf_13', 'ssnit_er_13_percent', 'emp_ssnit_13_percent', 'ssf']);
                    $petraCol    = $findCol($cols, ['petra', 'petra_5', 'petra_5_percent', 'petraorg']);
                    $tier3Col    = $findCol($cols, ['pf', 'trust', 'strust', 'provident_fund', 'tier_3', 'tier3']);

                    $selects = [];
                    $selects[] = $adminFeeCol ? "SUM(COALESCE(`$adminFeeCol`, 0)) as admin_fee" : "0 as admin_fee";
                    $selects[] = $getfundCol  ? "SUM(COALESCE(`$getfundCol`, 0)) as getfund"   : "0 as getfund";
                    $selects[] = $nhilCol     ? "SUM(COALESCE(`$nhilCol`, 0)) as nhil"         : "0 as nhil";
                    $selects[] = $netSalaryCol? "SUM(COALESCE(`$netSalaryCol`, 0)) as net_salary" : "0 as net_salary";
                    $selects[] = $ssnitEeCol  ? "SUM(COALESCE(`$ssnitEeCol`, 0)) as ssnit_ee" : "0 as ssnit_ee";
                    $selects[] = $ssnitErCol  ? "SUM(COALESCE(`$ssnitErCol`, 0)) as ssnit_er" : "0 as ssnit_er";
                    $selects[] = $petraCol    ? "SUM(COALESCE(`$petraCol`, 0)) as petra"       : "0 as petra";
                    $selects[] = $tier3Col    ? "SUM(COALESCE(`$tier3Col`, 0)) as tier_3"     : "0 as tier_3";
                    $selects[] = "COUNT(*) as count";

                    try {
                        $row = DB::table($tbl)->selectRaw(implode(', ', $selects))->first();
                        $cnt = (int)($row->count ?? 0);
                        if ($cnt > 0) {
                            $adminFee  = round((float)($row->admin_fee ?? 0), 2);
                            $getfund   = round((float)($row->getfund ?? 0), 2);
                            $nhil      = round((float)($row->nhil ?? 0), 2);
                            $netSalary = round((float)($row->net_salary ?? 0), 2);
                            $ssnitEe   = round((float)($row->ssnit_ee ?? 0), 2);
                            $ssnitEr   = round((float)($row->ssnit_er ?? 0), 2);
                            $ssnitTot  = round($ssnitEe + $ssnitEr, 2);
                            $petra     = round((float)($row->petra ?? 0), 2);
                            $tier3     = round((float)($row->tier_3 ?? 0), 2);
                            $totCost   = round($netSalary + $adminFee + $getfund + $nhil + $ssnitTot + $petra + $tier3, 2);

                            $cItem = [
                                'client_id'   => $client->id,
                                'client_name' => $client->client_name,
                                'short_name'  => $client->short_name ?? $client->client_name,
                                'table'       => $tbl,
                                'count'       => $cnt,
                                'admin_fee'   => $adminFee,
                                'getfund'     => $getfund,
                                'nhil'        => $nhil,
                                'net_salary'  => $netSalary,
                                'ssnit_ee'    => $ssnitEe,
                                'ssnit_er'    => $ssnitEr,
                                'ssnit_total' => $ssnitTot,
                                'petra'       => $petra,
                                'tier_3'      => $tier3,
                                'total_cost'  => $totCost,
                            ];

                            $byClient[] = $cItem;

                            $grand['count']       += $cnt;
                            $grand['admin_fee']   += $adminFee;
                            $grand['getfund']     += $getfund;
                            $grand['nhil']        += $nhil;
                            $grand['net_salary']  += $netSalary;
                            $grand['ssnit_ee']    += $ssnitEe;
                            $grand['ssnit_er']    += $ssnitEr;
                            $grand['ssnit_total'] += $ssnitTot;
                            $grand['petra']       += $petra;
                            $grand['tier_3']      += $tier3;
                            $grand['total_cost']  += $totCost;
                            $grand['clients_count']++;
                            break;
                        }
                    } catch (\Throwable $e) {
                        // Table read failed
                    }
                }
            }
        }

        // Round grand totals
        foreach ($grand as $k => &$v) {
            if ($k !== 'count' && $k !== 'clients_count') {
                $v = round($v, 2);
            }
        }
        unset($v);

        usort($byClient, fn($a, $b) => $b['count'] <=> $a['count']);

        return [
            'month'            => $yearMonth,
            'month_label'      => $selectedDate->format('F Y'),
            'available_months' => array_values($availableMonths),
            'grand_total'      => $grand,
            'by_client'        => $byClient,
        ];
    }
}
