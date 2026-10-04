<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\User;
use App\Models\UtilityBillingProof;
use Illuminate\Http\Request;

class UtilityController extends Controller
{
    // Display list of tenants with utility balances
    public function index()
    {
        // How many active leases share each unit — used on the Electricity
        // modal to divide a Bed-Spacer room's meter reading across its
        // current occupants. Computed from ALL active leases (not just this
        // page's tenants), same as UtilityBillController::index(), so both
        // screens agree on the same headcount.
        $occupantsByUnit = Lease::whereIn('lea_status', ['active', 'Active'])
            ->get()
            ->groupBy('unit_id')
            ->map->count();

        $tenants = User::with(['leases' => function($query) {
                $query->whereIn('lea_status', ['active', 'Active'])
                    ->with('unit');
            }])
            ->where('role', 'tenant')
            ->where('status', 'approved')
            ->get();

        $tenantGroups = $tenants
            ->filter(function($tenant) {
                return $tenant->leases->whereIn('lea_status', ['active', 'pending', 'Active', 'Pending'])->count() > 0;
            })
            ->map(function ($tenant) {

                $activeLeases = $tenant->leases->filter(function($lease) {
                    return in_array($lease->lea_status, ['active', 'pending', 'Active', 'Pending']);
                });

                if ($activeLeases->isEmpty()) {
                    return null;
                }

                $roomEntries = $activeLeases->map(function ($lease) {

                    $roomLabel = $lease->room_no ?? 'N/A';
                    if ($lease->unit) {
                        $roomLabel .= ' - ' . $lease->unit->type;
                    }
                    if ($lease->bed_number) {
                        $roomLabel .= ' (Bed ' . $lease->bed_number . ')';
                    }

                    // ✅ GET ELECTRICITY TOTAL
                    $electricityTotal = \App\Models\UtilityBillingProof::where('lease_id', $lease->id)
                        ->where('billing_type', 'Electricity')
                        ->sum('amount');

                    // ✅ GET WATER TOTAL
                    $waterTotal = \App\Models\UtilityBillingProof::where('lease_id', $lease->id)
                        ->where('billing_type', 'Water')
                        ->sum('amount');

                    // ✅ GRAND TOTAL (SUM OF ALL BILLING)
                    $totalUtility = \App\Models\UtilityBillingProof::where('lease_id', $lease->id)
                        ->sum('amount');

                     $created_at = \App\Models\UtilityBillingProof::where('lease_id', $lease->id)
                    ->latest()
                    ->value('created_at');

                    return (object) [
                        'lease_id'            => $lease->id,
                        'room_label'          => $roomLabel,

                        // ✅ INDIVIDUAL TOTALS
                        'electricity_amount' => $electricityTotal,
                        'water_amount'       => $waterTotal,

                        // ✅ COMBINED TOTAL
                        'utility_balance'    => $totalUtility,
                        'created_at' => $created_at,
                    ];
                });

                return (object) [
                    'tenant'       => $tenant,
                    'room_entries' => $roomEntries,
                    'all_leases'   => $activeLeases,
                ];
            })
            ->filter()
            ->sortBy(function($group) {
                return $group->tenant->name ?? '';
            });

        return view('manager.utilities.index', [
            'tenantGroups'     => $tenantGroups,
            'occupantsByUnit'  => $occupantsByUnit,
        ]);
    }

    public function updateUtilityBalance(Request $request, $id)
    {
        validator($request->all(), [
            // Electricity is billed by meter reading now, same as the Bills
            // ledger (manager.bills.store) — 'utility_balance' is required
            // for Water, 'kwh_reading' for Electricity.
            'utility_balance'          => 'required_unless:bill_type,Electricity|nullable|numeric|min:0',
            'kwh_reading'              => 'required_if:bill_type,Electricity|nullable|numeric|min:0.01',
            'proof_of_utility_billing' => 'nullable|image|mimes:jpg,jpeg,png', // nullable if optional
            'bill_type'                => 'nullable',
        ])->validate();

        try {
            \Log::info('UpdateUtilityBalance called', $request->all());

            $lease = Lease::with('unit')->findOrFail($id);
            $user = $lease->tenant;

            if (!$user) {
                return redirect()->back()->with('error', 'Tenant not found');
            }

            $pathToProof = $request->hasFile('proof_of_utility_billing')
                ? $request->file('proof_of_utility_billing')->store('utility_proofs', 'public')
                : null;

            $kwhReading = null;
            $kwhRate    = null;

            if ($request->bill_type === 'Electricity') {
                // Same math as UtilityBillController::store(): the amount is
                // always computed here from the meter reading (never trusted
                // from the client), and a Bed-Spacer room's total is split
                // across every lease currently active on that unit.
                $kwhRate    = (float) config('billing.electricity.rate_per_kwh', 18);
                $kwhReading = (float) $request->kwh_reading;
                $precision  = (int) config('billing.currency_precision', 2);
                $roomTotal  = round($kwhReading * $kwhRate, $precision);

                $isBedSpacer = optional($lease->unit)->type === 'Bed-Spacer';

                if ($isBedSpacer) {
                    $occupants = max(1, Lease::where('unit_id', $lease->unit_id)
                        ->whereIn('lea_status', ['active', 'Active'])
                        ->count());

                    $amount = round($roomTotal / $occupants, $precision);

                    $splitNote = "Electricity: {$kwhReading} kWh x PHP " . number_format($kwhRate, 2)
                        . " = PHP " . number_format($roomTotal, 2)
                        . " for the room, split {$occupants} way(s) => PHP "
                        . number_format($amount, 2) . " for this tenant.";
                } else {
                    $amount = $roomTotal;

                    $splitNote = "Electricity: {$kwhReading} kWh x PHP " . number_format($kwhRate, 2)
                        . " = PHP " . number_format($roomTotal, 2) . ".";
                }

                $notes = trim(($request->input('proof_notes') ?? '') . "\n" . $splitNote);
            } else {
                $amount = (float) $request->utility_balance;
                $notes  = $request->input('proof_notes');
            }

            // Update the lease-specific utility balance
            $lease->utility_balance += $amount;
            $lease->save();

            // Create a utility billing record
            $billingData = [
                'tenant_id' => $user->id,
                'lease_id' => $lease->id,
                'billing_month' => $request->input('billing_month') ?? now()->format('F Y'),
                'billing_type' => $request->bill_type ?? null,
                'amount' => $amount,
                'kwh_reading' => $kwhReading,
                'kwh_rate' => $kwhRate,
                'notes' => $notes,
                'file_path' => $pathToProof,
            ];

            UtilityBillingProof::create($billingData);

            if ($pathToProof) {
                $user->proof_of_utility_billing = $pathToProof;
                $user->save();
            }

            return redirect()->back()->with('success', 'Utility balance updated successfully!' . ($pathToProof ? ' Proof uploaded.' : ''));

        } catch (\Exception $e) {
            \Log::error('UpdateUtilityBalance error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }


}
