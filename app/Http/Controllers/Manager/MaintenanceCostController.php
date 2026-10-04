<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceWorkItem;
use App\Models\Notification;
use App\Services\MaintenanceCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Repair costs: labour, materials, who pays, and billing the tenant's share.
 *
 *   POST   /manager/requests/{id}/work-items                 addWorkItem()
 *   DELETE /manager/requests/{id}/work-items/{item}          removeWorkItem()
 *   POST   /manager/requests/{id}/responsibility             setResponsibility()
 *   POST   /manager/requests/{id}/charge                     charge()
 *   POST   /manager/requests/{id}/details                    updateDetails()
 *
 * All of it renders inside manager/requests/show.blade.php.
 *
 * The arithmetic and the guard rails are in App\Services\MaintenanceCostService.
 * The one worth knowing here: a repair cannot be charged to a tenant until
 * somebody has recorded who is responsible AND why. The reason field is not
 * decoration — it is the answer to "why am I being charged for this?".
 */
class MaintenanceCostController extends Controller
{
    public function __construct(private MaintenanceCostService $costs)
    {
    }

    /** Add a line of labour or a material. */
    public function addWorkItem(Request $request, $id)
    {
        $data = $request->validate([
            'type'         => 'required|in:labor,material',
            'description'  => 'required|string|max:255',
            'quantity'     => 'required|numeric|min:0.01|max:9999',
            'unit'         => 'nullable|string|max:20',
            'unit_cost'    => 'required|numeric|min:0|max:9999999',
            'performed_by' => 'nullable|string|max:150',
            'performed_on' => 'nullable|date',
            'notes'        => 'nullable|string|max:500',
            'receipt'      => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $maintenance = MaintenanceRequest::findOrFail($id);

        if ($maintenance->isCharged()) {
            return redirect()->back()->with('error',
                'This repair has already been charged to the tenant. Void its Repair bill (Bills > the bill > Void) to reverse the charge, then edit the costs.');
        }

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store('receipts', 'public');
        }

        $this->costs->addWorkItem($maintenance, $data, Auth::user());

        return redirect()->back()->with('success', 'Work item added.');
    }

    public function removeWorkItem($id, $itemId)
    {
        $maintenance = MaintenanceRequest::findOrFail($id);

        if ($maintenance->isCharged()) {
            return redirect()->back()->with('error',
                'This repair has already been charged. Void its Repair bill (Bills > the bill > Void) to reverse the charge, then edit the costs.');
        }

        $item = MaintenanceWorkItem::where('maintenance_request_id', $maintenance->id)
            ->findOrFail($itemId);

        $this->costs->removeWorkItem($item);

        return redirect()->back()->with('success', 'Work item removed.');
    }

    /**
     * Record who pays.
     *
     * Deliberately a separate, explicit step rather than a default. Whether a
     * repair is fair wear and tear or tenant damage is a judgement call with a
     * lease contract behind it — getting it wrong in software means silently
     * billing someone for something that was never theirs.
     */
    public function setResponsibility(Request $request, $id)
    {
        $data = $request->validate([
            'cost_responsibility'   => 'required|in:landlord,tenant,shared,undetermined',
            'tenant_share_percent'  => 'nullable|numeric|min:1|max:99',
            'responsibility_reason' => 'nullable|string|max:1000',
        ]);

        $maintenance = MaintenanceRequest::findOrFail($id);

        try {
            $this->costs->setResponsibility(
                $maintenance,
                $data['cost_responsibility'],
                $data['tenant_share_percent'] ?? null,
                $data['responsibility_reason'] ?? null,
                Auth::user()
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        $maintenance->refresh();

        return redirect()->back()->with('success',
            'Cost responsibility set: ' . $maintenance->responsibilityLabel()
            . ($maintenance->tenant_charge_amount > 0
                ? '. Tenant share: PHP ' . number_format((float) $maintenance->tenant_charge_amount, 2)
                  . ' — not yet charged.'
                : '.'));
    }

    /**
     * Post the tenant's share as a bill they can actually pay.
     *
     * It becomes a normal bill rather than being folded into rent, so it gets
     * the same treatment as everything else — its own due date, its own status,
     * partial payments allowed — and it shows on the ledger as a repair rather
     * than as unexplained rent.
     */
    public function charge(Request $request, $id)
    {
        $data = $request->validate([
            'due_date' => 'nullable|date|after_or_equal:today',
        ]);

        $maintenance = MaintenanceRequest::with('lease', 'tenant')->findOrFail($id);

        // Double-click on Charge would otherwise bill the tenant twice.
        $lock = Cache::lock('maintenance-charge:' . $maintenance->id, 15);

        if (! $lock->get()) {
            return redirect()->back()->with('info', 'That charge is already being posted.');
        }

        try {
            $bill = $this->costs->chargeTenant($maintenance, Auth::user(), $data['due_date'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } finally {
            $lock->release();
        }

        if ($maintenance->tenant && $bill) {
            $items = $maintenance->workItems()
                ->get()
                ->map(fn ($i) => sprintf('%s x%s @ PHP %s = PHP %s',
                    $i->description,
                    rtrim(rtrim((string) $i->quantity, '0'), '.'),
                    number_format((float) $i->unit_cost, 2),
                    number_format((float) $i->line_total, 2)))
                ->implode('; ');

            Notification::create([
                'user_id' => $maintenance->tenant->id,
                'title'   => 'Repair Charge',
                'message' => 'A charge of PHP ' . number_format((float) $maintenance->tenant_charge_amount, 2)
                    . ' has been added for maintenance request #' . $maintenance->id . '. '
                    . 'Reason: ' . ($maintenance->responsibility_reason ?: 'see the request details') . '. '
                    . 'Breakdown: ' . ($items ?: 'n/a') . '. '
                    . 'Total repair cost was PHP ' . number_format((float) $maintenance->total_cost, 2)
                    . ($maintenance->cost_responsibility === 'shared'
                        ? ', of which your share is ' . rtrim(rtrim((string) $maintenance->tenant_share_percent, '0'), '.') . '%.'
                        : '.'),
            ]);
        }

        return redirect()->back()->with('success',
            'Charged PHP ' . number_format((float) $maintenance->tenant_charge_amount, 2)
            . ' to the tenant as a bill. They can pay it in full or in parts like any other bill.');
    }

    /**
     * Assignment, scheduling, diagnosis and resolution notes.
     *
     * These four fields were being saved and then shown to nobody but the
     * manager. A tenant whose only signal is a status pill has no way to know
     * a technician is booked for Thursday — so a change to the schedule or
     * the assignment now notifies them, and the tenant can see the whole
     * record at /tenant/requests/{id}.
     *
     * Only material changes are announced. Re-saving the form with the same
     * values, or correcting a typo in the diagnosis, should not fire a
     * notification: an alert the tenant learns to ignore is worse than no
     * alert at all.
     */
    public function updateDetails(Request $request, $id)
    {
        $data = $request->validate([
            'assigned_to'      => 'nullable|string|max:150',
            'scheduled_date'   => 'nullable|date',
            'diagnosis'        => 'nullable|string|max:2000',
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        $maintenance = MaintenanceRequest::findOrFail($id);

        $before = [
            'assigned_to'      => $maintenance->assigned_to,
            'scheduled_date'   => $maintenance->scheduled_date?->toDateString(),
            'resolution_notes' => $maintenance->resolution_notes,
        ];

        $maintenance->update($data);
        $maintenance->refresh();

        $this->notifyTenantOfDetailChange($maintenance, $before);

        return redirect()->back()->with('success', 'Repair details updated.');
    }

    /**
     * Tell the tenant what changed, in the words they need: when someone is
     * coming, who, and what was done.
     */
    private function notifyTenantOfDetailChange(MaintenanceRequest $maintenance, array $before): void
    {
        if (! $maintenance->tenant_id) {
            return;
        }

        $lines = [];

        $newSchedule = $maintenance->scheduled_date?->toDateString();

        if ($newSchedule && $newSchedule !== $before['scheduled_date']) {
            $lines[] = 'Your maintenance request #' . $maintenance->id . ' is scheduled for '
                . $maintenance->scheduled_date->format('l, F d, Y') . '.'
                . ' Please make sure someone can let the technician into the unit.';
        }

        if ($maintenance->assigned_to && $maintenance->assigned_to !== $before['assigned_to']) {
            $lines[] = 'It has been assigned to ' . $maintenance->assigned_to . '.';
        }

        // Resolution notes only matter to the tenant once the job is finished
        // — mid-repair working notes are not an update, they are a draft.
        if ($maintenance->isComplete()
            && $maintenance->resolution_notes
            && $maintenance->resolution_notes !== $before['resolution_notes']) {
            $lines[] = 'Work completed: ' . \Illuminate\Support\Str::limit($maintenance->resolution_notes, 200);
        }

        if (empty($lines)) {
            return;
        }

        Notification::create([
            'user_id' => $maintenance->tenant_id,
            'title'   => 'Maintenance Update',
            'message' => implode(' ', $lines),
        ]);
    }
}
