<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('Companies')->cascadeOnDelete();
            $table->string('label');
            $table->string('bank_name');
            $table->string('rib', 24);
            $table->string('iban')->nullable();
            $table->string('swift')->nullable();
            $table->string('currency')->default('MAD');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'rib']);
            $table->index(['company_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
