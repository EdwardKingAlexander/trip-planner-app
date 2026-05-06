<?php

namespace App\Models;

use App\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripTask extends Model
{
    use TracksAuthor;

    protected $fillable = ['trip_id', 'title', 'description', 'due_at', 'completed_at', 'priority'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
