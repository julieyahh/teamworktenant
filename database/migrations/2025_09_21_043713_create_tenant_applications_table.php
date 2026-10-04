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
        // Schema::create('tenant_applications', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        //     $table->string('full_name');
        //     $table->string('email');
        //     $table->string('contact_number');
        //     $table->string('current_address');
        //     $table->date('birthdate');
        //     $table->string('unit_type');
        //     $table->date('move_in_date');
        //     $table->text('reason');
        //     $table->string('employment_status');
        //     $table->string('employer_school');
        //     $table->string('emergency_name');
        //     $table->string('emergency_number');
        //     $table->string('emergency_relationship');
        //     $table->string('valid_id');
        //     $table->string('id_picture');
        //     $table->timestamps();
        // });

        Schema::create('tenant_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // link to user
            $table->string('full_name');
            $table->string('email');
            $table->string('contact_number');
            $table->string('current_address');
            $table->date('birthdate');
            $table->string('unit_type');
            $table->date('move_in_date');
            $table->text('reason');
            $table->string('employment_status');
            $table->string('employer_school');
            $table->string('emergency_name');
            $table->string('emergency_number');
            $table->string('emergency_relationship');
            $table->string('valid_id_path'); // store uploaded file path
            $table->string('id_picture_path'); // store uploaded file path
            $table->boolean('is_complete')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_applications');
    }
};
