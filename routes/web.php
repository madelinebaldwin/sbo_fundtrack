<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseReceiptController;
use App\Http\Controllers\PasswordChangeOtpController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::middleware(['auth', 'can:view-finances'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/create', [EventController::class, 'create'])->middleware('can:manage-events')->name('events.create');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');

    Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
    Route::get('/collections/create', [CollectionController::class, 'create'])->middleware('can:manage-finances')->name('collections.create');
    Route::get('/collections/{collection}', [CollectionController::class, 'show'])->name('collections.show');

    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/expenses/create', [ExpenseController::class, 'create'])->middleware('can:manage-finances')->name('expenses.create');
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
    Route::get('/expenses/{expense}/receipt', ExpenseReceiptController::class)->name('expenses.receipt');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export.csv', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/analytics', AnalyticsController::class)->name('analytics.index');
});

Route::middleware(['auth', 'can:manage-events'])->group(function (): void {
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::patch('/events/{event}/archive', [EventController::class, 'archive'])->name('events.archive');
});

Route::middleware(['auth', 'can:manage-finances'])->group(function (): void {
    Route::post('/collections', [CollectionController::class, 'store'])->name('collections.store');
    Route::patch('/collections/{collection}/close', [CollectionController::class, 'close'])->name('collections.close');
    Route::post('/collections/{collection}/contributions', [ContributionController::class, 'store'])->name('contributions.store');

    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
});

Route::middleware(['auth', 'can:review-expenses'])->group(function (): void {
    Route::patch('/contributions/{contribution}/void', [ContributionController::class, 'void'])->name('contributions.void');
    Route::patch('/expenses/{expense}/review', [ExpenseController::class, 'review'])->name('expenses.review');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/password-code', [PasswordChangeOtpController::class, 'send'])->middleware('throttle:3,10')->name('settings.password-code');
    Route::put('/settings/password', [PasswordChangeOtpController::class, 'update'])->middleware('throttle:5,10')->name('settings.password-update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', 'can:manage-settings'])->group(function (): void {
    Route::put('/settings/academic-year', [SettingsController::class, 'updateAcademicYear'])->name('settings.academic-year');
});

Route::middleware(['auth', 'can:manage-users'])->group(function (): void {
    Route::get('/settings/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/settings/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::patch('/settings/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
});
