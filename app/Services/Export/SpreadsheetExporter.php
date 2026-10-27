<?php

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpreadsheetExporter
{
    /**
     * Stream a configured Spreadsheet as a standard .xlsx file.
     */
    public static function streamDownload(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        if (!str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    /**
     * Export SSNIT report records as a standardized Excel file.
     */
    public static function exportSsnit(
        iterable $records,
        string $filename,
        string $staffIdKey = 'staffid',
        string $basicKey = 'basic'
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('SSNIT');

        $headers = [
            'Staff ID',
            'Shop',
            'Last Name',
            'First Name',
            'Other Name',
            'SSNIT Number',
            'Ghana Card',
            'Basic Salary',
            'SSNIT (13.5%)',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        $totalBasic = 0.0;
        $totalSsnit = 0.0;

        foreach ($records as $r) {
            $data = is_array($r) ? $r : (array) $r;

            // Extract staff ID (try provided key, then common fallbacks)
            $staffId = $data[$staffIdKey]
                ?? $data['staffid']
                ?? $data['staff_id']
                ?? $data['STAFFID']
                ?? $data['STAFF_ID']
                ?? $data['employ']
                ?? $data['id']
                ?? $data['StaffID']
                ?? '';

            $shop = $data['shop'] ?? '';
            $lastName = $data['lastname'] ?? $data['name'] ?? '';
            $firstName = $data['firstname'] ?? '';
            $otherName = $data['othername'] ?? '';
            $ssnitNo = $data['ssnit_number'] ?? $data['ssnit1a'] ?? $data['ssnit'] ?? '';
            $ghCard = $data['gh_card_number'] ?? $data['national'] ?? $data['ghanacard'] ?? '';

            // Extract basic salary
            $basicRaw = $data[$basicKey]
                ?? $data['basic']
                ?? $data['basic_salary']
                ?? $data['base_salary_used']
                ?? $data['basicentitlement']
                ?? $data['basicsalary']
                ?? $data['basicsalaryearned']
                ?? $data['earned_basic_salary']
                ?? $data['total2']
                ?? $data['BASICSALARYEARNED_GHC']
                ?? $data['BASE_SALARY_GHC']
                ?? $data['base_salary']
                ?? 0;

            $basicVal = (float) str_replace(',', '', (string) $basicRaw);

            // Extract or compute SSNIT amount (13.5%)
            if (isset($data['ssnit_amount']) && $data['ssnit_amount'] !== null && $data['ssnit_amount'] !== '') {
                $ssnitVal = (float) str_replace(',', '', (string) $data['ssnit_amount']);
            } else {
                $ssnitVal = round($basicVal * 0.135, 2);
            }

            $totalBasic += $basicVal;
            $totalSsnit += $ssnitVal;

            $sheet->setCellValueExplicit("A{$row}", (string) $staffId, DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", (string) $shop);
            $sheet->setCellValue("C{$row}", (string) $lastName);
            $sheet->setCellValue("D{$row}", (string) $firstName);
            $sheet->setCellValue("E{$row}", (string) $otherName);
            $sheet->setCellValueExplicit("F{$row}", (string) $ssnitNo, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("G{$row}", (string) $ghCard, DataType::TYPE_STRING);
            $sheet->setCellValue("H{$row}", number_format($basicVal, 2, '.', ''));
            $sheet->setCellValue("I{$row}", number_format($ssnitVal, 2, '.', ''));

            $row++;
        }

        // Summary row
        $sheet->setCellValue("A{$row}", "TOTAL");
        $sheet->setCellValue("H{$row}", number_format($totalBasic, 2, '.', ''));
        $sheet->setCellValue("I{$row}", number_format($totalSsnit, 2, '.', ''));

        // Styling
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $headerStyle = $sheet->getStyle('A1:I1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9E1F2');

        $totalStyle = $sheet->getStyle("A{$row}:I{$row}");
        $totalStyle->getFont()->setBold(true);

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return self::streamDownload($spreadsheet, $filename);
    }

    /**
     * Export PETRA report records as a standardized Excel file.
     */
    public static function exportPetra(
        iterable $records,
        string $filename,
        string $staffIdKey = 'staff_id',
        string $basicKey = 'basic'
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('PETRA');

        $headers = [
            'Staff ID',
            'Shop',
            'Last Name',
            'First Name',
            'Other Name',
            'PETRA ID',
            'Ghana Card',
            'Basic Salary',
            'PETRA Amount (5%)',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        $totalBasic = 0.0;
        $totalPetra = 0.0;

        foreach ($records as $r) {
            $data = is_array($r) ? $r : (array) $r;

            $staffId = $data[$staffIdKey]
                ?? $data['staff_id']
                ?? $data['staffid']
                ?? $data['staffida']
                ?? $data['employ']
                ?? '';

            $shop = $data['shop'] ?? '';
            $lastName = $data['lastname'] ?? $data['name'] ?? '';
            $firstName = $data['firstname'] ?? '';
            $otherName = $data['othername'] ?? '';
            $petraId = $data['petra_id'] ?? $data['ssnit1a'] ?? '';
            $ghCard = $data['ghana_card'] ?? $data['national'] ?? $data['gh_card_number'] ?? '';

            $basicRaw = $data[$basicKey]
                ?? $data['basic']
                ?? $data['basic_salary']
                ?? $data['basicentitlement']
                ?? 0;

            $basicVal = (float) str_replace(',', '', (string) $basicRaw);

            if (isset($data['petra_amount']) && $data['petra_amount'] !== null && $data['petra_amount'] !== '') {
                $petraVal = (float) str_replace(',', '', (string) $data['petra_amount']);
            } else {
                $petraVal = round($basicVal * 0.05, 2);
            }

            $totalBasic += $basicVal;
            $totalPetra += $petraVal;

            $sheet->setCellValueExplicit("A{$row}", (string) $staffId, DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", (string) $shop);
            $sheet->setCellValue("C{$row}", (string) $lastName);
            $sheet->setCellValue("D{$row}", (string) $firstName);
            $sheet->setCellValue("E{$row}", (string) $otherName);
            $sheet->setCellValueExplicit("F{$row}", (string) $petraId, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("G{$row}", (string) $ghCard, DataType::TYPE_STRING);
            $sheet->setCellValue("H{$row}", number_format($basicVal, 2, '.', ''));
            $sheet->setCellValue("I{$row}", number_format($petraVal, 2, '.', ''));

            $row++;
        }

        // Summary row
        $sheet->setCellValue("A{$row}", "TOTAL");
        $sheet->setCellValue("H{$row}", number_format($totalBasic, 2, '.', ''));
        $sheet->setCellValue("I{$row}", number_format($totalPetra, 2, '.', ''));

        // Styling
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $headerStyle = $sheet->getStyle('A1:I1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9E1F2');

        $totalStyle = $sheet->getStyle("A{$row}:I{$row}");
        $totalStyle->getFont()->setBold(true);

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return self::streamDownload($spreadsheet, $filename);
    }

    /**
     * Export Salary Payment records as a standardized Excel file.
     */
    public static function exportSalaryPayment(
        iterable $records,
        string $filename
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Salary Payment');

        $headers = [
            'Staff ID',
            'Name',
            'Department',
            'Bank Name',
            'Account',
            'Branch Code',
            'Branch',
            'Net Pay',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        $totalNet = 0.0;

        foreach ($records as $r) {
            $data = is_array($r) ? $r : (array) $r;

            $staffId = $data['staffida'] ?? $data['staffid'] ?? $data['staff_id'] ?? '';
            $name = $data['name'] ?? $data['employeename'] ?? '';
            $dept = $data['shop'] ?? $data['department'] ?? '';
            $bank = $data['bank'] ?? $data['bankname'] ?? '';
            $account = $data['account'] ?? $data['alcnum'] ?? '';
            $code = $data['code'] ?? '';
            $branch = $data['branch'] ?? '';
            $netRaw = $data['netpay'] ?? $data['net'] ?? 0;
            $netVal = (float) str_replace(',', '', (string) $netRaw);

            $totalNet += $netVal;

            $sheet->setCellValueExplicit("A{$row}", (string) $staffId, DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", (string) $name);
            $sheet->setCellValue("C{$row}", (string) $dept);
            $sheet->setCellValue("D{$row}", (string) $bank);
            $sheet->setCellValueExplicit("E{$row}", (string) $account, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", (string) $code, DataType::TYPE_STRING);
            $sheet->setCellValue("G{$row}", (string) $branch);
            $sheet->setCellValue("H{$row}", number_format($netVal, 2, '.', ''));

            $row++;
        }

        // Summary row
        $sheet->setCellValue("A{$row}", "TOTAL");
        $sheet->setCellValue("H{$row}", number_format($totalNet, 2, '.', ''));

        // Styling
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $headerStyle = $sheet->getStyle('A1:H1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9E1F2');

        $totalStyle = $sheet->getStyle("A{$row}:H{$row}");
        $totalStyle->getFont()->setBold(true);

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return self::streamDownload($spreadsheet, $filename);
    }
}
