<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('Companies')->unique()->cascadeOnDelete();
            $table->string('format')->default('<doc><aa><cmp>');
            $table->integer('min_size')->default(5);
            $table->enum('reset_period', ['never', 'yearly', 'monthly'])->default('yearly');

            $table->integer('start_from_invoice')->default(1);
            $table->integer('start_from_quote')->default(1);
            $table->integer('start_from_credit_note')->default(1);
            $table->integer('start_from_deposit_invoice')->default(1);
            $table->integer('start_from_deposit_credit_note')->default(1);
            $table->integer('start_from_delivery_note')->default(1);
            $table->integer('start_from_purchase_order')->default(1);

            $table->integer('current_invoice')->default(1);
            $table->integer('current_quote')->default(1);
            $table->integer('current_credit_note')->default(1);
            $table->integer('current_deposit_invoice')->default(1);
            $table->integer('current_deposit_credit_note')->default(1);
            $table->integer('current_delivery_note')->default(1);
            $table->integer('current_purchase_order')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_series');
    }
};