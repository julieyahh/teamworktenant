@extends('layouts.managerdashboardlayout')

@section('title', 'Manage Units')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
  /* Body & container */
  .container {
    max-width: 1200px;
  }
  body {
    background-color: #f6f7fb;
  }

  /* Heading */
  h2 {
    font-weight: 700;
    color: #333;
    margin-bottom: 1rem;
  }

  /* Card style */
  .card {
    background: #fff;
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgb(0 0 0 / 0.1);
  }

  /* Card header */
  .card-header {
    border-bottom: none;
    background: transparent;
    font-weight: 700;
    font-size: 1.25rem;
    color: #222;
    padding: 1rem 1.5rem;
  }

  /* Buttons */
  .btn-add-unit {
    background-color: #b8793e;
    color: white;
    border-radius: 8px;
    padding: 10px 18px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgb(184 121 62 / 0.5);
    transition: background-color 0.2s ease;
  }
  .btn-add-unit:hover {
    background-color: #a5642f;
    color: #fff;
  }

  /* Table styles */
  table {
    border-collapse: separate;
    border-spacing: 0 10px;
    font-size: 0.9rem;
  }
  thead tr {
    background-color: transparent;
  }
  thead th {
    color: #666;
    font-weight: 600;
    text-align: center;
    padding: 12px 18px;
  }
  tbody tr {
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    transition: background-color 0.15s ease;
  }
  tbody tr:hover {
    background-color: #f4f7ff;
  }
  tbody td {
    padding: 14px 18px;
    vertical-align: middle;
  }
  tbody td span.fw-semibold {
    font-weight: 600;
    color: #222;
  }
  tbody td span.fw-bold {
    font-weight: 700;
    color: #222;
  }
  tbody td span.text-success {
    color: #2e7d32;
    font-weight: 600;
  }

  /* Status badges */
  .badge {
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
    padding: 6px 14px;
    display: inline-block;
  }
  .badge.bg-success {
    background-color: #d1e7dd;
    color: #0f5132;
  }
  .badge.bg-danger {
    background-color: #f8d7da;
    color: #842029;
  }

  /* Actions buttons */
  .btn-sm {
    padding: 5px 8px;
    font-size: 1rem;
    border-radius: 6px;
  }
  .btn-warning {
    background-color: #f9a825; /* amber accent */
    border: none;
    color: white;
    box-shadow: 0 2px 8px rgb(249 168 37 / 0.5);
  }
  .btn-warning:hover {
    background-color: #c17900;
  }
  .btn-danger {
    box-shadow: 0 2px 8px rgb(220 53 69 / 0.5);
  }

  /* Modal styling stays mostly same, except minor tweaks for rounding if needed */
  .modal-content {
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0,0,0,.2);
  }
  .modal-header {
    border-bottom: none;
    background-color: #b8793e;
    color: #fff;
    border-top-left-radius: 15px;
    border-top-right-radius: 15px;
  }
  .modal-header.bg-danger {
    background-color: #dc3545;
  }
  .modal-footer {
    border-top: none;
  }
  .btn-light.border {
    border-radius: 8px;
    color: #5a3e2b;
  }
  .btn-primary, .btn-warning, .btn-danger {
    border-radius: 8px;
  }
</style>
@endpush


@section('content')
<div class="container mt-4">

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif

    @if($errors->any())
        <x-alert type="error">{{ $errors->first() }}</x-alert>
    @endif

    <h2 class="mb-4 fw-bold">Unit Management</h2>

    <!-- Add Unit Button -->
  <button type="button" class="btn btn-add-unit mb-3" style="border: 2px solid #01017c; color: #01017c; background: transparent;"  data-bs-toggle="modal" data-bs-target="#createUnitModal">
    <i class="bi bi-plus-circle"></i> Add New Unit
  </button>
  <button type="button"
    class="btn btn-primary mb-3 ms-2 position-relative"
    style="background-color: #01017c; color: white; border: none; padding: 8px 20px;"
    data-bs-toggle="modal"
    data-bs-target="#leaseApplicationsModal">

    <i class="bi bi-file-earmark-text"></i> View Additional Units

    @if($pendingLeases->count() > 0)
        <span class="badge rounded-pill bg-danger" style="margin-left: 8px;">
            {{ $pendingLeases->count() }}
        </span>
    @endif
</button>

  <div class="card">
    <div class="card-header">All Units</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Type</th>
              <th>Room No</th>
              <th>Room Price</th>
              <th>Capacity</th>
              <th>Application Limit</th>
              <th>Status</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($units as $unit)
            <tr>
              <td class="text-center">{{ $unit->id }}</td>
              <td><span class="fw-semibold">{{ $unit->type }}</span></td>
              <td><span class="fw-bold">{{ $unit->room_no }}</span></td>
              <td><span class="text-success fw-semibold">₱{{ number_format($unit->room_price, 2) }}</span></td>
              <td class="text-center">{{ $unit->no_of_occupants }}/{{ $unit->capacity }}</td>
              <td class="text-center">
                {{ $unit->leases->filter(fn($lease) => strtolower($lease->lea_status) === 'pending')->count() }}/{{ $unit->application_limit }}
            </td>
              <td class="text-center">
                <span class="badge {{ $unit->status == 'vacant' ? 'bg-success' : 'bg-danger' }}">
                  {{ ucfirst($unit->status) }}
                </span>
              </td>
              <td class="text-center">
                <button
                    type="button"
                    class="btn btn-sm btn-warning me-1"
                    data-bs-toggle="modal"
                    data-bs-target="#editUnitModal{{ $unit->id }}"
                    title="Edit Unit"
                    {{ $unit->status === 'occupied' || $unit->status === 'maintenance' ? 'disabled' : '' }}
                >
                    <i class="bi bi-pencil-square"></i>
                </button>

                <button
                    type="button"
                    class="btn btn-sm btn-danger"
                    data-bs-toggle="modal"
                    data-bs-target="#deleteUnitModal{{ $unit->id }}"
                    title="Delete Unit"
                    {{ $unit->status === 'occupied' || $unit->status === 'maintenance' ? 'disabled' : '' }}
                >
                    <i class="bi bi-trash3-fill"></i>
                </button>
            </td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="text-center text-muted">No units available</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- CREATE modal -->
<div class="modal fade" id="createUnitModal" tabindex="-1" aria-labelledby="createUnitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('manager.units.store') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Unit</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="">Select Type</option>
                            @foreach(['Studio','1-Bedroom','2-Bedroom', 'Commercial', 'Bed-Spacer'] as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Room No</label>
                        <input type="text" name="room_no" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Room Price</label>
                        <input type="number" step="0.01" name="room_price" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacity</label>
                        <input type="number" name="capacity" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Application Limit</label>
                        <input type="number" name="application_limit" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="vacant">Vacant</option>
                            <option value="occupied">Occupied</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                <button class="btn btn-light"
                      style="border: 2px solid #01017c; color: #01017c; background: transparent;"
                        data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="submit"
                        class="btn text-white"
                        style="background-color: #01017c; color: white; border: none; padding: 8px 20px;">
                    Save Unit
                </button>
            </div>
            </div>
        </form>
    </div>
</div>

<!-- EDIT & DELETE modals -->
@foreach($units as $unit)
    <!-- Edit Modal -->
    <div class="modal fade" id="editUnitModal{{ $unit->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('manager.units.update', $unit->id) }}">
                @csrf
                @method('PUT')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Unit</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <input type="text" class="form-control" value="{{ $unit->type }}" readonly>
                            <input type="hidden" name="type" value="{{ $unit->type }}">
                            <small class="text-muted">Unit type cannot be changed after creation.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Room No</label>
                            <input type="text" name="room_no" class="form-control" value="{{ $unit->room_no }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Room Price</label>
                            <input type="number" step="0.01" name="room_price" class="form-control" value="{{ $unit->room_price }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" name="capacity" class="form-control" min="1" value="{{ $unit->capacity }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Application Limit</label>
                            <input type="number" name="application_limit" class="form-control" min="1" value="{{ $unit->application_limit }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="vacant" {{ $unit->status == 'vacant' ? 'selected' : '' }}>Vacant</option>
                                <option value="occupied" {{ $unit->status == 'occupied' ? 'selected' : '' }}>Occupied</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn text-white" style="background-color: #b8793e; border: none; border-radius: 8px;">
                           Update
                            </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteUnitModal{{ $unit->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('manager.units.destroy', $unit->id) }}">
                @csrf
                @method('DELETE')
                <div class="modal-content">
                    <div class="modal-header bg-danger">
                        <h5 class="modal-title text-white"><i class="bi bi-trash3-fill"></i> Delete Unit</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Are you sure you want to delete this unit?</p>
                        <p class="fw-bold text-danger">Room No: {{ $unit->room_no }}</p>
                    </div>
                    <div class="modal-footer">
                            <button type="button" class="btn btn-light"
                                    style="border: 2px solid #01017c; color: #01017c; background: transparent;"
                                    data-bs-dismiss="modal">
                                Cancel
                            </button>
                        <button type="submit" class="btn btn-danger">Yes, Delete</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<div class="modal fade" id="leaseApplicationsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-4">

            <!-- Header -->
            <div class="modal-header text-white py-3 rounded-top-4" style="background-color: #01017c; color: white; border: none; padding: 8px 20px;">
                <h5 class="modal-title d-flex align-items-center">
                    <i class="bi bi-file-earmark-text me-2 fs-4"></i>
                  View Additional Units
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-4">

                @if($pendingLeases->isEmpty())
                    <div class="alert alert-info text-center py-4 rounded-3">
                        <i class="bi bi-info-circle fs-5 me-2"></i>
                        No pending lease applications at the moment.
                    </div>

                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Tenant</th>
                                    <th>Unit Number</th>
                                    <th>Unit Type</th>
                                    <th>Room No</th>
                                    <th>Price</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($pendingLeases as $lease)
                                    <tr>
                                        <td class="fw-semibold">{{ $lease->tenant->name }}</td>
                                        <td>{{ $lease->unit_id }}</td>
                                        <td>{{ $lease->unit->type }}</td>
                                        <td>
                                            <span class="badge bg-secondary px-3 py-2 rounded-3">
                                                {{ $lease->unit->room_no }}
                                            </span>
                                        </td>
                                        <td>₱{{ number_format($lease->unit->room_price, 2) }}</td>
                                        <td>{{ $lease->lea_start_date ? \Carbon\Carbon::parse($lease->lea_start_date)->format('M d, Y') : 'TBD' }}</td>
                                        <td>{{ $lease->lea_end_date ? \Carbon\Carbon::parse($lease->lea_end_date)->format('M d, Y') : 'TBD' }}</td>

                                        <!-- Actions -->
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-2">

                                                <!-- Check if unit is occupied -->
                                                @if($lease->unit->status === 'occupied')
                                                    <!-- Disabled Occupied Button -->
                                                    <button type="button"
                                                            class="btn btn-secondary btn-sm px-3 rounded-pill"
                                                            disabled>
                                                        <i class="bi bi-lock-fill me-1"></i> Occupied
                                                    </button>
                                                @else
                                                    <!-- Approve Button (Active) -->
                                                    <form action="{{ route('manager.approve-unit', ['user' => $lease->user_id, 'unit' => $lease->unit_id, 'lease' => $lease->id]) }}" method="POST">
                                                        @csrf
                                                        <button type="submit"
                                                                class="btn btn-success btn-sm px-3 rounded-pill"
                                                                onclick="return confirm('Approve this application?')">
                                                            <i class="bi bi-check-circle me-1"></i> Approve
                                                        </button>
                                                    </form>
                                                @endif

                                                <!-- Reject Button (Always Active) -->
                                                <form action="{{ route('manager.reject-unit', [$lease->tenant->id, $lease->unit->id, $lease->id]) }}" method="POST">
                                                    @csrf
                                                    <button type="submit"
                                                            class="btn btn-danger btn-sm px-3 rounded-pill"
                                                            onclick="return confirm('Reject this application?')">
                                                        <i class="bi bi-x-circle me-1"></i> Reject
                                                    </button>
                                                </form>

                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>

                        </table>
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="modal-footer bg-light rounded-bottom-4">
                <button class="btn btn-secondary px-4 rounded-pill" style="border: 2px solid #01017c; color: #01017c; background: transparent;" data-bs-dismiss="modal">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>


@endforeach
@endsection


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tooltipEls = [].slice.call(document.querySelectorAll('[title]'));
    tooltipEls.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('approve-lease-btn')) {
        const btn = e.target;
        const leaseId = btn.dataset.id;

        if (!confirm('Are you sure you want to approve this application?')) return;

        btn.disabled = true;
        btn.textContent = 'Approving...';

        fetch(`/manager/lease/approve/${leaseId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.success);
                // Remove the row dynamically
                const row = document.getElementById(`lease-row-${leaseId}`);
                if (row) row.remove();
            } else if (data.error) {
                alert(data.error);
                btn.disabled = false;
                btn.textContent = 'Approve';
            }
        })
        .catch(err => {
            console.error(err);
            alert('Something went wrong. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Approve';
        });
    }
});

</script>
@endpush
