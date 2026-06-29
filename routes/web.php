<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\TemplateController as AdminTemplateController;
use App\Http\Controllers\Business\AuthController as BusinessAuthController;
use App\Http\Controllers\Business\ContactController;
use App\Http\Controllers\Business\DashboardController as BusinessDashboardController;
use App\Http\Controllers\Business\GroupController;
use App\Http\Controllers\Business\MessageController;
use App\Http\Controllers\Business\SequenceController;
use App\Http\Controllers\Business\TemplateController as BusinessTemplateController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/business');
Route::redirect('/login', '/business/login');

Route::prefix('master')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

        Route::middleware('master.auth')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::resource('orders', AdminOrderController::class)->only(['index', 'create', 'store']);
        Route::post('orders/package', [AdminOrderController::class, 'storePackage'])->name('orders.package.store');
        Route::delete('orders/{order}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');
        Route::get('packages', [AdminSettingController::class, 'packages'])->name('packages.index');
        Route::get('settings/tokens', [AdminSettingController::class, 'tokens'])->name('settings.tokens');
        Route::post('settings/app', [AdminSettingController::class, 'storeAppSettings'])->name('settings.app.store');
        Route::post('settings/tokens', [AdminSettingController::class, 'storeToken'])->name('settings.tokens.store');
        Route::post('settings/package', [AdminSettingController::class, 'storePackage'])->name('settings.package.store');
        Route::get('templates', [AdminTemplateController::class, 'index'])->name('templates.index');
    });
});

Route::prefix('business')->name('business.')->group(function () {
    Route::get('/login', [BusinessAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [BusinessAuthController::class, 'login'])->name('login.store');
    Route::post('/logout', [BusinessAuthController::class, 'logout'])->name('logout');

    Route::middleware('business.auth')->group(function () {
        Route::get('/', [BusinessDashboardController::class, 'index'])->name('dashboard');

        Route::get('contacts', [ContactController::class, 'create'])->name('contacts.index');
        Route::get('contacts/import', [ContactController::class, 'importForm'])->name('contacts.import');
        Route::get('contacts/import/sample', [ContactController::class, 'importSample'])->name('contacts.import.sample');
        Route::post('contacts/import', [ContactController::class, 'import'])->name('contacts.import.store');
        Route::get('contacts/group/{group}', [ContactController::class, 'group'])->name('contacts.group');
        Route::resource('contacts', ContactController::class)->only(['create', 'store']);
        Route::post('contacts/{contact}/status', [ContactController::class, 'updateStatus'])->name('contacts.status');
        Route::post('contacts/{contact}/followups', [ContactController::class, 'storeFollowUp'])->name('contacts.followups.store');
        Route::resource('groups', GroupController::class)->only(['index', 'store']);

        Route::get('templates/fetch/{template}', [BusinessTemplateController::class, 'fetch'])->name('templates.fetch');
        Route::resource('templates', BusinessTemplateController::class)->only(['index', 'create', 'store']);

        Route::get('sequences', [SequenceController::class, 'index'])->name('sequences.index');
        Route::post('sequences', [SequenceController::class, 'store'])->name('sequences.store');

        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('messages/send', [MessageController::class, 'create'])->name('messages.create');
        Route::post('messages/send', [MessageController::class, 'send'])->name('messages.send');
        Route::post('packages/request', [BusinessDashboardController::class, 'requestLimitIncrease'])->name('packages.request');
    });
});
