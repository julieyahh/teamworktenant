<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\PaymentDemand;
use App\Services\CollectionsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Arrears, demands and the paper trail behind them.
 *
 *   GET  /manager/collections                        index()   every lease in arrears
 *   GET  /manager/collections/{lease}                show()    one lease: stage + demand history
 *   POST /manager/collections/{lease}/demand         issue()   record a demand
 *   POST /manager/collections/demands/{demand}/deliver  deliver()  record service
 *   POST /manager/collections/demands/{demand}/close    close()    record the outcome
 *
 * WHAT THIS SCREEN IS FOR
 *   Building a file, not enforcing a rule. If a non-payment case is ever filed,
 *   what matters is whether the landlord can show a written demand, when it was
 *   served, how, and that the notice period ran before anything else happened.
 *   That is what these routes record.
 *
 * WHAT IT DELIBERATELY WILL NOT DO
 *   Evict, terminate a lease, disconnect a utility, or disable a login. There
 *   is no such route and no such button. The furthest the system goes is
 *   flagging a file as "refer to counsel" — a person takes it from there.
 *   See config/collections.php and App\Services\CollectionsService.
 */
class CollectionsController extends Controller
{
    public function __construct(private CollectionsService $collections)
    {
    }

    public function index(Request $request)
    {
        $cases = $this->buildCases($request->query('stage'));

        $totals = [
            'cases'       => $cases->count(),
            'arrears'     => round($cases->sum(fn ($c) => $c['assessment']['arrears']['total']), 2),
            'needs_serve' => PaymentDemand::open()->whereNull('delivered_at')->count(),
        ];

        return view('manager.collections.index', compact('cases', 'totals'));
    }

    /**
     * PDF of the same case list shown on-screen, filtered the same way (the
     * "stage" query param carries over from the dropdown), so what a manager
     * downloads always matches what they were just looking at.
     */
    public function export(Request $request)
    {
        $cases = $this->buildCases($request->query('stage'));

        $pdf = Pdf::loadView('reports.pdf.collections', [
            'cases' => $cases,
            'stage' => $request->query('stage'),
            'total' => round($cases->sum(fn ($c) => $c['assessment']['arrears']['total']), 2),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('collections-' . now()->format('Y-m-d_H-i-s') . '.pdf');
    }

    /** Arrears cases, assessed and optionally filtered by stage — shared by index() and export(). */
    private function buildCases(?string $stage)
    {
        $leases = Lease::with(['tenant', 'unit'])
            ->whereIn('lea_status', ['active', 'Active'])
            ->get();

        $cases = $leases
            ->map(fn (Lease $lease) => [
                'lease'      => $lease,
                'assessment' => $this->collections->assess($lease),
            ])
            ->filter(fn ($case) => $case['assessment']['stage'] !== 'current')
            ->sortByDesc(fn ($case) => $case['assessment']['days_past_due'])
            ->values();

        if ($stage) {
            $cases = $cases->filter(fn ($c) => $c['assessment']['stage'] === $stage)->values();
        }

        return $cases;
    }

    public function show($leaseId)
    {
        $lease = Lease::with(['tenant', 'unit'])->findOrFail($leaseId);

        $assessment = $this->collections->assess($lease);

        $demands = PaymentDemand::where('lease_id', $lease->id)
            ->with('issuedBy')
            ->orderByDesc('issued_at')
            ->get();

        $openBills = $lease->utilityBills()->open()->oldestFirst()->get();

        return view('manager.collections.show', compact('lease', 'assessment', 'demands', 'openBills'));
    }

    /**
     * Record a demand.
     *
     * The manager writes it and the manager sends it. This endpoint stores what
     * was demanded and starts the clock — it does not deliver anything itself,
     * because a delivery the system merely believes happened is worth nothing
     * as evidence.
     */
    public function issue(Request $request, $leaseId)
    {
        $data = $request->validate([
            'type'               => 'required|in:reminder,formal_demand,notice_to_vacate',
            'grace_period_days'  => 'nullable|integer|min:1|max:120',
            'body'               => 'nullable|string|max:5000',
            'notes'              => 'nullable|string|max:2000',
            'delivery_channel'   => 'nullable|in:email,sms,hand_delivered,registered_mail,courier',
            'delivery_reference' => 'nullable|string|max:255',
            'delivered_at'       => 'nullable|date',
            'acknowledge_review' => 'accepted',
        ], [
            'acknowledge_review.accepted' =>
                'Confirm you have reviewed the amounts and the wording before issuing this notice.',
        ]);

        $lease = Lease::with('tenant')->findOrFail($leaseId);

        try {
            $demand = $this->collections->issueDemand($lease, $data['type'], $data, Auth::user());
        } catch (\Throwable $e) {
            Log::error('Failed to issue demand on lease ' . $leaseId . ': ' . $e->getMessage());

            return redirect()->back()->with('error', 'Could not record that notice: ' . $e->getMessage());
        }

        // The tenant gets an in-app copy so the demand is not the first they
        // hear of it. This is a courtesy notification, NOT proof of service —
        // service is recorded separately in deliver().
        if ($lease->user_id) {
            Notification::create([
                'user_id' => $lease->user_id,
                'title'   => $demand->typeLabel(),
                'message' => $demand->body,
            ]);
        }

        $warning = $demand->delivered_at
            ? ''
            : ' Record how and when it was served — the notice period does not start until then.';

        return redirect()->route('manager.collections.show', $lease->id)
            ->with('success', $demand->typeLabel() . ' recorded.' . $warning);
    }

    /** Record that the notice was actually served. This is what starts the clock. */
    public function deliver(Request $request, $demandId)
    {
        $data = $request->validate([
            'delivery_channel'   => 'required|in:email,sms,hand_delivered,registered_mail,courier',
            'delivery_reference' => 'nullable|string|max:255',
            'delivered_at'       => 'required|date|before_or_equal:now',
            'document'           => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ], [
            'delivered_at.before_or_equal' => 'A notice cannot be recorded as served in the future.',
        ]);

        $demand = PaymentDemand::findOrFail($demandId);

        if ($request->hasFile('document')) {
            $data['document_path'] = $request->file('document')->store('demands', 'public');
        }

        $demand = $this->collections->recordDelivery($demand, $data);

        return redirect()->back()->with('success',
            'Service recorded. The tenant has until '
            . $demand->comply_by->format('M d, Y') . ' to comply.');
    }

    /** Close a demand off: paid, arrangement agreed, withdrawn, or escalated. */
    public function close(Request $request, $demandId)
    {
        $data = $request->validate([
            'outcome' => 'required|in:paid,arrangement,withdrawn,escalated',
            'notes'   => 'nullable|string|max:2000',
        ]);

        $demand = PaymentDemand::findOrFail($demandId);

        $this->collections->closeDemand($demand, $data['outcome'], $data['notes'] ?? null);

        // 'escalated' means the file has gone to a lawyer. Nothing further
        // happens in this system — that is the end of the road here, by design.
        $note = $data['outcome'] === 'escalated'
            ? ' The file is marked as referred out. No further automated steps will be taken.'
            : '';

        return redirect()->back()->with('success', 'Demand closed as ' . $data['outcome'] . '.' . $note);
    }
}
