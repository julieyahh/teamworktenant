{{-- resources/views/components/alert.blade.php --}}

@props([
    'type'        => 'success',   {{-- success | error | warning | info --}}
    'dismissible' => true,
    'duration'    => 5000,        {{-- ms; 0 = no auto-dismiss --}}
])

@php
    $alertId = 'pr-alert-' . uniqid();

    $config = [
        'success' => [
            'bg'          => '#f0fdf4',
            'border'      => '#bbf7d0',
            'color'       => '#166534',
            'icon'        => 'bi-check-circle-fill',
            'accent'      => '#16a34a',
            'label'       => 'Success',
        ],
        'error'   => [
            'bg'          => '#fef2f2',
            'border'      => '#fecaca',
            'color'       => '#991b1b',
            'icon'        => 'bi-exclamation-circle-fill',
            'accent'      => '#dc2626',
            'label'       => 'Error',
        ],
        'warning' => [
            'bg'          => '#fffbeb',
            'border'      => '#fde68a',
            'color'       => '#92400e',
            'icon'        => 'bi-exclamation-triangle-fill',
            'accent'      => '#d97706',
            'label'       => 'Warning',
        ],
        'info'    => [
            'bg'          => 'rgba(12,26,58,.05)',
            'border'      => 'rgba(12,26,58,.12)',
            'color'       => 'var(--pr-navy)',
            'icon'        => 'bi-info-circle-fill',
            'accent'      => 'var(--pr-gold)',
            'label'       => 'Notice',
        ],
    ];

    $c = $config[$type] ?? $config['info'];
@endphp

<style>
    .pr-alert-wrap {
        position: fixed;
        top: 1.5rem;
        left: 50%;
        transform: translate(-50%, -16px);
        z-index: 9999;
        min-width: 320px;
        max-width: 520px;
        width: calc(100% - 40px);
        opacity: 0;
        pointer-events: none;
        transition: opacity .28s ease, transform .28s cubic-bezier(.22,1,.36,1);
    }
    .pr-alert-wrap.pr-alert--visible {
        opacity: 1;
        transform: translate(-50%, 0);
        pointer-events: auto;
    }

    .pr-alert-inner {
        display: flex;
        align-items: flex-start;
        gap: 13px;
        padding: 14px 16px 14px 16px;
        border-radius: 14px;
        border: 1.5px solid;
        box-shadow: 0 8px 28px rgba(12,26,58,.12), 0 2px 8px rgba(12,26,58,.06);
        position: relative;
        overflow: hidden;
    }

    .pr-alert-icon-wrap {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
        margin-top: 1px;
    }

    .pr-alert-text-block {
        flex: 1;
        min-width: 0;
    }
    .pr-alert-label {
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        opacity: .65;
        margin-bottom: 2px;
    }
    .pr-alert-message {
        font-size: .875rem;
        font-weight: 600;
        line-height: 1.45;
    }

    .pr-alert-close {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        border: none;
        background: transparent;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: .85rem;
        flex-shrink: 0;
        opacity: .5;
        transition: opacity .18s, background .18s;
        padding: 0;
        margin-top: 1px;
    }
    .pr-alert-close:hover {
        opacity: 1;
        background: rgba(0,0,0,.07);
    }

    /* Progress bar */
    .pr-alert-progress {
        position: absolute;
        bottom: 0; left: 0; right: 0;
        height: 3px;
        border-radius: 0 0 12px 12px;
        overflow: hidden;
    }
    .pr-alert-progress__bar {
        height: 100%;
        width: 100%;
        border-radius: inherit;
        transform-origin: left center;
        transition: transform linear;
    }
</style>

<div id="{{ $alertId }}" class="pr-alert-wrap" role="alert" aria-live="polite" aria-atomic="true">
    <div class="pr-alert-inner"
         style="background:{{ $c['bg'] }};border-color:{{ $c['border'] }};color:{{ $c['color'] }};">

        {{-- Icon --}}
        <div class="pr-alert-icon-wrap"
             style="background:{{ $c['accent'] }}1a;color:{{ $c['accent'] }};">
            <i class="bi {{ $c['icon'] }}"></i>
        </div>

        {{-- Text --}}
        <div class="pr-alert-text-block">
            <div class="pr-alert-label">{{ $c['label'] }}</div>
            <div class="pr-alert-message">{{ $slot }}</div>
        </div>

        {{-- Dismiss --}}
        @if($dismissible)
            <button class="pr-alert-close"
                    onclick="prDismissAlert('{{ $alertId }}')"
                    aria-label="Dismiss"
                    style="color:{{ $c['color'] }};">
                <i class="bi bi-x-lg"></i>
            </button>
        @endif

        {{-- Progress bar --}}
        @if($duration > 0)
            <div class="pr-alert-progress">
                <div class="pr-alert-progress__bar"
                     id="{{ $alertId }}-bar"
                     style="background:{{ $c['accent'] }};opacity:.35;"></div>
            </div>
        @endif
    </div>
</div>

<script>
(function () {
    const id         = '{{ $alertId }}';
    const duration   = {{ $duration }};
    const wrap       = document.getElementById(id);
    if (!wrap) return;

    let tid          = null;
    let startedAt    = null;
    let remaining    = duration;
    let paused       = false;
    const bar        = document.getElementById(id + '-bar');

    /* ── show ───────────────────────────────────────── */
    requestAnimationFrame(() => wrap.classList.add('pr-alert--visible'));

    /* ── dismiss ────────────────────────────────────── */
    window.prDismissAlert = window.prDismissAlert || function (elId) {
        const el = document.getElementById(elId);
        if (!el) return;
        el.classList.remove('pr-alert--visible');
        el.addEventListener('transitionend', () => el.remove(), { once: true });
    };

    function startProgress(ms) {
        if (!bar) return;
        bar.style.transition = 'none';
        bar.style.transform  = 'scaleX(1)';
        requestAnimationFrame(() => {
            bar.style.transition = `transform ${ms}ms linear`;
            bar.style.transform  = 'scaleX(0)';
        });
    }

    function pauseProgress() {
        if (!bar) return;
        const style = window.getComputedStyle(bar);
        const matrix = new DOMMatrix(style.transform);
        const current = matrix.a; // scaleX
        bar.style.transition = 'none';
        bar.style.transform  = `scaleX(${current})`;
        remaining = current * duration;
    }

    /* ── auto-dismiss ───────────────────────────────── */
    if (duration > 0) {
        startedAt = Date.now();
        startProgress(duration);
        tid = setTimeout(() => window.prDismissAlert(id), duration);

        wrap.addEventListener('mouseenter', () => {
            if (paused) return;
            paused = true;
            clearTimeout(tid);
            pauseProgress();
        });

        wrap.addEventListener('mouseleave', () => {
            if (!paused) return;
            paused = false;
            startProgress(remaining);
            startedAt = Date.now();
            tid = setTimeout(() => window.prDismissAlert(id), remaining);
        });
    }

    /* ── keyboard ───────────────────────────────────── */
    {{ $dismissible ? 'true' : 'false' }} && wrap.addEventListener('keydown', e => {
        if (e.key === 'Escape') window.prDismissAlert(id);
    });
})();
</script>