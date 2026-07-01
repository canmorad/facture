<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('deposit_input_type', ['percentage', 'fixed'])->nullable()->after('type');
            $table->decimal('deposit_input_value', 15, 2)->default(0)->after('deposit_input_type');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['deposit_input_type', 'deposit_input_value']);
        });
    }
};