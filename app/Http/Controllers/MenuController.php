<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    public function getMenu()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['menu' => []]);
        }

        $baseItems = [];

        // Dashboard – always show
        if ($user->can('view dashboard')) {
            $baseItems[] = [
                'title' => 'Dashboard',
                'href' => '/dashboard',
                'icon' => 'LayoutGrid',
            ];
        }

        // Clients page
        if ($user->can('view clients')) {
            $baseItems[] = [
                'title' => 'Clients',
                'href' => '/clients',
                'icon' => 'Building2',
            ];
        }

        // Admin Management – only for those who can manage users
        if ($user->can('manage users')) {
            $baseItems[] = [
                'title' => 'Admin Management',
                'icon' => 'Users',
                'children' => [
                    ['title' => 'Users', 'href' => '/admin/users'],
                    ['title' => 'Roles', 'href' => '/admin/roles'],
                ],
            ];
        }

        // Dynamic Client-Based Sections (Salary, SSNIT, PETRA, Payslips)
        $allowedClients = $user->is_super_admin
            ? \App\Models\Client::where('is_active', 1)->orderBy('display_order')->get()
            : $user->clients()->where('is_active', 1)->orderBy('display_order')->get();

        if ($allowedClients->count() > 0) {
            $salaryChildren = $allowedClients->map(function ($client) {
                return [
                    'title' => $client->short_name ?? $client->client_name,
                    'href' => '/' . $client->slug . 'pay',
                ];
            })->toArray();

            $ssnitChildren = $allowedClients->map(function ($client) {
                return [
                    'title' => $client->short_name ?? $client->client_name,
                    'href' => '/' . $client->slug . 'ssnitpay',
                ];
            })->toArray();

            $petraChildren = $allowedClients->map(function ($client) {
                return [
                    'title' => $client->short_name ?? $client->client_name,
                    'href' => '/' . $client->slug . 'petrapay',
                ];
            })->toArray();

            $payslipChildren = $allowedClients->map(function ($client) {
                return [
                    'title' => $client->short_name ?? $client->client_name,
                    'href' => '/payslips/' . Str::lower(str_replace(' ', '', $client->short_name ?? $client->client_name)),
                ];
            })->toArray();

            if ($user->can('view payroll')) {
                $baseItems[] = [
                    'title' => 'Salary Payment',
                    'icon' => 'FileText',
                    'children' => $salaryChildren,
                ];
            }

            if ($user->can('view ssnit')) {
                $baseItems[] = [
                    'title' => 'SSNIT Payment',
                    'icon' => 'FileSpreadsheet',
                    'children' => $ssnitChildren,
                ];
            }

            if ($user->can('view petra')) {
                $baseItems[] = [
                    'title' => 'PETRA Payment',
                    'icon' => 'FileSpreadsheet',
                    'children' => $petraChildren,
                ];
            }

            if ($user->can('view payslips')) {
                $baseItems[] = [
                    'title' => 'Payslips',
                    'icon' => 'Receipt',
                    'children' => $payslipChildren,
                ];
            }
        }

        // Add other static sections you still want (Leave, Contracts, etc.)
        // ... copy from your old array and wrap with permission checks

        return response()->json(['menu' => $baseItems]);
    }
}
