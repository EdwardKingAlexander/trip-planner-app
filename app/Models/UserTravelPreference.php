<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTravelPreference extends Model
{
    protected $fillable = [
        'user_id',
        'home_timezone',
        'default_currency',
        'traveler_profiles',
        'packing_templates',
    ];

    protected function casts(): array
    {
        return [
            'traveler_profiles' => 'array',
            'packing_templates' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
