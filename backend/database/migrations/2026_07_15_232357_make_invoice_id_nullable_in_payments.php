<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['invoice_id']);

            // Make invoice_id nullable (for polymorphic relation support)
            $table->unsignedBigInteger('invoice_id')->nullable()->change();

            // Re-add the foreign key as nullable
            $table->foreign('invoice_id')
                  ->references('id')
                  ->on('invoices')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop nullable foreign key
            $table->dropForeign(['invoice_id']);

            // Restore original NOT NULL constraint
            $table->unsignedBigInteger('invoice_id')->nullable(false)->change();

            // Re-add foreign key with restrict
            $table->foreign('invoice_id')
                  ->references('id')
                  ->on('invoices')
                  ->restrictOnDelete();
        });
    }
};
