<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stored_documents', function (Blueprint $table) {
            $table->uuid('upload_key')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unique(['owner_id', 'upload_key']);
            $table->index(['deleted_at', 'folder_date', 'owner_id', 'id'], 'documents_daily_listing');
        });
        Schema::table('stored_document_shares', function (Blueprint $table) {
            $table->index(['user_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stored_documents', function (Blueprint $table) {
            $table->dropUnique(['owner_id', 'upload_key']);
            $table->dropIndex('documents_daily_listing');
            $table->dropColumn(['upload_key', 'sha256']);
        });
        Schema::table('stored_document_shares', fn(Blueprint $table) => $table->dropIndex(['user_id', 'document_id']));
    }
};
