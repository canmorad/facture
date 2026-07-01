<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('quote_id')->constrained('quotes')->restrictOnDelete();
            $table->enum('status', ['DRAFT', 'FINALIZED', 'PAID', 'CANCELLED'])->default('DRAFT');
            $table->enum('input_type', ['percentage', 'fixed']);
            $table->decimal('input_value', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};