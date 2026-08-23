<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\RoleAssignmentController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ServiceUnitController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/**
 * Administration area. Every route is additionally guarded by a policy check
 * inside its controller, so membership of this group is not on its own a grant.
 */
Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::resource('service-units', ServiceUnitController::class)
            ->parameters(['service-units' => 'service_unit']);

        Route::resource('units', UnitController::class);

        Route::resource('users', UserController::class);

        Route::post('users/{user}/assignments', [RoleAssignmentController::class, 'store'])
            ->name('users.assignments.store');
        Route::delete('users/{user}/assignments/{assignment}', [RoleAssignmentController::class, 'destroy'])
            ->name('users.assignments.destroy');

        Route::resource('roles', RoleController::class)->except(['show']);

        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    });
