<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlightDetails extends Model
{
    protected $table = 'flight_details';

    protected $fillable = [
        'reservation_id',
        'cabin_class',
        'currency',
        'carry_on_size',
        'carry_on_weight',
        'carry_on_fee',
        'personal_item_size',
        'personal_item_weight',
        'personal_item_fee',
        'checked_bag_size',
        'checked_bag_weight',
        'checked_bag_fee',
        'additional_checked_bag_fee',
        'additional_checked_bag_allowance',
        'visa_requirement',
        'passport_validity_rule',
        'layover_notes',
        'online_check_in_opens',
        'boarding_closes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'carry_on_fee' => 'decimal:2',
            'personal_item_fee' => 'decimal:2',
            'checked_bag_fee' => 'decimal:2',
            'additional_checked_bag_fee' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
