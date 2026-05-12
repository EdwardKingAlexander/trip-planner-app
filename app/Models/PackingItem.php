<?php

namespace App\Models;

use App\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackingItem extends Model
{
    use TracksAuthor;

    protected $fillable = ['trip_id', 'traveler_name', 'assigned_to_user_id', 'category', 'label', 'quantity', 'is_packed', 'sort_order', 'notes'];

    protected function casts(): array
    {
        return ['is_packed' => 'boolean'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
