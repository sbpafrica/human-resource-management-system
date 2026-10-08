<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Routing\Controllers\HasMiddleware;

class GenericContractController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:web',
        ];
    }

    private function getClientName(string $slug): string
    {
        $map = [
            'nyonkopa'      => 'Nyonkopa Cocoa Buying Ltd',
            'jaykay'        => 'Jay Kay',
            'pz'            => 'PZ Cussons Ghana Ltd',
            'twellium'      => 'Twellium Industrial Co.',
            'twelliumaccra' => 'Twellium Industrial Co. Accra',
            'shefer'        => 'Shefer Plastics Ltd',
            'fon'           => 'Fon Packaging',
            'a2feed'        => 'A2 Group (A2 Feed)',
            'ofibwh'        => 'OFI Bean Warehouse',
            'ofioffload'    => 'OFI Offloads',
            'ofiexternal'   => 'OFI Hygiene External',
            'ofihygiene'    => 'OFI Hygiene',
            'ofitemp'       => 'OFI Tempering',
            'honda'         => 'Honda Manufacturing GH Ltd',
            'bunge'         => 'Bunge West Africa',
            'inhouse'       => 'Inhouse',
            'a2soya'        => 'A2 Soya',
            'xiaomi'        => 'Xiaomi Ghana',
            'olamfood'      => 'Olam Food Ingredients',
            'gmoney'        => 'G-Money Financial Services',
            'avara'         => 'Avara RC Limited',
            'transsion-bfa' => 'Transsion BFA',
            'transsion'     => 'Transsion Technology',
            'melcom'        => 'Melcom Ghana Limited',
        ];

        return $map[strtolower($slug)] ?? ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    public function contracts(string $client)
    {
        $clientName = $this->getClientName($client);

        return Inertia::render('Contracts/EmptyState', [
            'clientName' => $clientName,
            'clientSlug' => $client,
            'type'       => 'contracts',
        ]);
    }

    public function renewals(string $client)
    {
        $clientName = $this->getClientName($client);

        return Inertia::render('Contracts/EmptyState', [
            'clientName' => $clientName,
            'clientSlug' => $client,
            'type'       => 'renewals',
        ]);
    }

    public function petrapay(string $client)
    {
        $clientName = $this->getClientName($client);

        return Inertia::render('Contracts/EmptyState', [
            'clientName' => $clientName,
            'clientSlug' => $client,
            'type'       => 'petra',
        ]);
    }
}
