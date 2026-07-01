<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('logo')->nullable();
            $table->string('ice', 15)->nullable();
            $table->string('if', 8)->nullable();
            $table->string('rc')->nullable();
            $table->string('patente')->nullable();
            $table->string('cnss')->nullable();
            $table->string('email');
            $table->string('phone');
            $table->string('website')->nullable();
            $table->text('address');
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Maroc');
            $table->string('template_id')->default('classic');
             $table->string('signature')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
