import os
import glob
import re

pages_dir = "resources/js/pages"

# Find all *ssnit Index.tsx files
ssnit_files = glob.glob(os.path.join(pages_dir, "*ssnit*", "Index.tsx")) + glob.glob(os.path.join(pages_dir, "*Ssnit*", "Index.tsx"))

for filepath in ssnit_files:
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    filename = os.path.basename(os.path.dirname(filepath))

    # Check if handleExport has broken response.json()
    if 'const records: Record[] = await response.json();' in content or 'await response.json()' in content:
        # Replace the handleExport function body with clean blob download logic
        # Find the route name used in fetch
        route_match = re.search(r"route\(\s*['\"]([^'\"]+)['\"]", content)
        route_name = route_match.group(1) if route_match else ""

        if route_name:
            new_handle_export = f"""const handleExport = async () => {{
        try {{
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const [sortColumn, sortOrder] = sort.split(':');

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
            
            # Replace old handleExport
            content = re.sub(r'const handleExport = async \(\) => \{[\s\S]*?\n    \};', new_handle_export, content)

            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(content)
            print(f"Fixed SSNIT export in {filepath}")

print("SSNIT files processing complete.")
