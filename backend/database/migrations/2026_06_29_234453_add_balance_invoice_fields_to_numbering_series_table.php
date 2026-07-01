<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('numbering_series', function (Blueprint $table) {
            $table->integer('start_from_balance_invoice')->default(1)->after('start_from_deposit_credit_note');
            $table->integer('current_balance_invoice')->default(1)->after('current_deposit_credit_note');
        });
    }

    public function down(): void
    {
        Schema::table('numbering_series', function (Blueprint $table) {
            $table->dropColumn(['start_from_balance_invoice', 'current_balance_invoice']);
        });
    }
};