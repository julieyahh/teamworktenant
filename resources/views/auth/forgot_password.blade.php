@extends('layouts.app')

@section('title', 'Forgot Password')

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
    :root {
        --pr-navy:     #0c1a3a;
        --pr-navy-mid: #132044;
        --pr-gold:     #c9a84c;
        --pr-gold-lt:  #e2c580;
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

    /* Background layers — identical to login */
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
    .auth-panel__steps {
        display: flex;
        flex-direction: column;
        gap: 0;
    }
    .auth-step {
        display: flex;
        gap: 18px;
        position: relative;
    }
    .auth-step:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 17px;
        top: 38px;
        width: 1px;
        height: calc(100% - 10px);
        background: rgba(201,168,76,.2);
    }
    .auth-step__num {
        width: 36px; height: 36px;
        border-radius: 50%;
        background: rgba(201,168,76,.12);
        border: 1px solid rgba(201,168,76,.25);
        display: flex; align-items: center; justify-content: center;
        color: var(--pr-gold);
        font-size: .8rem;
        font-weight: 700;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .auth-step__body {
        padding-bottom: 28px;
    }
    .auth-step__title {
        font-size: .9rem;
        font-weight: 600;
        color: rgba(255,255,255,.9);
        margin-bottom: 4px;
    }
    .auth-step__desc {
        font-size: .82rem;
        color: rgba(255,255,255,.5);
        line-height: 1.6;
    }

    /* Divider */
    .auth-divider {
        width: 1px;
        background: rgba(201,168,76,.12);
        align-self: stretch;
    }

    /* Right panel */
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
        line-height: 1.6;
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
        line-height: 1.5;
    }
    .auth-alert--success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .auth-alert--danger  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

    /* Info notice */
    .auth-notice {
        background: rgba(12,26,58,.04);
        border: 1px solid rgba(12,26,58,.08);
        border-radius: 12px;
        padding: 14px 16px;
        font-size: .83rem;
        color: #666;
        line-height: 1.6;
        margin-bottom: 24px;
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }
    .auth-notice__icon { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }

    /* Form */
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

    /* Submit */
    .pr-submit {
        width: 100%;
        padding: 13px;
        border: none;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--pr-gold), var(--pr-gold-lt));
        color: var(--pr-navy);
        font-family: var(--pr-font-body);
        font-size: .95rem;
        font-weight: 700;
        cursor: pointer;
        transition: var(--pr-transition);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .pr-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(201,168,76,.35);
    }

    /* Back link */
    .pr-back {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        margin-top: 20px;
        font-size: .85rem;
        color: #888;
        text-decoration: none;
        transition: color .2s;
    }
    .pr-back:hover { color: var(--pr-navy); }
    .pr-back svg { transition: transform .2s; }
    .pr-back:hover svg { transform: translateX(-3px); }

    /* Mobile */
    @media (max-width: 900px) {
        .auth-page { flex-direction: column; }
        .auth-panel { display: none; }
        .auth-divider { display: none; }
        .auth-card-wrap { flex: 1; padding: 100px 20px 40px; min-height: 100vh; }
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
            Account Recovery
        </div>
        <h1 class="auth-panel__title">
            Reset Your<br>
            <em>Password</em>
        </h1>
        <p class="auth-panel__desc">
            No worries — it happens to everyone. We'll send a secure link to your inbox so you can get back in quickly.
        </p>

        <div class="auth-panel__steps">
            <div class="auth-step">
                <div class="auth-step__num">1</div>
                <div class="auth-step__body">
                    <div class="auth-step__title">Enter your email</div>
                    <div class="auth-step__desc">Provide the email address linked to your account.</div>
                </div>
            </div>
            <div class="auth-step">
                <div class="auth-step__num">2</div>
                <div class="auth-step__body">
                    <div class="auth-step__title">Check your inbox</div>
                    <div class="auth-step__desc">We'll send a secure reset link — check spam if needed.</div>
                </div>
            </div>
            <div class="auth-step">
                <div class="auth-step__num">3</div>
                <div class="auth-step__body">
                    <div class="auth-step__title">Create a new password</div>
                    <div class="auth-step__desc">Follow the link and set a strong new password.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="auth-divider"></div>

    {{-- Right panel --}}
    <div class="auth-card-wrap">
        <div class="auth-card">

            <div class="auth-card__header">
                <div class="auth-card__logo">🔑</div>
                <h2 class="auth-card__title">Forgot Password?</h2>
                <p class="auth-card__sub">Enter your registered email and we'll send you a reset link.</p>
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

            <div class="auth-notice">
                <span class="auth-notice__icon">ℹ️</span>
                <span>The reset link will expire after 60 minutes. Make sure to check your spam or junk folder.</span>
            </div>

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="pr-field">
                    <label class="pr-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                        class="pr-input"
                        placeholder="you@example.com"
                        required autofocus
                        value="{{ old('email') }}">
                </div>

                <button type="submit" class="pr-submit">
                    Send Reset Link
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="22" y1="2" x2="11" y2="13"/>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                </button>
            </form>

            <a href="{{ route('login') }}" class="pr-back">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
                Back to Login
            </a>

        </div>
    </div>

</div>
@endsection