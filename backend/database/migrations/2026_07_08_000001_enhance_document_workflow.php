<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('late_fee_interest');
            $table->timestamp('locked_at')->nullable()->after('is_locked');
            $table->unsignedBigInteger('locked_by')->nullable()->after('locked_at');
            $table->string('lock_reason')->nullable()->after('locked_by');
            $table->index('is_locked');
            $table->index('locked_at');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('has_credit_note')->default(false)->after('type');
            $table->index('has_credit_note');
        });

        Schema::create('document_workflow_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('event', 50);
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('metadata')->nullable();
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->timestamps();
            $table->index(['document_id', 'event']);
            $table->index('triggered_by');
        });

        Schema::create('document_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('child_document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('relationship_type', 50);
            $table->decimal('allocated_amount', 15, 2)->nullable();
            $table->decimal('allocated_quantity', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['parent_document_id', 'child_document_id', 'relationship_type'], 'uq_doc_rel');
            $table->index(['parent_document_id', 'relationship_type'], 'idx_parent_type');
            $table->index(['child_document_id', 'relationship_type'], 'idx_child_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_relationships');
        Schema::dropIfExists('document_workflow_history');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_has_credit_note_index');
            $table->dropColumn('has_credit_note');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_locked_at_index');
            $table->dropIndex('documents_is_locked_index');
            $table->dropColumn('lock_reason');
            $table->dropColumn('locked_by');
            $table->dropColumn('locked_at');
            $table->dropColumn('is_locked');
        });
    }
};
