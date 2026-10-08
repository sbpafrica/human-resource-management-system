<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class TransbfaStatController extends Controller
{
    /**
     * Build the monthly table name.
     * E.g. April 2026 → transburkinaapril26
     */
    private function buildTableName(string $month): string
    {
        $date = Carbon::createFromFormat('Y-m', $month);
        return 'transburkina' . strtolower($date->format('F')) . $date->format('y');
    }

    // ──────────────────────────────────────────────────────────────
    // IUTS-TPA Report
    // ──────────────────────────────────────────────────────────────

    public function iutsTpa(Request $request)
    {
        $month  = $request->input('month', now()->format('Y-m'));
        $search = $request->input('search', '');
        $date   = Carbon::createFromFormat('Y-m', $month);
        $tableName = $this->buildTableName($month);

        if (!Schema::hasTable($tableName)) {
            return Inertia::render('TranssionBfa/IutsTpa/Index', [
                'records'        => [],
                'selectedMonth'  => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords'   => 0,
                'search'         => $search,
                'flash'          => ['error' => "No data available for {$date->format('F Y')}. Please upload data first."],
            ]);
        }

        $query = DB::table($tableName)
            ->select('staffid', 'firstname', 'lastname', 'fullname', 'gross', 'iutsimpos', 'iutsnet', 'enfant');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('fullname',  'like', "%{$search}%")
                  ->orWhere('staffid',  'like', "%{$search}%")
                  ->orWhere('firstname','like', "%{$search}%")
                  ->orWhere('lastname', 'like', "%{$search}%");
            });
        }

        $records = $query->orderBy('fullname')->get();

        return Inertia::render('TranssionBfa/IutsTpa/Index', [
            'records'        => $records,
            'selectedMonth'  => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords'   => $records->count(),
            'search'         => $search,
            'flash'          => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // DN-13 / CNSS Report
    // ──────────────────────────────────────────────────────────────

    public function dn13(Request $request)
    {
        $month  = $request->input('month', now()->format('Y-m'));
        $search = $request->input('search', '');
        $date   = Carbon::createFromFormat('Y-m', $month);
        $tableName = $this->buildTableName($month);

        if (!Schema::hasTable($tableName)) {
            return Inertia::render('TranssionBfa/Dn13/Index', [
                'withSsnit'        => [],
                'withoutSsnit'     => [],
                'selectedMonth'    => $month,
                'formattedMonth'   => $date->format('F Y'),
                'totalWithSsnit'   => 0,
                'totalWithoutSsnit'=> 0,
                'search'           => $search,
                'flash'            => ['error' => "No data available for {$date->format('F Y')}. Please upload data first."],
            ]);
        }

        // Records WITH SSNIT (joined to bfassnit)
        $withQuery = DB::table("{$tableName} as t")
            ->join('bfassnit as b', 'b.staffid1a', '=', 't.staffid')
            ->select(
                't.staffid',
                't.gross',
                't.fullname',
                't.firstname',
                't.lastname',
                'b.noempl',
                'b.matricule',
                'b.travail',
                'b.datenaiss',
                'b.nom',
                'b.nomepoux',
                'b.prenom'
            );

        if ($search) {
            $withQuery->where(function ($q) use ($search) {
                $q->where('t.fullname', 'like', "%{$search}%")
                  ->orWhere('t.staffid', 'like', "%{$search}%");
            });
        }

        $withSsnit = $withQuery->orderBy('t.fullname')->get();

        // Records WITHOUT SSNIT
        $withoutQuery = DB::table("{$tableName} as t")
            ->whereNotIn('t.staffid', DB::table('bfassnit')->pluck('staffid1a'))
            ->select(
                't.staffid',
                't.gross',
                't.fullname',
                't.firstname',
                't.lastname'
            );

        if ($search) {
            $withoutQuery->where(function ($q) use ($search) {
                $q->where('t.fullname', 'like', "%{$search}%")
                  ->orWhere('t.staffid', 'like', "%{$search}%");
            });
        }

        $withoutSsnit = $withoutQuery->orderBy('t.fullname')->get();

        return Inertia::render('TranssionBfa/Dn13/Index', [
            'withSsnit'         => $withSsnit,
            'withoutSsnit'      => $withoutSsnit,
            'selectedMonth'     => $month,
            'formattedMonth'    => $date->format('F Y'),
            'totalWithSsnit'    => $withSsnit->count(),
            'totalWithoutSsnit' => $withoutSsnit->count(),
            'search'            => $search,
            'flash'             => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Export IUTS-TPA
    // ──────────────────────────────────────────────────────────────

    public function exportIutsTpa(Request $request)
    {
        $month  = $request->input('month', now()->format('Y-m'));
        $date   = Carbon::createFromFormat('Y-m', $month);
        $tableName = $this->buildTableName($month);

        if (!Schema::hasTable($tableName)) {
            return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
        }

        try {
            $records = DB::table($tableName)
                ->select('staffid', 'firstname', 'lastname', 'fullname', 'gross', 'iutsimpos', 'iutsnet', 'enfant')
                ->orderBy('fullname')
                ->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('IUTS-TPA');

            // Headers
            $headers = ['#', 'Staff ID', 'Full Name', 'Salaire Brut', 'Base Imposable (IUTS)', 'Enfant', 'IUTS Net'];
            foreach ($headers as $i => $header) {
                $col = Coordinate::stringFromColumnIndex($i + 1);
                $sheet->setCellValue($col . '1', $header);
            }

            // Header style: blue background, white bold text
            $lastHeaderCol = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A1:{$lastHeaderCol}1")->applyFromArray([
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1e40af'],
                ],
            ]);

            // Freeze pane at A2
            $sheet->freezePane('A2');

            // Data rows
            $row = 2;
            foreach ($records as $i => $r) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(1) . $row, $i + 1);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(2) . $row, $r->staffid ?? '');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(3) . $row, $r->fullname ?? (trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''))));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(4) . $row, (float)($r->gross ?? 0));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(5) . $row, (float)($r->iutsimpos ?? 0));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(6) . $row, $r->enfant ?? '');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(7) . $row, (float)($r->iutsnet ?? 0));
                $row++;
            }

            // Auto-size columns
            for ($i = 1; $i <= count($headers); $i++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
            }

            $writer   = new Xlsx($spreadsheet);
            $filename = "transsion_bfa_iuts_tpa_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store',
            ]);

        } catch (\Exception $e) {
            Log::error('TransbfaStat exportIutsTpa failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Export DN-13
    // ──────────────────────────────────────────────────────────────

    public function exportDn13(Request $request)
    {
        $month  = $request->input('month', now()->format('Y-m'));
        $date   = Carbon::createFromFormat('Y-m', $month);
        $tableName = $this->buildTableName($month);

        if (!Schema::hasTable($tableName)) {
            return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
        }

        try {
            // Records with SSNIT
            $withSsnit = DB::table("{$tableName} as t")
                ->join('bfassnit as b', 'b.staffid1a', '=', 't.staffid')
                ->select(
                    't.staffid',
                    't.gross',
                    't.fullname',
                    't.firstname',
                    't.lastname',
                    'b.noempl',
                    'b.matricule',
                    'b.travail',
                    'b.datenaiss',
                    'b.nom',
                    'b.nomepoux',
                    'b.prenom'
                )
                ->orderBy('t.fullname')
                ->get();

            // Records without SSNIT
            $withoutSsnit = DB::table("{$tableName} as t")
                ->whereNotIn('t.staffid', DB::table('bfassnit')->pluck('staffid1a'))
                ->select(
                    't.staffid',
                    't.gross',
                    't.fullname',
                    't.firstname',
                    't.lastname'
                )
                ->orderBy('t.fullname')
                ->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('DN-13 CNSS');

            // Headers
            $headers = [
                '#', 'Staff ID', 'N°EMPL', 'MATRICULE', 'N° TRAVAILLEUR',
                'DATE NAISS', 'NOM', 'NOM EPOUX', 'PRENOM(S)',
                'DEBUT', 'FIN', 'BASE CNSS', 'SAL. BRUT', 'Has SSNIT'
            ];

            foreach ($headers as $i => $header) {
                $col = Coordinate::stringFromColumnIndex($i + 1);
                $sheet->setCellValue($col . '1', $header);
            }

            // Header style
            $lastHeaderCol = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A1:{$lastHeaderCol}1")->applyFromArray([
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1e40af'],
                ],
            ]);

            $sheet->freezePane('A2');

            $row = 2;
            $rowNum = 1;

            // With SSNIT rows
            foreach ($withSsnit as $r) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(1)  . $row, $rowNum);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(2)  . $row, $r->staffid ?? '');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(3)  . $row, $r->noempl ?? '');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(4)  . $row, $r->matricule ?? '');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(5)  . $row, $r->travail ?? '');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(6)  . $row, $r->datenaiss ?? '');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(7)  . $row, $r->nom ?? '');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(8)  . $row, $r->nomepoux ?? '');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(9)  . $row, $r->prenom ?? '');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(10) . $row, 'See contract');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(11) . $row, 'See contract');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(12) . $row, (float)($r->gross ?? 0));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(13) . $row, (float)($r->gross ?? 0));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(14) . $row, 'Oui');
                $row++;
                $rowNum++;
            }

            // Without SSNIT rows (amber highlighted)
            foreach ($withoutSsnit as $r) {
                $fullname = $r->fullname ?? trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));

                $sheet->setCellValue(Coordinate::stringFromColumnIndex(1)  . $row, $rowNum);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(2)  . $row, $r->staffid ?? '');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(3)  . $row, 'N/A');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(4)  . $row, 'N/A');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(5)  . $row, 'N/A');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(6)  . $row, 'N/A');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(7)  . $row, $fullname);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(8)  . $row, '—');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(9)  . $row, '—');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(10) . $row, 'N/A');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(11) . $row, 'N/A');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(12) . $row, (float)($r->gross ?? 0));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(13) . $row, (float)($r->gross ?? 0));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(14) . $row, 'Non');

                // Amber highlight for rows without SSNIT
                $lastCol = Coordinate::stringFromColumnIndex(count($headers));
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FEF3C7');

                $row++;
                $rowNum++;
            }

            // Auto-size columns
            for ($i = 1; $i <= count($headers); $i++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
            }

            $writer   = new Xlsx($spreadsheet);
            $filename = "transsion_bfa_dn13_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store',
            ]);

        } catch (\Exception $e) {
            Log::error('TransbfaStat exportDn13 failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }
}
