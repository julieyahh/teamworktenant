@if($tenants->isEmpty())
    <div class="alert alert-warning text-center mt-4" role="alert">
        <i class="bi bi-exclamation-circle-fill"></i> No tenants found for this filter.
    </div>
@else
    <div class="row g-3">
        @foreach($tenants as $tenant)
            <div class="col-md-4">
                <div class="card tenant-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-person-circle text-primary fs-3 me-2"></i>
                            <h5 class="card-title mb-0">{{ $tenant->name }}</h5>
                        </div>
                        <p class="mb-1"><i class="bi bi-envelope"></i> {{ $tenant->email }}</p>
                        <p class="mb-1"><i class="bi bi-telephone"></i> {{ $tenant->phone ?? 'N/A' }}</p>
                        <p class="mb-2"><i class="bi bi-house-door"></i> {{ $tenant->unit ?? 'Not Assigned' }}</p>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span class="badge 
                                @if($tenant->status == 'approved') bg-success
                                @elseif($tenant->status == 'pending') bg-warning text-dark
                                @elseif($tenant->status == 'rejected') bg-danger
                                @endif">
                                {{ ucfirst($tenant->status) }}
                            </span>

                            <a href="{{ route('manager.tenants', $tenant->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
