<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance requests grow from "a tenant reported something" into a repair
 * job with a cost and someone responsible for paying it.
 *
 * The cost columns are totals rolled up from maintenance_work_items. They are
 * denormalised on purpose so report queries do not have to join and sum, but
 * MaintenanceCostService::recalculate() is the only thing that writes them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            // --- The work itself -------------------------------------------
            $table->string('assigned_to')->nullable()->after('status');   // technician / contractor
            $table->date('scheduled_date')->nullable()->after('assigned_to');
            $table->timestamp('started_at')->nullable()->after('scheduled_date');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->text('diagnosis')->nullable()->after('completed_at');
            $table->text('resolution_notes')->nullable()->after('diagnosis');

            // --- Costs (rolled up from maintenance_work_items) -------------
            $table->decimal('labor_cost', 12, 2)->default(0)->after('resolution_notes');
            $table->decimal('material_cost', 12, 2)->default(0)->after('labor_cost');
            $table->decimal('total_cost', 12, 2)->default(0)->after('material_cost');

            // --- Who pays --------------------------------------------------
            // landlord | tenant | shared | undetermined
            //
            // 'undetermined' is the default and it matters: a job that has been
            // costed but never assigned shows up on the manager's list as
            // needing a decision, instead of quietly defaulting to the landlord.
            $table->string('cost_responsibility', 20)->default('undetermined')->after('total_cost');

            // Only meaningful when cost_responsibility = 'shared'.
            $table->decimal('tenant_share_percent', 5, 2)->nullable()->after('cost_responsibility');
            $table->text('responsibility_reason')->nullable()->after('tenant_share_percent');

            // What the tenant ends up owing, and whether it has been billed.
            $table->decimal('tenant_charge_amount', 12, 2)->default(0)->after('responsibility_reason');
            $table->timestamp('charged_at')->nullable()->after('tenant_charge_amount');
            $table->foreignId('charged_by')->nullable()->after('charged_at')
                ->constrained('users')->nullOnDelete();

            $table->index(['status', 'cost_responsibility']);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('charged_by');
            $table->dropIndex(['status', 'cost_responsibility']);
            $table->dropColumn([
                'assigned_to', 'scheduled_date', 'started_at', 'completed_at',
                'diagnosis', 'resolution_notes',
                'labor_cost', 'material_cost', 'total_cost',
                'cost_responsibility', 'tenant_share_percent', 'responsibility_reason',
                'tenant_charge_amount', 'charged_at',
            ]);
        });
    }
};
