<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Birthday SMS: send daily at 08:00 AM ─────────────────────────────────────
Schedule::command('birthday:send')->dailyAt('08:00');

// ── Birthday SMS: retry failed every 4 hours (catches recharged balances) ─────
Schedule::command('birthday:retry-failed')->everyFourHours();

// ── Test Super Admin Quick Provisioning ──────────────────────────────────────
Artisan::command('admin:create-superadmin {email=superadmin@hrs.test} {password=Password123!}', function ($email, $password) {
    $user = \App\Models\User::updateOrCreate(
        ['email' => $email],
        [
            'name' => 'Test Super Admin',
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'is_super_admin' => 1,
            'email_verified_at' => now(),
        ]
    );

    try {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin']);
        $user->assignRole($role);
    } catch (\Throwable $e) {
        // Spatie role table may not be migrated in all local environments
    }

    $this->info("✓ Super Admin user ready:");
    $this->line("  Email:    {$email}");
    $this->line("  Password: {$password}");
    $this->line("  Role:     is_super_admin = 1");
})->purpose('Create or reset a test Super Admin user');
