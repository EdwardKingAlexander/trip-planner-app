<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trip_day_reservation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['trip_day_id', 'reservation_id']);
            $table->index('reservation_id');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('itinerary_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('itinerary_item_id')->nullable()->after('trip_id')->constrained()->nullOnDelete();
        });

        Schema::dropIfExists('trip_day_reservation');
    }
};
