<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CentreController;
use App\Http\Controllers\ChildController;
use App\Http\Controllers\ChildDoseDirectiveController;
use App\Http\Controllers\ChildGuardianController;
use App\Http\Controllers\ChildPhotoController;
use App\Http\Controllers\ChildScheduleAssignmentController;
use App\Http\Controllers\DueRegisterController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReminderRuleController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScheduleApprovalController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleDoseController;
use App\Http\Controllers\ScheduleVersionController;
use App\Http\Controllers\StockBatchController;
use App\Http\Controllers\StockTransactionController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VaccinationAmendmentController;
use App\Http\Controllers\VaccinationRecordController;
use App\Http\Controllers\VaccineController;
use App\Http\Controllers\VaccineProductController;
use App\Http\Middleware\EnsureCentreChildAccess;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.update');
});

Route::middleware(['auth', 'active', EnsureCentreChildAccess::class])->group(function (): void {
    Route::get('/dashboard', fn () => view('dashboard'))->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/reports', [ReportsController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');
    Route::get('/audit-log', [AuditLogController::class, 'index'])->middleware('permission:audit.view')->name('audit.index');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('permission:users.manage')->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    });

    Route::middleware('permission:roles.manage')->group(function (): void {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    Route::middleware('permission:children.view')->group(function (): void {
        Route::get('/children', [ChildController::class, 'index'])->name('children.index');
        Route::get('/children/{child}', [ChildController::class, 'show'])->whereNumber('child')->name('children.show');
        Route::get('/children/{child}/photo', [ChildPhotoController::class, 'show'])->whereNumber('child')->name('children.photo');
        Route::get('/guardians', [GuardianController::class, 'index'])->name('guardians.index');
    });

    Route::get('/due-register', DueRegisterController::class)
        ->middleware(['permission:children.view', 'permission:due-register.view'])
        ->name('due-register.index');
    Route::post('/children/{child}/dose-directives', [ChildDoseDirectiveController::class, 'store'])
        ->whereNumber('child')->middleware(['permission:children.view', 'permission:vaccinations.clinical_authorize'])
        ->name('children.dose-directives.store');
    Route::post('/children/{child}/schedule-assignment', [ChildScheduleAssignmentController::class, 'store'])
        ->whereNumber('child')->middleware(['permission:children.view', 'permission:schedules.assign'])
        ->name('children.schedule-assignment.store');

    Route::middleware(['permission:children.view', 'permission:appointments.manage'])->group(function (): void {
        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
        Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('/appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->whereNumber('appointment')->name('appointments.edit');
        Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->whereNumber('appointment')->name('appointments.update');
        Route::post('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->whereNumber('appointment')->name('appointments.status');
    });

    Route::middleware(['permission:children.view', 'permission:reminders.manage'])->group(function (): void {
        Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');
        Route::post('/reminders', [ReminderController::class, 'store'])->name('reminders.store');
        Route::put('/reminders/{reminder}', [ReminderController::class, 'update'])->whereNumber('reminder')->name('reminders.update');
        Route::get('/reminders/rules', [ReminderRuleController::class, 'index'])->name('reminders.rules.index');
        Route::post('/reminders/rules', [ReminderRuleController::class, 'store'])->name('reminders.rules.store');
        Route::put('/reminders/rules/{reminderRule}', [ReminderRuleController::class, 'update'])->whereNumber('reminderRule')->name('reminders.rules.update');
        Route::get('/follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index');
        Route::post('/follow-ups', [FollowUpController::class, 'store'])->name('follow-ups.store');
    });

    Route::middleware('permission:inventory.manage')->group(function (): void {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/receipts/create', [StockBatchController::class, 'create'])->name('inventory.receipts.create');
        Route::post('/inventory/receipts', [StockBatchController::class, 'store'])->name('inventory.receipts.store');
        Route::post('/inventory/batches/{stockBatch}/status', [StockBatchController::class, 'updateStatus'])->whereNumber('stockBatch')->name('inventory.batches.status');
        Route::get('/inventory/reorder-levels', [InventoryController::class, 'reorderLevels'])->name('inventory.reorder-levels.index');
        Route::post('/inventory/reorder-levels', [InventoryController::class, 'saveReorderLevel'])->name('inventory.reorder-levels.store');
        Route::get('/inventory/transactions', [StockTransactionController::class, 'index'])->name('inventory.transactions.index');
        Route::get('/inventory/transactions/create', [StockTransactionController::class, 'create'])->name('inventory.transactions.create');
        Route::post('/inventory/transactions', [StockTransactionController::class, 'store'])->name('inventory.transactions.store');
    });

    Route::middleware(['permission:children.view', 'permission:children.manage'])->group(function (): void {
        Route::get('/children/create', [ChildController::class, 'create'])->name('children.create');
        Route::post('/children', [ChildController::class, 'store'])->name('children.store');
        Route::get('/children/{child}/edit', [ChildController::class, 'edit'])->name('children.edit');
        Route::put('/children/{child}', [ChildController::class, 'update'])->name('children.update');
        Route::post('/children/{child}/guardians', [ChildGuardianController::class, 'store'])->name('children.guardians.store');
        Route::put('/children/{child}/guardians/{guardian}/consent', [ChildGuardianController::class, 'updateConsent'])->name('children.guardians.consent.update');
        Route::delete('/children/{child}/guardians/{guardian}', [ChildGuardianController::class, 'destroy'])->name('children.guardians.destroy');
        Route::get('/guardians/create', [GuardianController::class, 'create'])->name('guardians.create');
        Route::post('/guardians', [GuardianController::class, 'store'])->name('guardians.store');
        Route::get('/guardians/{guardian}/edit', [GuardianController::class, 'edit'])->name('guardians.edit');
        Route::put('/guardians/{guardian}', [GuardianController::class, 'update'])->name('guardians.update');
    });

    Route::middleware('permission:settings.manage')->group(function (): void {
        Route::get('/settings', [SystemSettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SystemSettingController::class, 'update'])->name('settings.update');
        Route::get('/centres', [CentreController::class, 'index'])->name('centres.index');
        Route::get('/centres/create', [CentreController::class, 'create'])->name('centres.create');
        Route::post('/centres', [CentreController::class, 'store'])->name('centres.store');
        Route::get('/centres/{centre}/edit', [CentreController::class, 'edit'])->name('centres.edit');
        Route::put('/centres/{centre}', [CentreController::class, 'update'])->name('centres.update');
    });

    Route::middleware('permission:catalogue.view')->group(function (): void {
        Route::get('/vaccines', [VaccineController::class, 'index'])->name('vaccines.index');
        Route::get('/vaccines/{vaccine}', [VaccineController::class, 'show'])->whereNumber('vaccine')->name('vaccines.show');
    });

    Route::middleware('permission:catalogue.manage')->group(function (): void {
        Route::get('/vaccines/create', [VaccineController::class, 'create'])->name('vaccines.create');
        Route::post('/vaccines', [VaccineController::class, 'store'])->name('vaccines.store');
        Route::get('/vaccines/{vaccine}/edit', [VaccineController::class, 'edit'])->whereNumber('vaccine')->name('vaccines.edit');
        Route::put('/vaccines/{vaccine}', [VaccineController::class, 'update'])->whereNumber('vaccine')->name('vaccines.update');
        Route::get('/vaccines/{vaccine}/products/create', [VaccineProductController::class, 'create'])->whereNumber('vaccine')->name('vaccines.products.create');
        Route::post('/vaccines/{vaccine}/products', [VaccineProductController::class, 'store'])->whereNumber('vaccine')->name('vaccines.products.store');
        Route::get('/vaccines/{vaccine}/products/{product}/edit', [VaccineProductController::class, 'edit'])->whereNumber('vaccine')->whereNumber('product')->scopeBindings()->name('vaccines.products.edit');
        Route::put('/vaccines/{vaccine}/products/{product}', [VaccineProductController::class, 'update'])->whereNumber('vaccine')->whereNumber('product')->scopeBindings()->name('vaccines.products.update');
    });

    Route::middleware('permission:schedules.view')->group(function (): void {
        Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
        Route::get('/schedules/{schedule}', [ScheduleController::class, 'show'])->whereNumber('schedule')->name('schedules.show');
    });

    Route::middleware('permission:schedules.manage')->group(function (): void {
        Route::get('/schedules/create', [ScheduleController::class, 'create'])->name('schedules.create');
        Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
        Route::get('/schedules/{schedule}/edit', [ScheduleController::class, 'edit'])->whereNumber('schedule')->name('schedules.edit');
        Route::put('/schedules/{schedule}', [ScheduleController::class, 'update'])->whereNumber('schedule')->name('schedules.update');
        Route::get('/schedules/{schedule}/versions/create', [ScheduleController::class, 'createVersion'])->whereNumber('schedule')->name('schedules.versions.create');
        Route::post('/schedules/{schedule}/versions', [ScheduleVersionController::class, 'store'])->whereNumber('schedule')->name('schedules.versions.store');
        Route::get('/schedules/{schedule}/doses/create', [ScheduleDoseController::class, 'create'])->whereNumber('schedule')->name('schedules.doses.create');
        Route::post('/schedules/{schedule}/doses', [ScheduleDoseController::class, 'store'])->whereNumber('schedule')->name('schedules.doses.store');
        Route::get('/schedules/{schedule}/doses/{dose}/edit', [ScheduleDoseController::class, 'edit'])->whereNumber('schedule')->whereNumber('dose')->scopeBindings()->name('schedules.doses.edit');
        Route::put('/schedules/{schedule}/doses/{dose}', [ScheduleDoseController::class, 'update'])->whereNumber('schedule')->whereNumber('dose')->scopeBindings()->name('schedules.doses.update');
        Route::delete('/schedules/{schedule}/doses/{dose}', [ScheduleDoseController::class, 'destroy'])->whereNumber('schedule')->whereNumber('dose')->scopeBindings()->name('schedules.doses.destroy');
    });

    Route::post('/schedules/{schedule}/approval', [ScheduleApprovalController::class, 'store'])
        ->whereNumber('schedule')->middleware('permission:schedules.approve')->name('schedules.approval.store');

    Route::middleware(['permission:children.view', 'permission:vaccinations.view'])->group(function (): void {
        Route::get('/children/{child}/vaccinations', [VaccinationRecordController::class, 'index'])->whereNumber('child')->name('children.vaccinations.index');
    });

    Route::middleware(['permission:children.view', 'permission:vaccinations.view', 'permission:vaccinations.record'])->group(function (): void {
        Route::get('/children/{child}/vaccinations/create', [VaccinationRecordController::class, 'create'])->whereNumber('child')->name('children.vaccinations.create');
        Route::post('/children/{child}/vaccinations', [VaccinationRecordController::class, 'store'])->whereNumber('child')->name('children.vaccinations.store');
    });

    Route::middleware(['permission:children.view', 'permission:vaccinations.view', 'permission:vaccinations.amend'])->group(function (): void {
        Route::get('/children/{child}/vaccinations/{vaccinationRecord}/amend', [VaccinationAmendmentController::class, 'edit'])->whereNumber('child')->whereNumber('vaccinationRecord')->scopeBindings()->name('children.vaccinations.amend');
        Route::post('/children/{child}/vaccinations/{vaccinationRecord}/amend', [VaccinationAmendmentController::class, 'store'])->whereNumber('child')->whereNumber('vaccinationRecord')->scopeBindings()->name('children.vaccinations.amend.store');
    });
});
