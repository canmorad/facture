<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('Companies')->cascadeOnDelete();
            $table->enum('document_type', [
                'QUOTE',
                'INVOICE',
                'PURCHASE_ORDER',
                'DELIVERY_NOTE',
                'CREDIT_NOTE',
                'DEPOSIT_INVOICE',
                'DEPOSIT_CREDIT_NOTE'
            ]);
            $table->boolean('hide_signature_block')->default(false);
            $table->boolean('show_username_pdf')->default(true);
            $table->text('intro_text')->nullable();
            $table->text('conclusion_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_settings');
    }
};