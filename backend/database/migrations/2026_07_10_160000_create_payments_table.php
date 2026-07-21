<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->enum('payment_mode', ['espece', 'cheque', 'lcn', 'virement', 'carte']);
            $table->decimal('amount', 15, 2);
            $table->date('payment_date')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');

            // Foreign keys to related records
            $table->foreignId('cash_transaction_id')->nullable()->constrained('cash_transactions')->nullOnDelete();
            $table->foreignId('payment_document_id')->nullable()->constrained('payment_documents')->nullOnDelete();
            $table->foreignId('document_relationship_id')->nullable()->constrained('document_relationships')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'payment_date']);
            $table->index(['invoice_id', 'status']);
            $table->index('payment_mode');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
