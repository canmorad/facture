<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('Companies')->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_conditions');
    }
};
