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
        // Drop the foreign key constraint
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropForeign(['quote_id']);
        });

        // Make the column nullable
        Schema::table('deposits', function (Blueprint $table) {
            $table->unsignedBigInteger('quote_id')->nullable()->change();
        });

        // Re-add the foreign key constraint (nullable)
        Schema::table('deposits', function (Blueprint $table) {
            $table->foreign('quote_id')->references('id')->on('quotes')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the foreign key constraint
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropForeign(['quote_id']);
        });

        // Make the column NOT NULL (revert)
        Schema::table('deposits', function (Blueprint $table) {
            $table->unsignedBigInteger('quote_id')->nullable(false)->change();
        });

        // Re-add the foreign key constraint (NOT NULL)
        Schema::table('deposits', function (Blueprint $table) {
            $table->foreign('quote_id')->references('id')->on('quotes')->restrictOnDelete();
        });
    }
};
