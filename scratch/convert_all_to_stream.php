<?php
$files = glob(__DIR__ . '/../app/Http/Controllers/*PayController.php');
$files = array_merge($files, [__DIR__ . '/../app/Http/Controllers/SalaryPaymentController.php']);

$updated = 0;
foreach ($files as $file) {
    $code = file_get_contents($file);
    if (strpos($code, 'function downloadTemplate') === false || strpos($code, 'tempnam') === false) {
        continue;
    }

    // Pattern matching the temp file creation and download
    // Looks for:
    // ($writer = ...;)?
    // $(fileName|filename) = ...;
    // $tempFile = tempnam(...);
    // $writer->save($tempFile);
    // return response()->download($tempFile, $(fileName|filename))->deleteFileAfterSend(true);

    $pattern = '/(\$(?:fileName|filename)\s*=\s*[^;]+;)\s*\$tempFile\s*=\s*tempnam\([^;]+;\s*\$writer->save\(\$tempFile\);\s*return\s*response\(\)->download\(\$tempFile,\s*(\$(?:fileName|filename))\)->deleteFileAfterSend\(true\);/s';

    if (preg_match($pattern, $code, $matches)) {
        $fileVarDef = $matches[1];
        $varName    = $matches[2];

        $replacement = "{$fileVarDef}\n"
            . "        \$writer = new \\PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx(\$spreadsheet);\n\n"
            . "        return response()->streamDownload(function () use (\$writer) {\n"
            . "            if (ob_get_level() > 0) {\n"
            . "                ob_end_clean();\n"
            . "            }\n"
            . "            \$writer->save('php://output');\n"
            . "        }, {$varName}, [\n"
            . "            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',\n"
            . "            'Content-Disposition' => 'attachment; filename=\"' . {$varName} . '\"',\n"
            . "            'Cache-Control'       => 'max-age=0, no-cache, no-store, must-revalidate',\n"
            . "            'Pragma'              => 'public',\n"
            . "        ]);";

        $newCode = preg_replace($pattern, $replacement, $code, 1);
        if ($newCode && $newCode !== $code) {
            file_put_contents($file, $newCode);
            echo "UPDATED: " . basename($file) . " (using {$varName})\n";
            $updated++;
        } else {
            echo "FAILED REPLACEMENT in: " . basename($file) . "\n";
        }
    } else {
        echo "NO REGEX MATCH in: " . basename($file) . "\n";
    }
}

echo "Total updated: {$updated}\n";

