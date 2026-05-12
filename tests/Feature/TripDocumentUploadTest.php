<?php

use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function documentUploadTrip(User $owner): Trip
{
    return Trip::create([
        'user_id' => $owner->id,
        'name' => 'Lisbon Documents',
        'destination' => 'Lisbon, Portugal',
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-12',
        'status' => 'planned',
    ]);
}

test('owners can upload a pdf and create a private document row', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $trip = documentUploadTrip($owner);

    $this->actingAs($owner)->post(route('trips.documents.upload', $trip), [
        'files' => [UploadedFile::fake()->create('boarding-pass.pdf', 128, 'application/pdf')],
        'document_type' => 'boarding pass',
    ])->assertRedirect();

    $document = $trip->documents()->first();

    expect($document)->not->toBeNull()
        ->and($document->title)->toBe('boarding-pass')
        ->and($document->original_filename)->toBe('boarding-pass.pdf')
        ->and($document->mime_type)->toBe('application/pdf')
        ->and($document->file_size_bytes)->toBeGreaterThan(0)
        ->and($document->previewKind())->toBe('pdf');

    Storage::disk('local')->assertExists($document->file_path);

    expect($trip->activityEvents()->where('event_type', 'document.uploaded')->exists())->toBeTrue();
});

test('multi file uploads create one row per file with numbered prefix titles', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $trip = documentUploadTrip($owner);

    $this->actingAs($owner)->post(route('trips.documents.upload', $trip), [
        'files' => [
            UploadedFile::fake()->create('outbound.pdf', 64, 'application/pdf'),
            UploadedFile::fake()->image('passport.jpg'),
            UploadedFile::fake()->image('hotel.png'),
        ],
        'title_prefix' => 'Boarding Pass',
        'document_type' => 'travel file',
    ])->assertRedirect();

    expect($trip->documents()->pluck('title')->all())->toBe([
        'Boarding Pass (1)',
        'Boarding Pass (2)',
        'Boarding Pass (3)',
    ]);

    expect($trip->activityEvents()->where('event_type', 'document.uploaded')->count())->toBe(3);
});

test('uploads can be linked to a reservation on the same trip', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $trip = documentUploadTrip($owner);
    $reservation = $trip->reservations()->create([
        'type' => 'flight',
        'title' => 'Outbound flight',
        'status' => 'confirmed',
        'starts_timezone' => 'America/Denver',
        'ends_timezone' => 'Europe/Lisbon',
    ]);

    $this->actingAs($owner)->post(route('trips.documents.upload', $trip), [
        'files' => [UploadedFile::fake()->create('flight.pdf', 64, 'application/pdf')],
        'reservation_id' => $reservation->id,
    ])->assertRedirect();

    expect($trip->documents()->first()->reservation_id)->toBe($reservation->id);
});

test('uploads reject reservation ids from another trip', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $trip = documentUploadTrip($owner);
    $otherTrip = documentUploadTrip($owner);
    $otherReservation = $otherTrip->reservations()->create([
        'type' => 'flight',
        'title' => 'Wrong flight',
        'status' => 'confirmed',
        'starts_timezone' => 'UTC',
        'ends_timezone' => 'UTC',
    ]);

    $this->actingAs($owner)->post(route('trips.documents.upload', $trip), [
        'files' => [UploadedFile::fake()->create('flight.pdf', 64, 'application/pdf')],
        'reservation_id' => $otherReservation->id,
    ])->assertInvalid('reservation_id');

    expect($trip->documents()->count())->toBe(0);
});

test('uploads reject too many files disallowed files and non editors', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $trip = documentUploadTrip($owner);
    $trip->collaborators()->create([
        'user_id' => $viewer->id,
        'email' => $viewer->email,
        'role' => 'viewer',
        'accepted_at' => now(),
    ]);

    $this->actingAs($owner)->post(route('trips.documents.upload', $trip), [
        'files' => array_fill(0, 11, UploadedFile::fake()->create('too-many.pdf', 1, 'application/pdf')),
    ])->assertInvalid('files');

    $this->actingAs($owner)->post(route('trips.documents.upload', $trip), [
        'files' => [UploadedFile::fake()->create('archive.zip', 10, 'application/zip')],
    ])->assertInvalid('files.0');

    $this->actingAs($viewer)->post(route('trips.documents.upload', $trip), [
        'files' => [UploadedFile::fake()->create('boarding-pass.pdf', 64, 'application/pdf')],
    ])->assertForbidden();
});

test('participants can stream files and outsiders cannot', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $trip = documentUploadTrip($owner);

    $this->actingAs($owner)->post(route('trips.documents.upload', $trip), [
        'files' => [UploadedFile::fake()->createWithContent('boarding-pass.pdf', 'fake-pdf-body')],
    ])->assertRedirect();

    $document = $trip->documents()->first();

    $response = $this->actingAs($owner)->get(route('trips.documents.file', [$trip, $document]));

    $response->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="boarding-pass.pdf"');

    expect($response->streamedContent())->toBe('fake-pdf-body');

    $this->actingAs($stranger)
        ->get(route('trips.documents.file', [$trip, $document]))
        ->assertForbidden();

    Storage::disk('local')->delete($document->file_path);

    $this->actingAs($owner)
        ->get(route('trips.documents.file', [$trip, $document]))
        ->assertNotFound();
});

test('deleting a document deletes the underlying private file', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $trip = documentUploadTrip($owner);

    $this->actingAs($owner)->post(route('trips.documents.upload', $trip), [
        'files' => [UploadedFile::fake()->create('receipt.pdf', 64, 'application/pdf')],
    ])->assertRedirect();

    $document = $trip->documents()->first();
    $path = $document->file_path;

    Storage::disk('local')->assertExists($path);

    $this->actingAs($owner)
        ->delete(route('trips.documents.destroy', [$trip, $document]))
        ->assertRedirect();

    Storage::disk('local')->assertMissing($path);
});

test('text document notes can be linked and unlinked from reservations', function () {
    $owner = User::factory()->create();
    $trip = documentUploadTrip($owner);
    $reservation = $trip->reservations()->create([
        'type' => 'lodging',
        'title' => 'Hotel',
        'status' => 'confirmed',
        'starts_timezone' => 'UTC',
        'ends_timezone' => 'UTC',
    ]);
    $document = $trip->documents()->create([
        'title' => 'Hotel policy',
        'document_type' => 'note',
    ]);

    $this->actingAs($owner)->patch(route('trips.documents.update', [$trip, $document]), [
        'title' => 'Hotel policy',
        'document_type' => 'note',
        'reservation_id' => $reservation->id,
    ])->assertRedirect();

    expect($document->fresh()->reservation_id)->toBe($reservation->id);

    $this->actingAs($owner)->patch(route('trips.documents.update', [$trip, $document]), [
        'title' => 'Hotel policy',
        'document_type' => 'note',
        'reservation_id' => null,
    ])->assertRedirect();

    expect($document->fresh()->reservation_id)->toBeNull();
});
