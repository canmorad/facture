<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_settings', function (Blueprint $table) {
            $table->enum('document_type', [
                'QUOTE',
                'INVOICE',
                'PROFORMA',
                'PURCHASE_ORDER',
                'DELIVERY_NOTE',
                'CREDIT_NOTE',
                'DEPOSIT_INVOICE',
                'DEPOSIT_CREDIT_NOTE'
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('document_settings', function (Blueprint $table) {
            $table->enum('document_type', [
                'QUOTE',
                'INVOICE',
                'PURCHASE_ORDER',
                'DELIVERY_NOTE',
                'CREDIT_NOTE',
                'DEPOSIT_INVOICE',
                'DEPOSIT_CREDIT_NOTE'
            ])->change();
        });
    }
};
