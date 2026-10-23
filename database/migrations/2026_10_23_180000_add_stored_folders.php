<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stored_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('owner_id')->index();
            $table->unsignedBigInteger('parent_id')->default(0)->index();
            $table->string('name', 190)->collation('utf8mb4_bin');
            $table->string('tree_path', 1024)->default('');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['owner_id', 'parent_id', 'name']);
        });
        Schema::table('stored_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('folder_id')->nullable()->index();
            $table->unsignedBigInteger('upload_parent_id')->nullable();
            $table->string('relative_path', 2048)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('stored_documents', fn(Blueprint $table) => $table->dropColumn(['folder_id', 'upload_parent_id', 'relative_path']));
        Schema::dropIfExists('stored_folders');
    }
};
