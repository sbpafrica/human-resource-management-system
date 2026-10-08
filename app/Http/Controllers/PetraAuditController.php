<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PetraAuditController extends Controller
{
    private array $clientMap = [
        'melcom'           => ['name' => 'Melcom',           'keywords' => ['melcom']],
        'melcom_drivers'   => ['name' => 'Melcom Drivers',   'keywords' => ['melcom driver', 'driver']],
        'century'          => ['name' => 'Century',          'keywords' => ['century']],
        'transsnet'        => ['name' => 'Transsnet',         'keywords' => ['transsnet']],
        'pz'               => ['name' => 'PZ',               'keywords' => ['pz']],
        'olam_tempering'   => ['name' => 'Olam Tempering',   'keywords' => ['tempering']],
        'olam_hygiene'     => ['name' => 'Olam Hygiene',     'keywords' => ['hygiene']],
        'olam_operations'  => ['name' => 'Olam Operations',  'keywords' => ['operations', 'olam op']],
        'olam_bwh'         => ['name' => 'Olam BWH',         'keywords' => ['bwh', 'olam bwh']],
        'twellium_kumasi'  => ['name' => 'Twellium Kumasi',  'keywords' => ['twellium kumasi', 'kumasi']],
        'twellium_accra'   => ['name' => 'Twellium Accra',   'keywords' => ['twellium accra', 'accra']],
        'transsion'        => ['name' => 'Transsion',        'keywords' => ['transsion']],
        'tecno'            => ['name' => 'Tecno',            'keywords' => ['tecno']],
        'oppo'             => ['name' => 'Oppo',             'keywords' => ['oppo']],
        'shefer'           => ['name' => 'Shefer',           'keywords' => ['shefer']],
        'inhouse'          => ['name' => 'Inhouse',          'keywords' => ['inhouse', 'sbp']],
        'cloudview'        => ['name' => 'Cloudview',        'keywords' => ['cloudview']],
        'hygiene_external' => ['name' => 'Hygiene External', 'keywords' => ['hygiene external', 'external']],
    ];

    public function index(Request $request)
    {
        $selectedClient = $request->input('client', 'all');
        $search         = trim($request->input('search', ''));

        // Fetch staff IDs that exist in petraorg
        $validPetraIds = DB::table('petraorg')
            ->pluck('staffid')
            ->toArray();

        $query = DB::table('newemployeedata')
            ->select(
                'staffid as staff_id',
                'employeename as name',
                DB::raw("COALESCE(NULLIF(shop, ''), NULLIF(company, ''), 'N/A') as company_shop"),
                'company'
            )
            ->whereNotIn('staffid', $validPetraIds);

        // Apply Client Filter
        if ($selectedClient !== 'all' && isset($this->clientMap[$selectedClient])) {
            $keywords = $this->clientMap[$selectedClient]['keywords'];
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('company', 'like', "%{$kw}%")
                      ->orWhere('shop', 'like', "%{$kw}%");
                }
            });
        }

        // Apply Search Filter
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('staffid', 'like', "%{$search}%")
                  ->orWhere('employeename', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%")
                  ->orWhere('shop', 'like', "%{$search}%");
            });
        }

        $query->orderBy('employeename', 'asc');

        // Total count before pagination
        $totalRecords = $query->count();

        // Paginate results (25 per page)
        $perPage = (int) $request->input('per_page', 25);
        $records = $query->paginate($perPage)->withQueryString();

        // Calculate summary breakdown per client for PDF report generation
        $clientSummary = $this->getSummaryBreakdown($validPetraIds);

        return Inertia::render('HrAudit/PetraAudit', [
            'records' => $records,
            'filters' => [
                'client' => $selectedClient,
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
        $search         = trim($request->input('search', ''));

        $validPetraIds = DB::table('petraorg')
            ->pluck('staffid')
            ->toArray();

        $query = DB::table('newemployeedata')
            ->select(
                'staffid as staff_id',
                'employeename as name',
                DB::raw("COALESCE(NULLIF(shop, ''), NULLIF(company, ''), 'N/A') as company_shop"),
                'company'
            )
            ->whereNotIn('staffid', $validPetraIds);

        if ($selectedClient !== 'all' && isset($this->clientMap[$selectedClient])) {
            $keywords = $this->clientMap[$selectedClient]['keywords'];
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('company', 'like', "%{$kw}%")
                      ->orWhere('shop', 'like', "%{$kw}%");
                }
            });
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('staffid', 'like', "%{$search}%")
                  ->orWhere('employeename', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%")
                  ->orWhere('shop', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('employeename', 'asc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('PETRA Audit Report');

        $headers = ['No.', 'Staff ID', 'Employee Name', 'Company / Shop'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3E64D4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);

        $rowNum = 2;
        foreach ($data as $index => $row) {
            $sheet->setCellValue("A{$rowNum}", $index + 1);
            $sheet->setCellValue("B{$rowNum}", $row->staff_id);
            $sheet->setCellValue("C{$rowNum}", $row->name);
            $sheet->setCellValue("D{$rowNum}", $row->company_shop);
            $rowNum++;
        }

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = "PETRA_Audit_Report_" . date('Y-m-d') . ".xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function getSummaryBreakdown(array $validPetraIds): array
    {
        $summary = [];
        foreach ($this->clientMap as $key => $info) {
            $keywords = $info['keywords'];
            $count = DB::table('newemployeedata')
                ->whereNotIn('staffid', $validPetraIds)
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $q->orWhere('company', 'like', "%{$kw}%")
                          ->orWhere('shop', 'like', "%{$kw}%");
                    }
                })
                ->count();

            $summary[] = [
                'client_key'  => $key,
                'client_name' => $info['name'],
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
        foreach ($this->clientMap as $key => $info) {
            $options[] = ['value' => $key, 'label' => $info['name']];
        }
        return $options;
    }
}
