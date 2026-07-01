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
            $table->foreignId('company_id')->constrained('Companies')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
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
            $table->string('documentable_type', 50);
            $table->unsignedBigInteger('documentable_id');
            $table->timestamps();
            $table->index(['documentable_type', 'documentable_id']);
            $table->unique(['company_id', 'documentable_type', 'number'])->whereNotNull('number'); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};