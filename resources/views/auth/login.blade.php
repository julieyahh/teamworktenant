@extends('layouts.app')

@section('title', 'Login')

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
    :root {
        --pr-navy:     #0c1a3a;
        --pr-navy-mid: #132044;
        --pr-gold:     #c9a84c;
        --pr-gold-lt:  #e2c580;
        --pr-cream:    #faf8f3;
        --pr-font-display: 'Playfair Display', Georgia, serif;
        --pr-font-body:    'DM Sans', system-ui, sans-serif;
        --pr-transition:   0.3s cubic-bezier(.4,0,.2,1);
    }

    body {
        font-family: var(--pr-font-body);
        background: var(--pr-navy);
        min-height: 100vh;
        margin: 0;
        overflow-x: hidden;
    }

    /* Background layers */
    .auth-bg {
        position: fixed; inset: 0; z-index: 0;
        background: var(--pr-navy);
        overflow: hidden;
    }
    .auth-bg__grid {
        position: absolute; inset: 0;
        background-image:
            linear-gradient(rgba(201,168,76,.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(201,168,76,.05) 1px, transparent 1px);
        background-size: 56px 56px;
    }
    .auth-bg__orb-1 {
        position: absolute;
        width: 600px; height: 600px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(201,168,76,.12) 0%, transparent 70%);
        top: -200px; right: -150px;
        filter: blur(60px);
    }
    .auth-bg__orb-2 {
        position: absolute;
        width: 400px; height: 400px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(19,32,68,.9) 0%, transparent 70%);
        bottom: -100px; left: -100px;
        filter: blur(60px);
    }

    /* Page layout */
    .auth-page {
        position: relative; z-index: 1;
        min-height: 100vh;
        padding-top: 68px; /* height of fixed header */
        display: flex;
        flex-direction: row;
        align-items: stretch;
    }

    /* Left panel */
    .auth-panel {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        color: white;
    }
    .auth-panel__eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        color: var(--pr-gold);
        font-size: .75rem;
        font-weight: 600;
        letter-spacing: .14em;
        text-transform: uppercase;
        margin-bottom: 28px;
    }
    .auth-panel__dot {
        width: 7px; height: 7px;
        border-radius: 50%;
        background: var(--pr-gold);
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%,100% { opacity:1; transform:scale(1); }
        50%      { opacity:.4; transform:scale(.65); }
    }
    .auth-panel__title {
        font-family: var(--pr-font-display);
        font-size: clamp(2.4rem, 4vw, 3.2rem);
        font-weight: 700;
        line-height: 1.1;
        margin-bottom: 20px;
    }
    .auth-panel__title em {
        font-style: italic;
        color: var(--pr-gold);
    }
    .auth-panel__desc {
        color: rgba(255,255,255,.6);
        font-size: .95rem;
        line-height: 1.8;
        max-width: 360px;
        margin-bottom: 48px;
    }
    .auth-panel__features {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .auth-feature {
        display: flex;
        align-items: center;
        gap: 14px;
        color: rgba(255,255,255,.75);
        font-size: .88rem;
    }
    .auth-feature__icon {
        width: 36px; height: 36px;
        border-radius: 10px;
        background: rgba(201,168,76,.12);
        border: 1px solid rgba(201,168,76,.2);
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    /* Divider line between panels */
    .auth-divider {
        width: 1px;
        background: rgba(201,168,76,.12);
        align-self: stretch;
    }

    /* Right panel — card */
    .auth-card-wrap {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    .auth-card {
        background: rgba(255,255,255,.97);
        border-radius: 24px;
        padding: 44px 40px;
        width: 100%;
        max-width: 420px;
        box-shadow: 0 32px 80px rgba(12,26,58,.35);
        animation: fadeUp .6s .1s both;
    }
    @keyframes fadeUp {
        from { opacity:0; transform:translateY(20px); }
        to   { opacity:1; transform:translateY(0); }
    }

    .auth-card__header {
        margin-bottom: 32px;
        text-align: center;
    }
    .auth-card__logo {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 52px; height: 52px;
        border-radius: 16px;
        background: linear-gradient(135deg, var(--pr-navy), var(--pr-navy-mid));
        font-size: 1.4rem;
        margin-bottom: 20px;
        box-shadow: 0 8px 20px rgba(12,26,58,.25);
    }
    .auth-card__title {
        font-family: var(--pr-font-display);
        font-size: 1.7rem;
        font-weight: 700;
        color: var(--pr-navy);
        margin-bottom: 6px;
    }
    .auth-card__sub {
        font-size: .85rem;
        color: #7a7a7a;
    }

    /* Alert */
    .auth-alert {
        border-radius: 12px;
        padding: 12px 16px;
        font-size: .85rem;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    .auth-alert--success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .auth-alert--danger  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

    /* Form fields */
    .pr-field { margin-bottom: 20px; }
    .pr-label {
        display: block;
        font-size: .82rem;
        font-weight: 600;
        color: var(--pr-navy);
        margin-bottom: 7px;
    }
    .pr-input {
        width: 100%;
        padding: 12px 16px;
        border: 1.5px solid #ddd;
        border-radius: 12px;
        font-family: var(--pr-font-body);
        font-size: .9rem;
        color: #222;
        background: #fff;
        transition: border-color .2s, box-shadow .2s;
        outline: none;
        box-sizing: border-box;
    }
    .pr-input:focus {
        border-color: var(--pr-navy);
        box-shadow: 0 0 0 3px rgba(12,26,58,.08);
    }

    /* Password wrapper */
    .pr-input-wrap { position: relative; }
    .pr-eye {
        position: absolute;
        right: 12px; top: 50%;
        transform: translateY(-50%);
        background: none; border: none;
        color: #9a9a9a; cursor: pointer;
        padding: 4px;
        line-height: 1;
        transition: color .2s;
    }
    .pr-eye:hover { color: var(--pr-navy); }
    .pr-input-wrap .pr-input { padding-right: 42px; }

    /* Submit button */
    .pr-submit {
        width: 100%;
        padding: 13px;
        border: none;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--pr-navy), var(--pr-navy-mid));
        color: #fff;
        font-family: var(--pr-font-body);
        font-size: .95rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--pr-transition);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .pr-submit:hover {
        background: linear-gradient(135deg, var(--pr-gold), var(--pr-gold-lt));
        color: var(--pr-navy);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(201,168,76,.3);
    }

    /* Secondary button */
    .pr-btn-outline {
        width: 100%;
        padding: 12px;
        border: 1.5px solid #ddd;
        border-radius: 12px;
        background: transparent;
        color: #555;
        font-family: var(--pr-font-body);
        font-size: .9rem;
        font-weight: 500;
        cursor: pointer;
        transition: var(--pr-transition);
        text-align: center;
        display: block;
        margin-bottom: 20px;
        text-decoration: none;
    }
    .pr-btn-outline:hover {
        border-color: var(--pr-navy);
        color: var(--pr-navy);
        background: rgba(12,26,58,.04);
    }

    .auth-card__divider {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 20px 0;
        color: #ccc;
        font-size: .8rem;
    }
    .auth-card__divider::before,
    .auth-card__divider::after {
        content: ''; flex: 1; height: 1px; background: #eee;
    }

    .pr-forgot {
        display: block;
        text-align: center;
        margin-top: 16px;
        font-size: .83rem;
        color: var(--pr-gold);
        font-weight: 500;
        text-decoration: none;
        transition: color .2s;
    }
    .pr-forgot:hover { color: var(--pr-navy); }


    /* Sign-in loading overlay (v18) */
    .auth-loading {
        position: fixed; inset: 0; z-index: 2000;
        display: flex; align-items: center; justify-content: center;
        background: rgba(12,26,58,.55);
        backdrop-filter: blur(3px);
        opacity: 0; visibility: hidden;
        transition: opacity .2s ease, visibility .2s ease;
    }
    .auth-loading.is-visible { opacity: 1; visibility: visible; }
    .auth-loading__box {
        background: #fff; border-radius: 20px; padding: 30px 38px;
        min-width: 240px; text-align: center;
        box-shadow: 0 24px 60px rgba(12,26,58,.35);
        transform: translateY(8px); transition: transform .2s ease;
    }
    .auth-loading.is-visible .auth-loading__box { transform: translateY(0); }
    .auth-spinner {
        width: 46px; height: 46px; margin: 0 auto 16px;
        border-radius: 50%;
        border: 4px solid rgba(12,26,58,.12);
        border-top-color: var(--pr-gold);
        animation: authSpin .8s linear infinite;
    }
    @keyframes authSpin { to { transform: rotate(360deg); } }
    .auth-loading__title {
        font-family: var(--pr-font-display);
        font-size: 1.15rem; font-weight: 700; color: var(--pr-navy);
    }
    .auth-loading__sub { font-size: .82rem; color: #7a7a7a; margin-top: 4px; }
    .pr-submit__spin {
        width: 16px; height: 16px; border-radius: 50%;
        border: 2px solid rgba(255,255,255,.35); border-top-color: #fff;
        animation: authSpin .8s linear infinite;
    }
    .pr-submit[disabled] { opacity: .85; cursor: wait; transform: none !important; }
    @media (prefers-reduced-motion: reduce) {
        .auth-spinner, .pr-submit__spin { animation-duration: 2s; }
    }

    /* Mobile */
    @media (max-width: 900px) {
        .auth-page { flex-direction: column; padding-top: 68px; }
        .auth-panel { display: none; }
        .auth-divider { display: none; }
        .auth-card-wrap { flex: 1; padding: 40px 20px; justify-content: center; }
        .auth-card { padding: 36px 28px; }
    }
    @media (max-width: 480px) {
        .auth-card { padding: 28px 20px; }
    }
</style>
@endpush

@section('content')
<div class="auth-bg">
    <div class="auth-bg__grid"></div>
    <div class="auth-bg__orb-1"></div>
    <div class="auth-bg__orb-2"></div>
</div>

<div class="container auth-page">

    {{-- Left panel --}}
    <div class="auth-panel">
        <div class="auth-panel__eyebrow">
            <span class="auth-panel__dot"></span>
            Pinikitan Rental
        </div>
        <h1 class="auth-panel__title">
            Welcome<br>
            <em>Back Home</em>
        </h1>
        <p class="auth-panel__desc">
            Log in to manage your rental, view your lease, track payments, and connect with your property manager.
        </p>
        <div class="auth-panel__features">
            <div class="auth-feature">
                <div class="auth-feature__icon">🏠</div>
                <span>View and manage your rental unit</span>
            </div>
            <div class="auth-feature">
                <div class="auth-feature__icon">💳</div>
                <span>Track payments and billing history</span>
            </div>
            <div class="auth-feature">
                <div class="auth-feature__icon">📄</div>
                <span>Access your lease documents anytime</span>
            </div>
        </div>
    </div>

    <div class="auth-divider"></div>

    {{-- Right panel --}}
    <div class="auth-card-wrap">
        <div class="auth-card">

            <div class="auth-card__header">
                <div class="auth-card__logo">🏠</div>
                <h2 class="auth-card__title">Sign In</h2>
                <p class="auth-card__sub">Enter your credentials to continue</p>
            </div>

            @if(session('success'))
                <div class="auth-alert auth-alert--success">
                    ✓ {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="auth-alert auth-alert--danger">
                    ✕ {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}" id="loginForm" data-allow-multi-submit>
                @csrf

                <div class="pr-field">
                    <label class="pr-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                        class="pr-input"
                        placeholder="you@example.com"
                        required autofocus
                        pattern="[a-zA-Z0-9]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                        title="Only letters/numbers before @, valid domain after @">
                </div>

                <div class="pr-field">
                    <label class="pr-label" for="password">Password</label>
                    <div class="pr-input-wrap">
                        <input type="password" id="password" name="password"
                            class="pr-input"
                            placeholder="••••••••"
                            required>
                        <button type="button" class="pr-eye" id="togglePwd" aria-label="Toggle password">
                            <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="pr-submit" id="loginSubmit">
                    Sign In
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </button>
            </form>

            <a href="{{ route('password.request') }}" class="pr-forgot">
                Forgot your password?
            </a>

        </div>
    </div>

</div>

{{-- Shown the moment the Sign In button is pressed (v18). --}}
<div class="auth-loading" id="authLoading" role="status" aria-live="polite" aria-hidden="true">
    <div class="auth-loading__box">
        <div class="auth-spinner" aria-hidden="true"></div>
        <div class="auth-loading__title">Please wait…</div>
        <div class="auth-loading__sub" id="authLoadingSub">Signing you in</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const togglePwd = document.getElementById('togglePwd');
    const pwdInput  = document.getElementById('password');
    const eyeIcon   = document.getElementById('eyeIcon');

    const eyeOpen   = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
    const eyeClosed = `<line x1="17.94" y1="17.94" x2="6.06" y2="6.06"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M6.24 6.24A18.42 18.42 0 0 0 1 12s4 8 11 8a9.14 9.14 0 0 0 5.76-2.06"/><line x1="1" y1="1" x2="23" y2="23"/>`;

    togglePwd.addEventListener('click', function () {
        const isHidden = pwdInput.type === 'password';
        pwdInput.type  = isHidden ? 'text' : 'password';
        eyeIcon.innerHTML = isHidden ? eyeClosed : eyeOpen;
    });

    // ── Sign-in spinner (v18) ────────────────────────────────────────
    // The form opts out of the global single-submit guard
    // (data-allow-multi-submit) and handles its own lock here, so the
    // button and the full-page "Please wait" overlay stay in step.
    (function () {
        const form     = document.getElementById('loginForm');
        const btn      = document.getElementById('loginSubmit');
        const overlay  = document.getElementById('authLoading');
        const sub      = document.getElementById('authLoadingSub');
        if (!form || !btn || !overlay) return;

        const originalHtml = btn.innerHTML;
        let busy = false, slowTimer = null, giveUpTimer = null;

        function show() {
            busy = true;
            overlay.classList.add('is-visible');
            overlay.setAttribute('aria-hidden', 'false');
            btn.disabled = true;
            btn.innerHTML = '<span class="pr-submit__spin" aria-hidden="true"></span> Please wait…';
            sub.textContent = 'Signing you in';
            slowTimer   = setTimeout(() => { sub.textContent = 'Still working — this is taking longer than usual…'; }, 8000);
            giveUpTimer = setTimeout(reset, 30000);   // never leave the page locked
        }

        function reset() {
            busy = false;
            clearTimeout(slowTimer); clearTimeout(giveUpTimer);
            overlay.classList.remove('is-visible');
            overlay.setAttribute('aria-hidden', 'true');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }

        // The browser only fires 'submit' once the required/pattern checks pass,
        // so the overlay never covers a form the user still has to fix.
        form.addEventListener('submit', function (e) {
            if (busy) { e.preventDefault(); return; }
            show();
        });

        // Coming back with the Back button restores the page from cache with
        // the overlay still up — clear it.
        window.addEventListener('pageshow', function (e) { if (e.persisted) reset(); });
    })();
</script>
@endpush