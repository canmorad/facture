<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('payment_condition')->nullable()->after('conclusion_text');
            $table->string('payment_mode')->nullable()->after('payment_condition');
            $table->string('late_fee_interest')->nullable()->after('payment_mode');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['payment_condition', 'payment_mode', 'late_fee_interest']);
        });
    }
};