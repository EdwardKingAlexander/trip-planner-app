<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'itinerary_items',
        'reservations',
        'trip_costs',
        'packing_items',
        'trip_tasks',
        'trip_documents',
        'trip_reminders',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('updated_by_user_id')
                    ->nullable()
                    ->after('created_by_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('updated_by_user_id');
                $table->dropConstrainedForeignId('created_by_user_id');
            });
        }
    }
};
