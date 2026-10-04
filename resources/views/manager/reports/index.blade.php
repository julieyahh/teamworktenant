@extends('layouts.managerdashboardlayout')

@section('title', 'Pinikitan Rental | Reports')
@section('page-title', 'Reports')

@push('styles')
<style>
    /* ===================== PAGE HEADER ===================== */
    .pr-dash-header { margin-bottom: 32px; }
    .pr-dash-header__eyebrow {
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: var(--pr-gold);
        margin-bottom: 6px;
    }
    .pr-dash-header__title {
        font-family: var(--pr-font-display);
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--pr-navy);
        margin: 0 0 4px;
        line-height: 1.2;
    }
    .pr-dash-header__sub {
        font-size: .85rem;
        color: var(--pr-gray-500);
        margin: 0;
    }

    /* ===================== REPORT GRID ===================== */
    .pr-reports-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 22px;
    }

    /* ===================== REPORT CARD ===================== */
    .pr-report-card {
        background: var(--pr-white);
        border-radius: 18px;
        border: 1px solid rgba(12,26,58,.07);
        box-shadow: 0 2px 12px rgba(12,26,58,.06);
        overflow: hidden;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        transition: transform .25s ease, box-shadow .25s ease;
        cursor: pointer;
        position: relative;
    }
    .pr-report-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 16px 40px rgba(12,26,58,.13);
        text-decoration: none;
    }

    /* Colour accent strip at top */
    .pr-report-card__strip {
        height: 4px;
        width: 100%;
        flex-shrink: 0;
    }

    /* Icon area */
    .pr-report-card__icon-wrap {
        padding: 28px 28px 16px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }
    .pr-report-card__icon {
        width: 52px; height: 52px;
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }
    .pr-report-card__arrow {
        width: 32px; height: 32px;
        border-radius: 8px;
        background: rgba(12,26,58,.05);
        border: 1px solid rgba(12,26,58,.07);
        display: flex; align-items: center; justify-content: center;
        color: var(--pr-gray-500);
        font-size: .85rem;
        transition: background .2s, color .2s;
        flex-shrink: 0;
    }
    .pr-report-card:hover .pr-report-card__arrow {
        background: var(--pr-navy);
        border-color: var(--pr-navy);
        color: var(--pr-gold-lt);
    }

    /* Text area */
    .pr-report-card__body {
        padding: 0 28px 28px;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .pr-report-card__title {
        font-family: var(--pr-font-display);
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--pr-navy);
        margin: 0;
        line-height: 1.3;
    }
    .pr-report-card__desc {
        font-size: .82rem;
        color: var(--pr-gray-500);
        margin: 0;
        line-height: 1.55;
    }
    .pr-report-card__footer {
        margin-top: auto;
        padding-top: 16px;
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: .78rem;
        font-weight: 600;
        color: var(--pr-navy);
        opacity: .7;
        transition: opacity .2s;
    }
    .pr-report-card:hover .pr-report-card__footer { opacity: 1; }

    /* ===================== COMING SOON CARD ===================== */
    .pr-report-card--soon {
        cursor: default;
        opacity: .72;
    }
    .pr-report-card--soon:hover {
        transform: none;
        box-shadow: 0 2px 12px rgba(12,26,58,.06);
    }
    .pr-report-card--soon .pr-report-card__footer {
        color: var(--pr-gray-500);
    }
    .pr-soon-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        padding: 3px 10px;
        border-radius: 999px;
        background: rgba(12,26,58,.06);
        color: var(--pr-gray-500);
        border: 1px solid rgba(12,26,58,.08);
    }

    /* ===================== COLOUR THEMES ===================== */
    /* Navy */
    .pr-theme-navy .pr-report-card__strip  { background: linear-gradient(90deg, var(--pr-navy), #1a2d55); }
    .pr-theme-navy .pr-report-card__icon   { background: rgba(12,26,58,.08); color: var(--pr-navy); }
    /* Gold */
    .pr-theme-gold .pr-report-card__strip  { background: linear-gradient(90deg, var(--pr-gold), var(--pr-gold-lt)); }
    .pr-theme-gold .pr-report-card__icon   { background: rgba(201,168,76,.12); color: #a07c2a; }
    /* Green */
    .pr-theme-green .pr-report-card__strip { background: linear-gradient(90deg, #16a34a, #4ade80); }
    .pr-theme-green .pr-report-card__icon  { background: rgba(34,197,94,.1); color: #16a34a; }
    /* Amber */
    .pr-theme-amber .pr-report-card__strip { background: linear-gradient(90deg, #d97706, #fbbf24); }
    .pr-theme-amber .pr-report-card__icon  { background: rgba(245,158,11,.1); color: #d97706; }
    /* Purple */
    .pr-theme-purple .pr-report-card__strip { background: linear-gradient(90deg, #7c3aed, #a78bfa); }
    .pr-theme-purple .pr-report-card__icon  { background: rgba(124,58,237,.1); color: #7c3aed; }
    /* Red */
    .pr-theme-red .pr-report-card__strip   { background: linear-gradient(90deg, #dc2626, #f87171); }
    .pr-theme-red .pr-report-card__icon    { background: rgba(220,38,38,.08); color: #dc2626; }
    /* Teal */
    .pr-theme-teal .pr-report-card__strip  { background: linear-gradient(90deg, #0d9488, #2dd4bf); }
    .pr-theme-teal .pr-report-card__icon   { background: rgba(13,148,136,.1); color: #0d9488; }
    /* Gray (soon) */
    .pr-theme-gray .pr-report-card__strip  { background: linear-gradient(90deg, #9ca3af, #d1d5db); }
    .pr-theme-gray .pr-report-card__icon   { background: rgba(156,163,175,.1); color: #9ca3af; }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 768px) {
        .pr-reports-grid { grid-template-columns: 1fr 1fr; gap: 16px; }
        .pr-report-card__icon-wrap { padding: 20px 20px 12px; }
        .pr-report-card__body { padding: 0 20px 20px; }
    }
    @media (max-width: 480px) {
        .pr-reports-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')

@php
    use Illuminate\Support\Str;

    /*
     * Theme + metadata map.
     * Keys must match what the controller passes in $reports.
     */
    $meta = [
        'active-tenants' => [
            'theme' => 'pr-theme-green',
            'icon'  => 'bi-people-fill',
            'desc'  => 'View all currently active tenants and their occupancy details.',
        ],
        'payment-history' => [
            'theme' => 'pr-theme-navy',
            'icon'  => 'bi-cash-stack',
            'desc'  => 'Browse full payment records, dues, and transaction history.',
        ],
        'lease-summary' => [
            'theme' => 'pr-theme-gold',
            'icon'  => 'bi-house-fill',
            'desc'  => 'Review lease agreements, dates, and contract summaries.',
        ],
        'maintenance-requests' => [
            'theme' => 'pr-theme-teal',
            'icon'  => 'bi-tools',
            'desc'  => 'Track open and resolved maintenance tickets per unit.',
        ],
        'renewal-history' => [
            'theme' => 'pr-theme-amber',
            'icon'  => 'bi-arrow-repeat',
            'desc'  => 'Every lease renewal — who renewed, when, and the rent before and after.',
        ],
        'renovation-requests' => [
            'theme' => 'pr-theme-purple',
            'icon'  => 'bi-hammer',
            'desc'  => 'Review, approve or deny tenant renovation requests.',
        ],
        'feedback' => [
            'theme' => 'pr-theme-gold',
            'icon'  => 'bi-chat-square-heart-fill',
            'desc'  => 'Read tenant feedback and respond to open submissions.',
        ],
        'finance' => [
            'theme' => 'pr-theme-red',
            'icon'  => 'bi-wallet2',
            'desc'  => 'Financial overview including revenue, expenses, and projections.',
        ],
        'application' => [
            'theme' => 'pr-theme-purple',
            'icon'  => 'bi-file-earmark-text',
            'desc'  => 'Monitor tenant applications and approval pipeline.',
        ],
        'default' => [
            'theme' => 'pr-theme-gray',
            'icon'  => 'bi-bar-chart-line-fill',
            'desc'  => 'View this report for detailed insights.',
        ],
    ];
@endphp

    {{-- Page header --}}
    <div class="pr-dash-header">
        <div class="pr-dash-header__eyebrow">Manager Portal</div>
        <h2 class="pr-dash-header__title">Reports &amp; Analytics</h2>
        <p class="pr-dash-header__sub">Select a report to explore detailed data and insights.</p>
    </div>

    {{-- Report cards --}}
    <div class="pr-reports-grid">
        @foreach($reports as $key => $label)
            @php
                $slug    = Str::slug($key);
                $info    = $meta[$slug] ?? ($meta[$key] ?? $meta['default']);
                $isSoon  = str_contains(strtolower($label), 'coming soon');
            @endphp

            @if($isSoon)
                {{-- Coming soon --}}
                <div class="pr-report-card pr-report-card--soon {{ $info['theme'] }}">
                    <div class="pr-report-card__strip"></div>
                    <div class="pr-report-card__icon-wrap">
                        <div class="pr-report-card__icon">
                            <i class="bi {{ $info['icon'] }}"></i>
                        </div>
                        <span class="pr-soon-badge">
                            <i class="bi bi-clock"></i> Soon
                        </span>
                    </div>
                    <div class="pr-report-card__body">
                        <h3 class="pr-report-card__title">{{ $label }}</h3>
                        <p class="pr-report-card__desc">This report is currently under development.</p>
                        <div class="pr-report-card__footer">
                            <i class="bi bi-wrench"></i> Coming soon
                        </div>
                    </div>
                </div>

            @else
                {{-- Active report --}}
                <a href="{{ route('manager.reports.show', $key) }}"
                   class="pr-report-card {{ $info['theme'] }}"
                   aria-label="Open {{ $label }} report">
                    <div class="pr-report-card__strip"></div>
                    <div class="pr-report-card__icon-wrap">
                        <div class="pr-report-card__icon">
                            <i class="bi {{ $info['icon'] }}"></i>
                        </div>
                        <div class="pr-report-card__arrow">
                            <i class="bi bi-arrow-right"></i>
                        </div>
                    </div>
                    <div class="pr-report-card__body">
                        <h3 class="pr-report-card__title">{{ $label }}</h3>
                        <p class="pr-report-card__desc">{{ $info['desc'] }}</p>
                        <div class="pr-report-card__footer">
                            <i class="bi bi-arrow-right-circle"></i>
                            View Report
                        </div>
                    </div>
                </a>

            @endif
        @endforeach
    </div>

@endsection