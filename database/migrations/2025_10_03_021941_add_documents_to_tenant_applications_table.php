<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            $table->string('valid_id')->nullable();
            $table->string('id_picture')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            $table->dropColumn(['valid_id', 'id_picture']);
        });
    }
};