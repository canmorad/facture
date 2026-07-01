<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('Companies')->cascadeOnDelete();
            $table->string('libelle');
            $table->decimal('rate', 5, 2)->default(0.00);
            $table->string('motif_exoneration')->nullable();
            $table->boolean('is_actif')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'is_actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};