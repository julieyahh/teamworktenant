{{--
    Placeholder settings page.

    Rendered by: App\Http\Controllers\Manager\DashboardController@settings
    Route:       GET /manager/settings   (manager.settings)

    This view did not exist, so hitting /manager/settings threw
    "View [manager.settings] not found". Nothing in the sidebar links here yet;
    replace the card below when the settings screen is actually built.
--}}
@extends('layouts.managerdashboardlayout')

@section('title', 'Pinikitan Rental | Settings')
@section('page-title', 'Settings')

@section('content')
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;
                padding:32px;box-shadow:0 2px 10px rgba(12,26,58,.06);">
        <h2 style="font-size:1.05rem;color:#0f172a;margin:0 0 8px;">Settings</h2>
        <p style="color:#64748b;font-size:.92rem;margin:0;">
            There are no configurable settings yet. Rates, unit setup and utility
            billing are managed from their own pages in the sidebar.
        </p>
    </div>
@endsection
