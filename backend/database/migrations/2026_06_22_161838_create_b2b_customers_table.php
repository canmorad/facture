<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_customers', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('ice')->unique();
            $table->string('rc')->nullable();
            $table->string('if')->nullable();
            $table->string('patente')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_customers');
    }
};