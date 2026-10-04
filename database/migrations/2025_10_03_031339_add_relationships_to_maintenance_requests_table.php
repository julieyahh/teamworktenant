<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            // Add foreign keys
            $table->unsignedBigInteger('lease_id')->nullable()->after('tenant_id');
            $table->unsignedBigInteger('unit_id')->nullable()->after('lease_id');

            // Foreign key constraints
            $table->foreign('lease_id')->references('id')->on('leases')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['lease_id', 'unit_id',]);
        });
    }
};
