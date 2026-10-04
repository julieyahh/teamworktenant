<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every demand for payment the landlord has made, and proof it was delivered.
 *
 * This table is the point of the whole collections feature. If a non-payment
 * case is ever filed, what matters is not what the software calculated — it is
 * whether the landlord can show a written demand, when it was served, how, and
 * that the tenant was given the notice period before anything else happened.
 *
 * Nothing here triggers automatically. A manager creates every row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_demands', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained('leases')->nullOnDelete();

            // reminder | formal_demand | notice_to_vacate
            // (config('collections.demand_sequence'))
            $table->string('type', 30);

            // What was claimed at the moment of issue. Frozen — it must still
            // read the same in six months even after the balance has moved.
            $table->decimal('amount_claimed', 12, 2)->default(0);
            $table->decimal('rent_component', 12, 2)->default(0);
            $table->decimal('utility_component', 12, 2)->default(0);
            $table->decimal('other_component', 12, 2)->default(0);

            $table->timestamp('issued_at');
            $table->integer('grace_period_days');
            $table->date('comply_by');              // issued_at + grace, precomputed

            // Service of the notice.
            $table->string('delivery_channel', 30)->nullable();
            $table->string('delivery_reference')->nullable();  // registry receipt no., message id
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();  // tenant confirmed receipt

            // How it ended. open | paid | arrangement | superseded | escalated | withdrawn
            $table->string('outcome', 30)->default('open');
            $table->timestamp('resolved_at')->nullable();

            $table->text('body')->nullable();          // the text that was sent
            $table->text('notes')->nullable();         // internal
            $table->string('document_path')->nullable(); // scan of the served copy

            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'outcome']);
            $table->index(['lease_id', 'type']);
            $table->index('comply_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_demands');
    }
};
