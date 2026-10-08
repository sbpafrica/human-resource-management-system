<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class TenantService
{
    public static function switchToTenant($dbname)
    {
        if (!$dbname || $dbname === 'placeholder') {
            return self::switchToMain();
        }

        Config::set('database.connections.tenant.database', $dbname);
        DB::purge('tenant');
        DB::setDefaultConnection('tenant');
    }

    public static function switchToMain()
    {
        DB::setDefaultConnection('mysql'); // your main DB
    }
}
