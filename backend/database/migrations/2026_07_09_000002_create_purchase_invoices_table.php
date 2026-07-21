<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('fournisseur_id')->constrained('fournisseurs')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('invoice_number')->nullable();
            $table->string('supplier_invoice_number');

            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            $table->decimal('amount_ht', 15, 2)->default(0);
            $table->decimal('amount_tva', 15, 2)->default(0);
            $table->decimal('amount_ttc', 15, 2)->default(0);

            $table->boolean('apply_withholding_tax')->default(false);
            $table->decimal('withholding_tax_rate', 5, 2)->default(0);
            $table->decimal('withholding_tax_amount', 15, 2)->default(0);
            $table->decimal('amount_after_withholding', 15, 2)->default(0);

            $table->json('taxes')->nullable();

            $table->enum('global_discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('global_discount_value', 15, 2)->default(0);
            $table->decimal('global_discount_amount', 15, 2)->default(0);

            $table->enum('status', ['draft', 'validated', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->date('paid_at')->nullable();

            $table->boolean('is_ocr_extracted')->default(false);
            $table->text('ocr_raw_data')->nullable();
            $table->unsignedBigInteger('media_id')->nullable();

            $table->text('notes')->nullable();
            $table->text('payment_terms')->nullable();
            $table->string('payment_mode')->nullable();

            $table->timestamp('validated_at')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'supplier_invoice_number'], 'uq_purchase_inv_company_supplier');
            $table->index(['company_id', 'status']);
            $table->index(['invoice_date', 'status']);
            $table->index('fournisseur_id');
            $table->index('media_id');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->foreign('validated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
