<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('destination');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status')->default('planned');
            $table->text('summary')->nullable();
            $table->string('cover_theme')->default('coastal');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'starts_on']);
            $table->index(['status', 'starts_on']);
        });

        Schema::create('trip_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('role')->default('viewer');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['trip_id', 'email']);
            $table->index(['email', 'accepted_at']);
        });

        Schema::create('trip_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['trip_id', 'date']);
        });

        Schema::create('itinerary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_day_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('custom');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location_name')->nullable();
            $table->string('address')->nullable();
            $table->string('map_url')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('timezone')->default('UTC');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('planned');
            $table->timestamps();

            $table->index(['trip_id', 'starts_at']);
            $table->index(['trip_day_id', 'sort_order']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('itinerary_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('custom');
            $table->string('title');
            $table->string('provider_name')->nullable();
            $table->string('booking_reference')->nullable();
            $table->string('booking_source')->nullable();
            $table->string('status')->default('reserved');
            $table->dateTime('starts_at')->nullable();
            $table->string('starts_timezone')->default('UTC');
            $table->dateTime('ends_at')->nullable();
            $table->string('ends_timezone')->default('UTC');
            $table->string('location_name')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->text('notes')->nullable();
            $table->json('raw_details')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'starts_at']);
            $table->index(['type', 'status']);
        });

        Schema::create('flight_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('airline')->nullable();
            $table->string('flight_number')->nullable();
            $table->string('confirmation_code')->nullable();
            $table->string('departure_airport')->nullable();
            $table->string('arrival_airport')->nullable();
            $table->dateTime('departs_at')->nullable();
            $table->string('departure_timezone')->default('UTC');
            $table->dateTime('arrives_at')->nullable();
            $table->string('arrival_timezone')->default('UTC');
            $table->string('seat')->nullable();
            $table->string('terminal')->nullable();
            $table->string('gate')->nullable();
            $table->string('baggage_claim')->nullable();
            $table->unsignedInteger('segment_order')->default(0);
            $table->timestamps();
        });

        Schema::create('lodging_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('property_name');
            $table->string('room_type')->nullable();
            $table->dateTime('check_in_at')->nullable();
            $table->string('check_in_timezone')->default('UTC');
            $table->dateTime('check_out_at')->nullable();
            $table->string('check_out_timezone')->default('UTC');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->text('late_arrival_note')->nullable();
            $table->text('parking_details')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category')->default('custom');
            $table->string('label');
            $table->decimal('planned_amount', 10, 2)->nullable();
            $table->decimal('actual_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->date('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('packing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('traveler_name')->nullable();
            $table->string('category')->default('general');
            $table->string('label');
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_packed')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('priority')->default('normal');
            $table->timestamps();
        });

        Schema::create('trip_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('document_type')->default('confirmation');
            $table->string('file_path')->nullable();
            $table->date('expires_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('remindable_type')->nullable();
            $table->unsignedBigInteger('remindable_id')->nullable();
            $table->string('label');
            $table->dateTime('remind_at');
            $table->string('timezone')->default('UTC');
            $table->json('delivery_channels')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'remind_at']);
        });

        Schema::create('trip_activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->string('summary');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_activity_events');
        Schema::dropIfExists('trip_reminders');
        Schema::dropIfExists('trip_documents');
        Schema::dropIfExists('trip_tasks');
        Schema::dropIfExists('packing_items');
        Schema::dropIfExists('trip_costs');
        Schema::dropIfExists('lodging_stays');
        Schema::dropIfExists('flight_segments');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('itinerary_items');
        Schema::dropIfExists('trip_days');
        Schema::dropIfExists('trip_collaborators');
        Schema::dropIfExists('trips');
    }
};
