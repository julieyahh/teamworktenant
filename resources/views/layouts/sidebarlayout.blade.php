<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Manager Dashboard')</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
            --pr-gray-100:    #f0f0f0;
            --pr-gray-500:    #7a7a7a;
            --pr-gray-800:    #222222;
            --pr-danger:      #ef4444;

            --pr-font-display: 'Playfair Display', Georgia, serif;
            --pr-font-body:    'DM Sans', system-ui, sans-serif;
            --pr-sidebar-w:    260px;
            --pr-transition:   0.3s cubic-bezier(.4,0,.2,1);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: var(--pr-font-body);
            background: var(--pr-cream);
            color: var(--pr-gray-800);
            margin: 0;
            overflow-x: hidden;
        }

        /* ===================== SIDEBAR ===================== */
        .pr-sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--pr-sidebar-w);
            height: 100vh;
            background: var(--pr-navy);
            display: flex;
            flex-direction: column;
            z-index: 1050;
            transition: transform var(--pr-transition);
            overflow: hidden;
        }

        /* Decorative gold grid overlay */
        .pr-sidebar::before {
            content: '';
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(201,168,76,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(201,168,76,.04) 1px, transparent 1px);
            background-size: 28px 28px;
            pointer-events: none;
        }

        /* Gold orb accent top-right */
        .pr-sidebar::after {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201,168,76,.14) 0%, transparent 70%);
            filter: blur(30px);
            pointer-events: none;
        }

        /* ---- Brand area ---- */
        .pr-sidebar__brand {
            position: relative; z-index: 1;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 28px 22px 20px;
            border-bottom: 1px solid rgba(201,168,76,.12);
        }
        .pr-sidebar__logo-mark {
            width: 40px; height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--pr-gold), var(--pr-gold-lt));
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(201,168,76,.3);
        }
        .pr-sidebar__brand-text {
            font-family: var(--pr-font-display);
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--pr-white);
            line-height: 1.2;
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
            padding: 18px 22px;
            border-bottom: 1px solid rgba(255,255,255,.05);
        }
        .pr-sidebar__avatar {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--pr-gold), var(--pr-gold-lt));
            display: flex; align-items: center; justify-content: center;
            color: var(--pr-navy);
            font-weight: 700;
            font-size: .9rem;
            flex-shrink: 0;
            box-shadow: 0 0 0 2px rgba(201,168,76,.3);
        }
        .pr-sidebar__user-info { min-width: 0; }
        .pr-sidebar__user-name {
            font-size: .88rem;
            font-weight: 600;
            color: var(--pr-white);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pr-sidebar__user-role {
            font-size: .72rem;
            color: var(--pr-gold);
            font-weight: 500;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        /* ---- Nav section label ---- */
        .pr-nav-label {
            position: relative; z-index: 1;
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: rgba(255,255,255,.25);
            padding: 18px 22px 8px;
        }

        /* ---- Nav links ---- */
        .pr-sidebar__nav {
            position: relative; z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 8px 12px;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: rgba(201,168,76,.2) transparent;
        }
        .pr-sidebar__nav::-webkit-scrollbar { width: 4px; }
        .pr-sidebar__nav::-webkit-scrollbar-thumb {
            background: rgba(201,168,76,.2);
            border-radius: 4px;
        }

        .pr-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border-radius: 10px;
            color: rgba(255,255,255,.6);
            font-size: .88rem;
            font-weight: 500;
            text-decoration: none;
            transition: var(--pr-transition);
            position: relative;
            margin-bottom: 2px;
            white-space: nowrap;
        }
        .pr-nav-item__icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
            background: rgba(255,255,255,.05);
            transition: var(--pr-transition);
        }
        .pr-nav-item:hover {
            color: var(--pr-white);
            background: rgba(255,255,255,.07);
        }
        .pr-nav-item:hover .pr-nav-item__icon {
            background: rgba(201,168,76,.12);
            color: var(--pr-gold-lt);
        }
        .pr-nav-item.active {
            color: var(--pr-navy);
            background: linear-gradient(135deg, var(--pr-gold), var(--pr-gold-lt));
            font-weight: 600;
            box-shadow: 0 4px 16px rgba(201,168,76,.25);
        }
        .pr-nav-item.active .pr-nav-item__icon {
            background: rgba(12,26,58,.15);
            color: var(--pr-navy);
        }

        /* Active left indicator */
        .pr-nav-item.active::before {
            content: '';
            position: absolute;
            left: -12px; top: 50%;
            transform: translateY(-50%);
            width: 3px; height: 60%;
            background: var(--pr-gold-lt);
            border-radius: 0 3px 3px 0;
        }

        /* Notification badge */
        .pr-nav-badge {
            margin-left: auto;
            background: var(--pr-danger);
            color: #fff;
            font-size: .65rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 999px;
            min-width: 20px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(239,68,68,.4);
        }
        .pr-nav-item.active .pr-nav-badge {
            background: rgba(12,26,58,.25);
            color: var(--pr-navy);
            box-shadow: none;
        }

        /* ---- Divider ---- */
        .pr-sidebar__divider {
            position: relative; z-index: 1;
            height: 1px;
            background: rgba(255,255,255,.06);
            margin: 8px 12px;
        }

        /* ---- Footer / Logout ---- */
        .pr-sidebar__footer {
            position: relative; z-index: 1;
            padding: 16px 12px 24px;
            border-top: 1px solid rgba(201,168,76,.1);
        }
        .pr-logout-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 11px;
            border: 1.5px solid rgba(239,68,68,.35);
            border-radius: 10px;
            background: rgba(239,68,68,.06);
            color: rgba(239,68,68,.85);
            font-family: var(--pr-font-body);
            font-size: .88rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--pr-transition);
        }
        .pr-logout-btn:hover {
            background: var(--pr-danger);
            border-color: var(--pr-danger);
            color: #fff;
            box-shadow: 0 4px 14px rgba(239,68,68,.3);
        }

        /* ===================== MAIN CONTENT ===================== */
        .pr-main {
            margin-left: var(--pr-sidebar-w);
            min-height: 100vh;
            transition: margin var(--pr-transition);
        }

        /* ===================== TOP BAR ===================== */
        .pr-topbar {
            position: sticky;
            top: 0; z-index: 100;
            background: rgba(250,248,243,.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(12,26,58,.07);
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .pr-topbar__title {
            font-family: var(--pr-font-display);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--pr-navy);
            margin: 0;
        }
        .pr-topbar__right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .pr-topbar__user {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--pr-white);
            border: 1px solid rgba(12,26,58,.08);
            border-radius: 999px;
            padding: 6px 16px 6px 6px;
            font-size: .85rem;
            font-weight: 500;
            color: var(--pr-navy);
        }
        .pr-topbar__avatar {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--pr-navy), var(--pr-navy-mid));
            display: flex; align-items: center; justify-content: center;
            color: var(--pr-gold);
            font-weight: 700;
            font-size: .72rem;
        }

        /* ===================== CONTENT AREA ===================== */
        .pr-content {
            padding: 28px;
        }

        /* ===================== MOBILE TOGGLE ===================== */
        .pr-toggle {
            display: none;
            position: fixed;
            top: 14px; left: 14px;
            z-index: 1100;
            width: 42px; height: 42px;
            background: var(--pr-navy);
            border: 1px solid rgba(201,168,76,.25);
            border-radius: 10px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--pr-white);
            font-size: 1.2rem;
            box-shadow: 0 4px 12px rgba(12,26,58,.3);
            transition: var(--pr-transition);
        }
        .pr-toggle:hover { background: var(--pr-navy-light); }

        /* ===================== OVERLAY ===================== */
        .pr-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(12,26,58,.55);
            backdrop-filter: blur(3px);
            z-index: 1040;
        }
        .pr-overlay.show { display: block; }

        /* ===================== RESPONSIVE ===================== */
        @media (max-width: 992px) {
            .pr-sidebar {
                transform: translateX(-100%);
            }
            .pr-sidebar.open {
                transform: translateX(0);
            }
            .pr-main {
                margin-left: 0;
            }
            .pr-toggle {
                display: flex;
            }
            .pr-topbar {
                padding-left: 70px; /* room for toggle btn */
            }
        }

        @media (max-width: 576px) {
            .pr-content { padding: 16px; }
            .pr-topbar { padding: 12px 16px 12px 70px; }
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

    <!-- Mobile Toggle -->
    <button class="pr-toggle" id="prToggle" aria-label="Open sidebar">
        <i class="bi bi-list"></i>
    </button>

    <!-- Overlay -->
    <div class="pr-overlay" id="prOverlay"></div>

    <!-- ===================== SIDEBAR ===================== -->
    <nav class="pr-sidebar" id="prSidebar">

        <!-- Brand -->
        <div class="pr-sidebar__brand">
            <div class="pr-sidebar__logo-mark">🏠</div>
            <div class="pr-sidebar__brand-text">
                Pinikitan<em>Rental</em>
            </div>
        </div>

        <!-- User chip -->
        <div class="pr-sidebar__user">
            <div class="pr-sidebar__avatar">M</div>
            <div class="pr-sidebar__user-info">
                <div class="pr-sidebar__user-name">Manager</div>
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
               class="pr-nav-item {{ request()->routeIs('manager.reports') ? 'active' : '' }}">
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
            <div class="pr-nav-label" style="padding-top:8px;">Account</div>

            <a href="{{ route('tenant.notifications') }}"
               class="pr-nav-item {{ request()->routeIs('tenant.notifications') ? 'active' : '' }}">
                <div class="pr-nav-item__icon"><i class="bi bi-bell-fill"></i></div>
                Notifications
                @if($hasUnreadNotifications)
                    <span class="pr-nav-badge">{{ $unreadNotificationCount }}</span>
                @endif
            </a>

        </div>

        <!-- Footer / Logout -->
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

    <!-- ===================== MAIN CONTENT ===================== -->
    <div class="pr-main" id="prMain">

        <!-- Top Bar -->
        <div class="pr-topbar">
            <h1 class="pr-topbar__title">@yield('page-title', 'Dashboard')</h1>
            <div class="pr-topbar__right">
                <div class="pr-topbar__user">
                    <div class="pr-topbar__avatar">M</div>
                    Manager
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="pr-content">
            @yield('content')
        </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const prToggle  = document.getElementById('prToggle');
        const prSidebar = document.getElementById('prSidebar');
        const prOverlay = document.getElementById('prOverlay');

        function openSidebar() {
            prSidebar.classList.add('open');
            prOverlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        function closeSidebar() {
            prSidebar.classList.remove('open');
            prOverlay.classList.remove('show');
            document.body.style.overflow = '';
        }

        prToggle.addEventListener('click', openSidebar);
        prOverlay.addEventListener('click', closeSidebar);

        // Close on nav link click (mobile)
        document.querySelectorAll('.pr-nav-item').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) closeSidebar();
            });
        });
    </script>

    @stack('scripts')
</body>
</html>