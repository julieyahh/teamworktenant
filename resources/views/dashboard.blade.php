@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Welcome, {{ auth()->user()->name }}!</h1>
    <p>You are logged in as <strong>{{ auth()->user()->role }}</strong>.</p>

    @if(auth()->user()->role === 'manager')
        <a href="{{ route('manager.dashboard') }}" class="btn btn-primary">Go to Manager Dashboard</a>
    @endif
</div>
@endsection
