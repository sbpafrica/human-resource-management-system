<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use DateTime;
use DateInterval;
use DatePeriod;

class NewJoinersController extends Controller
{
    private array $clientTableMap = [
        'melcom' => 'melcom',
        'melcom_drivers' => 'driverssnit',
        'century' => 'centurypay',
        'transsnet' => 'transsnet',
        'pz' => 'pz',
        'olam_tempering' => 'olamtem',
        'olam_hygiene' => 'olamhygien',
        'olam_operations' => 'offload',
        'olam_bwh' => 'olambwh',
        'twellium_kumasi' => 'twellium',
        'twellium_accra' => 'twelliumaccra',
        'transsion' => 'transsion',
        'tecno' => 'transsiontec',
        'oppo' => 'oppo',
        'shefer' => 'shefer',
        'inhouse' => 'inhouse',
        'cloudview' => 'cloudview',
        'hygiene_external' => 'hygieneexternal'
    ];

    private array $clientColumnMap = [
        'melcom' => ['id' => 'employ', 'name' => 'empoyeename', 'shop' => 'shopname', 'start_date' => 'joindate'],
        'melcom_drivers' => ['id' => 'employ', 'name' => 'name', 'shop' => 'dept', 'start_date' => 'joindate'],
        'century' => ['id' => 'employ', 'name' => 'empoyeename', 'shop' => 'outsourced', 'start_date' => 'joindate'],
        'transsnet' => ['id' => 'staffid', 'name' => 'name', 'shop' => 'department'],
        'pz' => ['id' => 'staffid', 'name' => 'name', 'shop' => 'dept'],
        'olam_tempering' => ['id' => 'id', 'name' => 'names', 'shop' => 'dept', 'days_worked' => 'noofdays'],
        'olam_hygiene' => ['id' => 'id', 'name' => 'names', 'shop' => 'dept', 'days_worked' => 'noofdays'],
        'olam_operations' => ['id' => 'StaffID', 'name' => 'employeename', 'shop' => 'workarea'],
        'olam_bwh' => ['id' => 'id', 'name' => 'name', 'shop' => 'basic', 'days_worked' => 'workdays'],
        'twellium_kumasi' => ['id' => 'employ', 'name' => 'name', 'shop' => 'position', 'start_date' => 'dateemploy'],
        'twellium_accra' => ['id' => 'employ', 'name' => 'name', 'shop' => 'position', 'days_worked' => 'workdays'],
        'transsion' => ['id' => 'staffida', 'name' => 'name', 'shop' => 'department', 'start_date' => 'hiredate'],
        'tecno' => ['id' => 'staffida', 'name' => 'name', 'shop' => 'department', 'start_date' => 'hiredate'],
        'oppo' => ['id' => 'StaffID', 'name' => 'name', 'shop' => 'Department'],
        'shefer' => ['id' => 'staff_id', 'name' => 'name1', 'shop' => 'department', 'start_date' => 'startdate'],
        'inhouse' => ['id' => 'id', 'name' => 'name', 'shop' => 'position'],
        'cloudview' => ['id' => 'Staff_ID', 'name' => 'Name', 'shop' => 'Department1'],
        'hygiene_external' => ['id' => 'staffid', 'name' => 'employeename', 'shop' => 'workarea']
    ];

    private array $clientNames = [
        'melcom'           => 'Melcom',
        'melcom_drivers'   => 'Melcom Drivers',
        'century'          => 'Century',
        'transsnet'        => 'Transsnet',
        'pz'               => 'PZ',
        'olam_tempering'   => 'Olam Tempering',
        'olam_hygiene'     => 'Olam Hygiene',
        'olam_operations'  => 'Olam Operations',
        'olam_bwh'         => 'Olam BWH',
        'twellium_kumasi'  => 'Twellium Kumasi',
        'twellium_accra'   => 'Twellium Accra',
        'transsion'        => 'Transsion',
        'tecno'            => 'Tecno',
        'oppo'             => 'Oppo',
        'shefer'           => 'Shefer',
        'inhouse'          => 'Inhouse',
        'cloudview'        => 'Cloudview',
        'hygiene_external' => 'Hygiene External',
    ];

    public function index(Request $request)
    {
        $selectedClient = $request->input('client', 'all');
        $monthRange     = $request->input('month_range', '');
        $search         = trim($request->input('search', ''));
        $perPage        = (int) $request->input('per_page', 25);

        $newJoiners = [];

        if (!empty($monthRange)) {
            $newJoiners = $this->getNewJoinersData($selectedClient, $monthRange);
        }

        // Apply Search Filter
        if (!empty($search)) {
            $newJoiners = array_filter($newJoiners, function ($row) use ($search) {
                $searchLower = strtolower($search);
                return str_contains(strtolower($row['staff_id']), $searchLower)
                    || str_contains(strtolower($row['name']), $searchLower)
                    || str_contains(strtolower($row['company_shop']), $searchLower);
            });
            $newJoiners = array_values($newJoiners);
        }

        $totalRecords = count($newJoiners);
        
        $clientSummary = $this->getSummaryBreakdown($newJoiners);

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = array_slice($newJoiners, ($currentPage - 1) * $perPage, $perPage);
        $paginatedRecords = new LengthAwarePaginator($currentItems, $totalRecords, $perPage, $currentPage, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => $request->query()
        ]);

        return Inertia::render('HrAudit/NewJoiners', [
            'records' => $paginatedRecords,
            'filters' => [
                'client' => $selectedClient,
                'month_range' => $monthRange,
                'search' => $search,
            ],
            'clients' => $this->getClientDropdownOptions(),
            'summary' => $clientSummary,
            'totalRecords' => $totalRecords,
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function exportExcel(Request $request)
    {
        $selectedClient = $request->input('client', 'all');
        $monthRange     = $request->input('month_range', '');
        $search         = trim($request->input('search', ''));

        if (empty($monthRange)) {
            return redirect()->back()->with('error', 'Month range is required for export.');
        }

        $newJoiners = $this->getNewJoinersData($selectedClient, $monthRange);

        if (!empty($search)) {
            $newJoiners = array_filter($newJoiners, function ($row) use ($search) {
                $searchLower = strtolower($search);
                return str_contains(strtolower($row['staff_id']), $searchLower)
                    || str_contains(strtolower($row['name']), $searchLower)
                    || str_contains(strtolower($row['company_shop']), $searchLower);
            });
            $newJoiners = array_values($newJoiners);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('New Joiners Report');

        $headers = ['No.', 'Staff ID', 'Employee Name', 'Company / Shop', 'Start Date'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3E64D4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

        $rowNum = 2;
        foreach ($newJoiners as $index => $row) {
            $sheet->setCellValue("A{$rowNum}", $index + 1);
            $sheet->setCellValue("B{$rowNum}", $row['staff_id']);
            $sheet->setCellValue("C{$rowNum}", $row['name']);
            $sheet->setCellValue("D{$rowNum}", $row['company_shop']);
            $sheet->setCellValue("E{$rowNum}", $row['start_date']);
            $rowNum++;
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $safeRange = str_replace([' ', '/', '-'], '_', $monthRange);
        $fileName = "New_Joiners_{$safeRange}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function getNewJoinersData($client, $monthRange): array
    {
        $months = array_map('trim', explode('-', str_replace(' - ', '-', $monthRange)));
        $startMonth = $months[0];
        $endMonth = $months[1] ?? $startMonth;

        $startDate = DateTime::createFromFormat('m/Y', $startMonth);
        $endDate = DateTime::createFromFormat('m/Y', $endMonth);

        if (!$startDate || !$endDate) {
            return [];
        }

        $newJoiners = [];
        $currentDate = clone $startDate;
        
        while ($currentDate <= $endDate) {
            if ($client !== 'all') {
                $monthData = $this->getPayrollData($client, $currentDate, true);
                if (!isset($monthData['error'])) {
                    foreach ($monthData as &$data) {
                        $data['client_key'] = $client;
                        $data['client_name'] = $this->clientNames[$client] ?? $client;
                    }
                    $newJoiners = array_merge($newJoiners, $monthData);
                }
            } else {
                foreach (array_keys($this->clientTableMap) as $clientKey) {
                    $monthData = $this->getPayrollData($clientKey, $currentDate, true);
                    if (!isset($monthData['error'])) {
                        foreach ($monthData as &$data) {
                            $data['client_key'] = $clientKey;
                            $data['client_name'] = $this->clientNames[$clientKey] ?? $clientKey;
                        }
                        $newJoiners = array_merge($newJoiners, $monthData);
                    }
                }
            }
            $currentDate->modify('+1 month');
        }

        // Remove duplicates based on staff_id
        $uniqueJoiners = [];
        $seen = [];
        foreach ($newJoiners as $row) {
            if (!in_array($row['staff_id'], $seen)) {
                $seen[] = $row['staff_id'];
                $uniqueJoiners[] = $row;
            }
        }
        
        usort($uniqueJoiners, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $uniqueJoiners;
    }

    private function getPayrollData($client, $month, $isPrevious = false)
    {
        if (!isset($this->clientTableMap[$client])) return [];

        $tablePrefix = $this->clientTableMap[$client];
        $columns = $this->clientColumnMap[$client];
        $idCol = $columns['id'];
        $nameCol = $columns['name'];
        $shopCol = $columns['shop'];
        $startDateCol = $columns['start_date'] ?? null;
        $daysWorkedCol = $columns['days_worked'] ?? null;

        $monthStr = strtolower($month->format('F')) . $month->format('y');
        $prevMonth = (clone $month)->modify('-1 month');
        $prevMonthStr = strtolower($prevMonth->format('F')) . $prevMonth->format('y');

        $currentTable = $tablePrefix . $monthStr;
        $prevTable = $tablePrefix . $prevMonthStr;

        $tableExists = collect(DB::select("SHOW TABLES LIKE ?", [$currentTable]))->isNotEmpty();

        if (!$tableExists) {
            $currentTable = $prevTable;
            $prevMonthStr = strtolower($prevMonth->modify('-1 month')->format('F')) . $prevMonth->format('y');
            $prevTable = $tablePrefix . $prevMonthStr;

            $tableExists = collect(DB::select("SHOW TABLES LIKE ?", [$currentTable]))->isNotEmpty();
            if (!$tableExists) return [];
        }

        $hasData = DB::table($currentTable)->count() > 0;
        if (!$hasData) return [];

        $query = DB::table($currentTable)
            ->select(
                "$idCol as staff_id", 
                "$nameCol as name", 
                "$shopCol as company_shop"
            );
            
        if ($startDateCol) $query->addSelect("$startDateCol as start_date_raw");
        if ($daysWorkedCol) $query->addSelect("$daysWorkedCol as days_worked_raw");

        if ($isPrevious) {
            $prevCheck = collect(DB::select("SHOW TABLES LIKE ?", [$prevTable]))->isNotEmpty();
            if ($prevCheck) {
                // We must use a whereNotIn subquery instead of a string to be safe
                // but doing subquery with Query Builder for dynamic tables can be tricky.
                // Raw whereNotIn is easiest:
                $query->whereNotIn($idCol, function ($q) use ($prevTable, $idCol) {
                    $q->select($idCol)->from($prevTable);
                });
            }
        }

        $results = $query->get()->toArray();
        $data = [];

        foreach ($results as $row) {
            $rowArray = (array)$row;
            
            $startDate = 'N/A';
            if (isset($rowArray['start_date_raw']) && !empty($rowArray['start_date_raw'])) {
                $startDate = $rowArray['start_date_raw'];
            } elseif (isset($rowArray['days_worked_raw']) && is_numeric($rowArray['days_worked_raw'])) {
                $startDate = $this->calculateStartDateFromDaysWorked(
                    (int)$rowArray['days_worked_raw'],
                    $month->format('m'),
                    $month->format('Y')
                );
            } else {
                $startDate = $this->inferStartDate($month->format('m'), $month->format('Y'));
            }

            $data[] = [
                'staff_id' => $rowArray['staff_id'] ?? 'N/A',
                'name' => $rowArray['name'] ?? 'N/A',
                'company_shop' => $rowArray['company_shop'] ?? 'N/A',
                'start_date' => $startDate
            ];
        }

        return $data;
    }

    private function calculateStartDateFromDaysWorked($daysWorked, $month, $year) {
        $endDate = new DateTime("$year-$month-20");
        $endDate->modify('+1 month'); 
        $startDate = new DateTime("$year-$month-21");

        $interval = new DateInterval('P1D');
        $period = new DatePeriod($startDate, $interval, $endDate);
        $weekdays = [];
        
        foreach ($period as $date) {
            if ($date->format('N') < 6) {
                $weekdays[] = clone $date;
            }
        }

        usort($weekdays, fn($a, $b) => $b <=> $a);

        if ($daysWorked >= count($weekdays)) {
            return $weekdays[count($weekdays) - 1]->format('Y-m-d');
        }

        $startIndex = $daysWorked - 1;
        if (isset($weekdays[$startIndex])) {
            return $weekdays[$startIndex]->format('Y-m-d');
        }

        return $startDate->format('Y-m-d');
    }

    private function inferStartDate($month, $year) {
        $start = new DateTime("$year-$month-21");
        while ($start->format('N') >= 6) {
            $start->modify('+1 day');
        }
        return $start->format('Y-m-d');
    }

    private function getSummaryBreakdown(array $newJoiners): array
    {
        $summary = [];
        $counts = [];

        foreach ($newJoiners as $row) {
            $clientKey = $row['client_key'] ?? 'unknown';
            if (!isset($counts[$clientKey])) {
                $counts[$clientKey] = 0;
            }
            $counts[$clientKey]++;
        }

        foreach ($counts as $key => $count) {
            $summary[] = [
                'client_key'  => $key,
                'client_name' => $this->clientNames[$key] ?? ucfirst($key),
                'count'       => $count,
            ];
        }

        usort($summary, fn($a, $b) => $b['count'] <=> $a['count']);
        return $summary;
    }

    private function getClientDropdownOptions(): array
    {
        $options = [
            ['value' => 'all', 'label' => 'All']
        ];
        foreach ($this->clientNames as $key => $name) {
            $options[] = ['value' => $key, 'label' => $name];
        }
        return $options;
    }
}
