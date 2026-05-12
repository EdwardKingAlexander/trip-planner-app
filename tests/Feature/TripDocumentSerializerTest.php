<?php

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('trip payload includes document file metadata and reservation document counts', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $trip = Trip::create([
        'user_id' => $owner->id,
        'name' => 'Paris Documents',
        'destination' => 'Paris, France',
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-05',
        'status' => 'planned',
    ]);
    $reservation = $trip->reservations()->create([
        'type' => 'flight',
        'title' => 'Outbound flight',
        'status' => 'confirmed',
        'starts_timezone' => 'America/Denver',
        'ends_timezone' => 'Europe/Paris',
    ]);
    $document = $trip->documents()->create([
        'reservation_id' => $reservation->id,
        'title' => 'Boarding pass',
        'document_type' => 'boarding pass',
        'file_path' => 'trips/1/documents/1/pass.pdf',
        'original_filename' => 'pass.pdf',
        'mime_type' => 'application/pdf',
        'file_size_bytes' => 2048,
    ]);

    Storage::disk('local')->put($document->file_path, 'pdf');

    $this->actingAs($owner)
        ->get(route('trips.show', $trip))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Trips/Show')
            ->where('trip.documents.0.file_url', route('trips.documents.file', [$trip, $document], false))
            ->where('trip.documents.0.preview_kind', 'pdf')
            ->where('trip.documents.0.size_label', '2 KB')
            ->where('trip.reservations.0.document_count', 1));
});
