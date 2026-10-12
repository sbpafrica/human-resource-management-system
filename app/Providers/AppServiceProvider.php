<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (app()->environment('production') || request()->header('X-Forwarded-Proto') === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        Validator::extend('no_french_accents', function ($attribute, $value, $parameters, $validator) {
            return !preg_match('/[ÂÊÎÔÛ]/u', $value);
        }, 'The :attribute field contains invalid French accented characters (Â, Ê, Î, Ô, Û).');
    }
}
