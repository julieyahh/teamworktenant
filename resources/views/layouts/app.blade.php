<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <title>@yield('title', 'Apartment Portal')</title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        /* ===================== TOKENS ===================== */
        :root {
            --pr-navy:      #0c1a3a;
            --pr-navy-mid:  #132044;
            --pr-gold:      #c9a84c;
            --pr-gold-lt:   #e2c580;
            --pr-cream:     #faf8f3;
            --pr-white:     #ffffff;
            --pr-gray-100:  #f0f0f0;
            --pr-gray-300:  #d0d0d0;
            --pr-gray-500:  #7a7a7a;
            --pr-gray-800:  #222222;

            --pr-font-display: 'Playfair Display', Georgia, serif;
            --pr-font-body:    'DM Sans', system-ui, sans-serif;
            --pr-transition:   0.3s cubic-bezier(.4,0,.2,1);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: var(--pr-font-body);
            background: var(--pr-cream);
            color: var(--pr-gray-800);
            margin: 0;
        }

        a { text-decoration: none; transition: var(--pr-transition); }

        /* ===================== HEADER ===================== */
        .pr-header {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            transition: background var(--pr-transition), box-shadow var(--pr-transition), padding var(--pr-transition);
            padding: 0;
        }

        /* Transparent on hero, solid when scrolled */
        .pr-header--transparent {
            background: transparent;
        }
        .pr-header--solid {
            background: rgba(12, 26, 58, 0.97);
            backdrop-filter: blur(16px);
            box-shadow: 0 2px 24px rgba(12,26,58,.25);
        }

        .pr-header__inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 18px 0;
            transition: padding var(--pr-transition);
        }

        .pr-header--solid .pr-header__inner {
            padding: 14px 0;
        }

        /* Logo */
        .pr-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--pr-white) !important;
            font-family: var(--pr-font-display);
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -.01em;
            flex-shrink: 0;
        }
        .pr-logo__mark {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--pr-gold), var(--pr-gold-lt));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem;
            flex-shrink: 0;
        }
        .pr-logo__text em {
            font-style: italic;
            color: var(--pr-gold);
        }

        /* Nav links (desktop) */
        .pr-nav {
            display: flex;
            align-items: center;
            gap: 6px;
            list-style: none;
            margin: 0; padding: 0;
        }
        .pr-nav__link {
            color: rgba(255,255,255,.7);
            font-size: .88rem;
            font-weight: 500;
            padding: 8px 14px;
            border-radius: 8px;
            transition: var(--pr-transition);
            white-space: nowrap;
        }
        .pr-nav__link:hover {
            color: var(--pr-white);
            background: rgba(255,255,255,.08);
        }
        .pr-nav__link.active {
            color: var(--pr-gold-lt);
        }

        /* Auth buttons */
        .pr-header__actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .pr-btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 22px;
            border-radius: 999px;
            font-family: var(--pr-font-body);
            font-weight: 600;
            font-size: .85rem;
            cursor: pointer;
            border: none;
            transition: var(--pr-transition);
            white-space: nowrap;
        }
        .pr-btn-nav--ghost {
            background: transparent;
            color: rgba(255,255,255,.75);
            border: 1.5px solid rgba(255,255,255,.25);
        }
        .pr-btn-nav--ghost:hover {
            color: var(--pr-white);
            border-color: rgba(255,255,255,.6);
            background: rgba(255,255,255,.07);
        }
        .pr-btn-nav--primary {
            background: linear-gradient(135deg, var(--pr-gold), var(--pr-gold-lt));
            color: var(--pr-navy);
        }
        .pr-btn-nav--primary:hover {
            color: var(--pr-navy);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(201,168,76,.35);
        }

        /* User chip (logged in) */
        .pr-user-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 999px;
            padding: 6px 18px 6px 8px;
            color: var(--pr-white);
            font-size: .85rem;
            font-weight: 500;
            transition: var(--pr-transition);
            cursor: default;
        }
        .pr-user-chip:hover {
            background: rgba(255,255,255,.12);
            color: var(--pr-white);
        }
        .pr-user-avatar {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--pr-gold), var(--pr-gold-lt));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--pr-navy);
            font-weight: 700;
            font-size: .75rem;
            flex-shrink: 0;
        }

        /* Mobile hamburger */
        .pr-hamburger {
            display: none;
            flex-direction: column;
            justify-content: center;
            gap: 5px;
            width: 38px; height: 38px;
            background: rgba(255,255,255,.08);
            border: 1.5px solid rgba(255,255,255,.2);
            border-radius: 10px;
            cursor: pointer;
            padding: 8px;
            transition: var(--pr-transition);
        }
        .pr-hamburger:hover { background: rgba(255,255,255,.14); }
        .pr-hamburger span {
            display: block;
            width: 100%; height: 1.5px;
            background: var(--pr-white);
            border-radius: 2px;
            transition: var(--pr-transition);
        }
        .pr-hamburger.open span:nth-child(1) { transform: translateY(6.5px) rotate(45deg); }
        .pr-hamburger.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
        .pr-hamburger.open span:nth-child(3) { transform: translateY(-6.5px) rotate(-45deg); }

        /* Mobile drawer */
        .pr-mobile-menu {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 999;
            pointer-events: none;
        }
        .pr-mobile-menu.open { display: block; pointer-events: all; }

        .pr-mobile-menu__backdrop {
            position: absolute; inset: 0;
            background: rgba(12,26,58,.7);
            backdrop-filter: blur(4px);
            opacity: 0;
            transition: opacity .3s ease;
        }
        .pr-mobile-menu.open .pr-mobile-menu__backdrop { opacity: 1; }

        .pr-mobile-menu__drawer {
            position: absolute;
            top: 0; right: 0;
            width: min(320px, 85vw);
            height: 100%;
            background: var(--pr-navy);
            border-left: 1px solid rgba(201,168,76,.15);
            padding: 28px 24px;
            transform: translateX(100%);
            transition: transform .35s cubic-bezier(.4,0,.2,1);
            overflow-y: auto;
        }
        .pr-mobile-menu.open .pr-mobile-menu__drawer { transform: translateX(0); }

        .pr-mobile-menu__top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 36px;
        }
        .pr-mobile-menu__close {
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 8px;
            width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            color: rgba(255,255,255,.7);
            cursor: pointer;
            transition: var(--pr-transition);
        }
        .pr-mobile-menu__close:hover { background: rgba(255,255,255,.15); color: var(--pr-white); }

        .pr-mobile-nav { list-style: none; margin: 0 0 32px; padding: 0; }
        .pr-mobile-nav li { border-bottom: 1px solid rgba(255,255,255,.06); }
        .pr-mobile-nav a {
            display: block;
            padding: 14px 4px;
            color: rgba(255,255,255,.75);
            font-size: .95rem;
            font-weight: 500;
            transition: var(--pr-transition);
        }
        .pr-mobile-nav a:hover { color: var(--pr-gold-lt); padding-left: 8px; }

        .pr-mobile-actions { display: flex; flex-direction: column; gap: 10px; }
        .pr-mobile-actions .pr-btn-nav {
            width: 100%;
            justify-content: center;
            padding: 13px;
            border-radius: 12px;
            font-size: .92rem;
        }

        /* ===================== MAIN ===================== */
        main {
            /* push content below fixed header only on non-hero pages */
        }

        /* ===================== FOOTER ===================== */
        .pr-footer {
            background: var(--pr-navy);
            color: rgba(255,255,255,.6);
            font-size: .85rem;
        }
        .pr-footer__inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            padding: 32px 0;
            border-top: 1px solid rgba(201,168,76,.12);
        }
        .pr-footer__brand {
            font-family: var(--pr-font-display);
            color: var(--pr-white);
            font-size: 1.1rem;
            font-weight: 700;
        }
        .pr-footer__brand em { color: var(--pr-gold); font-style: italic; }

        .pr-footer__links {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .pr-footer__links a {
            color: rgba(255,255,255,.5);
            font-size: .82rem;
            transition: color .2s;
        }
        .pr-footer__links a:hover { color: var(--pr-gold-lt); }

        .pr-footer__copy {
            color: rgba(255,255,255,.35);
            font-size: .78rem;
        }

        /* ===================== RESPONSIVE ===================== */
        @media (max-width: 767px) {
            .pr-nav,
            .pr-header__actions { display: none !important; }
            .pr-hamburger { display: flex; }
            .pr-footer__inner { flex-direction: column; text-align: center; }
            .pr-footer__links { justify-content: center; }
        }
    </style>

    @stack('styles')
</head>
<body class="d-flex flex-column min-vh-100">

    <!-- ===================== HEADER ===================== -->
    <header class="pr-header pr-header--transparent" id="prHeader">
        <div class="container">
            <div class="pr-header__inner">

                <!-- Logo -->
                <a href="{{ url('/') }}" class="pr-logo">
                    <div class="pr-logo__mark">🏠</div>
                    <span class="pr-logo__text">Pinikitan<em>Rental</em></span>
                </a>

                <!-- Desktop Nav -->
                <ul class="pr-nav">
                    <li><a href="{{ url('/') }}#units"   class="pr-nav__link">Rooms</a></li>
                    <li><a href="{{ url('/') }}#about"   class="pr-nav__link">About</a></li>
                    <li><a href="{{ url('/') }}#terms-info" class="pr-nav__link">Lease Terms</a></li>
                    <li><a href="{{ url('/') }}#contact" class="pr-nav__link">Contact</a></li>
                </ul>

                <!-- Desktop Auth Actions -->
                @if(Route::has('login'))
                <div class="pr-header__actions">
                    @auth
                        @php $user = auth()->user(); @endphp
                        <span class="pr-user-chip">
                            <span class="pr-user-avatar">
                                {{ strtoupper(substr($user->first_name ?? $user->name ?? 'U', 0, 1)) }}
                            </span>
                            {{ $user->first_name ?? $user->name }}
                        </span>
                        @if($user->role === 'manager')
                            <a href="{{ route('manager.dashboard') }}" class="pr-btn-nav pr-btn-nav--primary">Dashboard</a>
                        @else
                            <a href="{{ route('tenant.home') }}" class="pr-btn-nav pr-btn-nav--primary">Dashboard</a>
                        @endif
                    @else
                        <a href="{{ route('login') }}"    class="pr-btn-nav pr-btn-nav--ghost">Log in</a>
                        @if(Route::has('register'))
                            {{-- <a href="{{ route('register') }}" class="pr-btn-nav pr-btn-nav--primary">Register</a> --}}
                            <a href="{{ url('/') }}#units" class="pr-btn-nav pr-btn-nav--primary">Apply Now</a>
                        @endif
                    @endauth
                </div>
                @endif

                <!-- Mobile Hamburger -->
                <button class="pr-hamburger" id="prHamburger" aria-label="Open menu">
                    <span></span><span></span><span></span>
                </button>

            </div>
        </div>
    </header>

    <!-- ===================== MOBILE MENU ===================== -->
    <div class="pr-mobile-menu" id="prMobileMenu">
        <div class="pr-mobile-menu__backdrop" id="prMobileBackdrop"></div>
        <div class="pr-mobile-menu__drawer">
            <div class="pr-mobile-menu__top">
                <a href="{{ url('/') }}" class="pr-logo" style="font-size:1.1rem;">
                    <div class="pr-logo__mark" style="width:30px;height:30px;font-size:.75rem;">🏠</div>
                    <span class="pr-logo__text">Pinikitan<em>Rental</em></span>
                </a>
                <button class="pr-mobile-menu__close" id="prMobileClose" aria-label="Close menu">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <ul class="pr-mobile-nav">
                <li><a href="{{ url('/') }}#units">Rooms</a></li>
                <li><a href="{{ url('/') }}#about">About</a></li>
                <li><a href="{{ url('/') }}#terms-info">Lease Terms</a></li>
                <li><a href="{{ url('/') }}#contact">Contact</a></li>
            </ul>

            <div class="pr-mobile-actions">
                @auth
                    @php $user = auth()->user(); @endphp
                    @if($user->role === 'manager')
                        <a href="{{ route('manager.dashboard') }}" class="pr-btn-nav pr-btn-nav--primary">Dashboard</a>
                    @else
                        <a href="{{ route('tenant.home') }}"       class="pr-btn-nav pr-btn-nav--primary">Dashboard</a>
                    @endif
                @else
                    <a href="{{ route('login') }}"    class="pr-btn-nav pr-btn-nav--ghost">Log in</a>
                    @if(Route::has('register'))
                        {{-- <a href="{{ route('register') }}" class="pr-btn-nav pr-btn-nav--primary">Register</a> --}}
                        <a href="{{ url('/') }}#units" class="pr-btn-nav pr-btn-nav--primary">Apply Now</a>
                    @endif
                @endauth
            </div>
        </div>
    </div>

    <!-- ===================== MAIN ===================== -->
    <main class="flex-fill">
        @yield('content')
    </main>

    <!-- ===================== FOOTER ===================== -->
    <footer class="pr-footer mt-auto">
        <div class="container">
            <div class="pr-footer__inner">
                <div class="pr-footer__brand">Pinikitan<em>Rental</em></div>
                <div class="pr-footer__links">
                    <a href="#" data-privacy-open>Data Privacy Notice</a>
                    <a href="#">Terms &amp; Conditions</a>
                    <a href="{{ url('/') }}#contact">Contact Us</a>
                </div>
                <div class="pr-footer__copy">&copy; {{ date('Y') }} PinikitanRental. All rights reserved.</div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    (function () {
        const header    = document.getElementById('prHeader');
        const hamburger = document.getElementById('prHamburger');
        const mobileMenu   = document.getElementById('prMobileMenu');
        const mobileClose  = document.getElementById('prMobileClose');
        const mobileBackdrop = document.getElementById('prMobileBackdrop');

        // ── Scroll-aware header ──────────────────────────────────────
        function updateHeader() {
            if (window.scrollY > 40) {
                header.classList.remove('pr-header--transparent');
                header.classList.add('pr-header--solid');
            } else {
                header.classList.remove('pr-header--solid');
                header.classList.add('pr-header--transparent');
            }
        }
        window.addEventListener('scroll', updateHeader, { passive: true });
        updateHeader();

        // ── Mobile drawer ────────────────────────────────────────────
        function openMenu() {
            mobileMenu.classList.add('open');
            hamburger.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function closeMenu() {
            mobileMenu.classList.remove('open');
            hamburger.classList.remove('open');
            document.body.style.overflow = '';
        }

        hamburger.addEventListener('click', openMenu);
        mobileClose.addEventListener('click', closeMenu);
        mobileBackdrop.addEventListener('click', closeMenu);

        // Close on nav link click
        document.querySelectorAll('.pr-mobile-nav a').forEach(a => {
            a.addEventListener('click', closeMenu);
        });

        // ── Active nav link highlight ────────────────────────────────
        const currentPath = window.location.pathname;
        document.querySelectorAll('.pr-nav__link, .pr-mobile-nav a').forEach(link => {
            if (link.getAttribute('href') === currentPath) link.classList.add('active');
        });
    })();
    </script>

    {{-- RA 10173 Data Privacy Notice — opened by any [data-privacy-open] (v18) --}}
    @include('partials.data-privacy-notice')

    @stack('scripts')
    {{-- Global double-submit guard --}}
    @include('partials.single-submit')
</body>
</html>