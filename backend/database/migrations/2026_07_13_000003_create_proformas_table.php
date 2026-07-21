<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proformas', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['DRAFT', 'FINALIZED', 'SENT', 'CONVERTED', 'EXPIRED', 'CANCELLED'])->default('DRAFT');
            $table->date('validity_date')->nullable();
            $table->unsignedBigInteger('converted_to_invoice_id')->nullable();
            $table->datetime('finalized_at')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->datetime('converted_at')->nullable();
            $table->datetime('expired_at')->nullable();
            $table->datetime('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('converted_to_invoice_id')->nullable()->references('id')->on('invoices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proformas');
    }
};
