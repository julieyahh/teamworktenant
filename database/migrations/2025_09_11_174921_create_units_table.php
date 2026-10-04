<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('room_no')->unique();
            $table->decimal('room_price', 10, 2);
            $table->integer('application_limit')->nullable();
            $table->string('status')->default('vacant'); // vacant/occupied
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('units');
    }
};
