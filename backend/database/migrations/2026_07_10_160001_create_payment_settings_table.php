<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('default_cash_register_id')->nullable()->constrained('cash_registers')->nullOnDelete();
            $table->boolean('auto_mark_invoice_paid')->default(true);
            $table->boolean('allow_partial_payments')->default(true);
            $table->boolean('allow_overpayment')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
