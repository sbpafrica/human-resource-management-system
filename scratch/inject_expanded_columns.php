<?php
/**
 * Safely merge generated columns into app/Models/PayrollColumn.php
 */
$generated = require __DIR__ . '/generated_columns.php';
$modelPath = __DIR__ . '/../app/Models/PayrollColumn.php';

$content = file_get_contents($modelPath);

// Find the target insertion point right before the end of getCanonicalStandardColumns
$target = "            'covid' => [
                'display_label'   => 'COVID-19 Levy',
                'header_aliases'  => 'COVID, COVID-19, COVID 19, COVID Levy, COVID 1%, Covid 19 Levy',
                'payslip_section' => 'tax_reference',
                'data_type'       => 'decimal(15,2)',
                'description'     => 'COVID-19 health recovery levy.',
            ],";

if (strpos($content, $target) === false) {
    die("Target marker not found in PayrollColumn.php\n");
}

$newCode = "\n\n            // =====================================================\n"
         . "            // ALL CLIENT-SPECIFIC & TEMPLATE COLUMNS (EXPANDED)\n"
         . "            // =====================================================\n";

foreach ($generated as $colName => $def) {
    // Avoid re-declaring staff_id or existing
    if ($colName === 'staff_id') continue;
    
    $newCode .= "            '{$colName}' => [\n";
    $newCode .= "                'display_label'   => " . var_export($def['display_label'], true) . ",\n";
    $newCode .= "                'header_aliases'  => " . var_export($def['header_aliases'], true) . ",\n";
    $newCode .= "                'payslip_section' => '{$def['payslip_section']}',\n";
    $newCode .= "                'data_type'       => '{$def['data_type']}',\n";
    $newCode .= "                'description'     => " . var_export($def['description'], true) . ",\n";
    $newCode .= "            ],\n";
}

$replacement = $target . $newCode;
$updatedContent = str_replace($target, $replacement, $content);

file_put_contents($modelPath, $updatedContent);
echo "Successfully updated PayrollColumn.php with " . count($generated) . " columns!\n";

