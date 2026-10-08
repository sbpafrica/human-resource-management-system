import os
import glob
import re

pages_dir = "resources/js/pages"

# 1. FIX PETRA PAY PAGES
petra_files = glob.glob(os.path.join(pages_dir, "*PetraPay", "Index.tsx")) + glob.glob(os.path.join(pages_dir, "PetraPay", "Index.tsx"))

for filepath in petra_files:
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    filename = os.path.basename(os.path.dirname(filepath))
    
    # Ensure Record interface includes basic_salary
    if 'basic_salary?:' not in content:
        content = re.sub(
            r'interface Record \{([^}]+)\}',
            lambda m: m.group(0).replace('basic: number', 'basic_salary?: number | null;\n    basic?: number').replace('basic?: number', 'basic_salary?: number | null;\n    basic?: number'),
            content
        )

    # Fix table cell rendering for basic salary to avoid NaN
    content = re.sub(
        r'\{r\.basic !== null \? Number\(r\.basic\)\.toLocaleString\([^\}]+\) : "—"\}',
        '{(r.basic_salary ?? r.basic) != null && !isNaN(Number(r.basic_salary ?? r.basic)) ? Number(r.basic_salary ?? r.basic).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "—"}',
        content
    )

    # Fix handleExport if it contains response.json()
    if 'await response.json()' in content and 'handleExport' in content:
        # Pattern match handleExport body
        handle_export_pattern = r'const handleExport = async \(\) => \{[\s\S]*?\n    \};'
        
        # Determine route name or URL from file
        route_match = re.search(r"fetch\(\s*[`'\"]([^`'\"]+)[`'\"]", content)
        url_expr = route_match.group(1) if route_match else ""
        
        # Build replacement handleExport
        new_handle_export = f"""const handleExport = async () => {{
        try {{
            setIsExporting(true);
            const [sortColumn, sortOrder] = sort.split(':');
            const url = `{url_expr}?month=${{encodeURIComponent(data.month)}}&search=${{encodeURIComponent(search)}}&sort=${{encodeURIComponent(sortColumn)}}&order=${{encodeURIComponent(sortOrder)}}`;
            const response = await fetch(url);

            if (!response.ok) {{
                const text = await response.text();
                let errorMsg = "Failed to export data";
                try {{
                    const json = JSON.parse(text);
                    errorMsg = json.error || errorMsg;
                }} catch {{
                    errorMsg = text || errorMsg;
                }}
                throw new Error(errorMsg);
            }}

            const blob = await response.blob();
            const downloadUrl = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = `{filename}_${{data.month}}.xlsx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(downloadUrl);

            setToastMessage("Export completed successfully");
            setToastType("success");
            setShowToast(true);
        }} catch (err: any) {{
            setToastMessage(err?.message || "Export failed");
            setToastType("error");
            setShowToast(true);
        }} finally {{
            setIsExporting(false);
        }}
    }};"""
        content = re.sub(handle_export_pattern, new_handle_export, content)

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

print("PetraPay pages fixed.")
