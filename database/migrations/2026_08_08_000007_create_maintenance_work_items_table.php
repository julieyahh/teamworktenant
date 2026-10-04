<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The line items on a repair: labour and materials.
 *
 * Kept as rows rather than two numbers on the request so the tenant can be
 * shown an itemised bill when they are being charged for it — "P1,800" invites
 * an argument, "2 hrs @ P450 + 1 flush valve @ P900" does not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_work_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('maintenance_request_id')
                ->constrained('maintenance_requests')->cascadeOnDelete();

            $table->string('type', 20);               // labor | material
            $table->string('description');

            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 20)->nullable();   // hours, pcs, m, L
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);  // quantity * unit_cost

            $table->string('performed_by')->nullable();
            $table->date('performed_on')->nullable();
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['maintenance_request_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_work_items');
    }
};
