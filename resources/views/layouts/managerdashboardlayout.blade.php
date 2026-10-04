{{-- resources/views/layouts/managerdashboardlayout.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <title>@yield('title', 'Manager Dashboard')</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        /* ===================== TOKENS ===================== */
        :root {
            --pr-navy:        #0c1a3a;
            --pr-navy-mid:    #132044;
            --pr-navy-light:  #1a2d55;
            --pr-gold:        #c9a84c;
            --pr-gold-lt:     #e2c580;
            --pr-cream:       #faf8f3;
            --pr-white:       #ffffff;
            --pr-gray-50:     #f8f9fa;
            --pr-gray-100:    #f0f0f0;
            --pr-gray-200:    #e5e5e5;
            --pr-gray-500:    #7a7a7a;
            --pr-gray-800:    #222222;
            --pr-danger:      #ef4444;
            --pr-success:     #22c55e;

            --pr-font-display: 'Playfair Display', Georgia, serif;
            --pr-font-body:    'DM Sans', system-ui, sans-serif;
            --pr-sidebar-w:    260px;
            --pr-topbar-h:     66px;
            --pr-transition:   0.3s cubic-bezier(.4,0,.2,1);
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            height: 100%;
            margin: 0;
        }

        body {
            font-family: var(--pr-font-body);
            background: var(--pr-cream);
            color: var(--pr-gray-800);
            overflow-x: hidden;
        }

        a { text-decoration: none; }

        /* ===================== LAYOUT SHELL ===================== */
        /* Full-height flex row: sidebar | main column */
        #dashboardWrapper {
            display: flex;
            flex-direction: row;
            min-height: 100vh;
        }

        /* ===================== SIDEBAR ===================== */
        .pr-sidebar {
            flex-shrink: 0;
            width: var(--pr-sidebar-w);
            height: 100vh;
            position: sticky;         /* sticks while scrolling main content */
            top: 0;
            background: var(--pr-navy);
            display: flex;
            flex-direction: column;
            z-index: 1050;
            transition: transform var(--pr-transition);
            overflow: hidden;
        }

        /* Gold grid texture */
        .pr-sidebar::before {
            content: '';
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(201,168,76,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(201,168,76,.04) 1px, transparent 1px);
            background-size: 28px 28px;
            pointer-events: none;
        }

        /* Gold orb glow */
        .pr-sidebar::after {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201,168,76,.15) 0%, transparent 70%);
            filter: blur(32px);
            pointer-events: none;
        }

        /* ---- Brand ---- */
        .pr-sidebar__brand {
            position: relative; z-index: 1;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 24px 20px;
            border-bottom: 1px solid rgba(201,168,76,.12);
            flex-shrink: 0;
        }
        .pr-sidebar__logo-mark {
            width: 38px; height: 38px;
            border-radius: 11px;
            background: linear-gradient(135deg, var(--pr-gold), var(--pr-gold-lt));
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(201,168,76,.3);
        }
        .pr-sidebar__brand-text {
            font-family: var(--pr-font-display);
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--pr-white);
            line-height: 1.2;
            white-space: nowrap;
        }
        .pr-sidebar__brand-text em {
            font-style: italic;
            color: var(--pr-gold);
        }

        /* ---- User chip ---- */
        .pr-sidebar__user {
            position: relative; z-index: 1;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,.05);
            flex-shrink: 0;
        }
        .pr-sidebar__avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--pr-gold), var(--pr-gold-lt));
            display: flex; align-items: center; justify-content: center;
            color: var(--pr-navy);
            font-weight: 700;
            font-size: .85rem;
            flex-shrink: 0;
            box-shadow: 0 0 0 2px rgba(201,168,76,.3);
        }
        .pr-sidebar__user-info { min-width: 0; }
        .pr-sidebar__user-name {
            font-size: .85rem;
            font-weight: 600;
            color: var(--pr-white);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pr-sidebar__user-role {
            font-size: .68rem;
            color: var(--pr-gold);
            font-weight: 500;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        /* ---- Nav section label ---- */
        .pr-nav-label {
            position: relative; z-index: 1;
            font-size: .62rem;
            font-weight: 700;
            letter-spacing: .15em;
            text-transform: uppercase;
            color: rgba(255,255,255,.22);
            padding: 16px 14px 6px;
        }

        /* ---- Nav scroll area ---- */
        .pr-sidebar__nav {
            position: relative; z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 6px 10px;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: rgba(201,168,76,.18) transparent;
        }
        .pr-sidebar__nav::-webkit-scrollbar { width: 3px; }
        .pr-sidebar__nav::-webkit-scrollbar-thumb {
            background: rgba(201,168,76,.18);
            border-radius: 3px;
        }

        /* ---- Nav items ---- */
        .pr-nav-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 13px;
            border-radius: 10px;
            color: rgba(255,255,255,.58);
            font-size: .86rem;
            font-weight: 500;
            text-decoration: none;
            transition: var(--pr-transition);
            position: relative;
            margin-bottom: 2px;
            white-space: nowrap;
        }
        .pr-nav-item__icon {
            width: 30px; height: 30px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem;
            flex-shrink: 0;
            background: rgba(255,255,255,.05);
            transition: var(--pr-transition);
        }
        .pr-nav-item:hover {
            color: var(--pr-white);
            background: rgba(255,255,255,.07);
        }
        .pr-nav-item:hover .pr-nav-item__icon {
            background: rgba(201,168,76,.13);
            color: var(--pr-gold-lt);
        }
        .pr-nav-item.active {
            color: var(--pr-navy);
            background: linear-gradient(135deg, var(--pr-gold), var(--pr-gold-lt));
            font-weight: 600;
            box-shadow: 0 4px 14px rgba(201,168,76,.22);
        }
        .pr-nav-item.active .pr-nav-item__icon {
            background: rgba(12,26,58,.15);
            color: var(--pr-navy);
        }
        .pr-nav-item.active::before {
            content: '';
            position: absolute;
            left: -10px; top: 50%;
            transform: translateY(-50%);
            width: 3px; height: 55%;
            background: var(--pr-gold-lt);
            border-radius: 0 3px 3px 0;
        }

        /* ---- Badge ---- */
        .pr-nav-badge {
            margin-left: auto;
            background: var(--pr-danger);
            color: #fff;
            font-size: .62rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 999px;
            min-width: 18px;
            height: 17px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(239,68,68,.35);
        }
        .pr-nav-item.active .pr-nav-badge {
            background: rgba(12,26,58,.2);
            color: var(--pr-navy);
            box-shadow: none;
        }

        /* ---- Divider ---- */
        .pr-sidebar__divider {
            position: relative; z-index: 1;
            height: 1px;
            background: rgba(255,255,255,.06);
            margin: 6px 10px;
        }

        /* ---- Logout ---- */
        .pr-sidebar__footer {
            position: relative; z-index: 1;
            padding: 14px 10px 20px;
            border-top: 1px solid rgba(201,168,76,.1);
            flex-shrink: 0;
        }
        .pr-logout-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 10px;
            border: 1.5px solid rgba(239,68,68,.3);
            border-radius: 10px;
            background: rgba(239,68,68,.06);
            color: rgba(239,68,68,.82);
            font-family: var(--pr-font-body);
            font-size: .86rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--pr-transition);
        }
        .pr-logout-btn:hover {
            background: var(--pr-danger);
            border-color: var(--pr-danger);
            color: #fff;
            box-shadow: 0 4px 14px rgba(239,68,68,.28);
        }

        /* ===================== MAIN COLUMN ===================== */
        #mainContent {
            flex: 1;
            min-width: 0;            /* prevents flex overflow */
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ---- Top bar ---- */
        .pr-topbar {
            position: sticky;
            top: 0; z-index: 100;
            height: var(--pr-topbar-h);
            background: rgba(250,248,243,.94);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(12,26,58,.07);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 0 28px;
            flex-shrink: 0;
        }
        .pr-topbar__left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }
        .pr-topbar__title {
            font-family: var(--pr-font-display);
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--pr-navy);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pr-topbar__right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        /* Topbar user pill */
        .pr-topbar__user {
            display: flex;
            align-items: center;
            gap: 9px;
            background: var(--pr-white);
            border: 1px solid rgba(12,26,58,.08);
            border-radius: 999px;
            padding: 5px 14px 5px 5px;
            font-size: .83rem;
            font-weight: 500;
            color: var(--pr-navy);
            box-shadow: 0 1px 4px rgba(12,26,58,.06);
        }
        .pr-topbar__avatar {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--pr-navy), var(--pr-navy-mid));
            display: flex; align-items: center; justify-content: center;
            color: var(--pr-gold);
            font-weight: 700;
            font-size: .7rem;
        }

        /* Mobile hamburger in topbar */
        .pr-topbar__toggle {
            display: none;
            width: 36px; height: 36px;
            border-radius: 9px;
            background: transparent;
            border: 1.5px solid rgba(12,26,58,.12);
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--pr-navy);
            font-size: 1.1rem;
            transition: var(--pr-transition);
            flex-shrink: 0;
        }
        .pr-topbar__toggle:hover {
            background: rgba(12,26,58,.05);
            border-color: rgba(12,26,58,.2);
        }

        /* ---- Page content area ---- */
        .pr-content {
            flex: 1;
            padding: 28px;
        }

        /* ===================== OVERLAY ===================== */
        .pr-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(12,26,58,.52);
            backdrop-filter: blur(3px);
            z-index: 1040;
        }
        .pr-overlay.show { display: block; }

        /* ===================== RESPONSIVE ===================== */
        @media (max-width: 992px) {
            /* Sidebar slides off-screen, floats over content */
            .pr-sidebar {
                position: fixed;
                top: 0; left: 0;
                height: 100vh;
                transform: translateX(-100%);
            }
            .pr-sidebar.open {
                transform: translateX(0);
                box-shadow: 8px 0 32px rgba(12,26,58,.3);
            }

            /* Main fills full width */
            #mainContent { width: 100%; }

            /* Show hamburger in topbar */
            .pr-topbar__toggle { display: flex; }
            .pr-topbar { padding: 0 20px; }
        }

        @media (max-width: 576px) {
            .pr-content { padding: 16px; }
            .pr-topbar__title { font-size: 1.05rem; }
        }
    </style>

    @stack('styles')
</head>
<body>

    @php
        use App\Models\Notification;
        use App\Models\Lease;
        $unreadNotificationCount = Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();
        $hasUnreadNotifications = $unreadNotificationCount > 0;
        $pendingLeases = Lease::with('tenant', 'unit')
            ->where('lea_status', 'pending')
            ->whereHas('unit', function ($q) {
                $q->where('status', '!=', 'occupied');
            })
            ->get();
    @endphp

    <!-- Overlay (mobile) -->
    <div class="pr-overlay" id="prOverlay"></div>

    <!-- ==================== WRAPPER ==================== -->
    <div id="dashboardWrapper">

        <!-- ============ SIDEBAR ============ -->
        <nav class="pr-sidebar" id="prSidebar">

            <!-- Brand -->
            <div class="pr-sidebar__brand">
                <div class="pr-sidebar__logo-mark">🏠</div>
                <div class="pr-sidebar__brand-text">Pinikitan<em>Rental</em></div>
            </div>

            <!-- User -->
            <div class="pr-sidebar__user">
                <div class="pr-sidebar__avatar">
                    {{ strtoupper(substr(auth()->user()->first_name ?? auth()->user()->name ?? 'M', 0, 1)) }}
                </div>
                <div class="pr-sidebar__user-info">
                    <div class="pr-sidebar__user-name">
                        {{ auth()->user()->first_name ?? auth()->user()->name ?? 'Manager' }}
                    </div>
                    <div class="pr-sidebar__user-role">Administrator</div>
                </div>
            </div>

            <!-- Nav -->
            <div class="pr-sidebar__nav">

                <div class="pr-nav-label">Main Menu</div>

                <a href="{{ route('manager.dashboard') }}"
                   class="pr-nav-item {{ request()->routeIs('manager.dashboard') ? 'active' : '' }}">
                    <div class="pr-nav-item__icon"><i class="bi bi-house-fill"></i></div>
                    Dashboard
                </a>

                <a href="{{ route('manager.reports') }}"
                   class="pr-nav-item {{ request()->routeIs('manager.reports*') ? 'active' : '' }}">
                    <div class="pr-nav-item__icon"><i class="bi bi-bar-chart-fill"></i></div>
                    Reports
                </a>

                <a href="{{ route('manager.tenants') }}"
                   class="pr-nav-item {{ request()->routeIs('manager.tenants') ? 'active' : '' }}">
                    <div class="pr-nav-item__icon"><i class="bi bi-people-fill"></i></div>
                    Tenants
                </a>

                <a href="{{ route('manager.units.index') }}"
                   class="pr-nav-item {{ request()->routeIs('manager.units.*') ? 'active' : '' }}">
                    <div class="pr-nav-item__icon"><i class="bi bi-building-fill-add"></i></div>
                    Units
                    @if($pendingLeases->count())
                        <span class="pr-nav-badge">{{ $pendingLeases->count() }}</span>
                    @endif
                </a>

                <a href="{{ route('manager.utilities.index') }}"
                   class="pr-nav-item {{ request()->routeIs('manager.utilities.*') ? 'active' : '' }}">
                    <div class="pr-nav-item__icon"><i class="bi bi-lightning-charge-fill"></i></div>
                    Utilities
                </a>

                <a href="{{ route('manager.bills.index') }}"
                   class="pr-nav-item {{ request()->routeIs('manager.bills.*') ? 'active' : '' }}">
                    <div class="pr-nav-item__icon"><i class="bi bi-receipt"></i></div>
                    Bills
                </a>

                <a href="{{ route('manager.collections.index') }}"
                   class="pr-nav-item {{ request()->routeIs('manager.collections.*') ? 'active' : '' }}">
                    <div class="pr-nav-item__icon"><i class="bi bi-exclamation-diamond-fill"></i></div>
                    Collections
                </a>

                <div class="pr-sidebar__divider"></div>
                <div class="pr-nav-label" style="padding-top: 8px;">Account</div>

                <a href="{{ route('tenant.notifications') }}"
                   class="pr-nav-item {{ request()->routeIs('tenant.notifications') ? 'active' : '' }}">
                    <div class="pr-nav-item__icon"><i class="bi bi-bell-fill"></i></div>
                    Notifications
                    @if($hasUnreadNotifications)
                        <span class="pr-nav-badge">{{ $unreadNotificationCount }}</span>
                    @endif
                </a>

            </div>

            <!-- Logout -->
            <div class="pr-sidebar__footer">
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="pr-logout-btn">
                        <i class="bi bi-box-arrow-right"></i>
                        Logout
                    </button>
                </form>
            </div>

        </nav>
        <!-- ============ END SIDEBAR ============ -->

        <!-- ============ MAIN CONTENT ============ -->
        <div id="mainContent">

            <!-- Top Bar -->
            <div class="pr-topbar">
                <div class="pr-topbar__left">
                    <!-- Mobile hamburger lives in topbar -->
                    <button class="pr-topbar__toggle" id="prToggle" aria-label="Toggle sidebar">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="pr-topbar__title">@yield('page-title', 'Dashboard')</h1>
                </div>

                <div class="pr-topbar__right">
                    <div class="pr-topbar__user">
                        <div class="pr-topbar__avatar">
                            {{ strtoupper(substr(auth()->user()->first_name ?? auth()->user()->name ?? 'M', 0, 1)) }}
                        </div>
                        {{ auth()->user()->first_name ?? auth()->user()->name ?? 'Manager' }}
                    </div>
                </div>
            </div>

            <!-- Page Content -->
            <div class="pr-content">
                @yield('content')
            </div>

        </div>
        <!-- ============ END MAIN CONTENT ============ -->

    </div>
    <!-- ==================== END WRAPPER ==================== -->

    <!-- Page Modals (yielded from child views) -->
    @yield('modals')
    @stack('modals')

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        (function () {
            const toggle  = document.getElementById('prToggle');
            const sidebar = document.getElementById('prSidebar');
            const overlay = document.getElementById('prOverlay');

            function openSidebar() {
                sidebar.classList.add('open');
                overlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }

            toggle.addEventListener('click', openSidebar);
            overlay.addEventListener('click', closeSidebar);

            // Auto-close on mobile nav click
            sidebar.querySelectorAll('.pr-nav-item').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 992) closeSidebar();
                });
            });
        })();
    </script>

    @stack('scripts')
    {{-- Global double-submit guard --}}
    @include('partials.single-submit')
</body>
</html>