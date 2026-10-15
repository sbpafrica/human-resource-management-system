<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\TranssionPay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class PerformanceTrackerController extends Controller
{
    public function index(Request $request)
    {
        try {
            Log::debug('Performance Tracker Request', [
                'user_id' => $request->user()?->id,
                'database' => DB::connection()->getDatabaseName(),
                'params' => $request->query()
            ]);

            // Get filter parameters
            $staffida = $request->query('staffida', '');
            $month = $request->query('month', '');
            $comparisonPeriod = (int) $request->query('comparison_period', 3); // Default to 3 months

            // Validate inputs
            if (!in_array($comparisonPeriod, [3, 6])) {
                $comparisonPeriod = 3;
            }

            // Get available months
            $availableMonths = $this->getAvailableMonths();

            // Default to latest available month if none selected or invalid
            if (!$month || !in_array($month, $availableMonths)) {
                $month = !empty($availableMonths) ? $availableMonths[0] : Carbon::now()->subMonth()->format('F Y');
            }

            // Get available employees AFTER month is validated
            $availableEmployees = $this->getAvailableEmployees($month);

            // Default to first employee if none selected or if selected staffida is not in available employees
            $validStaffIds = array_column($availableEmployees, 'staffida');
            if ((!$staffida || !in_array($staffida, $validStaffIds)) && !empty($availableEmployees)) {
                $staffida = $availableEmployees[0]['staffida'];
            }

            // Fetch performance data
            $performanceData = $this->getPerformanceData($staffida, $month, $comparisonPeriod, $request);

            Log::info('Performance Data Sent to Frontend', [
                'staffida' => $staffida,
                'month' => $month,
                'comparison_period' => $comparisonPeriod,
                'performance_data' => $performanceData,
            ]);

            return Inertia::render('PerformanceTracker', [
                'performanceData' => $performanceData,
                'flash' => $this->getFlash($request),
            ]);
        } catch (\Exception $e) {
            Log::error("Performance Tracker: Failed to load data: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
            return Inertia::render('PerformanceTracker', [
                'performanceData' => $this->getEmptyPerformanceData(),
                'flash' => ['error' => 'Failed to load performance data: ' . $e->getMessage()],
            ]);
        }
    }

    private function getAvailableMonths(): array
    {
        $monthMap = [
            'january' => 'January', 'jan' => 'January',
            'february' => 'February', 'feb' => 'February',
            'march' => 'March', 'mar' => 'March',
            'april' => 'April', 'apr' => 'April',
            'may' => 'May',
            'june' => 'June', 'jun' => 'June',
            'july' => 'July', 'jul' => 'July',
            'august' => 'August', 'aug' => 'August',
            'september' => 'September', 'sept' => 'September', 'sep' => 'September',
            'october' => 'October', 'oct' => 'October',
            'november' => 'November', 'nov' => 'November',
            'december' => 'December', 'dec' => 'December'
        ];

        $monthIndexes = [
            'January' => 1, 'February' => 2, 'March' => 3, 'April' => 4,
            'May' => 5, 'June' => 6, 'July' => 7, 'August' => 8,
            'September' => 9, 'October' => 10, 'November' => 11, 'December' => 12
        ];

        try {
            $tables = DB::select("SHOW TABLES LIKE 'transsion%'");
            $available = [];
            foreach ($tables as $t) {
                $tableName = array_values((array)$t)[0];
                if (preg_match('/^transsion(?:tec)?([a-z]+)(\d{2})$/', $tableName, $matches)) {
                    $m = strtolower($matches[1]);
                    $y = $matches[2];
                    if (isset($monthMap[$m])) {
                        $fullMonthName = $monthMap[$m];
                        $fullMonth = $fullMonthName . ' 20' . $y;
                        $available[] = [
                            'month' => $fullMonth,
                            'sort' => '20' . $y . sprintf('%02d', $monthIndexes[$fullMonthName])
                        ];
                    }
                }
            }

            usort($available, fn($a, $b) => strcmp($b['sort'], $a['sort']));
            $months = array_values(array_unique(array_column($available, 'month')));

            if (!empty($months)) {
                return $months;
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch dynamic available months: " . $e->getMessage());
        }

        return [
            'September 2025', 'August 2025', 'July 2025', 'June 2025', 'May 2025', 'April 2025'
        ];
    }

    private function getAvailableEmployees(string $month): array
    {
        $table = $this->getPayrollTable($month);
        if (!$table || !Schema::hasTable($table)) {
            return [];
        }
        return TranssionPay::withTable($table)
            ->select('staffida', 'name')
            ->distinct()
            ->whereNotNull('staffida')
            ->where('staffida', '!=', '')
            ->get()
            ->map(function ($employee) {
                return [
                    'staffida' => (string) $employee->staffida,
                    'name' => $employee->name ?? 'Unknown',
                ];
            })->toArray();
    }

    private function getPayrollTable($month = null): ?string
    {
        $months = [
            'January' => ['january', 'jan'], 'February' => ['february', 'feb'],
            'March' => ['march', 'mar'], 'April' => ['april', 'apr'],
            'May' => ['may'], 'June' => ['june', 'jun'],
            'July' => ['july', 'jul'], 'August' => ['august', 'aug'],
            'September' => ['september', 'sept', 'sep'], 'October' => ['october', 'oct'],
            'November' => ['november', 'nov'], 'December' => ['december', 'dec']
        ];

        try {
            $date = $month ? Carbon::createFromFormat('F Y', $month) : Carbon::now();
        } catch (\Exception $e) {
            return null;
        }

        $monthName = $date->format('F');
        $yearShort = substr($date->format('Y'), -2);

        if (!isset($months[$monthName])) {
            return null;
        }

        foreach ($months[$monthName] as $mStr) {
            $candidates = [
                'transsion' . $mStr . $yearShort,
                'transsiontec' . $mStr . $yearShort,
            ];
            foreach ($candidates as $tbl) {
                if (Schema::hasTable($tbl)) {
                    return $tbl;
                }
            }
        }

        Log::warning("Payroll table for {$month} not found");
        return null;
    }

    private function getPerformanceData(string $staffida, string $month, int $comparisonPeriod, Request $request): array
    {
        $currentTable = $this->getPayrollTable($month);
        if (!$currentTable || !Schema::hasTable($currentTable)) {
            Log::warning("Payroll table not found for month: {$month}");
            return $this->getEmptyPerformanceData();
        }

        // Fetch employee data for selected month
        $employee = TranssionPay::withTable($currentTable)
            ->where('staffida', $staffida)
            ->first();

        if (!$employee) {
            Log::warning("Employee not found for staffida: {$staffida} in table: {$currentTable}");
            return $this->getEmptyPerformanceData();
        }

        // Calculate performstand if not provided
        $employee->performstand = $employee->performstand ?? (($employee->performbonus ?? 0) * ($employee->coefficient ?? 1));

        // Fallbacks for OT workdays & OT weekend column names
        $employeeOtWorkdays = floatval($employee->otworkdays ?? $employee->otworkingday ?? $employee->oteorkdays ?? 0);
        $employeeOtWeekend = floatval($employee->otweekend ?? $employee->otholiday ?? 0);

        // Fetch comparison data for previous months
        $comparisonData = [];
        $currentDate = Carbon::createFromFormat('F Y', $month);
        for ($i = 0; $i < $comparisonPeriod; $i++) {
            $compDate = $currentDate->copy()->subMonths($i);
            $compTable = $this->getPayrollTable($compDate->format('F Y'));
            if ($compTable && Schema::hasTable($compTable)) {
                $compData = TranssionPay::withTable($compTable)
                    ->where('staffida', $staffida)
                    ->first();
                if ($compData) {
                    $compPerformstand = $compData->performstand ?? (($compData->performbonus ?? 0) * ($compData->coefficient ?? 1));
                    $compOtWorkdays = floatval($compData->otworkdays ?? $compData->otworkingday ?? $compData->oteorkdays ?? 0);
                    $compOtWeekend = floatval($compData->otweekend ?? $compData->otholiday ?? 0);

                    $compEntry = [
                        'month' => $compDate->format('F Y'),
                        'performstand' => floatval($compPerformstand),
                        'performbonus' => floatval($compData->performbonus ?? 0),
                        'commission' => floatval($compData->commission ?? 0),
                        'workdays' => floatval($compData->workdays ?? 0),
                        'leavedays' => floatval($compData->leavedays ?? 0),
                        'netpay' => floatval($compData->netpay ?? 0),
                        'otworkdays' => $compOtWorkdays,
                        'otweekend' => $compOtWeekend,
                    ];

                    // Calculate percentage changes for non-current month
                    if ($compEntry['month'] !== $month) {
                        $compEntry['performstandChange'] = $employee->performstand && $compPerformstand
                            ? (($employee->performstand - $compPerformstand) / $compPerformstand) * 100
                            : null;
                        $compEntry['performbonusChange'] = $employee->performbonus && $compData->performbonus
                            ? (($employee->performbonus - $compData->performbonus) / $compData->performbonus) * 100
                            : null;
                        $compEntry['commissionChange'] = $employee->commission && $compData->commission
                            ? (($employee->commission - $compData->commission) / $compData->commission) * 100
                            : null;
                        $compEntry['workdaysChange'] = $employee->workdays && $compData->workdays
                            ? (($employee->workdays - $compData->workdays) / $compData->workdays) * 100
                            : null;
                        $compEntry['leavedaysChange'] = $employee->leavedays && $compData->leavedays
                            ? (($employee->leavedays - $compData->leavedays) / $compData->leavedays) * 100
                            : null;
                    }

                    $comparisonData[] = $compEntry;
                }
            }
        }

        // Sort comparison data (newest to oldest)
        $comparisonData = array_reverse($comparisonData);

        // Remove sensitive fields
        unset($employee->alcnum);
        unset($employee->bankname);
        unset($employee->ssnit);

        // Store report metadata
        $this->storeReportMetadata($staffida, $month, $request->user()?->id);

        return [
            'employee' => [
                'staffida' => $employee->staffida ?? '',
                'name' => $employee->name ?? 'Unknown',
                'department' => $employee->department ?? 'N/A',
                'position' => $employee->position ?? 'N/A',
                'grade' => $employee->grade ?? 'N/A',
                'workplace' => $employee->workplace ?? 'N/A',
                'performstand' => floatval($employee->performstand ?? 0),
                'performbonus' => floatval($employee->performbonus ?? 0),
                'coefficient' => floatval($employee->coefficient ?? 1),
                'commission' => floatval($employee->commission ?? 0),
                'workdays' => floatval($employee->workdays ?? 0),
                'leavedays' => floatval($employee->leavedays ?? 0),
                'netpay' => floatval($employee->netpay ?? 0),
                'leavededuction' => floatval($employee->leavededuction ?? 0),
                'otherdeduction' => floatval($employee->otherdeduction ?? 0),
                'basic' => floatval($employee->basic ?? 0),
                'allowance' => floatval($employee->allowance ?? 0),
                'otworkdays' => $employeeOtWorkdays,
                'otweekend' => $employeeOtWeekend,
            ],
            'comparisonData' => $comparisonData,
            'availableEmployees' => $this->getAvailableEmployees($month),
            'availableMonths' => $this->getAvailableMonths(),
        ];
    }

    private function validateSensitiveFields($employee, string $table): void
    {
        if (is_null($employee->netpay) || $employee->netpay < 0) {
            Log::warning("Invalid netpay for staffida: {$employee->staffida}", ['netpay' => $employee->netpay]);
        }
        if (Schema::hasColumn($table, 'alcnum') && empty($employee->alcnum)) {
            Log::warning("Missing alcnum for staffida: {$employee->staffida}");
        }
        if (Schema::hasColumn($table, 'ssnit') && empty($employee->ssnit)) {
            Log::warning("Missing ssnit for staffida: {$employee->staffida}");
        }
    }

    private function storeReportMetadata(string $staffida, string $month, ?int $userId): void
    {
        try {
            DB::table('performance_reports')->insert([
                'staffida' => $staffida,
                'month' => $month,
                'user_id' => $userId,
                'generated_at' => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to store report metadata: {$e->getMessage()}");
        }
    }

    private function getEmptyPerformanceData(): array
    {
        return [
            'employee' => [
                'staffida' => '',
                'name' => '',
                'department' => '',
                'position' => '',
                'grade' => '',
                'workplace' => '',
                'performstand' => 0,
                'performbonus' => 0,
                'coefficient' => 1,
                'commission' => 0,
                'workdays' => 0,
                'leavedays' => 0,
                'netpay' => 0,
                'leavededuction' => 0,
                'otherdeduction' => 0,
                'basic' => 0,
                'allowance' => 0,
                'otworkdays' => 0,
                'otweekend' => 0,
            ],
            'comparisonData' => [],
            'availableEmployees' => [],
            'availableMonths' => [],
        ];
    }

    private function getAuthUser(Request $request): array
    {
        return [
            'user' => $request->user() ? [
                'name' => $request->user()->name,
                'id' => $request->user()->id,
                'email' => $request->user()->email,
            ] : null,
        ];
    }

    private function getFlash(Request $request): array
    {
        return [
            'success' => $request->hasSession() ? $request->session()->get('success') : null,
            'error' => $request->hasSession() ? $request->session()->get('error') : null,
        ];
    }
}
