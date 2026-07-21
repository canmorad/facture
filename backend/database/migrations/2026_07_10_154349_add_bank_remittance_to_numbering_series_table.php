<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('numbering_series', function (Blueprint $table) {
            $table->integer('start_from_bank_remittance')->default(1);
            $table->integer('current_bank_remittance')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('numbering_series', function (Blueprint $table) {
            $table->dropColumn(['start_from_bank_remittance', 'current_bank_remittance']);
        });
    }
};
