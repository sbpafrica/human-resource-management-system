<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Models\Avarapay;

class AvarassnitController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month     = $request->input('month', now()->format('Y-m'));
        $date      = Carbon::createFromFormat('Y-m', $month);
        $tableName = Avarapay::tableName($month);

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('Avarassnit/Index', [
                'records'        => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedMonth'  => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords'   => 0,
                'searchCount'    => null,
                'flash'          => ['error' => "No data available for {$date->format('F Y')}."],
            ]);
        }

        $query = DB::table("{$tableName} as tp")
            ->leftJoin('ssnit as s', 'tp.STAFFID', '=', 's.staffid')
            ->select(
                'tp.STAFFID as staff_id',
                DB::raw('COALESCE(s.shop,    "Avara")  as shop'),
                DB::raw('COALESCE(s.lastname,  tp.NAME) as lastname'),
                DB::raw('COALESCE(s.firstname, "")      as firstname'),
                DB::raw('COALESCE(s.othername, "")      as othername'),
                DB::raw('COALESCE(s.ssnit1a,   "")      as ssnit_number'),
                DB::raw('COALESCE(s.national,  tp.GH_CARD, "") as gh_card_number'),
                'tp.BASIC_SALARY_EARNED_GHC as basic_salary',
                DB::raw('ROUND(tp.BASIC_SALARY_EARNED_GHC * 0.135, 2) as ssnit_amount')
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.STAFFID',   'like', "%{$search}%")
                  ->orWhere('s.lastname',  'like', "%{$search}%")
                  ->orWhere('s.firstname', 'like', "%{$search}%")
                  ->orWhere('s.othername', 'like', "%{$search}%")
                  ->orWhere('s.ssnit1a',   'like', "%{$search}%")
                  ->orWhere('s.national',  'like', "%{$search}%")
                  ->orWhere('tp.NAME',     'like', "%{$search}%");
            });
        }

        $sort  = $request->input('sort', 'staff_id');
        $order = $request->input('order', 'asc');

        $sortColumn = match($sort) {
            'staff_id'       => 'tp.STAFFID',
            'shop'           => 's.shop',
            'lastname'       => 's.lastname',
            'firstname'      => 's.firstname',
            'othername'      => 's.othername',
            'ssnit_number'   => 's.ssnit1a',
            'gh_card_number' => 's.national',
            'basic_salary'   => 'tp.BASIC_SALARY_EARNED_GHC',
            'ssnit_amount'   => DB::raw('ROUND(tp.BASIC_SALARY_EARNED_GHC * 0.135, 2)'),
            default          => 'tp.STAFFID',
        };

        $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

        $records      = $query->paginate(10)->appends($request->query());
        $totalRecords = DB::table($tableName)->count();
        $searchCount  = $search ? $query->getCountForPagination() : null;

        return Inertia::render('Avarassnit/Index', [
            'records'        => $records,
            'selectedMonth'  => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords'   => $totalRecords,
            'searchCount'    => $searchCount,
            'flash'          => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function export(Request $request)
    {
        try {
            $request->validate([
                'month' => ['required', 'date_format:Y-m', 'after_or_equal:2020-01'],
            ]);

            $month     = $request->input('month');
            $date      = Carbon::createFromFormat('Y-m', $month);
            $tableName = Avarapay::tableName($month);

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
            }

            $query = DB::table("{$tableName} as tp")
                ->leftJoin('ssnit as s', 'tp.STAFFID', '=', 's.staffid')
                ->select(
                    'tp.STAFFID as staff_id',
                    DB::raw('COALESCE(s.shop,    "Avara")  as shop'),
                    DB::raw('COALESCE(s.lastname,  tp.NAME) as lastname'),
                    DB::raw('COALESCE(s.firstname, "")      as firstname'),
                    DB::raw('COALESCE(s.othername, "")      as othername'),
                    DB::raw('COALESCE(s.ssnit1a,   "")      as ssnit_number'),
                    DB::raw('COALESCE(s.national,  tp.GH_CARD, "") as gh_card_number'),
                    'tp.BASIC_SALARY_EARNED_GHC as basic_salary',
                    DB::raw('ROUND(tp.BASIC_SALARY_EARNED_GHC * 0.135, 2) as ssnit_amount')
                );

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.STAFFID',   'like', "%{$search}%")
                      ->orWhere('s.lastname',  'like', "%{$search}%")
                      ->orWhere('s.firstname', 'like', "%{$search}%")
                      ->orWhere('s.othername', 'like', "%{$search}%")
                      ->orWhere('s.ssnit1a',   'like', "%{$search}%")
                      ->orWhere('s.national',  'like', "%{$search}%")
                      ->orWhere('tp.NAME',     'like', "%{$search}%");
                });
            }

            $sort  = $request->input('sort', 'staff_id');
            $order = $request->input('order', 'asc');

            $sortColumn = match($sort) {
                'staff_id'       => 'tp.STAFFID',
                'shop'           => 's.shop',
                'lastname'       => 's.lastname',
                'firstname'      => 's.firstname',
                'othername'      => 's.othername',
                'ssnit_number'   => 's.ssnit1a',
                'gh_card_number' => 's.national',
                'basic_salary'   => 'tp.BASIC_SALARY_EARNED_GHC',
                'ssnit_amount'   => DB::raw('ROUND(tp.BASIC_SALARY_EARNED_GHC * 0.135, 2)'),
                default          => 'tp.STAFFID',
            };

            $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

            $records = $query->get();

            // ── Build Excel ──────────────────────────────────────────
            $spreadsheet = new Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Avara SSNIT');

            $headers = [
                'Staff ID', 'Shop', 'Last Name', 'First Name', 'Other Name',
                'SSNIT Number', 'GH Card Number', 'Basic Salary Earned', 'SSNIT Amount (13.5%)',
            ];
            $sheet->fromArray($headers, null, 'A1');

            $sheet->getStyle('A1:I1')->getFont()->setBold(true)->setSize(11)->setName('Calibri');
            $sheet->getStyle('A1:I1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('BBDEFE');

            $row = 2;
            foreach ($records as $r) {
                $sheet->setCellValue("A{$row}", $r->staff_id);
                $sheet->setCellValue("B{$row}", $r->shop);
                $sheet->setCellValue("C{$row}", $r->lastname);
                $sheet->setCellValue("D{$row}", $r->firstname);
                $sheet->setCellValue("E{$row}", $r->othername);
                $sheet->setCellValue("F{$row}", $r->ssnit_number);
                $sheet->setCellValue("G{$row}", $r->gh_card_number);
                $sheet->setCellValue("H{$row}", $r->basic_salary  !== null ? (float)$r->basic_salary  : '');
                $sheet->setCellValue("I{$row}", $r->ssnit_amount  !== null ? (float)$r->ssnit_amount  : '');
                $row++;
            }

            // Totals row
            $totalBasic = $records->sum(fn($r) => (float)($r->basic_salary ?? 0));
            $totalSsnit = $records->sum(fn($r) => (float)($r->ssnit_amount ?? 0));
            $sheet->setCellValue("G{$row}", 'TOTAL');
            $sheet->setCellValue("H{$row}", number_format($totalBasic, 2, '.', ''));
            $sheet->setCellValue("I{$row}", number_format($totalSsnit, 2, '.', ''));
            $sheet->getStyle("G{$row}:I{$row}")->getFont()->setBold(true);

            foreach (range('A', 'I') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer   = new Xlsx($spreadsheet);
            $filename = "avara_ssnit_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);

        } catch (\Exception $e) {
            Log::error('AvaraSSNIT export failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }
}
