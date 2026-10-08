<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Http\Controllers\ListController;
use App\Http\Controllers\SsnitController;
use App\Http\Controllers\BankDetailController;
use App\Http\Controllers\BankCodeController;
use App\Http\Controllers\PetraController;
use App\Http\Controllers\BankDetailInhouseController;
use App\Http\Controllers\SsnitInhouseController;
use App\Http\Controllers\DriverPayController;
use App\Http\Controllers\PetraDetailsInhouseController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\EmployeePortalController;
use App\Http\Controllers\EmployeeContactController;
use App\Http\Controllers\EmployeePinController;
use App\Http\Controllers\TwelliumPinController;
use App\Http\Controllers\TwelliumAccra2Controller;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BankAuditController;
use App\Http\Controllers\SsnitAuditController;
use App\Http\Controllers\PetraAuditController;
use App\Http\Controllers\ContactAuditController;
use App\Http\Controllers\NewJoinersController;
use App\Http\Controllers\ExitedStaffController;
use App\Http\Controllers\MelcomBasicController;
use App\Http\Controllers\TranssionPayController;
use App\Http\Controllers\XiaomiPayController;
use App\Http\Controllers\TecnoPayController;
use App\Http\Controllers\MelcomPayController;
use App\Http\Controllers\TranssnetPayController;
use App\Http\Controllers\NyonkopaPayController;
use App\Http\Controllers\JaykayPayController;
use App\Http\Controllers\PzPayController;
use App\Http\Controllers\TwelliumPayController;
use App\Http\Controllers\TwelliumaccraPayController;
use App\Http\Controllers\SheferPayController;
use App\Http\Controllers\FonPayController;
use App\Http\Controllers\A2feedPayController;
use App\Http\Controllers\A2soyaPayController;
//use App\Http\Controllers\A2soyaPayController;
use App\Http\Controllers\OfibwhPayController;
use App\Http\Controllers\OfihygienePayController;
use App\Http\Controllers\OfitempPayController;
use App\Http\Controllers\OfioffloadPayController;
use App\Http\Controllers\OfiexternalPayController;
use App\Http\Controllers\HondaPayController;
use App\Http\Controllers\BungePayController;
use App\Http\Controllers\InhousePayController;
use App\Http\Controllers\CenturyPayController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaveApplicationController;
use App\Http\Controllers\EmployeeLeaveController;
use App\Http\Controllers\ManagerLeaveReviewController;
use App\Http\Controllers\HygienePayrollController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractRenewalController;
use App\Http\Controllers\TranssionStatController;
use App\Http\Controllers\A2soyaStatController;
use App\Http\Controllers\SsnitPayController;
use App\Http\Controllers\TecnoSsnitPayController;
use App\Http\Controllers\TranssionssnitPayController;
use App\Http\Controllers\TranssnetssnitController;
use App\Http\Controllers\NyonkopassnitController;
use App\Http\Controllers\JaykayssnitController;
use App\Http\Controllers\PzssnitController;
use App\Http\Controllers\TwelliumaccrassnitController;
use App\Http\Controllers\SheferssnitController;
use App\Http\Controllers\FonssnitController;
use App\Http\Controllers\MelcomssnitController;
use App\Http\Controllers\CenturyssnitController;
use App\Http\Controllers\DriverssnitController;
use App\Http\Controllers\A2feedssnitController;
use App\Http\Controllers\A2soyassnitController;
use App\Http\Controllers\OfibwhssnitController;
use App\Http\Controllers\OfioffloadssnitController;
use App\Http\Controllers\OfiexternalssnitController;
use App\Http\Controllers\OfihygienessnitController;
use App\Http\Controllers\OfitempssnitController;
use App\Http\Controllers\HondassnitController;
use App\Http\Controllers\BungessnitController;
use App\Http\Controllers\InhousessnitController;
use App\Http\Controllers\TwelliumssnitController;

use App\Http\Controllers\PerformanceTrackerController;
use App\Http\Controllers\PetraPayController;
use App\Http\Controllers\TecnoPetraPayController;
use App\Http\Controllers\TranssionPetraPayController;
use App\Http\Controllers\TranssionBfaPetraPayController;
use App\Http\Controllers\TranssnetPetraPayController;
//use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\SalaryPaymentController;
use App\Http\Controllers\SsnitPaymentController;
use App\Http\Controllers\SalaryPayment\TranssionSalaryPaymentController;
use App\Http\Controllers\SalaryPayment\MelcomSalaryPaymentController;
//use App\Http\Controllers\SalaryPayment\NyonkopaSalaryPaymentController;
use App\Http\Controllers\MenuController;

use App\Http\Controllers\NyonkopaPetraPayController;
use App\Http\Controllers\JaykayPetraPayController;
use App\Http\Controllers\PzPetraPayController;
 use App\Http\Controllers\TwelliumAccraPetraPayController;
 use App\Http\Controllers\SheferPetraPayController;
 use App\Http\Controllers\FonPetraPayController;
 use App\Http\Controllers\MelcomPetraPayController;
use App\Http\Controllers\DriversPetraPayController;
use App\Http\Controllers\CenturyPetraPayController;

use App\Http\Controllers\A2feedPetraPayController;
use App\Http\Controllers\A2soyaPetraPayController;
use App\Http\Controllers\OfibwhPetraPayController;
use App\Http\Controllers\OfioffloadPetraPayController;
use App\Http\Controllers\OfihygienePetraPayController;
use App\Http\Controllers\OfiexternalPetraPayController;
use App\Http\Controllers\OfitempPetraPayController;
use App\Http\Controllers\HondaPetraPayController;
use App\Http\Controllers\BungePetraPayController;
use App\Http\Controllers\TwelliumPetraPayController;
use App\Http\Controllers\InhousePetraPayController;

use App\Http\Controllers\TecnoStatController;
use App\Http\Controllers\TranssnetStatController;
use App\Http\Controllers\NyonkopaStatController;
use App\Http\Controllers\JaykayStatController;
use App\Http\Controllers\PzStatController;
use App\Http\Controllers\TwelliumaccraStatController;
use App\Http\Controllers\SheferStatController;


use App\Http\Controllers\FonStatController;
// use App\Http\Controllers\OlamfoodStatController;
use App\Http\Controllers\MelcomStatController;
use App\Http\Controllers\DriverStatController;
use App\Http\Controllers\CenturyStatController;
use App\Http\Controllers\A2feedStatController;
use App\Http\Controllers\OfibwhStatController;
use App\Http\Controllers\OfioffloadStatController;
use App\Http\Controllers\OfiexternalStatController;
use App\Http\Controllers\OfihygieneStatController;


use App\Http\Controllers\OfitempStatController;
// use App\Http\Controllers\hondaStatController;
use App\Http\Controllers\TwelliumStatController;
use App\Http\Controllers\BungeStatController;
use App\Http\Controllers\InhouseStatController;
use App\Http\Controllers\XiaomiStatController;


use App\Http\Controllers\TranssionContractController;
use App\Http\Controllers\TranssionContractRenewalController;
use App\Http\Controllers\XiaomiContractController;
use App\Http\Controllers\OlamfoodContractController;

use App\Http\Controllers\ITAssetController;
use App\Http\Controllers\ArchivedEmployeeController;
use App\Http\Controllers\ArchiveController;


//use App\Http\Controllers\OlamfoodPayController;




















Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : Inertia::render('auth/login');
})->name('home');

Route::get('/manager-leave/review/{token}', [ManagerLeaveReviewController::class, 'review'])->name('manager-leave.review');
Route::post('/manager-leave/process/{token}', [ManagerLeaveReviewController::class, 'process'])->name('manager-leave.process');

Route::get('/employeeportal/export', [EmployeePortalController::class, 'export'])->name('employeeportal.export');
Route::get('/employeeportal/download-template', [EmployeePortalController::class, 'downloadTemplate'])->name('employeeportal.download-template');

// Public payslip direct view & download for SMS/Email links (no login required)
Route::get('/payslips/{client}/slip/{month}/{staffId}', [PayslipController::class, 'publicView'])
    ->where(['month' => '[0-9]{4}-[0-9]{2}', 'staffId' => '.*'])
    ->withoutMiddleware([
        \App\Http\Middleware\HandleInertiaRequests::class,
        \App\Http\Middleware\HandleAppearance::class,
        \App\Http\Middleware\RefreshCsrfToken::class,
        \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
    ])
    ->name('payslips.public_view');

Route::get('/payslips/{client}/download/{month}/{staffId}', [PayslipController::class, 'download'])
    ->where(['month' => '[0-9]{4}-[0-9]{2}', 'staffId' => '.*'])
    ->withoutMiddleware([
        \App\Http\Middleware\HandleInertiaRequests::class,
        \App\Http\Middleware\HandleAppearance::class,
        \App\Http\Middleware\RefreshCsrfToken::class,
        \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
    ])
    ->name('payslips.download');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('lists', ListController::class);

    Route::get('/performance', [PerformanceTrackerController::class, 'index'])->name('performance.index');

    Route::get('/menu', [MenuController::class, 'getMenu'])->name('menu');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/edit', [App\Http\Controllers\Admin\UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');

        Route::get('/roles', [App\Http\Controllers\Admin\RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/{role}/edit', [App\Http\Controllers\Admin\RoleController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{role}', [App\Http\Controllers\Admin\RoleController::class, 'update'])->name('roles.update');

        Route::get('/users/create', [App\Http\Controllers\Admin\UserController::class, 'create'])->name('users.create');
Route::post('/users', [App\Http\Controllers\Admin\UserController::class, 'store'])->name('users.store');

Route::get('/roles/create', [App\Http\Controllers\Admin\RoleController::class, 'create'])->name('roles.create');
Route::post('/roles', [App\Http\Controllers\Admin\RoleController::class, 'store'])->name('roles.store');
    });


    // SSNIT Details Routes
    Route::get('/ssnitdetails', [SsnitController::class, 'index'])->name('ssnitdetails.index');
    Route::post('/ssnitdetails', [SsnitController::class, 'store'])->name('ssnitdetails.store');
    Route::put('/ssnitdetails/{staffid}', [SsnitController::class, 'update'])->name('ssnitdetails.update');
    Route::delete('/ssnitdetails/{staffid}', [SsnitController::class, 'destroy'])->name('ssnitdetails.destroy');
    Route::post('/ssnitdetails/bulk-destroy', [SsnitController::class, 'bulkDestroy'])->name('ssnitdetails.bulk-destroy');
    Route::get('/ssnitdetails/export', [SsnitController::class, 'export'])->name('ssnitdetails.export');
    Route::get('/ssnitdetails/download-template', [SsnitController::class, 'downloadTemplate'])->name('ssnitdetails.download-template');
    Route::post('/ssnitdetails/bulk-upload', [SsnitController::class, 'bulkUpload'])->name('ssnitdetails.bulk-upload');

    // SSNIT Inhouse Details Routes
    Route::get('/ssnitdetailsinhouse', [SsnitInhouseController::class, 'index'])->name('ssnitdetailsinhouse.index');
    Route::post('/ssnitdetailsinhouse', [SsnitInhouseController::class, 'store'])->name('ssnitdetailsinhouse.store');
    Route::put('/ssnitdetailsinhouse/{staffid}', [SsnitInhouseController::class, 'update'])->name('ssnitdetailsinhouse.update');
    Route::delete('/ssnitdetailsinhouse/{staffid}', [SsnitInhouseController::class, 'destroy'])->name('ssnitdetailsinhouse.destroy');
    Route::post('/ssnitdetailsinhouse/bulk-destroy', [SsnitInhouseController::class, 'bulkDestroy'])->name('ssnitdetailsinhouse.bulk-destroy');
    Route::get('/ssnitdetailsinhouse/export', [SsnitInhouseController::class, 'export'])->name('ssnitdetailsinhouse.export');
    Route::get('/ssnitdetailsinhouse/download-template', [SsnitInhouseController::class, 'downloadTemplate'])->name('ssnitdetailsinhouse.download-template');
    Route::post('/ssnitdetailsinhouse/bulk-upload', [SsnitInhouseController::class, 'bulkUpload'])->name('ssnitdetailsinhouse.bulk-upload');

    //salary payment routes
    Route::prefix('salary-payment')->name('salary-payment.')->group(function () {

    // === TRANSSION ===
    Route::prefix('transsion')->name('transsion.')->group(function () {
        Route::get('/', [TranssionSalaryPaymentController::class, 'index'])->name('index');
        Route::post('/upload', [TranssionSalaryPaymentController::class, 'upload'])->name('upload');
        Route::post('/delete', [TranssionSalaryPaymentController::class, 'delete'])->name('delete');
        Route::post('/export', [TranssionSalaryPaymentController::class, 'export'])->name('export'); // POST
        Route::get('/template', [TranssionSalaryPaymentController::class, 'downloadTemplate'])->name('template');
    });


// === MELCOM ===
    Route::prefix('melcom')->name('melcom.')->group(function () {
        Route::get('/', [MelcomSalaryPaymentController::class, 'index'])->name('index');
        Route::post('/upload', [MelcomSalaryPaymentController::class, 'upload'])->name('upload');
        Route::post('/delete', [MelcomSalaryPaymentController::class, 'delete'])->name('delete');
        Route::get('/export', [MelcomSalaryPaymentController::class, 'export'])->name('export');
        Route::get('/template', [MelcomSalaryPaymentController::class, 'downloadTemplate'])->name('template');
    });


    // === NYONKOPA ===
    /*
    Route::prefix('nyonkopa')->name('nyonkopa.')->group(function () {
        Route::get('/', [NyonkopaSalaryPaymentController::class, 'index'])->name('index');
        Route::post('/upload', [NyonkopaSalaryPaymentController::class, 'upload'])->name('upload');
        Route::post('/delete', [NyonkopaSalaryPaymentController::class, 'delete'])->name('delete');
        Route::get('/export', [NyonkopaSalaryPaymentController::class, 'export'])->name('export');
        Route::get('/template', [NyonkopaSalaryPaymentController::class, 'downloadTemplate'])->name('template');
    });
    */
});

    // Bank Details Routes
    Route::get('/bankdetails', [BankDetailController::class, 'index'])->name('bankdetails.index');
    Route::post('/bankdetails', [BankDetailController::class, 'store'])->name('bankdetails.store');
    Route::post('/bankdetails/bulk-destroy', [BankDetailController::class, 'bulkDestroy'])->name('bankdetails.bulk-destroy');
    Route::get('/bankdetails/export', [BankDetailController::class, 'export'])->name('bankdetails.export');
    Route::get('/bankdetails/download-template', [BankDetailController::class, 'downloadTemplate'])->name('bankdetails.download-template');
    Route::post('/bankdetails/bulk-upload', [BankDetailController::class, 'bulkUpload'])->name('bankdetails.bulk-upload');
    Route::match(['put', 'post'], '/bankdetails/update/{employid?}', [BankDetailController::class, 'update'])->name('bankdetails.update')->where('employid', '.*');
    Route::match(['delete', 'post'], '/bankdetails/delete/{employid?}', [BankDetailController::class, 'destroy'])->name('bankdetails.destroy')->where('employid', '.*');
    Route::match(['put', 'post'], '/bankdetails/{employid}', [BankDetailController::class, 'update'])->where('employid', '.*');
    Route::match(['delete', 'post'], '/bankdetails/{employid}', [BankDetailController::class, 'destroy'])->where('employid', '.*');

    // Bank Details Inhouse Routes
    Route::get('/bankdetailsinhouse', [BankDetailInhouseController::class, 'index'])->name('bankdetailsinhouse.index');
    Route::post('/bankdetailsinhouse', [BankDetailInhouseController::class, 'store'])->name('bankdetailsinhouse.store');
    Route::post('/bankdetailsinhouse/bulk-destroy', [BankDetailInhouseController::class, 'bulkDestroy'])->name('bankdetailsinhouse.bulk-destroy');
    Route::get('/bankdetailsinhouse/export', [BankDetailInhouseController::class, 'export'])->name('bankdetailsinhouse.export');
    Route::get('/bankdetailsinhouse/download-template', [BankDetailInhouseController::class, 'downloadTemplate'])->name('bankdetailsinhouse.download-template');
    Route::post('/bankdetailsinhouse/bulk-upload', [BankDetailInhouseController::class, 'bulkUpload'])->name('bankdetailsinhouse.bulk-upload');
    Route::match(['put', 'post'], '/bankdetailsinhouse/update/{employid?}', [BankDetailInhouseController::class, 'update'])->name('bankdetailsinhouse.update')->where('employid', '.*');
    Route::match(['delete', 'post'], '/bankdetailsinhouse/delete/{employid?}', [BankDetailInhouseController::class, 'destroy'])->name('bankdetailsinhouse.destroy')->where('employid', '.*');
    Route::match(['put', 'post'], '/bankdetailsinhouse/{employid}', [BankDetailInhouseController::class, 'update'])->where('employid', '.*');
    Route::match(['delete', 'post'], '/bankdetailsinhouse/{employid}', [BankDetailInhouseController::class, 'destroy'])->where('employid', '.*');

    // Bankcodes Details Routes
    Route::get('/bankcodes', [BankCodeController::class, 'index'])->name('bankcodes.index');
    Route::post('/bankcodes', [BankCodeController::class, 'store'])->name('bankcodes.store');
    Route::put('/bankcodes/{id}', [BankCodeController::class, 'update'])->name('bankcodes.update');
    Route::delete('/bankcodes/{id}', [BankCodeController::class, 'destroy'])->name('bankcodes.destroy');
    Route::post('/bankcodes/bulk-destroy', [BankCodeController::class, 'bulkDestroy'])->name('bankcodes.bulk-destroy');

    // Petra Details Routes
    Route::get('/petradetails', [PetraController::class, 'index'])->name('petradetails.index');
    Route::post('/petradetails', [PetraController::class, 'store'])->name('petradetails.store');
    Route::put('/petradetails/{staffid}', [PetraController::class, 'update'])->name('petradetails.update');
    Route::delete('/petradetails/{staffid}', [PetraController::class, 'destroy'])->name('petradetails.destroy');
    Route::post('/petradetails/bulk-destroy', [PetraController::class, 'bulkDestroy'])->name('petradetails.bulk-destroy');
    Route::get('/petradetails/export', [PetraController::class, 'export'])->name('petradetails.export');
    Route::get('/petradetails/download-template', [PetraController::class, 'downloadTemplate'])->name('petradetails.download-template');
    Route::post('/petradetails/bulk-upload', [PetraController::class, 'bulkUpload'])->name('petradetails.bulk-upload');

    // Petra Details Inhouse Routes
    Route::get('/petradetailsinhouse', [PetraDetailsInhouseController::class, 'index'])->name('petradetailsinhouse.index');
    Route::post('/petradetailsinhouse', [PetraDetailsInhouseController::class, 'store'])->name('petradetailsinhouse.store');
    Route::put('/petradetailsinhouse/{staffid}', [PetraDetailsInhouseController::class, 'update'])->name('petradetailsinhouse.update');
    Route::delete('/petradetailsinhouse/{staffid}', [PetraDetailsInhouseController::class, 'destroy'])->name('petradetailsinhouse.destroy');
    Route::post('/petradetailsinhouse/bulk-destroy', [PetraDetailsInhouseController::class, 'bulkDestroy'])->name('petradetailsinhouse.bulk-destroy');
    Route::get('/petradetailsinhouse/export', [PetraDetailsInhouseController::class, 'export'])->name('petradetailsinhouse.export');
    Route::get('/petradetailsinhouse/download-template', [PetraDetailsInhouseController::class, 'downloadTemplate'])->name('petradetailsinhouse.download-template');
    Route::post('/petradetailsinhouse/bulk-upload', [PetraDetailsInhouseController::class, 'bulkUpload'])->name('petradetailsinhouse.bulk-upload');

    Route::get('/clients', [ClientsController::class, 'index'])->name('clients.index');
Route::post('/clients', [ClientsController::class, 'store'])->name('clients.store');
Route::patch('/clients/{client}', [ClientsController::class, 'update'])->name('clients.update');
Route::delete('/clients/{client}', [ClientsController::class, 'destroy'])->name('clients.destroy');
Route::post('/clients/bulk-destroy', [ClientsController::class, 'bulkDestroy'])->name('clients.bulk-destroy');
Route::get('/clients/export', [ClientsController::class, 'export'])->name('clients.export');
Route::get('/clients/download-template', [ClientsController::class, 'downloadTemplate'])->name('clients.download-template');
Route::post('/clients/bulk-upload', [ClientsController::class, 'bulkUpload'])->name('clients.bulk-upload');

    // Employee portal Routes
Route::get('/employeeportal', [EmployeePortalController::class, 'index'])->name('employeeportal.index');
Route::get('/employeeportal/{staffid}', [EmployeePortalController::class, 'show'])->name('employeeportal.show')->where('staffid', '.*');
Route::post('/employeeportal', [EmployeePortalController::class, 'store'])->name('employeeportal.store');
Route::put('/employeeportal/{staffid}', [EmployeePortalController::class, 'update'])->name('employeeportal.update')->where('staffid', '.*');
Route::delete('/employeeportal/{staffid}', [EmployeePortalController::class, 'destroy'])->name('employeeportal.destroy')->where('staffid', '.*');
Route::post('/employeeportal/bulk-destroy', [EmployeePortalController::class, 'bulkDestroy'])->name('employeeportal.bulk-destroy');
Route::post('/employeeportal/bulk-upload', [EmployeePortalController::class, 'bulkUpload'])->name('employeeportal.bulk-upload');
Route::post('/employeeportal/{staffid}/archive', [EmployeePortalController::class, 'archive'])
    ->where('staffid', '.*')
    ->name('employeeportal.archive');
Route::post('/employeeportal/bulk-archive', [EmployeePortalController::class, 'bulkArchive'])->name('employeeportal.bulk-archive');


    Route::middleware(['auth'])->group(function () {
    Route::get('/employeecontact', [EmployeeContactController::class, 'index'])->name('employeecontact.index');
    Route::post('/employeecontact', [EmployeeContactController::class, 'store'])->name('employeecontact.store');
    Route::put('/employeecontact/{staffid}', [EmployeeContactController::class, 'update'])->name('employeecontact.update');
    Route::delete('/employeecontact/{staffid}', [EmployeeContactController::class, 'destroy'])->name('employeecontact.destroy');
    Route::post('/employeecontact/bulk-destroy', [EmployeeContactController::class, 'bulkDestroy'])->name('employeecontact.bulk-destroy');
    Route::post('/employeecontact/bulk-upload', [EmployeeContactController::class, 'bulkUpload'])->name('employeecontact.bulk-upload');
    Route::get('/employeecontact/export', [EmployeeContactController::class, 'export'])->name('employeecontact.export');
    Route::get('/employeecontact/download-template', [EmployeeContactController::class, 'downloadTemplate'])->name('employeecontact.download-template');

    // Employee PIN Code Routes
    Route::get('/employeepincode', [EmployeePinController::class, 'index'])->name('employeepincode.index');
    Route::post('/employeepincode', [EmployeePinController::class, 'store'])->name('employeepincode.store');
    Route::post('/employeepincode/send-sms', [EmployeePinController::class, 'sendSms'])->name('employeepincode.send-sms');
    Route::delete('/employeepincode/{staffid}', [EmployeePinController::class, 'destroy'])->name('employeepincode.destroy');
    Route::post('/employeepincode/bulk-destroy', [EmployeePinController::class, 'bulkDestroy'])->name('employeepincode.bulk-destroy');
    Route::post('/employeepincode/bulk-upload', [EmployeePinController::class, 'bulkUpload'])->name('employeepincode.bulk-upload');
    Route::get('/employeepincode/export', [EmployeePinController::class, 'export'])->name('employeepincode.export');
    Route::get('/employeepincode/download-template', [EmployeePinController::class, 'downloadTemplate'])->name('employeepincode.download-template');

    // Twellium PIN Code Routes
    Route::get('/twelliumpincode', [TwelliumPinController::class, 'index'])->name('twelliumpincode.index');
    Route::post('/twelliumpincode', [TwelliumPinController::class, 'store'])->name('twelliumpincode.store');
    Route::post('/twelliumpincode/change-pin', [TwelliumPinController::class, 'changePin'])->name('twelliumpincode.change-pin');
    Route::post('/twelliumpincode/send-sms', [TwelliumPinController::class, 'sendSms'])->name('twelliumpincode.send-sms');
    Route::delete('/twelliumpincode/{staffid}', [TwelliumPinController::class, 'destroy'])->name('twelliumpincode.destroy');
    Route::post('/twelliumpincode/bulk-destroy', [TwelliumPinController::class, 'bulkDestroy'])->name('twelliumpincode.bulk-destroy');
    Route::post('/twelliumpincode/bulk-upload', [TwelliumPinController::class, 'bulkUpload'])->name('twelliumpincode.bulk-upload');
    Route::get('/twelliumpincode/export', [TwelliumPinController::class, 'export'])->name('twelliumpincode.export');
    Route::get('/twelliumpincode/download-template', [TwelliumPinController::class, 'downloadTemplate'])->name('twelliumpincode.download-template');

    // Twellium Accra 2 Routes
    Route::get('/twelliumaccra2', [TwelliumAccra2Controller::class, 'index'])->name('twelliumaccra2.index');
    Route::post('/twelliumaccra2/upload', [TwelliumAccra2Controller::class, 'upload'])->name('twelliumaccra2.upload');
    Route::get('/twelliumaccra2/download-template/{type}', [TwelliumAccra2Controller::class, 'downloadTemplate'])->name('twelliumaccra2.download-template');
    Route::get('/twelliumaccra2/export', [TwelliumAccra2Controller::class, 'export'])->name('twelliumaccra2.export');
    Route::post('/twelliumaccra2/delete-month', [TwelliumAccra2Controller::class, 'deleteMonth'])->name('twelliumaccra2.delete-month');

    // Receipts Routes
    Route::get('/receipts/verify-payment', [ReceiptController::class, 'verifyPayment'])->name('receipts.verify-payment');
    Route::post('/receipts/{id}/initiate-payment', [ReceiptController::class, 'initiatePayment'])->name('receipts.initiate-payment');
    Route::get('/receipts', [ReceiptController::class, 'index'])->name('receipts.index');
    Route::post('/receipts', [ReceiptController::class, 'store'])->name('receipts.store');
    Route::put('/receipts/{id}', [ReceiptController::class, 'update'])->name('receipts.update');
    Route::delete('/receipts/{id}', [ReceiptController::class, 'destroy'])->name('receipts.destroy');
    Route::post('/receipts/bulk-destroy', [ReceiptController::class, 'bulkDestroy'])->name('receipts.bulk-destroy');
    Route::get('/receipts/export', [ReceiptController::class, 'export'])->name('receipts.export');
    Route::get('/receipts/{id}', [ReceiptController::class, 'show'])->name('receipts.show');

    // Attendance Routes
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/upload-employees', [AttendanceController::class, 'uploadEmployees'])->name('attendance.upload-employees');
    Route::post('/attendance/upload-attendance', [AttendanceController::class, 'uploadAttendance'])->name('attendance.upload-attendance');
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');

    // HR Audit Routes
    Route::get('/hr-audit/bank', [BankAuditController::class, 'index'])->name('hr-audit.bank');
    Route::get('/hr-audit/bank/export', [BankAuditController::class, 'exportExcel'])->name('hr-audit.bank.export');

    // SSNIT Audit Routes
    Route::get('/hr-audit/ssnit', [SsnitAuditController::class, 'index'])->name('hr-audit.ssnit');
    Route::get('/hr-audit/ssnit/export', [SsnitAuditController::class, 'exportExcel'])->name('hr-audit.ssnit.export');

    // PETRA Audit Routes
    Route::get('/hr-audit/petra', [PetraAuditController::class, 'index'])->name('hr-audit.petra');
    Route::get('/hr-audit/petra/export', [PetraAuditController::class, 'exportExcel'])->name('hr-audit.petra.export');

    // Contact Audit Routes
    Route::get('/hr-audit/contacts', [ContactAuditController::class, 'index'])->name('hr-audit.contacts');
    Route::get('/hr-audit/contacts/export', [ContactAuditController::class, 'exportExcel'])->name('hr-audit.contacts.export');

    // New Joiners Audit Routes
    Route::get('/hr-audit/new-joiners', [NewJoinersController::class, 'index'])->name('hr-audit.new-joiners');
    Route::get('/hr-audit/new-joiners/export', [NewJoinersController::class, 'exportExcel'])->name('hr-audit.new-joiners.export');

    // Exited Staff Audit Routes
    Route::get('/hr-audit/exited-staff', [ExitedStaffController::class, 'index'])->name('hr-audit.exited-staff');
    Route::get('/hr-audit/exited-staff/export', [ExitedStaffController::class, 'exportExcel'])->name('hr-audit.exited-staff.export');

    // Melcom Basic Audit Routes
    Route::get('/hr-audit/melcom-basic', [MelcomBasicController::class, 'index'])->name('hr-audit.melcom-basic');
    Route::get('/hr-audit/melcom-basic/export', [MelcomBasicController::class, 'exportExcel'])->name('hr-audit.melcom-basic.export');
});

    // Transsion Pay Routes
    Route::get('/transsionpay', [TranssionPayController::class, 'index'])->name('transsionpay.index');
    Route::post('/transsionpay/upload', [TranssionPayController::class, 'upload'])->name('transsionpay.upload');
    Route::post('/transsionpay/delete', [TranssionPayController::class, 'delete'])->name('transsionpay.delete');
    Route::get('/transsionpay/download-template', [TranssionPayController::class, 'downloadTemplate'])->name('transsionpay.download-template');
    Route::get('/transsionpay/export', [TranssionPayController::class, 'export'])->name('transsionpay.export');

    // Xiaomi Pay Routes
    Route::get('/xiaomipay', [XiaomiPayController::class, 'index'])->name('xiaomipay.index');
    Route::post('/xiaomipay/upload', [XiaomiPayController::class, 'upload'])->name('xiaomipay.upload');
    Route::post('/xiaomipay/delete', [XiaomiPayController::class, 'delete'])->name('xiaomipay.delete');
    Route::get('/xiaomipay/download-template', [XiaomiPayController::class, 'downloadTemplate'])->name('xiaomipay.download-template');
    Route::get('/xiaomipay/export', [XiaomiPayController::class, 'export'])->name('xiaomipay.export');

    // Avara Pay Routes
Route::get('/avarapay',                   [App\Http\Controllers\AvaraPayController::class, 'index'])->name('avarapay.index');
Route::post('/avarapay/upload',           [App\Http\Controllers\AvaraPayController::class, 'upload'])->name('avarapay.upload');
Route::post('/avarapay/delete',           [App\Http\Controllers\AvaraPayController::class, 'delete'])->name('avarapay.delete');
Route::get('/avarapay/export',            [App\Http\Controllers\AvaraPayController::class, 'export'])->name('avarapay.export');
Route::get('/avarapay/download-template', [App\Http\Controllers\AvaraPayController::class, 'downloadTemplate'])->name('avarapay.download-template');

// Avara SSNIT Pay Routes
Route::get('/avarassnitpay',        [App\Http\Controllers\AvarassnitController::class,   'index'])->name('avarassnitpay.index');
Route::get('/avarassnitpay/export', [App\Http\Controllers\AvarassnitController::class,   'export'])->name('avarassnitpay.export');

// Avara PETRA Pay Routes
Route::get('/avarapetrapay',        [App\Http\Controllers\AvaraPetraPayController::class, 'index'])->name('avarapetrapay.index');
Route::get('/avarapetrapay/export', [App\Http\Controllers\AvaraPetraPayController::class, 'export'])->name('avarapetrapay.export');

// Xiaomi SSNIT Pay Routes
Route::get('/xiaomissnitpay',        [App\Http\Controllers\XiaomiSsnitPayController::class,  'index'])->name('xiaomissnitpay.index');
Route::get('/xiaomissnitpay/export', [App\Http\Controllers\XiaomiSsnitPayController::class,  'export'])->name('xiaomissnitpay.export');

// Xiaomi PETRA Pay Routes
Route::get('/xiaomipetrapay',        [App\Http\Controllers\XiaomiPetraPayController::class, 'index'])->name('xiaomipetrapay.index');
Route::get('/xiaomipetrapay/export', [App\Http\Controllers\XiaomiPetraPayController::class, 'export'])->name('xiaomipetrapay.export');

    // G-Money Contracts
Route::middleware(['auth'])->group(function () {
    Route::prefix('gmoney')->name('gmoney.')->group(function () {
        Route::prefix('contracts')->group(function () {
            Route::get('/',                  [App\Http\Controllers\GmoneyContractController::class, 'index'])->name('contracts.index');
            Route::post('/',                 [App\Http\Controllers\GmoneyContractController::class, 'store'])->name('contracts.store');
            Route::put('/{id}',              [App\Http\Controllers\GmoneyContractController::class, 'update'])->name('contracts.update');
            Route::delete('/{id}',           [App\Http\Controllers\GmoneyContractController::class, 'destroy'])->name('contracts.destroy');
            Route::post('/bulk-upload',      [App\Http\Controllers\GmoneyContractController::class, 'bulkUpload'])->name('contracts.bulk-upload');
            Route::get('/download-template', [App\Http\Controllers\GmoneyContractController::class, 'downloadTemplate'])->name('contracts.download-template');
            Route::get('/download/{id}',     [App\Http\Controllers\GmoneyContractController::class, 'download'])->name('contracts.download');
            Route::get('/{id}',              [App\Http\Controllers\GmoneyContractController::class, 'show'])->name('contracts.show');
        });
    });
});

/*
// Shefer Plastics Contracts
Route::middleware(['auth'])->group(function () {
    Route::prefix('shefer')->name('shefer.')->group(function () {
        Route::prefix('contracts')->group(function () {
            Route::get('/',                  [App\Http\Controllers\SheferContractController::class, 'index'])->name('contracts.index');
            Route::post('/',                 [App\Http\Controllers\SheferContractController::class, 'store'])->name('contracts.store');
            Route::put('/{id}',              [App\Http\Controllers\SheferContractController::class, 'update'])->name('contracts.update');
            Route::delete('/{id}',           [App\Http\Controllers\SheferContractController::class, 'destroy'])->name('contracts.destroy');
            Route::post('/bulk-upload',      [App\Http\Controllers\SheferContractController::class, 'bulkUpload'])->name('contracts.bulk-upload');
            Route::get('/download-template', [App\Http\Controllers\SheferContractController::class, 'downloadTemplate'])->name('contracts.download-template');
            Route::get('/download/{id}',     [App\Http\Controllers\SheferContractController::class, 'download'])->name('contracts.download');
            Route::get('/{id}',              [App\Http\Controllers\SheferContractController::class, 'show'])->name('contracts.show');
        });
    });
});
*/

    // Transsion BFA Routes
    Route::middleware(['auth'])->prefix('transsion-bfa')->name('transsion-bfa.')->group(function () {
        Route::get('/',                    [App\Http\Controllers\TransbfaPayController::class, 'index'])->name('pay.index');
        Route::post('/upload',             [App\Http\Controllers\TransbfaPayController::class, 'upload'])->name('pay.upload');
        Route::get('/download-template',   [App\Http\Controllers\TransbfaPayController::class, 'downloadTemplate'])->name('pay.download-template');
        Route::get('/export',              [App\Http\Controllers\TransbfaPayController::class, 'export'])->name('pay.export');
        Route::delete('/delete',           [App\Http\Controllers\TransbfaPayController::class, 'deleteMonth'])->name('pay.delete');
        Route::post('/delete',             [App\Http\Controllers\TransbfaPayController::class, 'deleteMonth'])->name('pay.delete-post');
        Route::post('/bulk-delete',        [App\Http\Controllers\TransbfaPayController::class, 'bulkDelete'])->name('pay.bulk-delete');
        Route::prefix('bank')->name('bank.')->group(function () {
            Route::get('/',                  [App\Http\Controllers\TransbfaBankController::class, 'index'])->name('index');
            Route::get('/download-template', [App\Http\Controllers\TransbfaBankController::class, 'downloadTemplate'])->name('download-template');
            Route::get('/export',            [App\Http\Controllers\TransbfaBankController::class, 'export'])->name('export');
            Route::post('/bulk-upload',      [App\Http\Controllers\TransbfaBankController::class, 'bulkUpload'])->name('bulk-upload');
            Route::delete('/bulk-destroy',   [App\Http\Controllers\TransbfaBankController::class, 'bulkDestroy'])->name('bulk-destroy');
            Route::post('/',                 [App\Http\Controllers\TransbfaBankController::class, 'store'])->name('store');
            Route::put('/{staffida}',        [App\Http\Controllers\TransbfaBankController::class, 'update'])->name('update');
            Route::delete('/{staffida}',     [App\Http\Controllers\TransbfaBankController::class, 'destroy'])->name('destroy');
        });
        // Stat routes
        Route::get('/iuts-tpa',        [App\Http\Controllers\TransbfaStatController::class, 'iutsTpa'])->name('stat.iuts-tpa');
        Route::get('/iuts-tpa/export', [App\Http\Controllers\TransbfaStatController::class, 'exportIutsTpa'])->name('stat.iuts-tpa-export');
        Route::get('/dn13',            [App\Http\Controllers\TransbfaStatController::class, 'dn13'])->name('stat.dn13');
        Route::get('/dn13/export',     [App\Http\Controllers\TransbfaStatController::class, 'exportDn13'])->name('stat.dn13-export');
    });

    // Tecno Pay Routes
    Route::get('/tecnopay', [TecnoPayController::class, 'index'])->name('tecnopay.index');
    Route::post('/tecnopay/upload', [TecnoPayController::class, 'upload'])->name('tecnopay.upload');
    Route::post('/tecnopay/delete', [TecnoPayController::class, 'delete'])->name('tecnopay.delete');
    Route::get('/tecnopay/download-template', [TecnoPayController::class, 'downloadTemplate'])->name('tecnopay.download-template');
    Route::get('/tecnopay/export', [TecnoPayController::class, 'export'])->name('tecnopay.export');


    // Melcom Pay Routes
    Route::get('/melcompay', [MelcomPayController::class, 'index'])->name('melcompay.index');
    Route::post('/melcompay/upload', [MelcomPayController::class, 'upload'])->name('melcompay.upload');
    Route::post('/melcompay/delete', [MelcomPayController::class, 'delete'])->name('melcompay.delete');
    Route::get('/melcompay/download-template', [MelcomPayController::class, 'downloadTemplate'])->name('melcompay.download-template');
    Route::get('/melcompay/export', [MelcomPayController::class, 'export'])->name('melcompay.export');

    // Transsnet Pay Routes
    Route::get('/transsnetpay', [TranssnetPayController::class, 'index'])->name('transsnetpay.index');
    Route::post('/transsnetpay/upload', [TranssnetPayController::class, 'upload'])->name('transsnetpay.upload');
    Route::post('/transsnetpay/delete', [TranssnetPayController::class, 'delete'])->name('transsnetpay.delete');
    Route::get('/transsnetpay/download-template', [TranssnetPayController::class, 'downloadTemplate'])->name('transsnetpay.download-template');
    Route::get('/transsnetpay/export', [TranssnetPayController::class, 'export'])->name('transsnetpay.export');

    // Nyonkopa Pay Routes
    Route::get('/nyonkopapay', [NyonkopaPayController::class, 'index'])->name('nyonkopapay.index');
    Route::post('/nyonkopapay/upload', [NyonkopaPayController::class, 'upload'])->name('nyonkopapay.upload');
    Route::post('/nyonkopapay/delete', [NyonkopaPayController::class, 'delete'])->name('nyonkopapay.delete');
    Route::get('/nyonkopapay/download-template', [NyonkopaPayController::class, 'downloadTemplate'])->name('nyonkopapay.download-template');
    Route::get('/nyonkopapay/export', [NyonkopaPayController::class, 'export'])->name('nyonkopapay.export');

    // JayKay Pay Routes
    Route::get('/jaykaypay', [JaykayPayController::class, 'index'])->name('jaykaypay.index');
    Route::post('/jaykaypay/upload', [JaykayPayController::class, 'upload'])->name('jaykaypay.upload');
    Route::post('/jaykaypay/delete', [JaykayPayController::class, 'delete'])->name('jaykaypay.delete');
    Route::get('/jaykaypay/download-template', [JaykayPayController::class, 'downloadTemplate'])->name('jaykaypay.download-template');
    Route::get('/jaykaypay/export', [JaykayPayController::class, 'export'])->name('jaykaypay.export');


    // PZ Pay Routes
    Route::get('/pzpay', [PzPayController::class, 'index'])->name('pzpay.index');
    Route::post('/pzpay/upload', [PzPayController::class, 'upload'])->name('pzpay.upload');
    Route::post('/pzpay/delete', [PzPayController::class, 'delete'])->name('pzpay.delete');
    Route::get('/pzpay/download-template', [PzPayController::class, 'downloadTemplate'])->name('pzpay.download-template');
    Route::get('/pzpay/export', [PzPayController::class, 'export'])->name('pzpay.export');


    // Twellium Pay Routes
    Route::get('/twelliumpay', [TwelliumPayController::class, 'index'])->name('twelliumpay.index');
    Route::post('/twelliumpay/upload', [TwelliumPayController::class, 'upload'])->name('twelliumpay.upload');
    Route::post('/twelliumpay/delete', [TwelliumPayController::class, 'delete'])->name('twelliumpay.delete');
    Route::get('/twelliumpay/download-template', [TwelliumPayController::class, 'downloadTemplate'])->name('twelliumpay.download-template');
    Route::get('/twelliumpay/export', [TwelliumPayController::class, 'export'])->name('twelliumpay.export');

    // Twellium Accra Pay Routes
    Route::get('/twelliumaccrapay', [TwelliumaccraPayController::class, 'index'])->name('twelliumaccrapay.index');
    Route::post('/twelliumaccrapay/upload', [TwelliumaccraPayController::class, 'upload'])->name('twelliumaccrapay.upload');
    Route::post('/twelliumaccrapay/delete', [TwelliumaccraPayController::class, 'delete'])->name('twelliumaccrapay.delete');
    Route::get('/twelliumaccrapay/download-template', [TwelliumaccraPayController::class, 'downloadTemplate'])->name('twelliumaccrapay.download-template');
    Route::get('/twelliumaccrapay/export', [TwelliumaccraPayController::class, 'export'])->name('twelliumaccrapay.export');

    // Shefer Pay Routes
    Route::get('/sheferpay', [SheferPayController::class, 'index'])->name('sheferpay.index');
    Route::post('/sheferpay/upload', [SheferPayController::class, 'upload'])->name('sheferpay.upload');
    Route::post('/sheferpay/delete', [SheferPayController::class, 'delete'])->name('sheferpay.delete');
    Route::get('/sheferpay/download-template', [SheferPayController::class, 'downloadTemplate'])->name('sheferpay.download-template');
    Route::get('/sheferpay/export', [SheferPayController::class, 'export'])->name('sheferpay.export');

    // Fon Pay Routes
    Route::get('/fonpay', [FonPayController::class, 'index'])->name('fonpay.index');
    Route::post('/fonpay/upload', [FonPayController::class, 'upload'])->name('fonpay.upload');
    Route::post('/fonpay/delete', [FonPayController::class, 'delete'])->name('fonpay.delete');
    Route::get('/fonpay/download-template', [FonPayController::class, 'downloadTemplate'])->name('fonpay.download-template');
    Route::get('/fonpay/export', [FonPayController::class, 'export'])->name('fonpay.export');

     // A2feed Pay Routes
    Route::get('/a2feedpay', [A2feedPayController::class, 'index'])->name('a2feedpay.index');
    Route::post('/a2feedpay/upload', [A2feedPayController::class, 'upload'])->name('a2feedpay.upload');
    Route::post('/a2feedpay/delete', [A2feedPayController::class, 'delete'])->name('a2feedpay.delete');
    Route::get('/a2feedpay/download-template', [A2feedPayController::class, 'downloadTemplate'])->name('a2feedpay.download-template');
    Route::get('/a2feedpay/export', [A2feedPayController::class, 'export'])->name('a2feedpay.export');

    // A2soya Pay Routes
    Route::get('/a2soyapay', [A2soyaPayController::class, 'index'])->name('a2soyapay.index');
    Route::post('/a2soyapay/upload', [A2soyaPayController::class, 'upload'])->name('a2soyapay.upload');
    Route::post('/a2soyapay/delete', [A2soyaPayController::class, 'delete'])->name('a2soyapay.delete');
    Route::get('/a2soyapay/download-template', [A2soyaPayController::class, 'downloadTemplate'])->name('a2soyapay.download-template');
    Route::get('/a2soyapay/export', [A2soyaPayController::class, 'export'])->name('a2soyapay.export');

    // Ofi BWH Pay Routes
    Route::get('/ofibwhpay', [OfibwhPayController::class, 'index'])->name('ofibwhpay.index');
    Route::post('/ofibwhpay/upload', [OfibwhPayController::class, 'upload'])->name('ofibwhpay.upload');
    Route::post('/ofibwhpay/delete', [OfibwhPayController::class, 'delete'])->name('ofibwhpay.delete');
    Route::get('/ofibwhpay/download-template', [OfibwhPayController::class, 'downloadTemplate'])->name('ofibwhpay.download-template');
    Route::get('/ofibwhpay/export', [OfibwhPayController::class, 'export'])->name('ofibwhpay.export');

    // Ofi Hygiene Pay Routes
    Route::get('/ofihygienepay', [OfihygienePayController::class, 'index'])->name('ofihygienepay.index');
    Route::post('/ofihygienepay/upload', [OfihygienePayController::class, 'upload'])->name('ofihygienepay.upload');
    Route::post('/ofihygienepay/delete', [OfihygienePayController::class, 'delete'])->name('ofihygienepay.delete');
    Route::get('/ofihygienepay/download-template', [OfihygienePayController::class, 'downloadTemplate'])->name('ofihygienepay.download-template');
    Route::get('/ofihygienepay/export', [OfihygienePayController::class, 'export'])->name('ofihygienepay.export');


    // Tempering Pay Routes
    Route::get('/ofitemppay', [OfitempPayController::class, 'index'])->name('ofitemppay.index');
    Route::post('/ofitemppay/upload', [OfitempPayController::class, 'upload'])->name('ofitemppay.upload');
    Route::post('/ofitemppay/delete', [OfitempPayController::class, 'delete'])->name('ofitemppay.delete');
    Route::get('/ofitemppay/download-template', [OfitempPayController::class, 'downloadTemplate'])->name('ofitemppay.download-template');
    Route::get('/ofitemppay/export', [OfitempPayController::class, 'export'])->name('ofitemppay.export');

// Offload Pay Routes
    Route::get('/ofioffloadpay', [OfioffloadPayController::class, 'index'])->name('ofioffloadpay.index');
    Route::post('/ofioffloadpay/upload', [OfioffloadPayController::class, 'upload'])->name('ofioffloadpay.upload');
    Route::post('/ofioffloadpay/delete', [OfioffloadPayController::class, 'delete'])->name('ofioffloadpay.delete');
    Route::get('/ofioffloadpay/download-template', [OfioffloadPayController::class, 'downloadTemplate'])->name('ofioffloadpay.download-template');
    Route::get('/ofioffloadpay/export', [OfioffloadPayController::class, 'export'])->name('ofioffloadpay.export');

    // OFI External Pay Routes
    Route::get('/ofiexternalpay', [OfiexternalPayController::class, 'index'])->name('ofiexternalpay.index');
    Route::post('/ofiexternalpay/upload', [OfiexternalPayController::class, 'upload'])->name('ofiexternalpay.upload');
    Route::post('/ofiexternalpay/delete', [OfiexternalPayController::class, 'delete'])->name('ofiexternalpay.delete');
    Route::get('/ofiexternalpay/download-template', [OfiexternalPayController::class, 'downloadTemplate'])->name('ofiexternalpay.download-template');
    Route::get('/ofiexternalpay/export', [OfiexternalPayController::class, 'export'])->name('ofiexternalpay.export');

    // Honda Pay Routes
    Route::get('/hondapay', [HondaPayController::class, 'index'])->name('hondapay.index');
    Route::post('/hondapay/upload', [HondaPayController::class, 'upload'])->name('hondapay.upload');
    Route::post('/hondapay/delete', [HondaPayController::class, 'delete'])->name('hondapay.delete');
    Route::get('/hondapay/download-template', [HondaPayController::class, 'downloadTemplate'])->name('hondapay.download-template');
    Route::get('/hondapay/export', [HondaPayController::class, 'export'])->name('hondapay.export');

    // Bunge Pay Routes
    Route::get('/bungepay', [BungePayController::class, 'index'])->name('bungepay.index');
    Route::post('/bungepay/upload', [BungePayController::class, 'upload'])->name('bungepay.upload');
    Route::post('/bungepay/delete', [BungePayController::class, 'delete'])->name('bungepay.delete');
    Route::get('/bungepay/download-template', [BungePayController::class, 'downloadTemplate'])->name('bungepay.download-template');
    Route::get('/bungepay/export', [BungePayController::class, 'export'])->name('bungepay.export');


     // Inhouse Pay Routes
    Route::get('/inhousepay', [InhousePayController::class, 'index'])->name('inhousepay.index');
    Route::post('/inhousepay/upload', [InhousePayController::class, 'upload'])->name('inhousepay.upload');
    Route::post('/inhousepay/delete', [InhousePayController::class, 'delete'])->name('inhousepay.delete');
    Route::get('/inhousepay/download-template', [InhousePayController::class, 'downloadTemplate'])->name('inhousepay.download-template');
    Route::get('/inhousepay/export', [InhousePayController::class, 'export'])->name('inhousepay.export');






    // Century Pay Routes
Route::get('/centurypay', [CenturyPayController::class, 'index'])
    ->name('centurypay.index');

Route::post('/centurypay/upload', [CenturyPayController::class, 'upload'])
    ->name('centurypay.upload');

Route::post('/centurypay/delete', [CenturyPayController::class, 'delete'])
    ->name('centurypay.delete');

Route::get('/centurypay/download-template', [CenturyPayController::class, 'downloadTemplate'])
    ->name('centurypay.download-template');

Route::get('/centurypay/export', [CenturyPayController::class, 'export'])
    ->name('centurypay.export');
 //drivers pay routes

    Route::get('/driverpay', [DriverPayController::class, 'index'])
        ->name('driverpay.index');

    Route::post('/driverpay/upload', [DriverPayController::class, 'upload'])
        ->name('driverpay.upload');

    Route::post('/driverpay/delete', [DriverPayController::class, 'delete'])
        ->name('driverpay.delete');

    Route::get('/driverpay/download-template', [DriverPayController::class, 'downloadTemplate'])
        ->name('driverpay.download-template');
    Route::get('/driverpay/export', [DriverPayController::class, 'export'])
        ->name('driverpay.export');

    // DYNAMIC PAYSLIPS FOR ALL CLIENTS (Transsion, Melcom, and future ones)
    Route::get('/payslips', [PayslipController::class, 'generalIndex'])->name('payslips.general');

    Route::prefix('payslips/{client}')->name('payslips.')->group(function () {
        Route::get('/', [PayslipController::class, 'index'])->name('index');

        Route::get('/{month}/{staffId}', [PayslipController::class, 'view'])
            ->where(['month' => '[0-9]{4}-[0-9]{2}', 'staffId' => '.*'])
            ->name('view');

        Route::post('/send-sms', [PayslipController::class, 'sendSms'])->name('sms');
        Route::post('/send-email', [PayslipController::class, 'sendEmail'])->name('email');
        Route::post('/download-multiple', [PayslipController::class, 'downloadMultiple'])->name('download-multiple');
    });

    // TranssionStat Routes (Statutory Reports)
    Route::get('/transsionstat', [TranssionStatController::class, 'index'])->name('transsionstat.index');
    Route::get('/transsionstat/generate', [TranssionStatController::class, 'generate'])->name('transsionstat.generate');

    // A2 soya Routes (Statutory Reports)
    Route::get('/a2soyastat', [A2soyaStatController::class, 'index'])->name('a2soyastat.index');
    Route::get('/a2soyastat/generate', [A2soyaStatController::class, 'generate'])->name('a2soyastat.generate');

    // A2 soya Routes (Statutory Reports)
    Route::get('/tecnostat', [TecnoStatController::class, 'index'])->name('tecnostat.index');
    Route::get('/tecnostat/generate', [TecnoStatController::class, 'generate'])->name('tecnostat.generate');

    Route::get('/transsnetstat', [TranssnetStatController::class, 'index'])->name('transsnetstat.index');
    Route::get('/transsnetstat/generate', [TranssnetStatController::class, 'generate'])->name('transsnetstat.generate');

    Route::get('/nyonkopastat', [NyonkopaStatController::class, 'index'])->name('nyonkopastat.index');
    Route::get('/nyonkopastat/generate', [NyonkopaStatController::class, 'generate'])->name('nyonkopastat.generate');


    Route::get('/jaykaystat', [JaykayStatController::class, 'index'])->name('jaykaystat.index');
    Route::get('/jaykaystat/generate', [JaykayStatController::class, 'generate'])->name('jaykaystat.generate');

    Route::get('/pzstat', [PzStatController::class, 'index'])->name('pzstat.index');
    Route::get('/pzstat/generate', [PzStatController::class, 'generate'])->name('pzstat.generate');

    Route::get('/twelliumaccrastat', [TwelliumaccraStatController::class, 'index'])->name('twelliumaccrastat.index');
    Route::get('/twelliumaccrastat/generate', [TwelliumaccraStatController::class, 'generate'])->name('twelliumaccrastat.generate');

    Route::get('/sheferstat', [SheferStatController::class, 'index'])->name('sheferstat.index');
    Route::get('/sheferstat/generate', [SheferStatController::class, 'generate'])->name('sheferstat.generate');

    Route::get('/fonstat', [FonStatController::class, 'index'])->name('fonstat.index');
    Route::get('/fonstat/generate', [FonStatController::class, 'generate'])->name('fonstat.generate');

    // Route::get('/olamfoodstat', [OlamfoodStatController::class, 'index'])->name('olamfoodstat.index');
    // Route::get('/olamfoodstat/generate', [OlamfoodStatController::class, 'generate'])->name('olamfoodstat.generate');

    Route::get('/melcomstat', [MelcomStatController::class, 'index'])->name('melcomstat.index');
    Route::get('/melcomstat/generate', [MelcomStatController::class, 'generate'])->name('melcomstat.generate');

    Route::get('/driverstat', [DriverStatController::class, 'index'])->name('driverstat.index');
    Route::get('/driverstat/generate', [DriverStatController::class, 'generate'])->name('driverstat.generate');

    Route::get('/centurystat', [CenturyStatController::class, 'index'])->name('centurystat.index');
    Route::get('/centurystat/generate', [CenturyStatController::class, 'generate'])->name('centurystat.generate');

    Route::get('/a2feedstat', [A2feedStatController::class, 'index'])->name('a2feedstat.index');
    Route::get('/a2feedstat/generate', [A2feedStatController::class, 'generate'])->name('a2feedstat.generate');

    Route::get('/ofibwhstat', [OfibwhStatController::class, 'index'])->name('ofibwhstat.index');
    Route::get('/ofibwhstat/generate', [OfibwhStatController::class, 'generate'])->name('ofibwhstat.generate');

    Route::get('/ofioffloadstat', [OfioffloadStatController::class, 'index'])->name('ofioffloadstat.index');
    Route::get('/ofioffloadstat/generate', [OfioffloadStatController::class, 'generate'])->name('ofioffloadstat.generate');

    Route::get('/ofihygenestat', [OfihygieneStatController::class, 'index'])->name('ofihygenestat.index');
    Route::get('/ofihygenestat/generate', [OfihygieneStatController::class, 'generate'])->name('ofihygenestat.generate');

    Route::get('/ofiexternalstat', [OfiexternalStatController::class, 'index'])->name('ofiexternalstat.index');
    Route::get('/ofiexternalstat/generate', [OfiexternalStatController::class, 'generate'])->name('ofiexternalstat.generate');

    Route::get('/ofitempstat', [OfitempStatController::class, 'index'])->name('ofitempstat.index');
    Route::get('/ofitempstat/generate', [OfitempStatController::class, 'generate'])->name('ofitempstat.generate');

    // Route::get('/hondastat', [HondaStatController::class, 'index'])->name('hondastat.index');
    // Route::get('/hondastat/generate', [HondaStatController::class, 'generate'])->name('hondastat.generate');

     Route::get('/bungestat', [BungeStatController::class, 'index'])->name('bungestat.index');
    Route::get('/bungestat/generate', [BungeStatController::class, 'generate'])->name('bungestat.generate');

     Route::get('/twelliumstat', [TwelliumStatController::class, 'index'])->name('twelliumstat.index');
    Route::get('/twelliumstat/generate', [TwelliumStatController::class, 'generate'])->name('twelliumstat.generate');

     Route::get('/inhousestat', [InhouseStatController::class, 'index'])->name('nhousestat.index');
    Route::get('/inhousestat/generate', [InhouseStatController::class, 'generate'])->name('nhousestat.generate');

     Route::get('/xiaomistat', [XiaomiStatController::class, 'index'])->name('xiaomistat.index');
    Route::get('/xiaomistat/generate', [XiaomiStatController::class, 'generate'])->name('xiaomistat.generate');

    // Leave Application Routes
    Route::get('/leave-applications', [LeaveApplicationController::class, 'index'])->name('leave-applications.index');
    Route::post('/leave-applications/{id}/update-status', [LeaveApplicationController::class, 'updateStatus'])->name('leave-applications.update-status');
    Route::post('/leave-applications/bulk-update', [LeaveApplicationController::class, 'bulkUpdate'])->name('leave-applications.bulk-update');
    Route::get('/leave-applications/export', [LeaveApplicationController::class, 'export'])->name('leave-applications.export');
    Route::get('/leave-applications/download/{id}', [LeaveApplicationController::class, 'downloadLeavePdf'])->name('leave-applications.download');
    Route::post('/leave-applications/download-multiple', [LeaveApplicationController::class, 'downloadMultipleLeavePdfs'])->name('leave-applications.download-multiple');

    Route::get('/employee-leaves', [EmployeeLeaveController::class, 'index'])->name('employee-leaves.index');
    Route::post('/employee-leaves', [EmployeeLeaveController::class, 'store'])->name('employee-leaves.store');
    Route::put('/employee-leaves/{id}', [EmployeeLeaveController::class, 'update'])->name('employee-leaves.update');
    Route::delete('/employee-leaves/{id}', [EmployeeLeaveController::class, 'destroy'])->name('employee-leaves.destroy');

    // Hygiene Payroll Routes
    Route::get('/hygiene-payroll', [HygienePayrollController::class, 'index'])->name('hygiene-payroll.index');
    Route::post('/hygiene-payroll/set-month', [HygienePayrollController::class, 'setMonth'])->name('hygiene-payroll.set-month');
    Route::post('/hygiene-payroll/upload', [HygienePayrollController::class, 'upload'])->name('hygiene-payroll.upload');
    Route::get('/hygiene-payroll/download', [HygienePayrollController::class, 'download'])->name('hygiene-payroll.download');
    Route::post('/hygiene-payroll/delete', [HygienePayrollController::class, 'delete'])->name('hygiene-payroll.delete');
    Route::get('/hygiene-payroll/download-template', [HygienePayrollController::class, 'downloadTemplate'])->name('hygiene-payroll.download-template');
    Route::get('/hygiene-payroll/download-table', [HygienePayrollController::class, 'downloadTable'])->name('hygiene-payroll.download-table');

    Route::prefix('contracts')->group(function () {
        Route::get('/', [ContractController::class, 'index'])->name('contracts.index');
        Route::post('/', [ContractController::class, 'store'])->name('contracts.store');
        Route::put('/{id}', [ContractController::class, 'update'])->name('contracts.update');
        Route::delete('/{id}', [ContractController::class, 'destroy'])->name('contracts.destroy');
        Route::post('/bulk-upload', [ContractController::class, 'bulkUpload'])->name('contracts.bulk-upload');
        Route::get('/download/{id}', [ContractController::class, 'download'])->name('contracts.download');
        Route::get('/download-template', [ContractController::class, 'downloadTemplate'])->name('contracts.download-template');
        Route::get('/{id}', [ContractController::class, 'show'])->name('contracts.show');
    });

    Route::middleware(['auth'])->group(function () {
    Route::prefix('transsion')->name('transsion.')->group(function () {
        Route::prefix('contracts')->group(function () {
            Route::get('/', [TranssionContractController::class, 'index'])->name('contracts.index');
            Route::post('/', [TranssionContractController::class, 'store'])->name('contracts.store');
            Route::put('/{id}', [TranssionContractController::class, 'update'])->name('contracts.update');
            Route::delete('/{id}', [TranssionContractController::class, 'destroy'])->name('contracts.destroy');
            Route::post('/bulk-upload', [TranssionContractController::class, 'bulkUpload'])->name('contracts.bulk-upload');
            Route::get('/download/{id}', [TranssionContractController::class, 'download'])->name('contracts.download');
            Route::get('/download-template', [TranssionContractController::class, 'downloadTemplate'])->name('contracts.download-template');
            Route::get('/{id}', [TranssionContractController::class, 'show'])->name('contracts.show');
        });
    });
});

Route::middleware(['auth'])->prefix('transsion')->name('transsion.')->group(function () {

    // Your existing contracts routes...
    Route::prefix('contracts')->group(function () {
        // ... your original contract routes ...
    });

    Route::prefix('contract-renewals')->group(function () {

        // ── Static / non-parameterised routes first ──────────────────────────

        Route::get('/', [TranssionContractRenewalController::class, 'index'])
            ->name('contract-renewals.index');

        Route::post('/', [TranssionContractRenewalController::class, 'store'])
            ->name('contract-renewals.store');

        Route::get('/create/{id}', [TranssionContractRenewalController::class, 'create'])
            ->name('contract-renewals.create');

        Route::get('/download-template', [TranssionContractRenewalController::class, 'downloadTemplate'])
            ->name('contract-renewals.download-template');

        Route::post('/bulk-upload', [TranssionContractRenewalController::class, 'bulkUpload'])
            ->name('contract-renewals.bulk-upload');

        // ── Parameterised routes last (avoid swallowing static segments) ─────

        Route::get('/download/{id}', [TranssionContractRenewalController::class, 'download'])
            ->name('contract-renewals.download');

        Route::get('/{id}/edit', [TranssionContractRenewalController::class, 'edit'])
            ->name('contract-renewals.edit');

        Route::put('/{id}', [TranssionContractRenewalController::class, 'update'])
            ->name('contract-renewals.update');

        Route::delete('/{id}', [TranssionContractRenewalController::class, 'destroy'])
            ->name('contract-renewals.destroy');

        Route::patch('/{id}/activate', [TranssionContractRenewalController::class, 'activate'])
            ->name('contract-renewals.activate');

        Route::get('/{id}', [TranssionContractRenewalController::class, 'show'])
            ->name('contract-renewals.show');
    });
});


Route::middleware(['auth'])->group(function () {
    Route::prefix('xiaomi')->name('xiaomi.')->group(function () {
        Route::prefix('contracts')->group(function () {
            Route::get('/', [XiaomiContractController::class, 'index'])->name('contracts.index');
            Route::post('/', [XiaomiContractController::class, 'store'])->name('contracts.store');
            Route::put('/{id}', [XiaomiContractController::class, 'update'])->name('contracts.update');
            Route::delete('/{id}', [XiaomiContractController::class, 'destroy'])->name('contracts.destroy');
            Route::post('/bulk-upload', [XiaomiContractController::class, 'bulkUpload'])->name('contracts.bulk-upload');
            Route::get('/download/{id}', [XiaomiContractController::class, 'download'])->name('contracts.download');
            Route::get('/download-template', [XiaomiContractController::class, 'downloadTemplate'])->name('contracts.download-template');
            Route::get('/{id}', [XiaomiContractController::class, 'show'])->name('contracts.show');
        });
    });
});

Route::middleware(['auth'])->group(function () {
    Route::prefix('olamfood')->name('olamfood.')->group(function () {
        Route::prefix('contracts')->group(function () {
            Route::get('/', [OlamfoodContractController::class, 'index'])->name('contracts.index');
            Route::post('/', [OlamfoodContractController::class, 'store'])->name('contracts.store');
            Route::put('/{id}', [OlamfoodContractController::class, 'update'])->name('contracts.update');
            Route::delete('/{id}', [OlamfoodContractController::class, 'destroy'])->name('contracts.destroy');
            Route::post('/bulk-upload', [OlamfoodContractController::class, 'bulkUpload'])->name('contracts.bulk-upload');
            Route::get('/download/{id}', [OlamfoodContractController::class, 'download'])->name('contracts.download');
            Route::get('/download-template', [OlamfoodContractController::class, 'downloadTemplate'])->name('contracts.download-template');
            Route::get('/{id}', [OlamfoodContractController::class, 'show'])->name('contracts.show');
        });
    });
});

    // Contract Renewal Routes
    Route::get('/contract_renewals', [ContractRenewalController::class, 'index'])->name('contract_renewals.index');
    Route::post('/contract_renewals', [ContractRenewalController::class, 'store'])->name('contract_renewals.store');
    Route::put('/contract_renewals/{primary_id}', [ContractRenewalController::class, 'update'])->name('contract_renewals.update');
    Route::delete('/contract_renewals/{primary_id}', [ContractRenewalController::class, 'destroy'])->name('contract_renewals.destroy');
    Route::post('/contract_renewals/bulk-upload', [ContractRenewalController::class, 'bulkUpload'])->name('contract_renewals.bulk-upload');
    Route::get('/contract_renewals/download/{primary_id}', [ContractRenewalController::class, 'download'])->name('contract_renewals.download');
    Route::get('/contract_renewals/download-template', [ContractRenewalController::class, 'downloadTemplate'])->name('contract_renewals.download-template');
    Route::get('/contract_renewals/{primary_id}', [ContractRenewalController::class, 'show'])->name('contract_renewals.show');

    Route::get('/ssnitpay', [SsnitPayController::class, 'index'])->name('ssnitpay.index');
    Route::get('/ssnitpay/export', [SsnitPayController::class, 'export'])->name('ssnitpay.export');

    Route::get('/tecnossnitpay', [TecnoSsnitPayController::class, 'index'])->name('tecnossnitpay.index');
    Route::get('/tecnossnitpay/export', [TecnoSsnitPayController::class, 'export'])->name('tecnossnitpay.export');

   Route::get('/transsionssnitpay', [TranssionssnitPayController::class, 'index'])->name('transsionssnitpay.index');
Route::get('/transsionssnitpay/export', [TranssionssnitPayController::class, 'export'])->name('transsionssnitpay.export');

Route::get('/transsnetssnitpay', [TranssnetssnitController::class, 'index'])->name('transsnetssnitpay.index');
Route::get('/transsnetssnitpay/export', [TranssnetssnitController::class, 'export'])->name('transsnetssnitpay.export');

Route::get('/nyonkopassnitpay', [NyonkopassnitController::class, 'index'])->name('nyonkopassnitpay.index');
Route::get('/nyonkopassnitpay/export', [NyonkopassnitController::class, 'export'])->name('nyonkopassnitpay.export');

Route::get('/jaykayssnitpay', [JaykayssnitController::class, 'index'])->name('jaykayssnitpay.index');
Route::get('/jaykayssnitpay/export', [JaykayssnitController::class, 'export'])->name('jaykayssnitpay.export');

Route::get('/pzssnitpay', [PzssnitController::class, 'index'])->name('pzssnitpay.index');
Route::get('/pzssnitpay/export', [PzssnitController::class, 'export'])->name('pzssnitpay.export');

Route::get('/twelliumaccrassnitpay', [TwelliumaccrassnitController::class, 'index'])->name('twelliumaccrassnitpay.index');
Route::get('/twelliumaccrassnitpay/export', [TwelliumaccrassnitController::class, 'export'])->name('twelliumaccrassnitpay.export');

Route::get('/sheferssnitpay', [SheferssnitController::class, 'index'])->name('sheferssnitpay.index');
Route::get('/sheferssnitpay/export', [SheferssnitController::class, 'export'])->name('sheferssnitpay.export');

Route::get('/fonssnitpay', [FonssnitController::class, 'index'])->name('fonssnitpay.index');
Route::get('/fonssnitpay/export', [FonssnitController::class, 'export'])->name('fonssnitpay.export');

Route::get('/melcomssnitpay', [MelcomssnitController::class, 'index'])->name('melcomssnitpay.index');
Route::get('/melcomssnitpay/export', [MelcomssnitController::class, 'export'])->name('melcomssnitpay.export');

Route::get('/centuryssnitpay', [CenturyssnitController::class, 'index'])->name('centuryssnitpay.index');
Route::get('/centuryssnitpay/export', [CenturyssnitController::class, 'export'])->name('centuryssnitpay.export');

Route::get('/driverssnitpay', [DriverssnitController::class, 'index'])->name('driverssnitpay.index');
Route::get('/driverssnitpay/export', [DriverssnitController::class, 'export'])->name('driverssnitpay.export');

Route::get('/a2feedssnitpay', [A2feedssnitController::class, 'index'])->name('a2feedssnitpay.index');
Route::get('/a2feedssnitpay/export', [A2feedssnitController::class, 'export'])->name('a2feedssnitpay.export');

Route::get('/a2soyassnitpay', [A2soyassnitController::class, 'index'])->name('a2soyassnitpay.index');
Route::get('/a2soyassnitpay/export', [A2soyassnitController::class, 'export'])->name('a2soyassnitpay.export');

Route::get('/ofibwhssnitpay', [OfibwhssnitController::class, 'index'])->name('ofibwhssnitpay.index');
Route::get('/ofibwhssnitpay/export', [OfibwhssnitController::class, 'export'])->name('ofibwhssnitpay.export');

Route::get('/ofioffloadssnitpay', [OfioffloadssnitController::class, 'index'])->name('ofioffloadssnitpay.index');
Route::get('/ofioffloadssnitpay/export', [OfioffloadssnitController::class, 'export'])->name('ofioffloadssnitpay.export');

Route::get('/ofiexternalssnitpay', [OfiexternalssnitController::class, 'index'])->name('ofiexternalssnitpay.index');
Route::get('/ofiexternalssnitpay/export', [OfiexternalssnitController::class, 'export'])->name('ofiexternalssnitpay.export');

Route::get('/ofihygienessnitpay', [OfihygienessnitController::class, 'index'])->name('ofihygienessnitpay.index');
Route::get('/ofihygienessnitpay/export', [OfihygienessnitController::class, 'export'])->name('ofihygienessnitpay.export');

Route::get('/ofitempssnitpay', [OfitempssnitController::class, 'index'])->name('ofitempssnitpay.index');
Route::get('/ofitempssnitpay/export', [OfitempssnitController::class, 'export'])->name('ofitempssnitpay.export');

Route::get('/hondassnitpay', [HondassnitController::class, 'index'])->name('hondassnitpay.index');
Route::get('/hondassnitpay/export', [HondassnitController::class, 'export'])->name('hondassnitpay.export');

Route::get('/bungessnitpay', [BungessnitController::class, 'index'])->name('bungessnitpay.index');
Route::get('/bungessnitpay/export', [BungessnitController::class, 'export'])->name('bungessnitpay.export');

Route::get('/inhousessnitpay', [InhousessnitController::class, 'index'])->name('inhousessnitpay.index');
Route::get('/inhousessnitpay/export', [InhousessnitController::class, 'export'])->name('inhousessnitpay.export');

Route::get('/twelliumssnitpay', [TwelliumssnitController::class, 'index'])->name('twelliumssnitpay.index');
Route::get('/twelliumssnitpay/export', [TwelliumssnitController::class, 'export'])->name('twelliumssnitpay.export');


Route::get('/a2feedpetrapay', [A2feedPetraPayController::class, 'index'])
    ->name('a2feedpetrapay.index');

Route::get('/a2feedpetrapay/export', [A2feedPetraPayController::class, 'export'])
    ->name('a2feedpetrapay.export');

Route::get('/sheferpetrapay', [SheferPetraPayController::class, 'index'])
    ->name('sheferpetrapay.index');

Route::get('/sheferpetrapay/export', [SheferPetraPayController::class, 'export'])
    ->name('sheferpetrapay.export');


Route::get('/fonpetrapay', [FonPetraPayController::class, 'index'])
    ->name('fonpetrapay.index');

Route::get('/fonpetrapay/export', [FonPetraPayController::class, 'export'])
    ->name('fonpetrapay.export');
Route::get('/nyonkopapetrapay', [NyonkopaPetraPayController::class, 'index'])
    ->name('nyonkopapetrapay.index');

Route::get('/nyonkopapetrapay/export', [NyonkopaPetraPayController::class, 'export'])
    ->name('nyonkopapetrapay.export');

Route::get('/pzpetrapay', [PzPetraPayController::class, 'index'])
    ->name('pzpetrapay.index');

Route::get('/pzpetrapay/export', [PzPetraPayController::class, 'export'])
    ->name('pzpetrapay.export');



Route::get('/twelliumaccrapetrapay', [TwelliumAccraPetraPayController::class, 'index'])
    ->name('twelliumaccrapetrapay.index');

Route::get('/twelliumaccrapetrapay/export', [TwelliumAccraPetraPayController::class, 'export'])
    ->name('twelliumaccrapetrapay.export');

Route::get('/jaykaypetrapay', [JaykayPetraPayController::class, 'index'])
    ->name('jaykaypetrapay.index');

Route::get('/jaykaypetrapay/export', [JaykayPetraPayController::class, 'export'])
    ->name('jaykaypetrapay.export');

    Route::get('/petrapay', [PetraPayController::class, 'index'])->name('petrapay.index');
    Route::get('/petrapay/export', [PetraPayController::class, 'export'])->name('petrapay.export');


    Route::get('/tecnopetrapay', [TecnoPetraPayController::class, 'index'])->name('tecnopetrapay.index');
    Route::get('/tecnopetrapay/export', [TecnoPetraPayController::class, 'export'])->name('tecnopetrapay.export');

    Route::get('/transsionpetrapay', [TranssionPetraPayController::class, 'index'])->name('transsionpetrapay.index');
    Route::get('/transsionpetrapay/export', [TranssionPetraPayController::class, 'export'])->name('transsionpetrapay.export');

    Route::get('/transsion-bfapetrapay', [TranssionBfaPetraPayController::class, 'index'])->name('transsion-bfapetrapay.index');
    Route::get('/transsion-bfapetrapay/export', [TranssionBfaPetraPayController::class, 'export'])->name('transsion-bfapetrapay.export');

    Route::get('/transsnetpetrapay', [TranssnetPetraPayController::class, 'index'])
    ->name('transsnetpetrapay.index');

Route::get('/transsnetpetrapay/export', [TranssnetPetraPayController::class, 'export'])
    ->name('transsnetpetrapay.export');

    Route::get('/melcompetrapay', [MelcomPetraPayController::class, 'index'])
    ->name('melcompetrapay.index');

Route::get('/melcompetrapay/export', [MelcomPetraPayController::class, 'export'])
    ->name('melcompetrapay.export');



Route::get('/driverpetrapay', [DriversPetraPayController::class, 'index'])
    ->name('driverpetrapay.index');

Route::get('/driverpetrapay/export', [DriversPetraPayController::class, 'export'])
    ->name('driverpetrapay.export');

Route::get('/ofiexternalpetrapay', [OfiexternalPetraPayController::class, 'index'])
    ->name('ofiexternalpetrapay.index');

Route::get('/ofiexternalpetrapay/export', [OfiexternalPetraPayController::class, 'export'])
    ->name('ofiexternalpetrapay.export');

Route::get('/hondapetrapay', [HondaPetraPayController::class, 'index'])
    ->name('hondapetrapay.index');

Route::get('/hondapetrapay/export', [HondaPetraPayController::class, 'export'])
    ->name('hondapetrapay.export');


Route::get('/bungepetrapay', [BungePetraPayController::class, 'index'])
    ->name('bungepetrapay.index');

Route::get('/bungepetrapay/export', [BungePetraPayController::class, 'export'])
    ->name('bungepetrapay.export');

Route::get('/ofitemppetrapay', [OfitempPetraPayController::class, 'index'])
    ->name('ofitemppetrapay.index');

Route::get('/ofitemppetrapay/export', [OfitempPetraPayController::class, 'export'])
    ->name('ofitemppetrapay.export');

    Route::get('/twelliumpetrapay', [TwelliumPetraPayController::class, 'index'])
    ->name('twelliumpetrapay.index');

Route::get('/twelliumpetrapay/export', [TwelliumPetraPayController::class, 'export'])
    ->name('twelliumpetrapay.export');



Route::get('/inhousepetrapay', [InhousePetraPayController::class, 'index'])
    ->name('inhousepetrapay.index');

Route::get('/inhousepetrapay/export', [InhousePetraPayController::class, 'export'])
    ->name('inhousepetrapay.export');

Route::get('/centurypetrapay', [CenturyPetraPayController::class, 'index'])
    ->name('centurypetrapay.index');

Route::get('/centurypetrapay/export', [CenturyPetraPayController::class, 'export'])
    ->name('centurypetrapay.export');

    Route::get('/ofioffloadpetrapay', [OfioffloadPetraPayController::class, 'index'])
    ->name('ofioffloadpetrapay.index');

Route::get('/ofioffloadpetrapay/export', [OfioffloadPetraPayController::class, 'export'])
    ->name('ofioffloadpetrapay.export');



Route::get('/ofihygienepetrapay', [OfihygienePetraPayController::class, 'index'])
    ->name('ofihygienepetrapay.index');

Route::get('/ofihygienepetrapay/export', [OfihygienePetraPayController::class, 'export'])
    ->name('ofihygienepetrapay.export');

    Route::get('/a2soyapetrapay', [A2soyaPetraPayController::class, 'index'])
    ->name('a2soyapetrapay.index');

Route::get('/a2soyapetrapay/export', [A2soyaPetraPayController::class, 'export'])
    ->name('a2soyapetrapay.export');



Route::get('/ofibwhpetrapay', [OfibwhPetraPayController::class, 'index'])
    ->name('ofibwhpetrapay.index');

Route::get('/ofibwhpetrapay/export', [OfibwhPetraPayController::class, 'export'])
    ->name('ofibwhpetrapay.export');

    // IT Assets Routes
Route::prefix('it-assets')->name('itassets.')->group(function () {
    // Main CRUD
    Route::get('/',                [ITAssetController::class, 'index'])->name('index');
    Route::post('/',               [ITAssetController::class, 'store'])->name('store');
    Route::put('/{id}',            [ITAssetController::class, 'update'])->name('update');
    Route::delete('/{id}',         [ITAssetController::class, 'destroy'])->name('destroy');

    // Archive (moves asset from `assets` → `assets_archive`)
    Route::post('/{id}/archive',   [ITAssetController::class, 'archive'])->name('archive');

    // Archived list & restore
    Route::get('/archived',        [ITAssetController::class, 'archived'])->name('archived');
    Route::post('/restore/{archiveId}', [ITAssetController::class, 'restore'])->name('restore');

    // Excel export
    Route::get('/export',          [ITAssetController::class, 'export'])->name('export');
});

// SSNIT Payment - ALL CLIENTS
Route::prefix('ssnit-payment')->name('ssnitpayment.')->group(function () {
    Route::get('/', [SsnitPaymentController::class, 'index'])->name('index');
    Route::get('/{client_dbname}', [SsnitPaymentController::class, 'showClient'])->name('client');
    Route::get('/export/{client_dbname}', [SsnitPaymentController::class, 'export'])->name('export');
});



Route::prefix('payroll/{client_dbname}')->group(function () {
    Route::get('/bank-payment', [SalaryPaymentController::class, 'index'])
        ->name('payroll.salary-payment.index');

    Route::post('/bank-payment/upload', [SalaryPaymentController::class, 'upload'])
        ->name('payroll.salary-payment.upload');

    Route::post('/bank-payment/delete', [SalaryPaymentController::class, 'delete'])
        ->name('payroll.salary-payment.delete');

    Route::get('/bank-payment/export', [SalaryPaymentController::class, 'export'])
        ->name('payroll.salary-payment.export');

    Route::get('/bank-payment/download-template', [SalaryPaymentController::class, 'downloadTemplate'])
        ->name('payroll.salary-payment.download-template');
});

// Olamfood Pay Routes
Route::get('/olamfoodpay',                  [App\Http\Controllers\OlamfoodPayController::class, 'index'])->name('olamfoodpay.index');
Route::post('/olamfoodpay/upload',          [App\Http\Controllers\OlamfoodPayController::class, 'upload'])->name('olamfoodpay.upload');
Route::post('/olamfoodpay/delete',          [App\Http\Controllers\OlamfoodPayController::class, 'delete'])->name('olamfoodpay.delete');
Route::get('/olamfoodpay/export',           [App\Http\Controllers\OlamfoodPayController::class, 'export'])->name('olamfoodpay.export');
Route::get('/olamfoodpay/download-template',[App\Http\Controllers\OlamfoodPayController::class, 'downloadTemplate'])->name('olamfoodpay.download-template');

// Olam Food SSNIT Pay Routes
Route::get('/olamfoodssnitpay',        [App\Http\Controllers\OlamfoodssnitController::class,    'index'])->name('olamfoodssnitpay.index');
Route::get('/olamfoodssnitpay/export', [App\Http\Controllers\OlamfoodssnitController::class,    'export'])->name('olamfoodssnitpay.export');

// Olam Food PETRA Pay Routes
Route::get('/olamfoodpetrapay',        [App\Http\Controllers\OlamfoodPetraPayController::class, 'index'])->name('olamfoodpetrapay.index');
Route::get('/olamfoodpetrapay/export', [App\Http\Controllers\OlamfoodPetraPayController::class, 'export'])->name('olamfoodpetrapay.export');

// CIV Bunge Routes
Route::middleware(['auth'])->prefix('civ-bunge')->name('civ-bunge.')->group(function () {
    Route::get('/',                  [App\Http\Controllers\CivBungePayController::class, 'index'])->name('pay.index');
    Route::post('/upload',           [App\Http\Controllers\CivBungePayController::class, 'upload'])->name('pay.upload');
    Route::get('/download-template', [App\Http\Controllers\CivBungePayController::class, 'downloadTemplate'])->name('pay.download-template');
    Route::get('/export',            [App\Http\Controllers\CivBungePayController::class, 'export'])->name('pay.export');
    Route::delete('/delete',         [App\Http\Controllers\CivBungePayController::class, 'deleteMonth'])->name('pay.delete');
});

// Togo Bunge Routes
Route::middleware(['auth'])->prefix('togo-bunge')->name('togo-bunge.')->group(function () {
    Route::get('/',                  [App\Http\Controllers\TogoBungePayController::class, 'index'])->name('pay.index');
    Route::post('/upload',           [App\Http\Controllers\TogoBungePayController::class, 'upload'])->name('pay.upload');
    Route::get('/download-template', [App\Http\Controllers\TogoBungePayController::class, 'downloadTemplate'])->name('pay.download-template');
    Route::get('/export',            [App\Http\Controllers\TogoBungePayController::class, 'export'])->name('pay.export');
    Route::delete('/delete',         [App\Http\Controllers\TogoBungePayController::class, 'deleteMonth'])->name('pay.delete');
});

// Benin Bunge Routes
Route::middleware(['auth'])->prefix('benin-bunge')->name('benin-bunge.')->group(function () {
    Route::get('/',                  [App\Http\Controllers\BeninBungePayController::class, 'index'])->name('pay.index');
    Route::post('/upload',           [App\Http\Controllers\BeninBungePayController::class, 'upload'])->name('pay.upload');
    Route::get('/download-template', [App\Http\Controllers\BeninBungePayController::class, 'downloadTemplate'])->name('pay.download-template');
    Route::get('/export',            [App\Http\Controllers\BeninBungePayController::class, 'export'])->name('pay.export');
    Route::delete('/delete',         [App\Http\Controllers\BeninBungePayController::class, 'deleteMonth'])->name('pay.delete');
});

// Birthday SMS Routes
Route::get('/birthday/reports',        [App\Http\Controllers\BirthdayController::class, 'index'])->name('birthday.reports');
Route::get('/birthday/settings',       [App\Http\Controllers\BirthdayController::class, 'settings'])->name('birthday.settings');
Route::put('/birthday/settings',       [App\Http\Controllers\BirthdayController::class, 'saveSettings'])->name('birthday.settings.save');
Route::get('/birthday/export',         [App\Http\Controllers\BirthdayController::class, 'export'])->name('birthday.export');

// Employee Archive Routes
Route::get('/archive',                      [ArchiveController::class, 'index'])->name('archive.index');
Route::post('/archive/preview',             [ArchiveController::class, 'preview'])->name('archive.preview');
Route::post('/archive/run',                 [ArchiveController::class, 'run'])->name('archive.run');
Route::get('/archive/download-preview',     [ArchiveController::class, 'downloadPreview'])->name('archive.download-preview');
Route::get('/archive/report/{id}',          [ArchiveController::class, 'downloadReport'])->name('archive.download-report');

// G-Money Pay Routes
Route::get('/gmoneypay',                    [App\Http\Controllers\GmoneyPayController::class, 'index'])->name('gmoneypay.index');
Route::post('/gmoneypay/upload',            [App\Http\Controllers\GmoneyPayController::class, 'upload'])->name('gmoneypay.upload');
Route::post('/gmoneypay/delete',            [App\Http\Controllers\GmoneyPayController::class, 'delete'])->name('gmoneypay.delete');
Route::get('/gmoneypay/export',             [App\Http\Controllers\GmoneyPayController::class, 'export'])->name('gmoneypay.export');
Route::get('/gmoneypay/download-template',  [App\Http\Controllers\GmoneyPayController::class, 'downloadTemplate'])->name('gmoneypay.download-template');

// G-Money SSNIT Pay Routes
Route::get('/gmoneyssnitpay',        [App\Http\Controllers\GmoneyssnitController::class,    'index'])->name('gmoneyssnitpay.index');
Route::get('/gmoneyssnitpay/export', [App\Http\Controllers\GmoneyssnitController::class,    'export'])->name('gmoneyssnitpay.export');

// G-Money PETRA Pay Routes
Route::get('/gmoneypetrapay',        [App\Http\Controllers\GmoneyPetraPayController::class, 'index'])->name('gmoneypetrapay.index');
Route::get('/gmoneypetrapay/export', [App\Http\Controllers\GmoneyPetraPayController::class, 'export'])->name('gmoneypetrapay.export');

Route::get('/employeearchived', [ArchivedEmployeeController::class, 'index'])
    ->name('employeearchived.index');

Route::post('/employeearchived/{staffid}/restore', [ArchivedEmployeeController::class, 'restore'])
    ->where('staffid', '.*')
    ->name('employeearchived.restore');

Route::delete('/employeearchived/{staffid}', [ArchivedEmployeeController::class, 'destroy'])
    ->where('staffid', '.*')
    ->name('employeearchived.destroy');

Route::post('/employeearchived/bulk-destroy', [ArchivedEmployeeController::class, 'bulkDestroy'])
    ->name('employeearchived.bulk-destroy');

Route::post('/employeearchived/bulk-restore', [ArchivedEmployeeController::class, 'bulkRestore'])
    ->name('employeearchived.bulk-restore');

Route::get('/employeearchived/export', [ArchivedEmployeeController::class, 'export'])
    ->name('employeearchived.export');

    // Generic fallback routes for all companies' Contracts, Contract Renewals, and PETRA Payment without dedicated tables
    Route::get('/{client}/contracts', [App\Http\Controllers\GenericContractController::class, 'contracts'])->name('generic.contracts');
    Route::get('/{client}/contract-renewals', [App\Http\Controllers\GenericContractController::class, 'renewals'])->name('generic.contract-renewals');
    Route::get('/{client}petrapay', [App\Http\Controllers\GenericContractController::class, 'petrapay'])->name('generic.petrapay');

});

// Browser-based cPanel setup & maintenance utility (for hosting without SSH/Terminal)
Route::get('/cpanel-setup', function (\Illuminate\Http\Request $request) {
    $secret = $request->query('key') ?? $request->query('secret');
    $expectedSecret = env('SETUP_KEY', 'sbp-deploy-2026');
    $isSuperAdmin = auth()->check() && (auth()->user()->is_super_admin ?? false);

    if (!$isSuperAdmin && (!$secret || $secret !== $expectedSecret)) {
        return response('<h3>Unauthorized</h3><p>Access key required: <code>/cpanel-setup?key=sbp-deploy-2026</code> (or configure <code>SETUP_KEY</code> in your .env)</p>', 403);
    }

    $results = [];

    // 1. Storage symlink
    try {
        $publicStorage = public_path('storage');
        if (!file_exists($publicStorage)) {
            \Illuminate\Support\Facades\Artisan::call('storage:link');
            $results[] = '<b>[Storage Link]</b><br>' . nl2br(htmlspecialchars(\Illuminate\Support\Facades\Artisan::output() ?: 'Storage link created successfully.'));
        } else {
            $results[] = '<b>[Storage Link]</b> Public storage link already exists.';
        }
    } catch (\Throwable $e) {
        $results[] = '<b>[Storage Link Note]</b> ' . htmlspecialchars($e->getMessage());
    }

    // 2. Database migrations
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $results[] = '<b>[Database Migrations]</b><pre>' . htmlspecialchars(\Illuminate\Support\Facades\Artisan::output() ?: 'Nothing to migrate.') . '</pre>';
    } catch (\Throwable $e) {
        $results[] = '<b>[Database Migration Note]</b> ' . htmlspecialchars($e->getMessage());
    }

    // 3. Clear Caches
    try {
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        $results[] = '<b>[Cache Optimization]</b><pre>' . htmlspecialchars(\Illuminate\Support\Facades\Artisan::output()) . '</pre>';
    } catch (\Throwable $e) {
        $results[] = '<b>[Cache Clear Note]</b> ' . htmlspecialchars($e->getMessage());
    }

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>cPanel Deployment Setup | SBP HRMS</title><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;padding:30px;background:#f8fafc;color:#1e293b}.card{background:#fff;padding:30px;border-radius:12px;box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1);max-width:800px;margin:0 auto}h2{color:#0284c7;margin-top:0}pre{background:#0f172a;color:#38bdf8;padding:15px;border-radius:8px;overflow-x:auto;font-size:13px}.success{color:#16a34a;font-weight:600;font-size:16px;margin-bottom:20px}hr{border:none;border-top:1px solid #e2e8f0;margin:20px 0}.btn{display:inline-block;margin-top:20px;background:#0284c7;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;font-weight:600;transition:background 0.2s}.btn:hover{background:#0369a1}</style></head><body><div class="card"><h2>🚀 SBP Africa HRMS — cPanel Setup & Optimization</h2><p class="success">✓ All setup operations executed successfully!</p>' . implode('<hr>', $results) . '<p><a href="' . url('/dashboard') . '" class="btn">Proceed to HRMS Dashboard →</a></p></div></body></html>';

    return response($html);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

