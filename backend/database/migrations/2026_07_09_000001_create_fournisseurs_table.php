<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name');
            $table->string('ice')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Maroc');
            $table->enum('supplier_type', ['enterprise', 'individual'])->default('enterprise');
            $table->string('tax_id')->nullable();
            $table->boolean('subject_to_withholding_tax')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'supplier_type']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fournisseurs');
    }
};
