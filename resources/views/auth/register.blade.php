@extends('layouts.app')

@section('title', 'Register')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
    /* Body */
    body {
        background: linear-gradient(135deg, #f0f2f5, #d9e0eb);
        font-family: 'Inter', sans-serif;
    }

    /* Form Container */
    .registration-container {
        background: rgba(255,255,255,0.95);
        border-radius: 20px;
        padding: 50px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        backdrop-filter: blur(6px);
        max-width: 1200px;
        margin: auto;
    }

    /* Section Headers */
    .section-title {
        font-size: 1.4rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        background: linear-gradient(135deg, #01017c, #2d3b9a);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Inputs, Textareas, Selects */
    .form-control, .form-select, textarea {
        border-radius: 0.75rem;
        padding: 0.7rem 1rem;
        border: 1px solid #ced4da;
        transition: all 0.3s ease;
    }
    .form-control:focus, .form-select:focus, textarea:focus {
        border-color: #01017c;
        box-shadow: 0 0 10px rgba(1,1,124,0.2);
    }

    /* Buttons */
    .btn-gradient {
        background: linear-gradient(135deg, #01017c, #2d3b9a);
        border: none;
        border-radius: 50px;
        padding: 0.65rem 2rem;
        font-weight: 600;
        color: #fff;
        transition: all 0.3s ease;
    }
    .btn-gradient:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(1,1,124,0.25);
    }

    .bed-legend {
        display: flex;
        gap: 1.5rem;
        font-size: 0.9rem;
        align-items: center;
        flex-wrap: wrap;
    }
    .legend-pill {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        display: inline-block;
    }
    .legend-pill.upper {
        background-color: #dc3545;
    }
    .legend-pill.lower {
        background-color: #198754;
    }

    /* Checkbox */
    .form-check-input {
        transform: scale(1.3);
        cursor: pointer;
    }

    /* File Inputs */
    input[type="file"] {
        border-radius: 0.75rem;
        padding: 0.4rem;
    }

    /* Validation Error Text */
    .invalid-feedback {
        display: block;
        color: #dc3545;
        font-size: 0.85rem;
        margin-top: 0.25rem;
    }
    .form-control.is-invalid, .form-select.is-invalid {
        border-color: #dc3545;
    }
    .form-control.is-invalid:focus, .form-select.is-invalid:focus {
        border-color: #dc3545;
        box-shadow: 0 0 10px rgba(220, 53, 69, 0.25);
    }

    /* Modal Styling */
    .modal-content {
        border-radius: 1rem;
    }
    .modal-body iframe {
        border-radius: 0.5rem;
    }

    /* Password toggle button */
    #togglePassword,
    #togglePasswordConfirmation {
        position: absolute !important;
        right: 0.5rem !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        color: #6c757d;
        padding: 0.375rem 0.5rem;
        cursor: pointer;
        transition: color 0.2s ease;
        z-index: 10 !important;
        border: none !important;
        background: none !important;
        text-decoration: none !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    #togglePassword:hover,
    #togglePasswordConfirmation:hover {
        color: #495057;
    }

    #togglePassword:focus,
    #togglePasswordConfirmation:focus {
        outline: none;
        box-shadow: none;
    }

    /* Add padding to password inputs to make room for toggle button */
    #password,
    #password_confirmation {
        padding-right: 2.5rem;
    }

    /* Remove validation icons (exclamation) from password fields */
    #password.is-invalid,
    #password_confirmation.is-invalid {
        background-image: none !important;
        padding-right: 2.5rem;
    }

    #password.is-valid,
    #password_confirmation.is-valid {
        background-image: none !important;
        padding-right: 2.5rem;
    }

    /* Responsive Column Stacking */
    @media (max-width: 768px) {
        .registration-container .row > .col-md-6 {
            border-right: none !important;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-5">
    <div class="registration-container">
        <h3 class="text-center mb-4 text-dark">Tenant Registration & Application</h3>

        <form method="POST" action="{{ route('register.submit') }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-4">
                <!-- LEFT SIDE -->
                <div class="col-md-6 border-end">
                    <h5 class="section-title">Account Information</h5>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" value="{{ old('last_name') }}" class="form-control @error('last_name') is-invalid @enderror" required>
                            @error('last_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" value="{{ old('first_name') }}" class="form-control @error('first_name') is-invalid @enderror" required>
                            @error('first_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required
                               pattern="[a-zA-Z0-9]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                               title="Valid email format required">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact" value="{{ old('contact') }}" class="form-control @error('contact') is-invalid @enderror"
                               required maxlength="15" minlength="10"
                               pattern="^[0-9]{10,15}$"
                               title="Contact must be 10–15 digits only"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        @error('contact')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="position-relative">
                            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required
                                   pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$"
                                   title="At least 8 characters, with uppercase, lowercase, number & special character">
                            <button type="button" class="btn btn-link" id="togglePassword">
                                <i class="bi bi-eye" id="passwordEyeIcon"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <div class="position-relative">
                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control @error('password_confirmation') is-invalid @enderror" required>
                            <button type="button" class="btn btn-link" id="togglePasswordConfirmation">
                                <i class="bi bi-eye" id="passwordConfirmationEyeIcon"></i>
                            </button>
                        </div>
                        @error('password_confirmation')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- RIGHT SIDE -->
                <div class="col-md-6">
                    <h5 class="section-title">Tenant Application</h5>

                    <div class="mb-3">
                        <label class="form-label">Current Address</label>
                        <input type="text" name="current_address" value="{{ old('current_address') }}" class="form-control @error('current_address') is-invalid @enderror" required>
                        @error('current_address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Birthdate</label>
                        <input type="date" name="birthdate" value="{{ old('birthdate') }}" class="form-control @error('birthdate') is-invalid @enderror" required>
                        @error('birthdate')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Preferred Unit Type</label>
                        <select name="unit_type" id="unit_type" class="form-select @error('unit_type') is-invalid @enderror" required>
                            <option value="">Select Unit Type</option>
                            @foreach($unitTypes as $type)
                                <option value="{{ $type }}" {{ old('unit_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('unit_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Select Room</label>
                        <select name="unit_id" id="unit_id" class="form-select @error('unit_id') is-invalid @enderror" required>
                            <option value="">Select a room</option>
                            @foreach($availableUnits as $unit)
                                @php
                                    $takenBeds = ($unit->taken_beds ?? collect())->values();
                                    if ($unit->type === 'Bed-Spacer') {
                                        $displayCount = max($unit->no_of_occupants ?? 0, $takenBeds->count());
                                        $occupancyInfo = " ({$displayCount}/{$unit->capacity})";
                                        $isFullyBooked = $takenBeds->count() >= $unit->capacity;
                                    } else {
                                        $occupancyInfo = "";
                                        $isFullyBooked = $unit->status === 'occupied';
                                    }
                                @endphp
                                <option value="{{ $unit->id }}"
                                        data-type="{{ $unit->type }}"
                                        data-capacity="{{ $unit->capacity }}"
                                        data-taken-beds='@json($takenBeds)'
                                        {{ $isFullyBooked ? 'disabled' : '' }}
                                        {{ old('unit_id') == $unit->id ? 'selected' : '' }}>
                                    {{ $unit->room_no }} ({{ $unit->type }}{{ $occupancyInfo }})
                                    @if($isFullyBooked) - Occupied @endif
                                </option>
                            @endforeach
                        </select>
                        @error('unit_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3 d-none" id="bedSelectionGroup">
                        <label class="form-label">Select Bed Number</label>
                        <select name="bed_number" id="bed_number" class="form-select @error('bed_number') is-invalid @enderror">
                            <option value="">Choose a bed</option>
                        </select>
                        @error('bed_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="bed-legend mt-2">
                            <span><span class="legend-pill upper"></span> First half = Upper bunk</span>
                            <span><span class="legend-pill lower"></span> Second half = Lower bunk</span>
                        </div>
                        <small class="text-muted">Bed assignments apply to Bed-Spacer units only. Available beds only.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Preferred Move-in Date</label>
                        <input type="date" name="move_in_date" value="{{ old('move_in_date') }}" class="form-control @error('move_in_date') is-invalid @enderror" required>
                        @error('move_in_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reason for Renting</label>
                        <textarea name="reason" class="form-control @error('reason') is-invalid @enderror" rows="2" required>{{ old('reason') }}</textarea>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Employment Status</label>
                        <select name="employment_status" class="form-select @error('employment_status') is-invalid @enderror" required>
                            <option value="">Select</option>
                            <option value="Employed" {{ old('employment_status') == 'Employed' ? 'selected' : '' }}>Employed</option>
                            <option value="Unemployed" {{ old('employment_status') == 'Unemployed' ? 'selected' : '' }}>Unemployed</option>
                            <option value="Student" {{ old('employment_status') == 'Student' ? 'selected' : '' }}>Student</option>
                        </select>
                        @error('employment_status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Employer or School</label>
                        <input type="text" name="employer_school" class="form-control @error('employer_school') is-invalid @enderror" value="{{ old('employer_school') }}" required>
                        @error('employer_school')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Source of Income</label>
                        <input type="text" name="source_of_income" class="form-control @error('source_of_income') is-invalid @enderror" value="{{ old('source_of_income') }}" required>
                        @error('source_of_income')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_name" class="form-control @error('emergency_name') is-invalid @enderror" value="{{ old('emergency_name') }}" required>
                            @error('emergency_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Emergency Number</label>
                            <input type="text"
                                   name="emergency_number"
                                   class="form-control @error('emergency_number') is-invalid @enderror"
                                   value="{{ old('emergency_number') }}"
                                   required
                                   maxlength="15"
                                   minlength="10"
                                   pattern="^[0-9]{10,15}$"
                                   inputmode="numeric"
                                   title="Emergency contact must be 10–15 digits"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            @error('emergency_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Relationship</label>
                        <input type="text" name="emergency_relationship" class="form-control @error('emergency_relationship') is-invalid @enderror" value="{{ old('emergency_relationship') }}" required>
                        @error('emergency_relationship')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Valid ID</label>
                        {{-- v18: the camera opens to capture the ID — see partials/id-camera --}}
                        <input type="file" name="valid_id" id="valid_id" class="form-control @error('valid_id') is-invalid @enderror"
                               accept="image/*" capture="environment" data-id-camera required>
                        @error('valid_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">1x1 ID Picture</label>
                        <input type="file" name="id_picture" class="form-control @error('id_picture') is-invalid @enderror" accept="image/*" required>
                        @error('id_picture')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Image files only (Max: 5MB)</small>
                    </div>
                </div>
            </div>

            <div class="form-check d-flex flex-column justify-content-center align-items-center gap-2 mb-4 mt-4">
                <div class="d-flex align-items-center gap-2">
                    <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" id="terms" required>
                    <label class="form-check-label mb-0" for="terms">
                        I have read and agree to the
                        <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal" class="text-decoration-underline">
                            Terms and Conditions
                        </a>.
                    </label>
                </div>
                @error('terms')
                    <div class="invalid-feedback d-block text-center">{{ $message }}</div>
                @enderror

                {{-- v18: RA 10173 consent. Also enforced server-side. --}}
                <div class="d-flex align-items-start gap-2 dpn-consent" style="max-width:640px;">
                    <input class="form-check-input mt-1 @error('data_privacy') is-invalid @enderror" type="checkbox"
                           name="data_privacy" id="data_privacy" value="1" {{ old('data_privacy') ? 'checked' : '' }} required>
                    <label class="form-check-label mb-0" for="data_privacy">
                        I have read the
                        <a href="#" data-privacy-open class="text-decoration-underline">Data Privacy Notice</a>
                        and I consent to Pinikitan Rental collecting and processing my personal information,
                        including my ID photo, for my application and tenancy.
                        <small>Data Privacy Act of 2012 (RA 10173)</small>
                    </label>
                </div>
                @error('data_privacy')
                    <div class="invalid-feedback d-block text-center">{{ $message }}</div>
                @enderror
            </div>

            <div class="text-center mb-4">
                <button type="submit" class="btn btn-gradient">Submit Registration & Application</button>
            </div>

            <hr>

            <div class="text-center">
                <p class="mb-2">Already have an account?</p>
                <a href="{{ route('login') }}" class=" w-50">Login</a>
            </div>
        </form>
    </div>
</div>

{{-- v18: valid-ID camera capture --}}
@include('partials.id-camera')

<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="height: 80vh;">
        <iframe src="{{ asset('storage/assets/tenant_agreement.pdf') }}" width="100%" height="100%" style="border:none;"></iframe>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Validation Error Modal -->
<div class="modal fade" id="formErrorModal" tabindex="-1" aria-labelledby="formErrorModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="formErrorModalLabel">
            <i class="bi bi-exclamation-triangle me-2"></i> Please review your submission
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        @if ($errors->any())
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
        @if (session('error'))
            <div class="alert alert-warning mt-3 mb-0">
                {{ session('error') }}
            </div>
        @endif
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Got it</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
    const unitTypeSelect = document.getElementById('unit_type');
    const unitSelect = document.getElementById('unit_id');
    const bedSelectionGroup = document.getElementById('bedSelectionGroup');
    const bedSelect = document.getElementById('bed_number');
    let previousBed = @json(old('bed_number'));

    function resetBedSelector() {
        bedSelectionGroup.classList.add('d-none');
        bedSelect.innerHTML = '<option value=\"\">Choose a bed</option>';
    }

    function filterRoomsByType() {
        const selectedType = unitTypeSelect.value;
        Array.from(unitSelect.options).forEach(option => {
            if (option.value === '') {
                return;
            }
            const matches = !selectedType || option.dataset.type === selectedType;
            option.hidden = !matches;
            option.style.display = matches ? 'block' : 'none';
            if (!matches && option.selected) {
                option.selected = false;
                resetBedSelector();
            }
        });

        if (!selectedType) {
            unitSelect.value = '';
            resetBedSelector();
        }
    }

    function populateBeds() {
        const selectedOption = unitSelect.selectedOptions[0];
        if (!selectedOption || selectedOption.dataset.type !== 'Bed-Spacer') {
            resetBedSelector();
            return;
        }

        const capacity = parseInt(selectedOption.dataset.capacity || '0', 10);
        const takenBeds = JSON.parse(selectedOption.dataset.takenBeds || '[]').map(Number);
        
        // Calculate upper and lower bed counts
        const upperCount = Math.floor(capacity / 2);
        
        // Build bed options - only include available beds
        let optionsMarkup = '<option value=\"\">Choose a bed</option>';
        
        // Upper beds (first half): 1 to upperCount
        for (let i = 1; i <= upperCount; i++) {
            // Skip taken beds - don't show them in the dropdown
            if (!takenBeds.includes(i)) {
                const label = `Bed ${i} - Upper`;
                optionsMarkup += `<option value=\"${i}\">${label}</option>`;
            }
        }
        
        // Lower beds (second half): upperCount+1 to capacity
        for (let i = upperCount + 1; i <= capacity; i++) {
            // Skip taken beds - don't show them in the dropdown
            if (!takenBeds.includes(i)) {
                const label = `Bed ${i} - Lower`;
                optionsMarkup += `<option value=\"${i}\">${label}</option>`;
            }
        }

        bedSelect.innerHTML = optionsMarkup;
        bedSelectionGroup.classList.remove('d-none');

        if (previousBed && !takenBeds.includes(Number(previousBed))) {
            bedSelect.value = previousBed;
        } else {
            bedSelect.value = '';
        }
        previousBed = null;
    }

    unitTypeSelect.addEventListener('change', filterRoomsByType);
    unitSelect.addEventListener('change', populateBeds);

    filterRoomsByType();
    populateBeds();

    // ==================== PASSWORD VISIBILITY TOGGLE ====================
    const togglePasswordBtn = document.getElementById('togglePassword');
    const togglePasswordConfirmationBtn = document.getElementById('togglePasswordConfirmation');
    const passwordInput = document.getElementById('password');
    const passwordConfirmationInput = document.getElementById('password_confirmation');
    const passwordEyeIcon = document.getElementById('passwordEyeIcon');
    const passwordConfirmationEyeIcon = document.getElementById('passwordConfirmationEyeIcon');

    // Toggle password visibility
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle eye icon
            if (type === 'password') {
                passwordEyeIcon.classList.remove('bi-eye-slash');
                passwordEyeIcon.classList.add('bi-eye');
            } else {
                passwordEyeIcon.classList.remove('bi-eye');
                passwordEyeIcon.classList.add('bi-eye-slash');
            }
        });
    }

    // Toggle confirm password visibility
    if (togglePasswordConfirmationBtn && passwordConfirmationInput) {
        togglePasswordConfirmationBtn.addEventListener('click', function() {
            const type = passwordConfirmationInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordConfirmationInput.setAttribute('type', type);
            
            // Toggle eye icon
            if (type === 'password') {
                passwordConfirmationEyeIcon.classList.remove('bi-eye-slash');
                passwordConfirmationEyeIcon.classList.add('bi-eye');
            } else {
                passwordConfirmationEyeIcon.classList.remove('bi-eye');
                passwordConfirmationEyeIcon.classList.add('bi-eye-slash');
            }
        });
    }
</script>

@if($errors->any() || session('error'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalElement = document.getElementById('formErrorModal');
        if (modalElement) {
            const modalInstance = new bootstrap.Modal(modalElement);
            modalInstance.show();
        }
    });
</script>
@endif
@endpush
@endsection
