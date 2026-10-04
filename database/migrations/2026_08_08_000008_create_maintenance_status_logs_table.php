<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repair history: every status change on a maintenance request, who made it,
 * and what they said about it.
 *
 * `maintenance_requests.status` only ever holds the current value, so without
 * this table there is no way to answer "how long did that repair actually
 * take" or "who marked it completed" after the fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_status_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('maintenance_request_id')
                ->constrained('maintenance_requests')->cascadeOnDelete();

            $table->string('from_status')->nullable();   // null on the first entry
            $table->string('to_status');
            $table->text('notes')->nullable();

            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('maintenance_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_status_logs');
    }
};
