<?php

use App\Http\Controllers\TripCollaboratorController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripItineraryController;
use App\Http\Controllers\TripPlanningController;
use App\Http\Controllers\TripReservationController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', 'trips')->name('dashboard');
    Route::resource('trips', TripController::class);
    Route::post('trips/{trip}/itinerary-items', [TripItineraryController::class, 'store'])->name('trips.itinerary-items.store');
    Route::post('trips/{trip}/reservations', [TripReservationController::class, 'store'])->name('trips.reservations.store');
    Route::post('trips/{trip}/costs', [TripPlanningController::class, 'cost'])->name('trips.costs.store');
    Route::post('trips/{trip}/packing-items', [TripPlanningController::class, 'packing'])->name('trips.packing-items.store');
    Route::post('trips/{trip}/tasks', [TripPlanningController::class, 'task'])->name('trips.tasks.store');
    Route::post('trips/{trip}/documents', [TripPlanningController::class, 'document'])->name('trips.documents.store');
    Route::post('trips/{trip}/reminders', [TripPlanningController::class, 'reminder'])->name('trips.reminders.store');
    Route::post('trips/{trip}/collaborators', [TripCollaboratorController::class, 'store'])->name('trips.collaborators.store');
    Route::delete('trips/{trip}/collaborators/{collaborator}', [TripCollaboratorController::class, 'destroy'])->name('trips.collaborators.destroy');
});

require __DIR__.'/settings.php';
