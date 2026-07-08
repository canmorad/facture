<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('parent_document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->string('number', 50)->nullable();
            $table->decimal('total_ht', 15, 2);
            $table->decimal('total_tva', 15, 2);
            $table->decimal('total_ttc', 15, 2);
            $table->enum('global_discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('global_discount_value', 15, 2)->default(0);
            $table->decimal('global_discount_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->text('intro_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->text('conclusion_text')->nullable();
            $table->string('payment_condition')->nullable();
            $table->string('payment_mode')->nullable();
            $table->string('late_fee_interest')->nullable();
            $table->string('documentable_type', 50);
            $table->unsignedBigInteger('documentable_id');
            $table->timestamps();
            $table->index(['documentable_type', 'documentable_id']);
            $table->index('parent_document_id');
            $table->unique(['company_id', 'documentable_type', 'number'], 'uq_doc_company_type_number');
        });

        Schema::create('document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->text('description');
            $table->string('product_type')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('total_ht', 15, 2);
            $table->decimal('total_ttc', 15, 2);
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['DRAFT', 'FINALIZED', 'SENT', 'SIGNED', 'EXPIRED'])->default('DRAFT');
            $table->date('valid_until')->nullable();
            $table->datetime('finalized_at')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->datetime('signed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['DRAFT', 'FINALIZED', 'SENT', 'CONFIRMED', 'CANCELLED'])->default('DRAFT');
            $table->date('expected_date')->nullable();
            $table->datetime('finalized_at')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->datetime('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['DRAFT', 'FINALIZED', 'SENT', 'DELIVERED'])->default('DRAFT');
            $table->date('delivery_date')->nullable();
            $table->datetime('finalized_at')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->datetime('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['DRAFT', 'FINALIZED', 'SENT', 'PAID', 'OVERDUE', 'CANCELLED'])->default('DRAFT');
            $table->date('due_date')->nullable();
            $table->enum('type', ['STANDARD', 'ACOMPTE', 'SOLDE'])->default('STANDARD');
            $table->datetime('finalized_at')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->datetime('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['STANDARD', 'DEPOSIT'])->default('STANDARD');
            $table->text('reason')->nullable();
            $table->enum('status', ['DRAFT', 'FINALIZED', 'SENT', 'APPLIED'])->default('DRAFT');
            $table->datetime('finalized_at')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->datetime('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('quote_id')->constrained('quotes')->restrictOnDelete();
            $table->enum('status', ['DRAFT', 'FINALIZED', 'PAID', 'CANCELLED'])->default('DRAFT');
            $table->enum('input_type', ['percentage', 'fixed']);
            $table->decimal('input_value', 15, 2);
            $table->datetime('finalized_at')->nullable();
            $table->datetime('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('deducted_deposit_id')->constrained('deposits')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->unique(['invoice_id', 'deducted_deposit_id'], 'uq_invoice_deducted_deposit');
        });

        Schema::create('document_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('child_id')->constrained('documents')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_links');
        Schema::dropIfExists('invoice_deductions');
        Schema::dropIfExists('deposits');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('delivery_notes');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('document_items');
        Schema::dropIfExists('documents');
    }
};