<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('reset_token')->nullable()->after('password');
            $table->timestamp('reset_token_created_at')->nullable()->after('reset_token');
        });

    }

    public function down()
    {
        Schema::dropIfExists('password_resets');
    }
};
