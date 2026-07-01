<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['STANDARD', 'DEPOSIT'])->default('STANDARD');
            $table->text('reason');
            $table->enum('status', ['DRAFT', 'FINALIZED', 'SENT', 'APPLIED'])->default('DRAFT');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};