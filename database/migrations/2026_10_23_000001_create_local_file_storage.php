<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stored_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('owner_id')->index();
            $table->string('name');
            $table->string('path')->unique();
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->date('folder_date')->index();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('stored_document_shares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedInteger('user_id');
            $table->unique(['document_id', 'user_id']);
        });
        Schema::create('document_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->index();
            $table->unsignedInteger('requested_by');
            $table->string('status')->default('pending')->index();
            $table->text('reason');
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_deletion_requests');
        Schema::dropIfExists('stored_document_shares');
        Schema::dropIfExists('stored_documents');
    }
};
