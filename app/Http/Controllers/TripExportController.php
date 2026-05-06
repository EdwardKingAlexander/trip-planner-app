<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Services\TripExportService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TripExportController extends Controller
{
    public function print(Trip $trip): Response
    {
        $this->authorize('view', $trip);

        $trip->load([
            'collaborators',
            'days.itineraryItems',
            'reservations.flightSegments',
            'reservations.lodgingStay',
            'costs',
            'packingItems',
            'tasks',
            'documents',
            'reminders',
        ]);

        return Inertia::render('Trips/Print', [
            'trip' => $trip,
        ]);
    }

    public function json(Trip $trip, TripExportService $exporter): SymfonyResponse
    {
        $this->authorize('view', $trip);

        return response()->json($exporter->jsonPayload($trip), 200, [
            'Content-Disposition' => 'attachment; filename="trip-'.$trip->id.'.json"',
        ]);
    }

    public function ics(Trip $trip, TripExportService $exporter): SymfonyResponse
    {
        $this->authorize('view', $trip);

        return response($exporter->ics($trip), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="trip-'.$trip->id.'.ics"',
        ]);
    }
}
