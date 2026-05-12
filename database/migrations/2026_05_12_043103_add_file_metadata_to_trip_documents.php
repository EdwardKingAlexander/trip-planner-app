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
        Schema::table('trip_documents', function (Blueprint $table): void {
            $table->string('original_filename')->nullable()->after('file_path');
            $table->string('mime_type', 120)->nullable()->after('original_filename');
            $table->unsignedBigInteger('file_size_bytes')->nullable()->after('mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_documents', function (Blueprint $table): void {
            $table->dropColumn(['original_filename', 'mime_type', 'file_size_bytes']);
        });
    }
};
