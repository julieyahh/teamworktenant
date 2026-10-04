<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');

            // General | Facility | Staff | Service | Suggestion | Complaint
            $table->string('category')->default('General');
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5 stars, optional
            $table->string('subject');
            $table->text('message');

            // New | Reviewed
            $table->string('status')->default('New');
            $table->text('manager_response')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
