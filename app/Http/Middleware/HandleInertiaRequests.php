<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * The asset version is used by Inertia to detect when the server has been
     * redeployed while a user still has the old page open.
     *
     * When the version changes (e.g. after a deploy), Inertia automatically
     * forces a full page reload on the next navigation instead of silently
     * using the stale cached assets — which was causing blank/error screens.
     *
     * We use the Vite manifest hash so this automatically updates on every
     * `npm run build`. Falls back to APP_KEY if the manifest doesn't exist.
     */
    public function version(Request $request): ?string
    {
        $manifestPath = public_path('build/manifest.json');

        if (file_exists($manifestPath)) {
            return md5_file($manifestPath);
        }

        // Fallback: use APP_KEY so it's always set
        return config('app.key');
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'name' => config('app.name'),

            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],

            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',

            'flash' => [
                'success' => fn () => $request->hasSession() ? $request->session()->get('success') : null,
                'error'   => fn () => $request->hasSession() ? $request->session()->get('error') : null,
            ],

            // Pass session expiry status to frontend so it can show the modal
            'sessionExpired' => fn () => $request->hasSession() ? $request->session()->get('session_expired', false) : false,

            'auth' => $user ? [
                'user' => [
                    'id'              => $user->id,
                    'name'            => $user->name,
                    'email'           => $user->email,
                    'is_super_admin'  => (bool) $user->is_super_admin,

                    'roles'       => $user->getRoleNames()->toArray(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),

                    'clients' => $user->clients()
                        ->select([
                            'client.id as id',
                            'client.client_name',
                            'client.client_dbname',
                            'client.slug',
                            'client.short_name',
                            'client.color',
                            'client.is_active',
                            'client.display_order',
                        ])
                        ->orderBy('client.display_order')
                        ->get()
                        ->map(function ($client) {
                            $dbMap = [
                                'transsion'         => 'transsion',
                                'transsiontec'      => 'tecno',
                                'tecno'             => 'tecno',
                                'transsnet'         => 'transsnet',
                                'melcom'            => 'melcom',
                                'driverssnit'       => 'driver',
                                'driver'            => 'driver',
                                'centurypay'        => 'century',
                                'century'           => 'century',
                                'nyonkopa'          => 'nyonkopa',
                                'jkay'              => 'jaykay',
                                'jaykay'            => 'jaykay',
                                'pz'                => 'pz',
                                'twellium'          => 'twellium',
                                'twelliumaccra'     => 'twelliumaccra',
                                'shefer'            => 'shefer',
                                'fonpackage'        => 'fon',
                                'fon'               => 'fon',
                                'a2feed'            => 'a2feed',
                                'olambwh'           => 'ofibwh',
                                'ofibwh'            => 'ofibwh',
                                'offload'           => 'ofioffload',
                                'ofioffload'        => 'ofioffload',
                                'hygieneexternal'   => 'ofiexternal',
                                'ofiexternal'       => 'ofiexternal',
                                'olamhygiene'       => 'ofihygiene',
                                'ofihygiene'        => 'ofihygiene',
                                'olamtem'           => 'ofitemp',
                                'ofitemp'           => 'ofitemp',
                                'honda'             => 'honda',
                                'bungegh'           => 'bunge',
                                'bunge'             => 'bunge',
                                'inhouse'           => 'inhouse',
                                'a2soya'            => 'a2soya',
                                'xiaomi'            => 'xiaomi',
                                'olamfood'          => 'olamfood',
                                'gmoney'            => 'gmoney',
                                'avara'             => 'avara',
                                'transburkina'      => 'transsion-bfa',
                                'civ'               => 'civ',
                                'togobunge'         => 'togobunge',
                                'bungebenin'        => 'beninbunge',
                            ];

                            $slugMap = [
                                'transsion-tecnology-gh-ltd'         => 'transsion',
                                'good-ease-technology-gh-ltd'        => 'transsion',
                                'good-ease'                          => 'transsion',
                                'tecno-mobile-ltd'                   => 'tecno',
                                'unitycorp-technology-limited'       => 'tecno',
                                'unitycorp'                          => 'tecno',
                                'unity-corp'                         => 'tecno',
                                'transsnet-ltd'                      => 'transsnet',
                                'melcom'                             => 'melcom',
                                'melcom-drivers'                     => 'driver',
                                'century'                            => 'century',
                                'nyonkopa-cocoa-buying-ltd'          => 'nyonkopa',
                                'jay-kay'                            => 'jaykay',
                                'pz-cussons-ghana-ltd'               => 'pz',
                                'twellium-industrial-company-ghana'  => 'twellium',
                                'twellium-industrial-company-accra'  => 'twelliumaccra',
                                'shefer-plastics-ltd'                => 'shefer',
                                'fon-packaging'                      => 'fon',
                                'a2-group-of-companies-a2-feed'      => 'a2feed',
                                'ofi-bean-warehouse'                 => 'ofibwh',
                                'ofi-offloads'                       => 'ofioffload',
                                'ofi-hygiene-external'               => 'ofiexternal',
                                'ofi-hygiene'                        => 'ofihygiene',
                                'ofi-tempering'                      => 'ofitemp',
                                'honda-manufacturing-gh-ltd'         => 'honda',
                                'bunge'                              => 'bunge',
                                'inhouse'                            => 'inhouse',
                                'a2-soya'                            => 'a2soya',
                                'xiaomi-ghana'                       => 'xiaomi',
                                'olam-food-ingredients'              => 'olamfood',
                                'g-money-financial-services-limited' => 'gmoney',
                                'avara-rc-limited'                   => 'avara',
                                'transsion-bfa'                      => 'transsion-bfa',
                                'civ-bunge'                          => 'civ',
                                'togo-bunge'                         => 'togobunge',
                                'benin-bunge'                        => 'beninbunge',
                            ];

                            $dbname = strtolower($client->client_dbname ?? '');
                            $slug = strtolower($client->slug ?? '');

                            $client->route_slug = $dbMap[$dbname] ?? $slugMap[$slug] ?? $dbMap[$slug] ?? $slugMap[$dbname] ?? $dbname ?: $slug;

                            return $client;
                        })
                        ->toArray(),
                ],
            ] : null,
        ];
    }
}
