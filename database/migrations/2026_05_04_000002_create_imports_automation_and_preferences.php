<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Forge production carries a partial state where some of these tables
        // exist but the migrations row was never recorded. Guards keep this
        // migration safe to re-run in that scenario as well as on fresh DBs.
        if (! Schema::hasTable('trip_import_batches')) {
            Schema::create('trip_import_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('source_type');
                $table->string('status')->default('reviewing');
                $table->string('original_file_path')->nullable();
                $table->longText('raw_text')->nullable();
                $table->json('parsed_payload')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['trip_id', 'status']);
            });
        }

        if (! Schema::hasTable('trip_automation_suggestions')) {
            Schema::create('trip_automation_suggestions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
                $table->string('suggestion_type');
                $table->string('summary');
                $table->json('payload')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('dismissed_at')->nullable();
                $table->timestamps();

                $table->index(['trip_id', 'accepted_at', 'dismissed_at']);
            });
        }

        if (! Schema::hasTable('user_travel_preferences')) {
            Schema::create('user_travel_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('home_timezone')->default('UTC');
                $table->string('default_currency', 3)->default('USD');
                $table->json('traveler_profiles')->nullable();
                $table->json('packing_templates')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_travel_preferences');
        Schema::dropIfExists('trip_automation_suggestions');
        Schema::dropIfExists('trip_import_batches');
    }
};
