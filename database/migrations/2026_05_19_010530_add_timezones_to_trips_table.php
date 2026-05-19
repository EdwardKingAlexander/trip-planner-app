<?php

use App\Support\TimezoneLookup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('destination_timezone')->nullable()->after('destination');
            $table->string('home_timezone')->nullable()->after('destination_timezone');
        });

        DB::table('trips')
            ->whereNull('destination_timezone')
            ->orderBy('id')
            ->select(['id', 'destination'])
            ->lazyById()
            ->each(function (object $trip): void {
                $timezone = TimezoneLookup::guessDestinationTimezone((string) $trip->destination);

                if ($timezone === null) {
                    return;
                }

                DB::table('trips')
                    ->where('id', $trip->id)
                    ->whereNull('destination_timezone')
                    ->update([
                        'destination_timezone' => $timezone,
                        'updated_at' => now(),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['destination_timezone', 'home_timezone']);
        });
    }
};
