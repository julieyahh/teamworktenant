@extends('layouts.tenantlayout')

@section('title', 'Home')
@section('page-title', 'Tenant Dashboard')

@section('content')
<div class="row mb-4">
    <!-- Tenant Profile -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-dark text-white">Profile Info</div>
            <div class="card-body">
                <p><strong>Name:</strong> {{ $user->name }}</p>
                <p><strong>Email:</strong> {{ $user->email }}</p>
                <p><strong>Unit:</strong> {{ $user->unit ?? 'N/A' }}</p>
            </div>
        </div>
    </div>

    <!-- Application Status -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">Application Status</div>
            <div class="card-body">
                <p>Status: <strong>{{ ucfirst($user->status) }}</strong></p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Rent Payment -->
    <div class="col-md-6">
        <div class="card bg-info text-white mb-3">
            <div class="card-body">
                <h5 class="card-title">Your Rent Payment</h5>
                <p class="card-text fs-3">₱{{ number_format($tenantPayment) }}</p>
            </div>
        </div>
    </div>

    <!-- Utilities Payment -->
    <div class="col-md-6">
        <div class="card bg-secondary text-white mb-3">
            <div class="card-body">
                <h5 class="card-title">Your Utilities Payment</h5>
                <p class="card-text fs-3">₱{{ number_format($utilitiesPayment) }}</p>
            </div>
        </div>
    </div>
</div>

<!-- Optional: Notifications -->
<div class="card mb-4">
    <div class="card-header bg-success text-white">Notifications</div>
    <div class="card-body">
        <p>No new notifications.</p> <!-- Can be dynamic later -->
    </div>
</div>
@endsection