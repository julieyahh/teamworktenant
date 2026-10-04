<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->constrained('units')->after('unit_type');
        });
    }

    public function down()
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });
    }

};
