<!-- resources/views/manager/units/unit-modal.blade.php -->

<!-- Add Unit Modal -->
<div class="modal fade" id="addUnitModal" tabindex="-1" aria-labelledby="addUnitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-sm border-0 rounded-3">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-semibold" id="addUnitModalLabel">
                    <i class="bi bi-plus-circle me-2"></i> Add Unit
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('manager.units.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="unit_number" class="form-label fw-medium">Unit Number</label>
                        <input type="text" name="unit_number" id="unit_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="unit_type" class="form-label fw-medium">Unit Type</label>
                        <input type="text" name="unit_type" id="unit_type" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="monthly_rent" class="form-label fw-medium">Monthly Rent</label>
                        <input type="number" name="monthly_rent" id="monthly_rent" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Save Unit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Unit Modal -->
<div class="modal fade" id="editUnitModal" tabindex="-1" aria-labelledby="editUnitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-sm border-0 rounded-3">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-semibold" id="editUnitModalLabel">
                    <i class="bi bi-pencil-square me-2"></i> Edit Unit
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editUnitForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <input type="hidden" name="unit_id" id="edit_unit_id">
                    <div class="mb-3">
                        <label for="edit_unit_number" class="form-label fw-medium">Unit Number</label>
                        <input type="text" name="unit_number" id="edit_unit_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_unit_type" class="form-label fw-medium">Unit Type</label>
                        <input type="text" name="unit_type" id="edit_unit_type" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_monthly_rent" class="form-label fw-medium">Monthly Rent</label>
                        <input type="number" name="monthly_rent" id="edit_monthly_rent" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-warning text-dark">
                        <i class="bi bi-save me-1"></i> Update Unit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
