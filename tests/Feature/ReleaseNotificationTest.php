<?php

use App\Models\User;
use App\Notifications\ReleaseChangedNotification;
use Illuminate\Support\Facades\Notification;

test('release notify command sends a database notification to every user', function () {
    Notification::fake();

    $users = User::factory()->count(3)->create();

    $this->artisan('release:notify', [
        'description' => 'Implemented document uploads and reservation attachments.',
        '--sha' => 'abc123',
        '--branch' => 'main',
    ])->assertSuccessful();

    Notification::assertSentTo($users, ReleaseChangedNotification::class, function (ReleaseChangedNotification $notification): bool {
        return $notification->description === 'Implemented document uploads and reservation attachments.'
            && $notification->releaseSha === 'abc123'
            && $notification->branch === 'main';
    });
});
