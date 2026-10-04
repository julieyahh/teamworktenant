<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renovation_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('lease_id')->constrained('leases')->onDelete('cascade');
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();

            $table->string('room_no')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('renovation_type')->nullable();  // Painting, Flooring, Fixtures, Other
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->date('preferred_date')->nullable();
            $table->string('attachment')->nullable();

            // Pending | Approved | Denied | Cancelled
            $table->string('status')->default('Pending');
            $table->text('manager_remarks')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['lease_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renovation_requests');
    }
};
