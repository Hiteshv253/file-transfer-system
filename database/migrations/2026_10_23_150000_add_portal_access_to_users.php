<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('portal_permissions')->nullable();
            $table->timestamp('portal_deleted_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn(Blueprint $table) => $table->dropColumn(['portal_permissions', 'portal_deleted_at']));
    }
};
