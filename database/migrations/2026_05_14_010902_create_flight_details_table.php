<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flight_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('cabin_class', 40)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('carry_on_size', 120)->nullable();
            $table->string('carry_on_weight', 40)->nullable();
            $table->decimal('carry_on_fee', 10, 2)->nullable();
            $table->string('personal_item_size', 120)->nullable();
            $table->string('personal_item_weight', 40)->nullable();
            $table->decimal('personal_item_fee', 10, 2)->nullable();
            $table->string('checked_bag_size', 120)->nullable();
            $table->string('checked_bag_weight', 40)->nullable();
            $table->decimal('checked_bag_fee', 10, 2)->nullable();
            $table->decimal('additional_checked_bag_fee', 10, 2)->nullable();
            $table->string('additional_checked_bag_allowance', 80)->nullable();
            $table->text('visa_requirement')->nullable();
            $table->text('passport_validity_rule')->nullable();
            $table->text('layover_notes')->nullable();
            $table->string('online_check_in_opens', 80)->nullable();
            $table->string('boarding_closes', 80)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_details');
    }
};
