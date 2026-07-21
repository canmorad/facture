<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('numbering_series', function (Blueprint $table) {
            $table->integer('start_from_proforma')->default(1)->after('start_from_purchase_order');
            $table->integer('current_proforma')->default(1)->after('current_purchase_order');
        });
    }

    public function down(): void
    {
        Schema::table('numbering_series', function (Blueprint $table) {
            $table->dropColumn(['start_from_proforma', 'current_proforma']);
        });
    }
};
