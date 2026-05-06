<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_activity_events', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_activity_events', 'changed_area')) {
                $table->string('changed_area')->after('event_type')->index();
            }
            if (! Schema::hasColumn('trip_activity_events', 'subject_type')) {
                $table->string('subject_type')->nullable()->after('changed_area');
            }
            if (! Schema::hasColumn('trip_activity_events', 'subject_id')) {
                $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trip_activity_events', function (Blueprint $table) {
            if (Schema::hasColumn('trip_activity_events', 'subject_id')) {
                $table->dropColumn('subject_id');
            }
            if (Schema::hasColumn('trip_activity_events', 'subject_type')) {
                $table->dropColumn('subject_type');
            }
            if (Schema::hasColumn('trip_activity_events', 'changed_area')) {
                $table->dropIndex(['changed_area']);
                $table->dropColumn('changed_area');
            }
        });
    }
};
