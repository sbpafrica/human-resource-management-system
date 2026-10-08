import os
import glob
import re

pages_dir = "resources/js/pages"

pay_files = glob.glob(os.path.join(pages_dir, "*pay", "Index.tsx")) + glob.glob(os.path.join(pages_dir, "*Pay", "Index.tsx"))

for filepath in pay_files:
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    filename = os.path.basename(os.path.dirname(filepath))

    # Check if handleExport calls await response.json() on success
    if 'const records: Record[] = await response.json();' in content or ('const res = await response.json()' in content and 'handleExport' in content):
        route_match = re.search(r"route\(\s*['\"]([^'\"]+)['\"]", content)
        route_name = route_match.group(1) if route_match else ""

        if route_name:
            new_handle_export = f"""const handleExport = async () => {{
        try {{
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const [sortColumn, sortOrder] = (sort || 'staffid:asc').split(':');

            const response = await fetch(
                route('{route_name}', {{ month: data.month, search, sort: sortColumn, order: sortOrder }}),
                {{
                    method: 'GET',
                    headers: {{
                        ...(csrfToken ? {{ 'X-CSRF-TOKEN': csrfToken }} : {{}}),
                    }},
                }}
            );

            if (!response.ok) {{
                const text = await response.text();
                let errorMessage = `HTTP ${{response.status}}: Failed to export data`;
                try {{
                    const errorData = JSON.parse(text);
                    errorMessage = errorData.error || errorMessage;
                }} catch {{
                    errorMessage = text ? text.substring(0, 100) : errorMessage;
                }}
                setToastMessage(errorMessage);
                setToastType('error');
                setShowToast(true);
                return;
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

            setToastMessage('Export successful!');
            setToastType('success');
            setShowToast(true);
        }} catch (error) {{
            console.error('Export error:', error);
            setToastMessage('Error exporting data: ' + (error instanceof Error ? error.message : 'Unknown error'));
            setToastType('error');
            setShowToast(true);
        }}
    }};"""

            content = re.sub(r'const handleExport = async \(\) => \{[\s\S]*?\n    \};', new_handle_export, content)

            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(content)
            print(f"Fixed Payroll export in {filepath}")

print("Pay files processing complete.")
