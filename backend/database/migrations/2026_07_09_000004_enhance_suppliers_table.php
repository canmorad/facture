<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->enum('supplier_type', ['enterprise', 'individual'])->default('enterprise')->after('address');
            $table->string('tax_id')->nullable()->after('supplier_type');
            $table->boolean('subject_to_withholding_tax')->default(true)->after('tax_id');
            $table->index('supplier_type');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex('suppliers_supplier_type_index');
            $table->dropColumn(['supplier_type', 'tax_id', 'subject_to_withholding_tax']);
        });
    }
};
