<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shareable_links', function (Blueprint $table) {
            $table
                ->boolean('allow_direct')
                ->default(false)
                ->index()
                ->after('allow_download');
        });
    }
};
