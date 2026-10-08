<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- Inline script to detect system dark mode preference -->
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';
                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        <!-- Inline style for background color and table -->
        <style>
            html {
                background-color: oklch(1 0 0);
            }
            html.dark {
                background-color: oklch(0.145 0 0);
            }
            /* Dark mode override for #e3ecfd */
            .dark [style*="background-color: #e3ecfd"] {
                background-color: #1e2a44 !important;
            }
            /* Table styling with Tailwind */
            .react-table {
                @apply w-full text-gray-900 dark:text-gray-100 border-collapse text-sm;
            }
            .react-table thead th {
                @apply bg-gray-100 dark:bg-gray-800 font-semibold text-left p-2;
            }
            .react-table th, .react-table td {
                @apply border border-gray-200 dark:border-gray-700 p-2;
            }
            .react-table tbody tr:hover {
                @apply bg-gray-50 dark:bg-gray-700;
            }
            /* Modal styling */
            .modal-content {
                @apply bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-4xl p-6;
            }
            .modal-overlay {
                @apply fixed inset-0 bg-black/50 backdrop-blur-sm z-50;
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

<link rel="icon" href="/hrsfavicon.png" type="image/png">
        <link rel="apple-touch-icon" href="/hrsfavicon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite([
            'resources/css/app.css',
            'resources/js/app.tsx',
            "resources/js/pages/{$page['component']}.tsx"
        ])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
