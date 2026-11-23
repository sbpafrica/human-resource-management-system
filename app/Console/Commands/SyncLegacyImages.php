<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Services\EmployeeImageService;
use Illuminate\Support\Facades\DB;

class SyncLegacyImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sbp:sync-legacy-images {--staffid= : Sync only for a specific staff ID} {--limit= : Limit the number of records to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download and cache all legacy employee photos, signatures, and Ghana Card images from hub.sbpafrica.group for local VPS hosting';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $staffId = $this->option('staffid');
        $limit = $this->option('limit');

        $query = Employee::query();

        if ($staffId) {
            $query->where('staffid', $staffId);
        } else {
            $query->where(function ($q) {
                $q->whereNotNull('profilepic')->where('profilepic', '!=', '')
                  ->orWhereNotNull('signature')->where('signature', '!=', '')
                  ->orWhereNotNull('ghanafront')->where('ghanafront', '!=', '')
                  ->orWhereNotNull('ghanaback')->where('ghanaback', '!=', '');
            });
        }

        if ($limit) {
            $query->limit((int)$limit);
        }

        $total = $query->count();
        $this->info("Found {$total} employee record(s) to inspect for legacy images.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $synced = 0;
        $failed = 0;

        $query->chunk(100, function ($employees) use ($bar, &$synced, &$failed) {
            foreach ($employees as $employee) {
                $fields = [
                    'profilepic' => $employee->profilepic,
                    'signature'  => $employee->signature,
                    'ghanafront' => $employee->ghanafront,
                    'ghanaback'  => $employee->ghanaback,
                ];

                foreach ($fields as $key => $filename) {
                    if (!empty($filename) && $filename !== '0' && $filename !== 'N/A') {
                        $path = EmployeeImageService::getLocalPathOrFetch($filename, $key);
                        if ($path && file_exists($path)) {
                            $synced++;
                        } else {
                            $failed++;
                        }
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Image sync complete! Synced/Verified: {$synced}, Missed/Unavailable: {$failed}.");
        $this->info("Images are stored in: " . public_path('images/melcomcon'));

        return Command::SUCCESS;
    }
}
