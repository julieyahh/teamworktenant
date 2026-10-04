<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_applications', 'bed_number')) {
                $table->string('bed_number')->nullable()->after('room_no');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_applications', 'bed_number')) {
                $table->dropColumn('bed_number');
            }
        });
    }
};
