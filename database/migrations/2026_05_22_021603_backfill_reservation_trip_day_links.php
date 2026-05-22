<?php

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Reservation::query()
            ->whereNotNull('starts_at')
            ->with('trip')
            ->chunkById(200, function (Collection $reservations): void {
                foreach ($reservations as $reservation) {
                    $trip = $reservation->trip;

                    if ($trip === null) {
                        continue;
                    }

                    $dates = $reservation->computeOverlappingDates($trip);

                    if ($dates === []) {
                        continue;
                    }

                    $tripDayIds = $trip->days()
                        ->whereIn('date', array_map(fn ($date) => $date->toDateString(), $dates))
                        ->pluck('id')
                        ->all();

                    if ($tripDayIds === []) {
                        continue;
                    }

                    $reservation->tripDays()->syncWithoutDetaching($tripDayIds);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
