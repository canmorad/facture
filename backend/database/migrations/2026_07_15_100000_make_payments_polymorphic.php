<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Add polymorphic columns for generic payable relationship
            $table->string('payable_type')->nullable()->after('id');
            $table->unsignedBigInteger('payable_id')->nullable()->after('payable_type');

            // Add index for polymorphic queries
            $table->index(['payable_type', 'payable_id']);
            $table->index(['payable_type', 'payable_id', 'status']);
        });

        // Migrate existing data: set payable_type and payable_id from invoice_id
        DB::statement('UPDATE payments SET payable_type = "App\\\Models\\\Invoice", payable_id = invoice_id WHERE invoice_id IS NOT NULL');

        // Now make the columns NOT NULL (after migration)
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payable_type')->nullable(false)->change();
            $table->unsignedBigInteger('payable_id')->nullable(false)->change();
        });

        // Note: We keep invoice_id for backward compatibility and easier queries
        // Future migrations can remove it if needed
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['payable_type', 'payable_id']);
            $table->dropIndex(['payable_type', 'payable_id', 'status']);
            $table->dropColumn(['payable_type', 'payable_id']);
        });
    }
};
