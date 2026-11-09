<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\MelcomContractRenewal;
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
        $dbClient = \App\Models\Client::where('client_dbname', $slug)
            ->orWhere('slug', $slug)
            ->first();

        if ($dbClient) {
            return $dbClient->short_name ?: $dbClient->client_name;
        }

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
            'compugh'       => 'CompuGH',
        ];

        return $map[strtolower($slug)] ?? ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    public function contracts(Request $request, string $client)
    {
        $clientName = $this->getClientName($client);

        $query = Contract::query();
        $query->where(function ($q) use ($clientName, $client) {
            $q->where('client', 'like', "%{$clientName}%")
              ->orWhere('client', 'like', "%{$client}%");
        });

        $totalRecords = (clone $query)->count();
        $searchCount = null;

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('employeename', 'like', "%{$search}%")
                  ->orWhere('position1', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
            $searchCount = (clone $query)->count();
        }

        $sort = $request->input('sort', 'employeename');
        $order = $request->input('order', 'asc');
        $allowedSorts = ['id', 'employeename', 'client', 'location', 'position1', 'startdate', 'enddate'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'employeename';
        }
        $query->orderBy($sort, $order);

        $contracts = $query->paginate(10)->withQueryString();

        return Inertia::render('Contracts/Index', [
            'contracts'    => $contracts,
            'filters'      => [
                'search' => $search ?? '',
                'sort'   => $sort,
                'order'  => $order,
            ],
            'totalRecords' => $totalRecords,
            'searchCount'  => $searchCount,
            'clientName'   => $clientName,
            'flash'        => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function renewals(Request $request, string $client)
    {
        $clientName = $this->getClientName($client);

        $query = MelcomContractRenewal::query();
        // Since contractmelcomemprenew doesn't store client name by default, 
        // we can filter if client is Melcom or match employername/position1 if applicable.
        $isMelcom = stripos($client, 'melcom') !== false || stripos($clientName, 'melcom') !== false;
        if (!$isMelcom) {
            $query->whereRaw('1 = 0'); // Empty set for other clients
        }

        $totalRecords = (clone $query)->count();
        $searchCount = null;

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('employeename', 'like', "%{$search}%")
                  ->orWhere('position1', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
            $searchCount = (clone $query)->count();
        }

        $sort = $request->input('sort', 'employeename');
        $order = $request->input('order', 'asc');
        $allowedSorts = ['id', 'employeename', 'position1', 'startdate', 'issuedate', 'hremail', 'email'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'employeename';
        }
        $query->orderBy($sort, $order);

        $renewals = $query->paginate(10)
            ->withQueryString()
            ->through(function ($item) {
                $arr = $item->toArray();
                $arr['primary_id'] = $item->id;
                return $arr;
            });

        return Inertia::render('ContractsRenewal/Index', [
            'renewals'     => $renewals,
            'filters'      => [
                'search' => $search ?? '',
                'sort'   => $sort,
                'order'  => $order,
            ],
            'totalRecords' => $totalRecords,
            'searchCount'  => $searchCount,
            'clientName'   => $clientName,
            'flash'        => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
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

    public function stat(string $client)
    {
        $clientName = $this->getClientName($client);

        return Inertia::render('Contracts/EmptyState', [
            'clientName' => $clientName,
            'clientSlug' => $client,
            'type'       => 'stat',
        ]);
    }
}
