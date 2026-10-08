<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use DateTime;

class ExitedStaffController extends Controller
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
        'melcom' => ['id' => 'employ', 'name' => 'empoyeename', 'shop' => 'shopname'],
        'melcom_drivers' => ['id' => 'employ', 'name' => 'name', 'shop' => 'dept'],
        'century' => ['id' => 'employ', 'name' => 'empoyeename', 'shop' => 'outsourced'],
        'transsnet' => ['id' => 'staffid', 'name' => 'name', 'shop' => 'department'],
        'pz' => ['id' => 'staffid', 'name' => 'name', 'shop' => 'dept'],
        'olam_tempering' => ['id' => 'id', 'name' => 'names', 'shop' => 'dept'],
        'olam_hygiene' => ['id' => 'id', 'name' => 'names', 'shop' => 'dept'],
        'olam_operations' => ['id' => 'StaffID', 'name' => 'employeename', 'shop' => 'workarea'],
        'olam_bwh' => ['id' => 'id', 'name' => 'name', 'shop' => 'basic'],
        'twellium_kumasi' => ['id' => 'employ', 'name' => 'name', 'shop' => 'position'],
        'twellium_accra' => ['id' => 'employ', 'name' => 'name', 'shop' => 'position'],
        'transsion' => ['id' => 'staffida', 'name' => 'name', 'shop' => 'department'],
        'tecno' => ['id' => 'staffida', 'name' => 'name', 'shop' => 'department'],
        'oppo' => ['id' => 'StaffID', 'name' => 'name', 'shop' => 'Department'],
        'shefer' => ['id' => 'staff_id', 'name' => 'name1', 'shop' => 'department'],
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

        $exitedStaff = [];

        if (!empty($monthRange)) {
            $exitedStaff = $this->getExitedStaffData($selectedClient, $monthRange);
        }

        // Apply Search Filter
        if (!empty($search)) {
            $exitedStaff = array_filter($exitedStaff, function ($row) use ($search) {
                $searchLower = strtolower($search);
                return str_contains(strtolower($row['staff_id']), $searchLower)
                    || str_contains(strtolower($row['name']), $searchLower)
                    || str_contains(strtolower($row['company_shop']), $searchLower);
            });
            $exitedStaff = array_values($exitedStaff);
        }

        $totalRecords = count($exitedStaff);
        $clientSummary = $this->getSummaryBreakdown($exitedStaff);

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = array_slice($exitedStaff, ($currentPage - 1) * $perPage, $perPage);
        $paginatedRecords = new LengthAwarePaginator($currentItems, $totalRecords, $perPage, $currentPage, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => $request->query()
        ]);

        return Inertia::render('HrAudit/ExitedStaff', [
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

        $exitedStaff = $this->getExitedStaffData($selectedClient, $monthRange);

        if (!empty($search)) {
            $exitedStaff = array_filter($exitedStaff, function ($row) use ($search) {
                $searchLower = strtolower($search);
                return str_contains(strtolower($row['staff_id']), $searchLower)
                    || str_contains(strtolower($row['name']), $searchLower)
                    || str_contains(strtolower($row['company_shop']), $searchLower);
            });
            $exitedStaff = array_values($exitedStaff);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Exited Staff Report');

        $headers = ['No.', 'Staff ID', 'Employee Name', 'Company / Shop'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3E64D4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);

        $rowNum = 2;
        foreach ($exitedStaff as $index => $row) {
            $sheet->setCellValue("A{$rowNum}", $index + 1);
            $sheet->setCellValue("B{$rowNum}", $row['staff_id']);
            $sheet->setCellValue("C{$rowNum}", $row['name']);
            $sheet->setCellValue("D{$rowNum}", $row['company_shop']);
            $rowNum++;
        }

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $safeRange = str_replace([' ', '/', '-'], '_', $monthRange);
        $fileName = "Exited_Staff_{$safeRange}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function getExitedStaffData($client, $monthRange): array
    {
        $months = array_map('trim', explode('-', str_replace(' - ', '-', $monthRange)));
        $startMonth = $months[0];
        $endMonth = $months[1] ?? $startMonth;

        $startDate = DateTime::createFromFormat('m/Y', $startMonth);
        $endDate = DateTime::createFromFormat('m/Y', $endMonth);

        if (!$startDate || !$endDate) {
            return [];
        }

        $exitedStaff = [];
        $currentDate = clone $startDate;
        
        while ($currentDate <= $endDate) {
            if ($client !== 'all') {
                $monthData = $this->getPayrollData($client, $currentDate);
                if (!isset($monthData['error'])) {
                    foreach ($monthData as &$data) {
                        $data['client_key'] = $client;
                        $data['client_name'] = $this->clientNames[$client] ?? $client;
                    }
                    $exitedStaff = array_merge($exitedStaff, $monthData);
                }
            } else {
                foreach (array_keys($this->clientTableMap) as $clientKey) {
                    $monthData = $this->getPayrollData($clientKey, $currentDate);
                    if (!isset($monthData['error'])) {
                        foreach ($monthData as &$data) {
                            $data['client_key'] = $clientKey;
                            $data['client_name'] = $this->clientNames[$clientKey] ?? $clientKey;
                        }
                        $exitedStaff = array_merge($exitedStaff, $monthData);
                    }
                }
            }
            $currentDate->modify('+1 month');
        }

        // Remove duplicates based on staff_id
        $uniqueExited = [];
        $seen = [];
        foreach ($exitedStaff as $row) {
            if (!in_array($row['staff_id'], $seen)) {
                $seen[] = $row['staff_id'];
                $uniqueExited[] = $row;
            }
        }
        
        usort($uniqueExited, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $uniqueExited;
    }

    private function getPayrollData($client, $month)
    {
        if (!isset($this->clientTableMap[$client])) return [];

        $tablePrefix = $this->clientTableMap[$client];
        $columns = $this->clientColumnMap[$client];
        $idCol = $columns['id'];
        $nameCol = $columns['name'];
        $shopCol = $columns['shop'];

        $monthStr = strtolower($month->format('F')) . $month->format('y');
        $prevMonth = (clone $month)->modify('-1 month');
        $prevMonthStr = strtolower($prevMonth->format('F')) . $prevMonth->format('y');

        $currentTable = $tablePrefix . $monthStr;
        $prevTable = $tablePrefix . $prevMonthStr;

        $prevExists = collect(DB::select("SHOW TABLES LIKE ?", [$prevTable]))->isNotEmpty();
        if (!$prevExists) {
            return []; // No previous data, no exits possible
        }

        $currentExists = collect(DB::select("SHOW TABLES LIKE ?", [$currentTable]))->isNotEmpty();

        $query = DB::table($prevTable)
            ->select(
                "$idCol as staff_id", 
                "$nameCol as name", 
                "$shopCol as company_shop"
            );

        if ($currentExists) {
            // Exclude anyone who is in the current table (they didn't exit)
            $query->whereNotIn($idCol, function ($q) use ($currentTable, $idCol) {
                $q->select($idCol)->from($currentTable);
            });
        }

        $results = $query->get()->toArray();
        $data = [];

        foreach ($results as $row) {
            $rowArray = (array)$row;
            $data[] = [
                'staff_id' => $rowArray['staff_id'] ?? 'N/A',
                'name' => $rowArray['name'] ?? 'N/A',
                'company_shop' => $rowArray['company_shop'] ?? 'N/A',
            ];
        }

        return $data;
    }

    private function getSummaryBreakdown(array $exitedStaff): array
    {
        $summary = [];
        $counts = [];

        foreach ($exitedStaff as $row) {
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
