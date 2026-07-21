<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->enum('type', ['cheque', 'lcn']);
            $table->string('number');
            $table->date('due_date');
            $table->decimal('amount', 15, 2);
            $table->string('drawer_name')->nullable();
            $table->string('drawer_bank')->nullable();
            $table->string('drawer_account')->nullable();
            $table->string('drawer_address')->nullable();
            $table->string('beneficiary_name')->nullable();
            $table->enum('status', ['pending', 'remitted', 'deposited', 'returned', 'paid', 'cancelled'])->default('pending');
            $table->foreignId('bank_remittance_id')->nullable()->constrained('bank_remittances')->nullOnDelete();
            $table->date('deposit_date')->nullable();
            $table->date('return_date')->nullable();
            $table->text('return_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('type');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_documents');
    }
};
