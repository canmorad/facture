<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('number')->nullable();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->enum('status', ['DRAFT', 'FINALIZED', 'SENT', 'DEPOSITED', 'RETURNED', 'CANCELLED'])->default('DRAFT');
            $table->date('remittance_date');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->integer('document_count')->default(0);
            $table->string('deposit_slip_reference')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('deposited_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
            $table->index('bank_account_id');
            $table->index('status');
            $table->index('remittance_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_remittances');
    }
};
