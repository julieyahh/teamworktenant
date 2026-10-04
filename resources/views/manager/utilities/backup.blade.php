@extends('layouts.managerdashboardlayout')

@section('title', 'Utilities Management')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
    /* ========= GLOBAL ========== */
    body {
        background: #f3f5f9;
        font-family: 'Inter', sans-serif;
    }

    /* Gradient Text */
    h2.gradient-text, .card-header.gradient-text, .modal-title.gradient-text {
        background: linear-gradient(90deg, #0d6efd, #00b4d8);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 700;
    }

    /* ========= CARD DESIGN ========== */
    .card {
        border: none;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 5px 15px rgba(0,0,0,0.06);
        transition: all 0.3s ease;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .card-header {
        border: none;
        border-radius: 16px 16px 0 0;
        background: #fff; /* we use gradient only on text */
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* ========= TABLE DESIGN ========== */
    .table thead th {
        background: #fff7e0;
        color: #343a40;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.875rem;
    }
    .table tbody tr:hover {
        background: #fffde8;
        transition: 0.25s ease-in;
    }
    .table td, .table th {
        vertical-align: middle;
    }
    .table .text-end {
        font-weight: 600;
    }

    /* ========= BUTTONS ========== */
    .btn {
        border-radius: 10px;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    .btn:hover {
        transform: translateY(-1px);
    }
    .btn-primary {
        background: linear-gradient(135deg, #0d6efd, #00b4d8);
        border: none;
        color: #fff;
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #00b4d8, #0d6efd);
    }
    .btn-outline-secondary {
        border-radius: 10px;
    }

    /* ========= MODAL DESIGN ========== */
    .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .modal-header {
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
        background: #fff; /* gradient applied to title */
    }
    .modal-footer {
        border-top: none;
        background-color: #f8f9fa;
    }
    .form-floating label {
        color: #666;
    }

    /* ========= ALERT DESIGN ========== */
    #alertContainer .alert {
        border-radius: 12px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.08);
    }

    /* ========= RESPONSIVE ========= */
    @media (max-width: 768px) {
        h2 { font-size: 1.4rem; }
        .btn-sm { font-size: 0.8rem; padding: 0.3rem 0.6rem; }
    }
</style>

<style>
    .alert-message-modern {
        position: fixed;
        top: 1.5rem;
        left: 50%;
        transform: translate(-50%, -20px);
        z-index: 9999;
        min-width: 320px;
        max-width: 500px;
        opacity: 0;
        transition: all 0.3s ease-out;
        border-radius: 1rem;
        border: 2px solid;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        backdrop-filter: blur(8px);
    }

    .alert-message-modern.show {
        opacity: 1;
        transform: translate(-50%, 0);
    }

    .alert-success-gradient {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border-color: rgba(16, 185, 129, 0.2);
    }

    .alert-danger-gradient {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        border-color: rgba(239, 68, 68, 0.2);
    }

    .alert-warning-gradient {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        color: #1f2937;
        border-color: rgba(251, 191, 36, 0.2);
    }

    .alert-info-gradient {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border-color: rgba(59, 130, 246, 0.2);
    }

    .alert-icon-badge {
        flex-shrink: 0;
        width: 2.5rem;
        height: 2.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(4px);
    }

    .alert-icon {
        width: 1.25rem;
        height: 1.25rem;
    }

    .alert-close-btn {
        flex-shrink: 0;
        width: 2rem;
        height: 2rem;
        padding: 0;
        border: none;
        background: transparent;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        cursor: pointer;
    }

    .alert-close-btn:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .alert-close-btn:active {
        background: rgba(255, 255, 255, 0.3);
    }

    .alert-close-btn:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.5);
    }

    .alert-close-icon {
        width: 1rem;
        height: 1rem;
    }

    .alert-progress-container {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 4px;
        width: 100%;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 0 0 1rem 1rem;
        overflow: hidden;
    }

    .alert-progress-bar {
        height: 100%;
        background: rgba(255, 255, 255, 0.6);
        width: 100%;
        transition: width linear;
    }
</style>
@endpush

@section('content')

<div class="container mt-4">
    <h2 class="mb-4 fw-bold">
        Utilities Management
    </h2>

    <template id="alert-template">
        <x-alert type="success" duration="5000">
            __MESSAGE__
        </x-alert>
    </template>

    <div id="globalAlertContainer"></div>

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif

    @if(session('error'))
        <x-alert type="error">{{ session('error') }}</x-alert>
    @endif

    <div class="card mb-4">
        <div class="card-header gradient-text">
            <i class="bi bi-list-check"></i> Tenant Utilities Overview
        </div>
        <div class="card-body">
            <div id="alertContainer"></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle text-center">
                    <thead>
                        <tr>
                            <th>Tenant Name</th>
                            <th>Room / Unit</th>
                            <th>Utility Balance (₱)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tenantGroups as $group)
                            @php
                                $roomCount = count($group->room_entries);
                            @endphp
                            @foreach($group->room_entries as $index => $entry)
                            <tr id="lease-row-{{ $entry->lease_id }}">
                                @if($index === 0)
                                    <td rowspan="{{ $roomCount }}" class="align-middle">
                                        <strong>{{ $group->tenant->name }}</strong>
                                    </td>
                                @endif
                                <td>{{ $entry->room_label }}</td>
                                <td>
                                    ₱{{ number_format($entry->utility_balance, 2) }}
                                    @if($entry->created_at)
                                        <small class="text-muted">
                                            ({{ $entry->created_at->diffForHumans() }})
                                        </small>
                                    @endif
                                </td>
                                @php
                                    $leasesJson = $group->all_leases->map(function($lease) {
                                        return [
                                            "id" => $lease->id,
                                            "room_no" => $lease->room_no ?? "N/A",
                                            "unit_type" => $lease->unit->type ?? "Unit",
                                            "bed_number" => $lease->bed_number
                                        ];
                                    })->toJson();
                                @endphp

                                <td>
                                    @php
                                        $electricityAmount = $entry->electricity_amount ?? 0;
                                        $waterAmount = $entry->water_amount ?? 0;

                                        // Check if billing is older than 29 days
                                        $isOlderThan29Days = $entry->created_at
                                            ? \Carbon\Carbon::parse($entry->created_at)->lt(now()->subDays(29))
                                            : false;

                                        // ELECTRICITY BUTTON: disable if amount exists, enable if older than 29 days
                                        $electricityDisabled = $electricityAmount > 0 && !$isOlderThan29Days;

                                        // WATER BUTTON: disable if amount exists, enable if older than 29 days
                                        $waterDisabled = $waterAmount > 0 && !$isOlderThan29Days;
                                    @endphp

                                    {{-- ELECTRICITY BUTTON --}}
                                    <button class="btn btn-sm btn-primary edit-btn"
                                        style="background-color: #01017c; color: white; border: none; padding: 8px 20px;"
                                        data-id="{{ $entry->lease_id }}"
                                        data-name="{{ $group->tenant->name }}"
                                        data-room="{{ $entry->room_label }}"
                                        data-balance="{{ number_format($electricityAmount, 2, '.', '') }}"
                                        data-leases='{{ $leasesJson }}'
                                        data-bill-type="Electricity"
                                        {{ $electricityDisabled ? 'disabled' : '' }}>
                                        <i class="bi bi-pencil-square me-1"></i> Electricity Billing
                                    </button>

                                    {{-- WATER BUTTON --}}
                                    <button class="btn btn-sm btn-primary edit-btn"
                                        style="background-color: #01017c; color: white; border: none; padding: 8px 20px;"
                                        data-id="{{ $entry->lease_id }}"
                                        data-name="{{ $group->tenant->name }}"
                                        data-room="{{ $entry->room_label }}"
                                        data-balance="{{ number_format($waterAmount, 2, '.', '') }}"
                                        data-leases='{{ $leasesJson }}'
                                        data-bill-type="Water"
                                        {{ $waterDisabled ? 'disabled' : '' }}>
                                        <i class="bi bi-pencil-square me-1"></i> Water Billing
                                    </button>

                                </td>

                           </tr>
                            @endforeach
                        @empty
                        <tr>
                            <td colspan="4" class="text-muted py-4">No tenant utility records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Update Utility Modal -->
<div class="modal fade" id="editUtilityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title gradient-text" id="editUtilityModalTitle">
                    <i class="bi bi-pencil-square me-1"></i> Update Billing
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editUtilityForm" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="hidden" id="leaseId" name="lease_id">
                <input type="hidden" id="billType" name="bill_type">
                <div class="modal-body">
                    <div class="form-floating mb-3">
                        <input type="text" id="tenantName" class="form-control" readonly>
                        <label for="tenantName">Tenant Name</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" id="selectedRoom" class="form-control" readonly>
                        <label for="selectedRoom">Room / Unit</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="number" step="0.01" min="0" id="utilityBalance" name="amount" class="form-control text-end" placeholder="0.00" required>
                        <label id="utilityBalanceLabel" for="utilityBalance">Utility Balance (₱)</label>
                    </div>
                    <div class="mb-3">
                        <label for="proofOfUtilityBilling" class="form-label fw-semibold" id="proofLabel">Proof of Utility Billing</label>
                        <input type="file" id="proofOfUtilityBilling" name="proof_of_utility_billing" class="form-control" accept="image/*">
                        <small class="text-muted">Upload an image of the bill (JPG, PNG).</small>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="month" id="billingMonth" name="billing_month" class="form-control">
                        <label for="billingMonth">Billing Month (optional)</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea name="proof_notes" class="form-control" rows="2" placeholder="Short note about this bill..."></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" style="border: 2px solid #01017c; color: #01017c; background: transparent;"
                        data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" style="background-color: #01017c; color: white; border: none; padding: 8px 20px;">
                        <i class="bi bi-check-circle me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- <script>
document.addEventListener("DOMContentLoaded", function () {
    const modalEl = document.getElementById('editUtilityModal');
    const modal = new bootstrap.Modal(modalEl);

    const editForm = document.getElementById('editUtilityForm');
    const tenantNameInput = document.getElementById('tenantName');
    const selectedRoomInput = document.getElementById('selectedRoom');
    const utilityBalanceInput = document.getElementById('utilityBalance');
    const proofInput = document.getElementById('proofOfUtilityBilling');
    const billingMonthInput = document.getElementById('billingMonth');
    const proofNotesInput = document.querySelector('textarea[name="proof_notes"]');
    const leaseIdInput = document.getElementById('leaseId');
    const billTypeInput = document.getElementById('billType');

    const modalTitle = document.getElementById('editUtilityModalTitle');
    const utilityBalanceLabel = document.getElementById('utilityBalanceLabel');
    const proofLabel = document.getElementById('proofLabel');

    const alertContainer = document.getElementById('alertContainer');

    // ✅ OPEN MODAL (Electricity / Water)
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', () => {

            const billType = button.dataset.billType; // Electricity | Water
            const currentBalance = button.dataset.balance;

            leaseIdInput.value = button.dataset.id;
            tenantNameInput.value = button.dataset.name;
            selectedRoomInput.value = button.dataset.room;
            billTypeInput.value = billType;

            // ✅ ONLY set balance if SAME bill type is being edited
            if (billTypeInput.value === billType && currentBalance !== undefined && currentBalance !== null) {
                utilityBalanceInput.value = currentBalance;
            } else {
                utilityBalanceInput.value = ''; // ✅ CLEAR if different type
            }

            // ✅ Change modal labels dynamically
            modalTitle.innerHTML = `<i class="bi bi-pencil-square me-1"></i> Update ${billType} Billing`;
            utilityBalanceLabel.innerText = `${billType} Bill Amount (₱)`;
            proofLabel.innerText = `Proof of ${billType} Billing`;

            // ✅ Reset optional fields
            proofInput.value = '';
            billingMonthInput.value = '';
            proofNotesInput.value = '';

            modal.show();
        });
    });


    // ✅ Allow only valid decimal numbers
    utilityBalanceInput.addEventListener('input', function () {
        let val = this.value.replace(/[^0-9.]/g, '');
        const parts = val.split('.');
        if (parts.length > 2) {
            val = parts[0] + '.' + parts.slice(1).join('');
        }
        this.value = val;
    });

    // ✅ FORM SUBMIT (AJAX)
    editForm.addEventListener('submit', function(e){
        e.preventDefault();

        const leaseId = leaseIdInput.value;
        const billType = billTypeInput.value;
        const formattedValue = parseFloat(utilityBalanceInput.value);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        if (!leaseId) {
            alertContainer.innerHTML = `<div class="alert alert-danger">Invalid lease ID.</div>`;
            return;
        }

        if (isNaN(formattedValue) || formattedValue < 0) {
            alertContainer.innerHTML = `<div class="alert alert-danger">Invalid number entered.</div>`;
            return;
        }

        const formData = new FormData();
        formData.append('bill_type', billType);
        formData.append('utility_balance', formattedValue);
        formData.append('_method', 'PUT');

        if (billingMonthInput.value) {
            formData.append('billing_month', billingMonthInput.value);
        }

        if (proofNotesInput.value) {
            formData.append('proof_notes', proofNotesInput.value);
        }

        if (proofInput.files[0]) {
            formData.append('proof_of_utility_billing', proofInput.files[0]);
        }

        fetch(`/manager/utilities/${leaseId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        })
        .then(async response => {
            const data = await response.json();

            // ✅ Properly catch Laravel validation errors (422)
            if (!response.ok) {
                if (response.status === 422) {
                    const firstError = Object.values(data.errors)[0][0];

                    alertContainer.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show mt-3">
                            <i class="bi bi-exclamation-triangle me-1"></i> ${firstError}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>`;
                    return;
                }

                throw new Error(data.message || 'Request failed');
            }

            // ✅ Success response
            if (data.success) {
                modal.hide();
                alertContainer.innerHTML = `
                    <div class="alert alert-success alert-dismissible fade show mt-3">
                        <i class="bi bi-check-circle me-1"></i> ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>`;
                setTimeout(() => window.location.reload(), 1000);
            } else {
                alertContainer.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show mt-3">
                        <i class="bi bi-exclamation-triangle me-1"></i> ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>`;
            }
        })
        .catch(err => {
            console.error(err);
            alertContainer.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show mt-3">
                    <i class="bi bi-x-circle me-1"></i> An error occurred while updating.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
        });

    });

});
</script> --}}

<script>
document.addEventListener("DOMContentLoaded", function () {
    const modalEl = document.getElementById('editUtilityModal');
    const modal = new bootstrap.Modal(modalEl);

    const editForm = document.getElementById('editUtilityForm');
    const tenantNameInput = document.getElementById('tenantName');
    const selectedRoomInput = document.getElementById('selectedRoom');
    const utilityBalanceInput = document.getElementById('utilityBalance');
    const proofInput = document.getElementById('proofOfUtilityBilling');
    const billingMonthInput = document.getElementById('billingMonth');
    const proofNotesInput = document.querySelector('textarea[name="proof_notes"]');
    const leaseIdInput = document.getElementById('leaseId');
    const billTypeInput = document.getElementById('billType');

    const modalTitle = document.getElementById('editUtilityModalTitle');
    const utilityBalanceLabel = document.getElementById('utilityBalanceLabel');
    const proofLabel = document.getElementById('proofLabel');

    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', () => {
            const leaseId = button.dataset.id;
            const billType = button.dataset.billType;
            const balance = button.dataset.balance;

            // Fill hidden inputs
            leaseIdInput.value = leaseId;
            billTypeInput.value = billType;

            // Fill modal fields
            tenantNameInput.value = button.dataset.name;
            selectedRoomInput.value = button.dataset.room;
            utilityBalanceInput.value = balance ?? '';

            // Update modal text dynamically
            modalTitle.innerHTML = `<i class="bi bi-pencil-square me-1"></i> Update ${billType} Billing`;
            utilityBalanceLabel.innerText = `${billType} Bill Amount (₱)`;
            proofLabel.innerText = `Proof of ${billType} Billing`;

            // Clear optional fields
            proofInput.value = '';
            billingMonthInput.value = '';
            proofNotesInput.value = '';

            // ✅ Set the correct action URL for the form
            editForm.action = `/manager/utilities/${leaseId}`;

            // Show modal
            modal.show();
        });
    });

    // Optional: ensure valid decimal input
    utilityBalanceInput.addEventListener('input', function () {
        let val = this.value.replace(/[^0-9.]/g, '');
        const parts = val.split('.');
        if (parts.length > 2) {
            val = parts[0] + '.' + parts.slice(1).join('');
        }
        this.value = val;
    });
});
</script>


@endsection
