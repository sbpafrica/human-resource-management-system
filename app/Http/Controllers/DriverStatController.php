<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Ssnit;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Support\Facades\Response;

class DriverStatController extends Controller
{
    public function index()
    {
        return Inertia::render('Driverstat/Index');
    }

    public function generate(Request $request)
    {
        $month = $request->input('month');
        $year = $request->input('year');

        $fullMonth = strtolower(\DateTime::createFromFormat('!m', $month)->format('F')) . substr($year, -2);
        $shortMonth = strtolower(\DateTime::createFromFormat('!m', $month)->format('M')) . substr($year, -2);

        $tableName1 = 'driverssnit' . $fullMonth;
        $tableName2 = 'driverssnit' . $shortMonth;

        if (Schema::hasTable($tableName1)) {
            $tableName = $tableName1;
        } elseif (Schema::hasTable($tableName2)) {
            $tableName = $tableName2;
        } else {
            return redirect()->back()->with('error', 'Payroll data for the selected month and year not found.');
        }

        $payrollData = DB::table($tableName)->get();

        if ($payrollData->isEmpty()) {
            return redirect()->back()->with('error', 'No payroll records found for the selected month and year.');
        }

        $staffIds = $payrollData->pluck('employ');
        $ssnitData = Ssnit::whereIn('staffid', $staffIds)->get()->keyBy('staffid');

        // Create spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('PAYE DATA');

        // Set fixed column widths
        $columnWidths = [
            'A' => 8.11, 'B' => 16.22, 'C' => 25.44, 'D' => 11.22, 'E' => 11.22, 'F' => 16.22,
            'G' => 10.11, 'H' => 10.11, 'I' => 10.11, 'J' => 10.11, 'K' => 10.11, 'L' => 10.11,
            'M' => 10.11, 'N' => 10.11, 'O' => 10.11, 'P' => 10.11, 'Q' => 10.11, 'R' => 10.11,
            'S' => 10.11, 'T' => 10.11, 'U' => 10.11, 'V' => 10.11, 'W' => 10.11, 'X' => 10.11,
            'Y' => 10.11, 'Z' => 10.11, 'AA' => 10.11, 'AB' => 10.11
        ];
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Set default row height for data rows
        $sheet->getDefaultRowDimension()->setRowHeight(18);

        // Set entire sheet background to white with no default borders
        $sheet->getStyle('A1:AB518')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:AB518')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_NONE);

        // Add GRA logo in A1:B1
        $sheet->mergeCells('A1:B1');
        $logo = new Drawing();
        $logo->setName('GRA Logo');
        $logo->setDescription('Ghana Revenue Authority Logo');
        $logo->setPath(public_path('images/gra.png')); // Path to logo
        $logo->setHeight(80); // Adjust height as needed
        $logo->setCoordinates('A1'); // Place logo in merged cell A1:B1
        $logo->setOffsetX(10); // Adjust horizontal offset
        $logo->setOffsetY(10); // Adjust vertical offset
        $logo->setWorksheet($sheet);

        // Set fixed header rows (starting from column I for main headers)
        $sheet->setCellValue('I1', 'GHANA REVENUE AUTHORITY');
        $sheet->getStyle('I1')->getFont()->setBold(true)->setSize(16); // Larger and bolder
        $sheet->getStyle('I1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('I2', 'DOMESTIC TAX REVENUE DIVISION');
        $sheet->getStyle('I2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('I2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('I5', "EMPLOYER'S MONTHLY TAX DEDUCTIONS SCHEDULE (P. A. Y. E.)");
        $sheet->getStyle('I5')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('I5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Set CURRENT TAX OFFICE, NAME OF EMPLOYER, EMPLOYER'S TIN / GH. CARD NO. starting at A with bold text, no borders
        $sheet->setCellValue('A7', 'CURRENT TAX OFFICE');
        $sheet->getStyle('A7')->getFont()->setBold(true);

        $sheet->setCellValue('A9', 'NAME OF EMPLOYER');
        $sheet->getStyle('A9')->getFont()->setBold(true);

        $sheet->setCellValue('A11', "EMPLOYER'S TIN / GH. CARD NO.");
        $sheet->getStyle('A11')->getFont()->setBold(true);

        // Add thick-bordered boxes starting from D (keep only these borders as specified)
        // CURRENT TAX OFFICE: Divided into LTO, TSC, and empty space
        $sheet->mergeCells('D7:F7');
        $sheet->setCellValue('D7', 'LTO');
        $sheet->getStyle('D7:F7')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM); // Reduced from THICK
        $sheet->getStyle('D7:F7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');

        $sheet->mergeCells('G7:I7');
        $sheet->setCellValue('G7', 'TSC');
        $sheet->getStyle('G7:I7')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM); // Reduced from THICK
        $sheet->getStyle('G7:I7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');

        $sheet->mergeCells('J7:O7');
        $sheet->getStyle('J7:O7')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM); // Reduced from THICK
        $sheet->getStyle('J7:O7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');

        // NAME OF EMPLOYER: Single box D9:O9
        $sheet->mergeCells('D9:O9');
        $sheet->getStyle('D9:O9')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM); // Reduced from THICK
        $sheet->getStyle('D9:O9')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');

        // EMPLOYER'S TIN / GH. CARD NO.: Single box D11:O11
        $sheet->mergeCells('D11:O11');
        $sheet->getStyle('D11:O11')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM); // Reduced from THICK
        $sheet->getStyle('D11:O11')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');

        $sheet->setCellValue('S9', 'FOR THE MONTH OF');
        $sheet->setCellValue('U10', '(MM/YYYY)');
        $sheet->setCellValue('U9', sprintf('%02d/%d', $month, $year));
        $sheet->setCellValue('AM12', 'paye2025grra');

        // Row 13: numbers 1 to 27
        for ($col = 1; $col <= 27; $col++) {
            $columnLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue($columnLetter . '13', $col);
            $sheet->getStyle($columnLetter . '13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Row 14: PENSIONS
        $sheet->setCellValue('I14', 'PENSIONS');
        $sheet->getStyle('I14')->getFont()->setBold(true);

        // Row 15-17: headers with y-axis starting from A17, split content (e.g., Security Fund to Security on I16, Fund on I17)
        $yAxisHeaders = ['Ser. No', 'TIN / GH. CARD NO.', 'Name Of Employee', 'Position', 'Residency/ Part-Time/ Casual',
                        'Basic Salary', 'Secondary Employment (Y / N)', 'Paid SSNIT (Y / N)', 'Security', 'Third Tier',
                        'Cash Allowances', 'Bonus Income(up to 15% of Annual Basic salary)', 'Final Tax on Bonus Income',
                        'Excess Bonus', 'Total Cash emolument (6+11+14)', 'Accommodation Element', 'Vehicle Element',
                        'Non Cash Benefit', 'Total Assessable Income (15+16+17+18)', 'Deductible Reliefs',
                        'Total Reliefs (9+10+20)', 'Chargeable Income', 'Tax Deductible', 'Overtime Income',
                        'Overtime Tax', 'Total Tax Payable to GRA (13+23+25)', 'Severance pay paid', 'Remarks'];
        $sheet->setCellValue('A17', '1'); // Y-axis start
        $sheet->getStyle('A17')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headersSecondLine = ['', '', '', '', '',
                            '', '', '', 'Fund', '',
                            '', '', '', '', '', '', '', '',
                            '', '', '', '', '', '', '', '', '', ''];
        for ($col = 0; $col < count($yAxisHeaders); $col++) {
            $columnLetter = Coordinate::stringFromColumnIndex($col + 1);
            if ($yAxisHeaders[$col] !== '') {
                $sheet->setCellValue($columnLetter . '16', $yAxisHeaders[$col]);
                $sheet->getStyle($columnLetter . '16')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            if ($headersSecondLine[$col] !== '') {
                $sheet->setCellValue($columnLetter . '17', $headersSecondLine[$col]);
                $sheet->getStyle($columnLetter . '17')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }
        $sheet->getRowDimension(16)->setRowHeight(18); // Adjust row height for split headers
        $sheet->getRowDimension(17)->setRowHeight(18);

        // Apply borders to headers with reduced thickness
        $borderStyleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_HAIR, // Reduced to hairline
                    'color' => ['argb' => 'FF000000'],
                ],
                'vertical' => [
                    'borderStyle' => Border::BORDER_MEDIUM, // Reduced from THICK
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ];
        $sheet->getStyle('A16:AB17')->applyFromArray($borderStyleArray);

        // Data rows starting from 18
        $dataStartRow = 18;
        $maxRows = 500; // Match the 500 rows in the sample file
        $actualDataCount = count($payrollData);

        foreach ($payrollData as $index => $row) {
            $currentRow = $dataStartRow + $index;
            $ssnitRecord = $ssnitData->get($row->employ);

            // Ser. No
            $sheet->setCellValue('A' . $currentRow, $index + 1);

            // TIN / GH. CARD NO.
            $sheet->setCellValue('B' . $currentRow, $ssnitRecord->national ?? $row->{'COL 12'} ?? '');

            // Name Of Employee
            $sheet->setCellValue('C' . $currentRow, $row->name ?? '');

            // Position
            $sheet->setCellValue('D' . $currentRow, $row->dept ?? '');

            // Residency/ Part-Time/ Casual
            $residency = ($row->categories ?? null) === 'Casual'
    ? 'Casual'
    : 'Resident-Full-Time';
            $sheet->setCellValue('E' . $currentRow, $residency);

            // Basic Salary
            $sheet->setCellValue('F' . $currentRow, $row->basic ?? 0);

            // Secondary Employment (Y / N)
            $sheet->setCellValue('G' . $currentRow, 'N');

            $ssfVal = $row->ssfyeed ?? $row->ssfyee ?? 0;

            // Paid SSNIT (Y / N)
            $paidSsnit = ($ssfVal > 0) ? 'Y' : 'N';
            $sheet->setCellValue('H' . $currentRow, $paidSsnit);

            // Social Security Fund
            $sheet->setCellValue('I' . $currentRow, $ssfVal);

            // Third Tier
            $sheet->setCellValue('J' . $currentRow, $row->nssf ?? 0);

            // Cash Allowances
            $sheet->setCellValue('K' . $currentRow, ($row->salary - $row->basic) ?? 0);

            // Bonus Income
            $sheet->setCellValue('L' . $currentRow, 0);

            // Final Tax on Bonus Income
            $sheet->setCellValue('M' . $currentRow, 0);

            // Excess Bonus
            $sheet->setCellValue('N' . $currentRow, 0);

            // Total Cash emolument
            $sheet->setCellValueExplicit('O' . $currentRow, '=F' . $currentRow . '+K' . $currentRow . '+N' . $currentRow, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);

            // Accommodation Element
            $sheet->setCellValue('P' . $currentRow, 0);

            // Vehicle Element
            $sheet->setCellValue('Q' . $currentRow, 0);

            // Non Cash Benefit
            $sheet->setCellValue('R' . $currentRow, 0);

            // Total Assessable Income
            $sheet->setCellValueExplicit('S' . $currentRow, '=O' . $currentRow . '+P' . $currentRow . '+Q' . $currentRow . '+R' . $currentRow, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);

            // Deductible Reliefs
            $sheet->setCellValue('T' . $currentRow, 0);

            // Total Reliefs
            $sheet->setCellValueExplicit('U' . $currentRow, '=I' . $currentRow . '+J' . $currentRow . '+T' . $currentRow, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);

            // Chargeable Income
            $sheet->setCellValueExplicit('V' . $currentRow, '=S' . $currentRow . '-U' . $currentRow, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);

            // Tax Deductible
            /*$taxFormula = '=IF(V' . $currentRow . '<=0,0,IF(V' . $currentRow . '<=490,0,IF(V' . $currentRow . '<=600,(V' . $currentRow . '-490)*0.05,IF(V' . $currentRow . '<=730,5.5+(V' . $currentRow . '-600)*0.1,IF(V' . $currentRow . '<=3897,18.5+(V' . $currentRow . '-730)*0.175,IF(V' . $currentRow . '<=19897,572.725+(V' . $currentRow . '-3897)*0.25,IF(V' . $currentRow . '<=50417,4572.725+(V' . $currentRow . '-19897)*0.3,13728.725+(V' . $currentRow . '-50417)*0.35)))))))';
            $sheet->setCellValueExplicit('W' . $currentRow, $taxFormula, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);*/
            $sheet->setCellValue('W' . $currentRow, $row->paye);

            // Overtime Income
            $sheet->setCellValue('X' . $currentRow, 0);

            // Overtime Tax
            $sheet->setCellValue('Y' . $currentRow, 0);

            // Total Tax Payable
            $sheet->setCellValueExplicit('Z' . $currentRow, '=M' . $currentRow . '+W' . $currentRow . '+Y' . $currentRow, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);

            // Severance pay paid
            $sheet->setCellValue('AA' . $currentRow, 0);

            // Remarks
            $sheet->setCellValue('AB' . $currentRow, '');
        }

        // Fill remaining rows up to 500 with zeros
        for ($row = $dataStartRow + $actualDataCount; $row < $dataStartRow + $maxRows; $row++) {
            $sheet->setCellValue('A' . $row, $row - $dataStartRow + 1); // Serial No
            $sheet->setCellValue('F' . $row, 0);
            $sheet->setCellValue('I' . $row, 0);
            $sheet->setCellValue('J' . $row, 0);
            $sheet->setCellValue('K' . $row, 0);
            $sheet->setCellValue('L' . $row, 0);
            $sheet->setCellValue('M' . $row, 0);
            $sheet->setCellValue('N' . $row, 0);
            $sheet->setCellValueExplicit('O' . $row, '=F' . $row . '+K' . $row . '+N' . $row, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
            $sheet->setCellValue('P' . $row, 0);
            $sheet->setCellValue('Q' . $row, 0);
            $sheet->setCellValue('R' . $row, 0);
            $sheet->setCellValueExplicit('S' . $row, '=O' . $row . '+P' . $row . '+Q' . $row . '+R' . $row, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
            $sheet->setCellValue('T' . $row, 0);
            $sheet->setCellValueExplicit('U' . $row, '=I' . $row . '+J' . $row . '+T' . $row, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
            $sheet->setCellValueExplicit('V' . $row, '=S' . $row . '-U' . $row, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
            $sheet->setCellValueExplicit('W' . $row, '=IF(V' . $row . '<=0,0,IF(V' . $row . '<=490,0,IF(V' . $row . '<=600,(V' . $row . '-490)*0.05,IF(V' . $row . '<=730,5.5+(V' . $row . '-600)*0.1,IF(V' . $row . '<=3897,18.5+(V' . $row . '-730)*0.175,IF(V' . $row . '<=19897,572.725+(V' . $row . '-3897)*0.25,IF(V' . $row . '<=50417,4572.725+(V' . $row . '-19897)*0.3,13728.725+(V' . $row . '-50417)*0.35)))))))', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
            $sheet->setCellValue('X' . $row, 0);
            $sheet->setCellValue('Y' . $row, 0);
            $sheet->setCellValueExplicit('Z' . $row, '=M' . $row . '+W' . $row . '+Y' . $row, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
            $sheet->setCellValue('AA' . $row, 0);
        }

        // Totals row
        $totalsRow = $dataStartRow + $maxRows; // Row 518
        $sheet->setCellValue('A' . $totalsRow, 'TOTALS');
        $sheet->getStyle('A' . $totalsRow)->getFont()->setBold(true);

        // Sum columns
        $sumColumns = ['F', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA'];
        foreach ($sumColumns as $col) {
            $sheet->setCellValueExplicit($col . $totalsRow, '=SUM(' . $col . $dataStartRow . ':' . $col . ($totalsRow - 1) . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
        }

        // Apply borders to the entire data range with reduced thickness
        $borderStyleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_HAIR, // Reduced to hairline
                    'color' => ['argb' => 'FF000000'],
                ],
                'vertical' => [
                    'borderStyle' => Border::BORDER_MEDIUM, // Reduced from THICK
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ];
        $sheet->getStyle('A18:AB' . $totalsRow)->applyFromArray($borderStyleArray);

        // Prepare for download
        $writer = new Xlsx($spreadsheet);
        $fileName = 'PAYE_SCHEDULE_' . $month . '_' . $year . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);
    }
}
