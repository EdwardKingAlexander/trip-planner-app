<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\ReleaseChangedNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('release:notify {description : Description of the change or implementation} {--sha= : Git commit SHA} {--branch=main : Git branch that was pushed}')]
#[Description('Send an in-app release notification to every user.')]
class NotifyCodeRelease extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $description = trim((string) $this->argument('description'));

        if ($description === '') {
            $this->error('A release notification description is required.');

            return self::FAILURE;
        }

        $notified = 0;
        $notification = new ReleaseChangedNotification(
            description: $description,
            releaseSha: $this->option('sha') ? (string) $this->option('sha') : null,
            branch: $this->option('branch') ? (string) $this->option('branch') : null,
        );

        User::query()
            ->select(['id', 'name', 'email'])
            ->chunkById(100, function ($users) use ($notification, &$notified): void {
                Notification::send($users, $notification);
                $notified += $users->count();
            });

        $this->info("Release notification sent to {$notified} user(s).");

        return self::SUCCESS;
    }
}
