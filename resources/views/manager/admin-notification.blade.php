@extends('layouts.managerdashboardlayout')

@section('title', 'Pinikitan Rental | Notifications')
@section('page-title', 'Notifications')

@push('styles')
<style>
    /* ===================== PAGE HEADER ===================== */
    .pr-dash-header { margin-bottom:24px; }
    .pr-dash-header__eyebrow { font-size:.72rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--pr-gold); margin-bottom:6px; }
    .pr-dash-header__title { font-family:var(--pr-font-display); font-size:1.5rem; font-weight:700; color:var(--pr-navy); margin:0 0 4px; line-height:1.2; }
    .pr-dash-header__sub { font-size:.85rem; color:var(--pr-gray-500); margin:0; }

    /* ===================== NOTIFICATION CARD ===================== */
    .pr-notif-card {
        background:var(--pr-white);
        border:1px solid rgba(12,26,58,.07);
        border-radius:16px;
        box-shadow:0 2px 12px rgba(12,26,58,.06);
        overflow:hidden;
    }
    .pr-notif-card__header {
        display:flex; align-items:center; justify-content:space-between;
        gap:12px; padding:16px 22px;
        border-bottom:1px solid rgba(12,26,58,.06);
    }
    .pr-notif-card__title {
        font-family:var(--pr-font-display); font-size:.98rem;
        font-weight:700; color:var(--pr-navy); margin:0;
    }
    .pr-notif-card__count {
        font-size:.72rem; font-weight:600; padding:3px 10px;
        border-radius:999px; background:rgba(201,168,76,.1);
        color:var(--pr-gold); border:1px solid rgba(201,168,76,.2);
    }
    .pr-notif-card__unread-badge {
        font-size:.72rem; font-weight:700; padding:3px 10px;
        border-radius:999px; background:rgba(239,68,68,.1);
        color:#dc2626; border:1px solid rgba(239,68,68,.2);
        display:inline-flex; align-items:center; gap:5px;
    }
    .pr-notif-card__unread-badge::before {
        content:''; width:6px; height:6px;
        border-radius:50%; background:#dc2626;
        animation:pulse 2s infinite;
    }
    @keyframes pulse {
        0%,100% { opacity:1; transform:scale(1); }
        50%      { opacity:.4; transform:scale(.65); }
    }

    /* ===================== NOTIFICATION LIST ===================== */
    .pr-notif-list { display:flex; flex-direction:column; }

    .pr-notif-item {
        display:flex; align-items:flex-start; gap:16px;
        padding:18px 22px;
        border-bottom:1px solid rgba(12,26,58,.04);
        transition:background .15s ease;
        position:relative;
        animation:fadeUp .4s ease both;
    }
    .pr-notif-item:last-child { border-bottom:0; }
    .pr-notif-item:hover { background:rgba(12,26,58,.015); }

    /* Unread indicator — left accent */
    .pr-notif-item--unread {
        background:rgba(201,168,76,.03);
    }
    .pr-notif-item--unread::before {
        content:'';
        position:absolute;
        left:0; top:0; bottom:0;
        width:3px;
        background:linear-gradient(to bottom, var(--pr-gold), var(--pr-gold-lt));
        border-radius:0 3px 3px 0;
    }

    @keyframes fadeUp {
        from { opacity:0; transform:translateY(8px); }
        to   { opacity:1; transform:translateY(0); }
    }

    /* Icon bubble */
    .pr-notif-icon {
        width:40px; height:40px; border-radius:12px;
        display:flex; align-items:center; justify-content:center;
        font-size:1rem; flex-shrink:0;
    }
    .pr-notif-icon--unread { background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid)); color:var(--pr-gold-lt); }
    .pr-notif-icon--read   { background:rgba(12,26,58,.06); color:var(--pr-gray-500); }

    /* Body */
    .pr-notif-body { flex:1; min-width:0; }
    .pr-notif-body__top {
        display:flex; align-items:flex-start;
        justify-content:space-between; gap:12px;
        margin-bottom:5px;
    }
    .pr-notif-title {
        font-weight:700; font-size:.9rem;
        color:var(--pr-navy); margin:0;
        line-height:1.3;
    }
    .pr-notif-item--read .pr-notif-title {
        font-weight:500; color:var(--pr-gray-500);
    }
    .pr-notif-time {
        font-size:.72rem; color:var(--pr-gray-500);
        white-space:nowrap; flex-shrink:0;
        display:flex; align-items:center; gap:4px;
        padding-top:2px;
    }
    .pr-notif-message {
        font-size:.84rem; color:var(--pr-gray-500);
        line-height:1.55; margin:0;
    }
    .pr-notif-item--unread .pr-notif-message {
        color:var(--pr-gray-800);
    }

    /* Unread dot */
    .pr-notif-dot {
        width:8px; height:8px; border-radius:50%;
        background:var(--pr-gold); flex-shrink:0;
        margin-top:6px;
        box-shadow:0 0 0 3px rgba(201,168,76,.2);
    }

    /* ===================== EMPTY STATE ===================== */
    .pr-empty {
        text-align:center; padding:64px 24px;
        color:var(--pr-gray-500);
    }
    .pr-empty__icon-wrap {
        width:72px; height:72px; border-radius:20px;
        background:rgba(12,26,58,.05);
        display:inline-flex; align-items:center; justify-content:center;
        font-size:1.8rem; margin-bottom:16px; opacity:.5;
    }
    .pr-empty__title {
        font-family:var(--pr-font-display); font-size:1.1rem;
        font-weight:700; color:var(--pr-navy); margin-bottom:6px;
    }
    .pr-empty__sub { font-size:.85rem; }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width:576px) {
        .pr-notif-item { padding:14px 16px; gap:12px; }
        .pr-notif-icon { width:34px; height:34px; font-size:.85rem; border-radius:10px; }
        .pr-notif-body__top { flex-direction:column; gap:3px; }
        .pr-notif-card__header { padding:14px 16px; }
    }
</style>
@endpush

@section('content')

    {{-- Page header --}}
    <div class="pr-dash-header">
        <div class="pr-dash-header__eyebrow">Manager Portal</div>
        <h2 class="pr-dash-header__title">Notifications</h2>
        <p class="pr-dash-header__sub">Stay updated on all tenant activity and system alerts.</p>
    </div>

    {{-- Notification card --}}
    <div class="pr-notif-card">

        <div class="pr-notif-card__header">
            <h3 class="pr-notif-card__title">
                <i class="bi bi-bell-fill me-2" style="color:var(--pr-gold);font-size:.9rem;"></i>
                All Notifications
            </h3>
            <div style="display:flex;align-items:center;gap:8px;">
                @php $unreadCount = $notifications->where('is_read', false)->count(); @endphp
                @if($unreadCount > 0)
                    <span class="pr-notif-card__unread-badge">{{ $unreadCount }} unread</span>
                @endif
                <span class="pr-notif-card__count">{{ $notifications->count() }} total</span>
            </div>
        </div>

        @if($notifications->isEmpty())
            <div class="pr-empty">
                <div class="pr-empty__icon-wrap">
                    <i class="bi bi-bell-slash"></i>
                </div>
                <div class="pr-empty__title">All caught up!</div>
                <div class="pr-empty__sub">You have no notifications at this time.</div>
            </div>
        @else
            <div class="pr-notif-list">
                @foreach($notifications as $i => $notification)
                    @php $isUnread = !$notification->is_read; @endphp
                    <div class="pr-notif-item {{ $isUnread ? 'pr-notif-item--unread' : 'pr-notif-item--read' }}"
                         style="animation-delay:{{ $i * 0.04 }}s;">

                        {{-- Icon bubble --}}
                        <div class="pr-notif-icon {{ $isUnread ? 'pr-notif-icon--unread' : 'pr-notif-icon--read' }}">
                            <i class="bi bi-bell{{ $isUnread ? '-fill' : '' }}"></i>
                        </div>

                        {{-- Content --}}
                        <div class="pr-notif-body">
                            <div class="pr-notif-body__top">
                                <h5 class="pr-notif-title">{{ $notification->title }}</h5>
                                <span class="pr-notif-time">
                                    <i class="bi bi-clock" style="font-size:.65rem;"></i>
                                    {{ $notification->created_at->diffForHumans() }}
                                </span>
                            </div>
                            <p class="pr-notif-message">{{ $notification->message }}</p>
                        </div>

                        {{-- Unread dot --}}
                        @if($isUnread)
                            <div class="pr-notif-dot"></div>
                        @endif

                    </div>
                @endforeach
            </div>
        @endif

    </div>

@endsection