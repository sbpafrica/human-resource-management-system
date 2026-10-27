<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends BaseApiController
{
    /**
     * List active client companies.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Client::where('is_active', 1);

        // If not super admin and user has specific client assignments
        if ($user && !$user->is_super_admin && method_exists($user, 'clients') && $user->clients()->exists()) {
            $query = $user->clients()->where('is_active', 1);
        }

        $clients = rescue(
            fn() => $query->orderBy('display_order')->orderBy('client_name')->get([
                'id',
                'client_name',
                'short_name',
                'slug',
                'client_code',
                'is_active',
            ]),
            collect([]),
            false
        );

        return $this->sendResponse($clients, 'Clients retrieved successfully.');
    }
}
