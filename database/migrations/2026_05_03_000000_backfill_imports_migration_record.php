<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reconcile production databases where the imports/automation tables were
     * created out-of-band but the matching `migrations` row was never written,
     * causing every subsequent `migrate` to crash with "table already exists".
     * On fresh databases the tables don't exist yet, so this is a no-op.
     */
    public function up(): void
    {
        $migration = '2026_05_04_000002_create_imports_automation_and_preferences';

        $tablesAlreadyExist = Schema::hasTable('trip_import_batches')
            && Schema::hasTable('trip_automation_suggestions')
            && Schema::hasTable('user_travel_preferences');

        if (! $tablesAlreadyExist) {
            return;
        }

        $alreadyRecorded = DB::table('migrations')
            ->where('migration', $migration)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        DB::table('migrations')->insert([
            'migration' => $migration,
            'batch' => (int) DB::table('migrations')->max('batch') + 1,
        ]);
    }

    public function down(): void
    {
        DB::table('migrations')
            ->where('migration', '2026_05_04_000002_create_imports_automation_and_preferences')
            ->delete();
    }
};
