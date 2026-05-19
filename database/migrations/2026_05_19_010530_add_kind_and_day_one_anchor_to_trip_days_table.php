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
        Schema::table('trip_days', function (Blueprint $table) {
            $table->string('kind')->default('vacation')->after('title');
            $table->boolean('is_day_one_anchor')->default(false)->after('kind');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_days', function (Blueprint $table) {
            $table->dropColumn(['kind', 'is_day_one_anchor']);
        });
    }
};
