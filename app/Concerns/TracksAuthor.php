<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait TracksAuthor
{
    public static function bootTracksAuthor(): void
    {
        static::creating(function ($model): void {
            $userId = Auth::id();

            if ($userId === null) {
                return;
            }

            if (! $model->getAttribute('created_by_user_id')) {
                $model->setAttribute('created_by_user_id', $userId);
            }

            if (! $model->getAttribute('updated_by_user_id')) {
                $model->setAttribute('updated_by_user_id', $userId);
            }
        });

        static::updating(function ($model): void {
            $userId = Auth::id();

            if ($userId === null) {
                return;
            }

            $model->setAttribute('updated_by_user_id', $userId);
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
