<?php
/**
 * Script to generate array definitions for all missing columns
 * and output ready-to-paste PHP code.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollColumn;

$canonical = PayrollColumn::getCanonicalStandardColumns();
$canonicalKeys = array_map('strtolower', array_keys($canonical));

// Run the previous compare to get missing list
ob_start();
require __DIR__ . '/compare_columns.php';
ob_end_clean();

// We know $missing array is available from compare_columns.php logic
// Let's create an automated mapper that defines display_label, header_aliases, payslip_section, data_type, description
$definitions = [];

foreach ($missing as $lower => $orig) {
    // Determine section
    $section = 'info';
    $dataType = 'decimal(15,2)';
    
    // Classify
    if (preg_match('/(date|hire|joined|start|end|dob|expir)/i', $lower)) {
        $section = 'info';
        $dataType = 'varchar(50)';
    } elseif (preg_match('/(bank|account|branch|alcnum)/i', $lower)) {
        $section = 'info';
        $dataType = 'varchar(100)';
    } elseif (preg_match('/(ghcard|ssnitnum|ssnit1|ssnit2|ssnitaa|tin|gh_card)/i', $lower)) {
        $section = 'info';
        $dataType = 'varchar(50)';
    } elseif (preg_match('/(days|workdays|hours|tonage|actualdays|noofdays|extraldays|halfdays|satoff|sunoff|absenthrs|rate|coeff|stand)/i', $lower)) {
        $section = 'info';
        $dataType = 'decimal(10,2)';
    } elseif (preg_match('/(position|dept|category|workplace|workarea|shop|brand|business|entity|desig|report|remarks|outsourc)/i', $lower)) {
        $section = 'info';
        $dataType = 'varchar(100)';
    } elseif (preg_match('/(tax|paye|vat|nhil|getfund|gfl|covid|nssf|cost|mgt|fee|service|ssf_13|ssnit_er|ssniter|er_cost|c2c|wcomp|workmen)/i', $lower)) {
        $section = 'tax_reference';
        $dataType = 'decimal(15,2)';
    } elseif (preg_match('/(deduct|loan|advance|absent_without|absent_days_deduct|absent_deduct|leavededuct|pf|trust|welfare|ssnit_ee|ssnitee|ssfyee)/i', $lower)) {
        $section = 'deductions';
        $dataType = 'decimal(15,2)';
    } else {
        // Earnings by default for pay, allowances, overtime, basic, gross, net
        $section = 'earnings';
        $dataType = 'decimal(15,2)';
    }

    // Generate human friendly label
    $cleanLabel = str_replace(['_ghc', '_15_percent', '_2_5_percent', '_1_percent', '_8', '_10', '_5_percent', '_13_percent', '_13', '_5_5', '_0_5', '_05', '_ee', '_er', '_repeat'], '', $lower);
    $cleanLabel = ucwords(str_replace('_', ' ', $cleanLabel));

    // Generate aliases
    $aliases = [$orig, $cleanLabel];
    if ($orig !== $lower) $aliases[] = $lower;
    if ($cleanLabel !== $orig) $aliases[] = strtolower($cleanLabel);
    $aliases = array_unique($aliases);

    $definitions[$lower] = [
        'display_label'   => $cleanLabel,
        'header_aliases'  => implode(', ', $aliases),
        'payslip_section' => $section,
        'data_type'       => $dataType,
        'description'     => "Standard payroll column for {$cleanLabel}.",
    ];
}

echo "Generated " . count($definitions) . " new column definitions.\n";

$export = "[\n";
foreach ($definitions as $colName => $def) {
    $export .= "            '{$colName}' => [\n";
    $export .= "                'display_label'   => " . var_export($def['display_label'], true) . ",\n";
    $export .= "                'header_aliases'  => " . var_export($def['header_aliases'], true) . ",\n";
    $export .= "                'payslip_section' => '{$def['payslip_section']}',\n";
    $export .= "                'data_type'       => '{$def['data_type']}',\n";
    $export .= "                'description'     => " . var_export($def['description'], true) . ",\n";
    $export .= "            ],\n";
}
$export .= "        ]";

file_put_contents(__DIR__ . '/generated_columns.php', "<?php\nreturn " . $export . ";\n");
echo "Saved to scratch/generated_columns.php\n";

