<?php

use App\Http\Controllers\Api\V1\AssistantController;
use App\Http\Controllers\Api\V1\AttendanceApiController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\BankDetailApiController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\LeaveController;
use App\Http\Controllers\Api\V1\PayslipApiController;
use App\Http\Controllers\Api\V1\PetraPaymentApiController;
use App\Http\Controllers\Api\V1\PinCodeApiController;
use App\Http\Controllers\Api\V1\ReceiptApiController;
use App\Http\Controllers\Api\V1\SalaryPaymentApiController;
use App\Http\Controllers\Api\V1\SsnitPaymentApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API Routes (v1)
|--------------------------------------------------------------------------
|
| Versioned REST API endpoints for the HRS Mobile Application.
|
*/

Route::prefix('v1')->group(function () {
    // Health check endpoint
    Route::get('/health', function () {
        return response()->json([
            'status'    => 'ok',
            'timestamp' => now()->toIso8601String(),
            'service'   => 'HRS Mobile API',
            'version'   => 'v1',
        ]);
    });

    // ── Authentication Endpoints ───────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        });
    });

    // ── Protected Business Endpoints (Sanctum) ─────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        // Clients / Subsidiary Entities
        Route::get('/clients', [ClientController::class, 'index']);

        // Employee Directory & Profiles
        Route::prefix('employees')->group(function () {
            Route::get('/', [EmployeeController::class, 'index']);
            Route::get('/{staffid}', [EmployeeController::class, 'show']);
            Route::put('/{staffid}/profile', [EmployeeController::class, 'updateProfile']);
        });

        // Leave Management
        Route::prefix('leaves')->group(function () {
            Route::get('/', [LeaveController::class, 'index']);
            Route::post('/', [LeaveController::class, 'store']);
            Route::get('/{id}', [LeaveController::class, 'show']);
            Route::patch('/{id}/status', [LeaveController::class, 'updateStatus']);
        });

        // Payslips & Payroll Data
        Route::prefix('payslips')->group(function () {
            Route::get('/{staffid}', [PayslipApiController::class, 'getEmployeePayslip']);
            Route::get('/{staffid}/download', [PayslipApiController::class, 'downloadPdf']);
        });

        // Attendance Tracking
        Route::prefix('attendance')->group(function () {
            Route::get('/', [AttendanceApiController::class, 'index']);
            Route::post('/record', [AttendanceApiController::class, 'record']);
            Route::get('/summary', [AttendanceApiController::class, 'summary']);
        });

        // Receipts Management & Payment
        Route::prefix('receipts')->group(function () {
            Route::get('/', [ReceiptApiController::class, 'index']);
            Route::post('/', [ReceiptApiController::class, 'store']);
            Route::get('/verify-payment', [ReceiptApiController::class, 'verifyPayment']);
            Route::get('/{id}', [ReceiptApiController::class, 'show']);
            Route::put('/{id}', [ReceiptApiController::class, 'update']);
            Route::delete('/{id}', [ReceiptApiController::class, 'destroy']);
            Route::post('/{id}/initiate-payment', [ReceiptApiController::class, 'initiatePayment']);
        });

        // PIN Code Generation & SMS Dispatches
        Route::prefix('pincodes')->group(function () {
            Route::get('/', [PinCodeApiController::class, 'index']);
            Route::post('/generate', [PinCodeApiController::class, 'generate']);
            Route::post('/send-sms', [PinCodeApiController::class, 'sendSms']);
            Route::delete('/{staffid}', [PinCodeApiController::class, 'destroy']);
        });

        // SSNIT Deductions & Master Records
        Route::prefix('ssnit-payments')->group(function () {
            Route::get('/', [SsnitPaymentApiController::class, 'index']);
            Route::get('/master', [SsnitPaymentApiController::class, 'masterRecords']);
            Route::post('/master', [SsnitPaymentApiController::class, 'storeMasterRecord']);
        });

        // Petra Trust Provident Fund & Master Records
        Route::prefix('petra-payments')->group(function () {
            Route::get('/', [PetraPaymentApiController::class, 'index']);
            Route::get('/master', [PetraPaymentApiController::class, 'masterRecords']);
            Route::post('/master', [PetraPaymentApiController::class, 'storeMasterRecord']);
        });

        // Salary Payments (Bank / MoMo / Cash)
        Route::prefix('salary-payments')->group(function () {
            Route::get('/', [SalaryPaymentApiController::class, 'index']);
        });

        // Bank Details & Sorting Codes
        Route::prefix('bank-details')->group(function () {
            Route::get('/', [BankDetailApiController::class, 'index']);
            Route::post('/', [BankDetailApiController::class, 'store']);
            Route::get('/codes', [BankDetailApiController::class, 'bankCodes']);
        });

        // IT Assets Inventory
        Route::prefix('it-assets')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\ITAssetApiController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\ITAssetApiController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\ITAssetApiController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\ITAssetApiController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\ITAssetApiController::class, 'destroy']);
        });

        // Contracts & Renewals
        Route::prefix('contracts')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\ContractApiController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\ContractApiController::class, 'show']);
            Route::patch('/{id}/toggle-activation', [\App\Http\Controllers\Api\V1\ContractApiController::class, 'toggleActivation']);
        });

        // Tasks & Checklists
        Route::prefix('tasks')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\TaskApiController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\TaskApiController::class, 'store']);
            Route::patch('/{id}/toggle', [\App\Http\Controllers\Api\V1\TaskApiController::class, 'toggle']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\TaskApiController::class, 'destroy']);
        });

        // Birthday Engine
        Route::prefix('birthdays')->group(function () {
            Route::get('/upcoming', [\App\Http\Controllers\Api\V1\BirthdayApiController::class, 'upcoming']);
            Route::get('/logs', [\App\Http\Controllers\Api\V1\BirthdayApiController::class, 'logs']);
            Route::post('/send-sms', [\App\Http\Controllers\Api\V1\BirthdayApiController::class, 'sendSms']);
        });

        // New Joiners Onboarding
        Route::prefix('new-joiners')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\NewJoinersApiController::class, 'index']);
        });

        // AI Assistant (Grok API Integration)
        Route::prefix('assistant')->group(function () {
            Route::get('/threads', [AssistantController::class, 'indexThreads']);
            Route::post('/threads', [AssistantController::class, 'createThread']);
            Route::get('/threads/{id}/messages', [AssistantController::class, 'getMessages']);
            Route::post('/threads/{id}/messages', [AssistantController::class, 'sendMessage']);
            Route::delete('/threads/{id}', [AssistantController::class, 'deleteThread']);
        });
    });
});
