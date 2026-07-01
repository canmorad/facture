<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('Companies')->cascadeOnDelete()->unique();
            $table->string('font_family')->default('Nunito');
            $table->string('primary_color')->default('#062121');
            $table->string('background_pattern')->default('none');
            $table->string('table_border_style')->default('sharp');
            $table->string('table_line_style')->default('standard');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_themes');
    }
};