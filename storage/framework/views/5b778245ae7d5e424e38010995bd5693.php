<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['dark' => ($appearance ?? 'system') == 'dark']); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- Inline script to detect system dark mode preference -->
        <script>
            (function() {
                const appearance = '<?php echo e($appearance ?? "system"); ?>';
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

        <title inertia><?php echo e(config('app.name', 'Laravel')); ?></title>

<link rel="icon" href="/hrsfavicon.png" type="image/png">
        <link rel="apple-touch-icon" href="/hrsfavicon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        <?php echo app('Tighten\Ziggy\BladeRouteGenerator')->generate(); ?>
        <?php echo app('Illuminate\Foundation\Vite')->reactRefresh(); ?>
        <?php echo app('Illuminate\Foundation\Vite')([
            'resources/css/app.css',
            'resources/js/app.tsx',
            "resources/js/pages/{$page['component']}.tsx"
        ]); ?>
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->head; } ?>
    </head>
    <body class="font-sans antialiased">
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } else { ?><div id="app" data-page="<?php echo e(json_encode($page)); ?>"></div><?php } ?>
    </body>
</html>
<?php /**PATH /home/u1710176/hrs.sbpafrica.group/resources/views/app.blade.php ENDPATH**/ ?>