<?php

use App\Http\Controllers\TripAutomationController;
use App\Http\Controllers\TripCollaboratorController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripExportController;
use App\Http\Controllers\TripImportController;
use App\Http\Controllers\TripItineraryController;
use App\Http\Controllers\TripPlanningController;
use App\Http\Controllers\TripReservationController;
use App\Http\Controllers\TripSearchController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', 'trips')->name('dashboard');
    Route::get('trips/search', TripSearchController::class)->name('trips.search');
    Route::resource('trips', TripController::class);
    Route::get('trips/{trip}/print', [TripExportController::class, 'print'])->name('trips.print');
    Route::get('trips/{trip}/export.json', [TripExportController::class, 'json'])->name('trips.export.json');
    Route::get('trips/{trip}/export.ics', [TripExportController::class, 'ics'])->name('trips.export.ics');
    Route::post('trips/{trip}/itinerary-items', [TripItineraryController::class, 'store'])->name('trips.itinerary-items.store');
    Route::post('trips/{trip}/reservations', [TripReservationController::class, 'store'])->name('trips.reservations.store');
    Route::post('trips/{trip}/costs', [TripPlanningController::class, 'cost'])->name('trips.costs.store');
    Route::post('trips/{trip}/packing-items', [TripPlanningController::class, 'packing'])->name('trips.packing-items.store');
    Route::post('trips/{trip}/tasks', [TripPlanningController::class, 'task'])->name('trips.tasks.store');
    Route::post('trips/{trip}/documents', [TripPlanningController::class, 'document'])->name('trips.documents.store');
    Route::post('trips/{trip}/reminders', [TripPlanningController::class, 'reminder'])->name('trips.reminders.store');
    Route::post('trips/{trip}/collaborators', [TripCollaboratorController::class, 'store'])->name('trips.collaborators.store');
    Route::delete('trips/{trip}/collaborators/{collaborator}', [TripCollaboratorController::class, 'destroy'])->name('trips.collaborators.destroy');
    Route::post('trips/{trip}/imports', [TripImportController::class, 'store'])->name('trips.imports.store');
    Route::post('trips/{trip}/imports/{importBatch}/commit', [TripImportController::class, 'commit'])->name('trips.imports.commit');
    Route::post('trips/{trip}/imports/{importBatch}/discard', [TripImportController::class, 'discard'])->name('trips.imports.discard');
    Route::post('trips/{trip}/automation/refresh', [TripAutomationController::class, 'refresh'])->name('trips.automation.refresh');
    Route::post('trips/{trip}/automation/{suggestion}/accept', [TripAutomationController::class, 'accept'])->name('trips.automation.accept');
    Route::post('trips/{trip}/automation/{suggestion}/dismiss', [TripAutomationController::class, 'dismiss'])->name('trips.automation.dismiss');
});

require __DIR__.'/settings.php';
