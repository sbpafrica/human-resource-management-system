<?php

namespace App\Services\Payroll;

use App\Models\Client;
use App\Models\ClientTemplateColumn;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemplateBuilderService
{
    /**
     * Build a formatted PhpSpreadsheet instance for a client and module.
     */
    public function buildSpreadsheet(string $clientDb, string $module): Spreadsheet
    {
        $columns = ClientTemplateColumn::getActiveColumnsForTemplate($clientDb, $module);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(strtoupper($module) . ' Template');

        $headers = [];
        $sampleRow = [];
        $textColumns = [];

        foreach ($columns as $idx => $col) {
            $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
            $headers[] = $col->display_label;

            // Determine sample value
            $sampleVal = $col->sample_value;
            if ($sampleVal === null || $sampleVal === '') {
                $sampleVal = $this->getDefaultSampleValue($col->column_key, $clientDb);
            }
            $sampleRow[] = $sampleVal;

            // Track columns that need text formatting (like IDs, accounts, phone numbers)
            if (in_array($col->data_type, ['string', 'text'], true) ||
                in_array($col->column_key, ['staff_id', 'account_number', 'momo_number', 'national_id', 'ssnit_number', 'petra_id'], true)) {
                $textColumns[] = $colLetter;
            }
        }

        // Insert Header row (Row 1)
        $sheet->fromArray([$headers], null, 'A1');

        // Insert Sample row (Row 2)
        $sheet->fromArray([$sampleRow], null, 'A2');

        $lastColLetter = Coordinate::stringFromColumnIndex(count($headers));

        // Format Text columns so leading zeros and long numbers don't get truncated by Excel
        foreach ($textColumns as $colLetter) {
            $sheet->getStyle("{$colLetter}2:{$colLetter}1000")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        // Header Styling: Navy blue background, bold white text, centered vertically
        $headerStyle = [
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 11,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E40AF'], // Tailwind Blue-800
            ],
            'alignment' => [
                'vertical'   => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '3B82F6'],
                ],
            ],
        ];

        $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Sample Row Styling: Light gray fill, italic, subtle border
        $sampleStyle = [
            'font' => [
                'italic' => true,
                'color'  => ['rgb' => '4B5563'], // Gray-600
                'size'   => 10,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F3F4F6'], // Gray-100
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => 'E5E7EB'],
                ],
            ],
        ];

        $sheet->getStyle("A2:{$lastColLetter}2")->applyFromArray($sampleStyle);
        $sheet->getRowDimension(2)->setRowHeight(22);

        // Auto-size columns with minimum padding
        for ($i = 1; $i <= count($headers); $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Freeze top header row so it stays fixed while scrolling
        $sheet->freezePane('A2');

        return $spreadsheet;
    }

    /**
     * Download the template as a streamed HTTP response.
     */
    public function downloadResponse(string $clientDb, string $module): StreamedResponse
    {
        $client = Client::where('client_dbname', $clientDb)->first();
        $clientPrefix = $client ? ($client->short_name ?: $client->client_name) : strtoupper($clientDb);
        $cleanPrefix = preg_replace('/[^A-Za-z0-9_-]/', '_', $clientPrefix);

        $moduleLabel = match (strtolower($module)) {
            'payroll'  => 'Salary_Payment',
            'ssnit'    => 'SSNIT',
            'petra'    => 'PETRA',
            'bank'     => 'Bank_Details',
            'gra'      => 'GRA_PAYE_Return',
            'contract' => 'Contract',
            default    => ucfirst($module),
        };

        $fileName = "{$cleanPrefix}_{$moduleLabel}_Template.xlsx";
        $spreadsheet = $this->buildSpreadsheet($clientDb, $module);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control'       => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma'              => 'public',
        ]);
    }

    /**
     * Fallback sensible sample value based on column key.
     */
    private function getDefaultSampleValue(string $key, string $clientDb): string
    {
        $prefix = strtoupper(substr($clientDb, 0, 3));
        return match ($key) {
            'staff_id'               => "{$prefix}001",
            'name'                   => 'John Doe',
            'first_name'             => 'John',
            'last_name'              => 'Doe',
            'other_name'             => '',
            'position'               => 'Staff Member',
            'department'             => 'Operations',
            'shop'                   => 'Main Branch',
            'bank_name'              => 'GCB Bank',
            'branch'                 => 'High Street',
            'account_number'         => '1234567890123',
            'momo_number'            => '0241234567',
            'momo_name'              => 'John Doe',
            'network'                => 'MTN',
            'ssnit_number'           => 'C018301110076',
            'petra_id'               => 'PTR123456789',
            'national_id'            => 'GHA-712092453-9',
            'tin_ghana_card'         => 'GHA-712092453-9',
            'residency_status'       => 'Resident',
            'secondary_employment'   => 'N',
            'paid_ssnit'             => 'Y',
            'ssnit_5_5'              => '192.50',
            'tier_3'                 => '0.00',
            'chargeable_income'      => '3307.50',
            'total_tax_payable'      => '420.00',
            'start_date'             => '2025-01-01',
            'end_date'               => '2025-12-31',
            'location'               => 'Accra',
            'employer_name'          => 'SBP Africa',
            'days_worked'            => '22',
            'total_weekdays'         => '22',
            'basic_salary'           => '3500.00',
            'gross_salary'           => '3500.00',
            'net_pay'                => '2712.50',
            'employee_ssnit'         => '192.50',
            'ssnit_ee_0_5'           => '17.50',
            'employer_ssnit'         => '455.00',
            'petra'                  => '175.00',
            'taxable_income'         => '3132.50',
            'paye'                   => '420.00',
            'total_deductions'       => '787.50',
            default                  => '0.00',
        };
    }
}
