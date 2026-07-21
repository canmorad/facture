<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('cash_register_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['in', 'out', 'transfer']);
            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->default('cash');
            $table->string('reference')->nullable();
            $table->string('description');
            $table->nullableMorphs('transactionable');
            $table->foreignId('from_cash_register_id')->nullable()->constrained('cash_registers')->nullOnDelete();
            $table->foreignId('to_cash_register_id')->nullable()->constrained('cash_registers')->nullOnDelete();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('transaction_date');
            $table->timestamps();

            $table->index(['company_id', 'cash_register_id']);
            $table->index(['company_id', 'session_id']);
            $table->index(['transaction_date']);
            $table->index(['type', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
