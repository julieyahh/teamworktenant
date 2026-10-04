@extends('layouts.tenantdashboardlayout')

@section('title', 'Feedback')
@section('page-title', 'Feedback')

@push('styles')
<style>
    /* ===================== PAGE HEADER ===================== */
    .pr-dash-header { margin-bottom:24px; }
    .pr-dash-header__eyebrow { font-size:.72rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--pr-gold); margin-bottom:6px; }
    .pr-dash-header__title { font-family:var(--pr-font-display); font-size:1.5rem; font-weight:700; color:var(--pr-navy); margin:0 0 4px; line-height:1.2; }
    .pr-dash-header__sub { font-size:.85rem; color:var(--pr-gray-500); margin:0; }

    /* ===================== FORM CARD ===================== */
    .pr-form-card { background:var(--pr-white); border:1px solid rgba(12,26,58,.07); border-radius:16px; box-shadow:0 2px 12px rgba(12,26,58,.06); padding:24px 26px; margin-bottom:24px; }
    .pr-form-card__title { font-family:var(--pr-font-display); font-size:1rem; font-weight:700; color:var(--pr-navy); margin:0 0 18px; display:flex; align-items:center; gap:8px; }

    .pr-form-group { margin-bottom:16px; }
    .pr-form-group:last-child { margin-bottom:0; }
    .pr-form-label { display:block; font-size:.78rem; font-weight:600; color:var(--pr-navy); margin-bottom:6px; }
    .pr-required { color:#dc2626; }
    .pr-form-control { width:100%; padding:10px 14px; border:1.5px solid var(--pr-gray-200); border-radius:10px; font-family:var(--pr-font-body); font-size:.88rem; color:var(--pr-gray-800); background:var(--pr-white); outline:none; transition:border-color .2s; box-sizing:border-box; }
    .pr-form-control:focus { border-color:var(--pr-navy); box-shadow:0 0 0 3px rgba(12,26,58,.07); }
    .pr-form-hint { font-size:.72rem; color:var(--pr-gray-500); margin-top:4px; }
    .pr-invalid-feedback { font-size:.78rem; color:#dc2626; margin-top:4px; }
    .pr-form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    @media (max-width:640px) { .pr-form-row { grid-template-columns:1fr; } }

    .pr-btn { display:inline-flex; align-items:center; gap:7px; padding:10px 18px; border-radius:10px; font-family:var(--pr-font-body); font-size:.85rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; white-space:nowrap; transition:all .25s ease; }
    .pr-btn--navy { background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid)); color:var(--pr-white); }
    .pr-btn--navy:hover { color:var(--pr-white); transform:translateY(-2px); box-shadow:0 6px 18px rgba(12,26,58,.22); }

    /* ===================== STAR RATING ===================== */
    /* Fill state is driven entirely by JS (see the script below), which
       toggles .pr-star--filled based on each label's data-value against
       the hovered/checked rating. There is deliberately no CSS
       :hover / :checked sibling-selector rule here — an earlier version
       of this had one, and because "input:checked ~ label" is MORE
       specific than ".pr-star--filled", it silently overrode the JS's
       gold color back to gray the moment the mouse left a selected star.
       If you're tempted to add a pure-CSS hover rule back in, don't —
       it will re-introduce that bug. */
    .pr-star-rating { display:flex; gap:6px; align-items:center; }
    .pr-star-rating input { display:none; }
    .pr-star-rating label { font-size:1.5rem; color:var(--pr-gray-200); cursor:pointer; transition:color .15s; }
    .pr-star-rating label i { pointer-events:none; }
    .pr-star-rating label.pr-star--filled { color: var(--pr-gold); }

    /* ===================== TABLE CARD ===================== */
    .pr-table-card { background:var(--pr-white); border:1px solid rgba(12,26,58,.07); border-radius:16px; box-shadow:0 2px 12px rgba(12,26,58,.06); overflow:hidden; }
    .pr-table-card__header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 22px; border-bottom:1px solid rgba(12,26,58,.06); }
    .pr-table-card__title { font-family:var(--pr-font-display); font-size:.98rem; font-weight:700; color:var(--pr-navy); margin:0; }
    .pr-table-card__count { font-size:.72rem; font-weight:600; padding:3px 10px; border-radius:999px; background:rgba(201,168,76,.1); color:var(--pr-gold); border:1px solid rgba(201,168,76,.2); }

    .pr-table-wrap { overflow-x:auto; }
    .pr-table { width:100%; border-collapse:collapse; font-size:.85rem; }
    .pr-table thead th { font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--pr-gray-500); padding:12px 16px; border-bottom:1px solid rgba(12,26,58,.07); background:rgba(12,26,58,.02); text-align:left; white-space:nowrap; }
    .pr-table tbody td { padding:13px 16px; color:var(--pr-gray-800); border-bottom:1px solid rgba(12,26,58,.04); vertical-align:middle; }
    .pr-table tbody tr:last-child td { border-bottom:0; }
    .pr-table tbody tr:hover td { background:rgba(12,26,58,.015); }

    .pr-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; font-size:.71rem; font-weight:600; }
    .pr-pill::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .pr-pill--green { background:#f0fdf4; color:#16a34a; }
    .pr-pill--amber { background:#fffbeb; color:#d97706; }
    .pr-pill--gray  { background:#f3f4f6; color:#6b7280; }

    .pr-cat-tag { display:inline-flex; align-items:center; padding:3px 10px; border-radius:999px; font-size:.72rem; font-weight:600; background:rgba(12,26,58,.07); color:var(--pr-navy); border:1px solid rgba(12,26,58,.1); }

    .pr-stars-readonly i { color:var(--pr-gold); font-size:.8rem; }
    .pr-stars-readonly i.pr-star-empty { color:var(--pr-gray-200); }

    .pr-empty { text-align:center; padding:56px 24px; color:var(--pr-gray-500); }
    .pr-empty__icon-wrap { width:72px; height:72px; border-radius:20px; background:rgba(12,26,58,.05); display:inline-flex; align-items:center; justify-content:center; font-size:1.8rem; margin-bottom:16px; opacity:.5; }
    .pr-empty__title { font-family:var(--pr-font-display); font-size:1.05rem; font-weight:700; color:var(--pr-navy); margin-bottom:6px; }
    .pr-empty__sub { font-size:.85rem; }
</style>
@endpush

@section('content')

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif
    @if(session('error'))
        <x-alert type="error">{{ session('error') }}</x-alert>
    @endif
    @if(session('info'))
        <x-alert type="info">{{ session('info') }}</x-alert>
    @endif
    @if($errors->any())
        <x-alert type="error">{{ $errors->first() }}</x-alert>
    @endif

    {{-- Page header --}}
    <div class="pr-dash-header">
        <div class="pr-dash-header__eyebrow">Tenant Portal</div>
        <h2 class="pr-dash-header__title">Feedback</h2>
        <p class="pr-dash-header__sub">Tell the property manager what's working and what isn't — you'll be notified if they respond.</p>
    </div>

    {{-- Submit form --}}
    <div class="pr-form-card">
        <h3 class="pr-form-card__title">
            <i class="bi bi-chat-square-heart-fill" style="color:var(--pr-gold);font-size:.9rem;"></i>
            Share Your Feedback
        </h3>

        <form method="POST" action="{{ route('tenant.feedback.store') }}">
            @csrf

            <div class="pr-form-row">
                <div class="pr-form-group">
                    <label class="pr-form-label">Category <span class="pr-required">*</span></label>
                    <select name="category" class="pr-form-control @error('category') is-invalid @enderror" required>
                        <option value="">Select a category…</option>
                        @foreach(\App\Models\Feedback::CATEGORIES as $category)
                            <option value="{{ $category }}" {{ old('category') === $category ? 'selected' : '' }}>{{ $category }}</option>
                        @endforeach
                    </select>
                    @error('category')<div class="pr-invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="pr-form-group">
                    <label class="pr-form-label">Rating</label>
                    <div class="pr-star-rating" id="starRating">
                        {{-- Ascending 1→5 so the leftmost star is worth 1 and the
                             rightmost is worth 5, matching normal star-rating UX.
                             The fill logic in JS reads each label's data-value
                             directly, so DOM order doesn't need to be reversed
                             the way a pure-CSS sibling-selector trick would require. --}}
                        @for($i = 1; $i <= 5; $i++)
                            <input type="radio" name="rating" id="star{{ $i }}" value="{{ $i }}" {{ (int) old('rating') === $i ? 'checked' : '' }}>
                            <label for="star{{ $i }}" data-value="{{ $i }}"><i class="bi bi-star-fill"></i></label>
                        @endfor
                    </div>
                    <div class="pr-form-hint">Optional — how would you rate your overall experience?</div>
                </div>
            </div>

            <div class="pr-form-group">
                <label class="pr-form-label">Subject <span class="pr-required">*</span></label>
                <input type="text" name="subject" maxlength="150" required
                       class="pr-form-control @error('subject') is-invalid @enderror"
                       placeholder="A short summary…" value="{{ old('subject') }}">
                @error('subject')<div class="pr-invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="pr-form-group">
                <label class="pr-form-label">Message <span class="pr-required">*</span></label>
                <textarea name="message" rows="4" required maxlength="3000"
                          class="pr-form-control @error('message') is-invalid @enderror"
                          placeholder="Tell us more…">{{ old('message') }}</textarea>
                @error('message')<div class="pr-invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div style="margin-top:18px;text-align:right;">
                <button type="submit" class="pr-btn pr-btn--navy">
                    <i class="bi bi-send-fill"></i> Submit Feedback
                </button>
            </div>
        </form>
    </div>

    {{-- History --}}
    <div class="pr-table-card">
        <div class="pr-table-card__header">
            <h3 class="pr-table-card__title">
                <i class="bi bi-clock-history me-2" style="color:var(--pr-gold);font-size:.9rem;"></i>
                My Feedback History
            </h3>
            <span class="pr-table-card__count">
                {{ $feedback->count() }} submission{{ $feedback->count() !== 1 ? 's' : '' }}
            </span>
        </div>

        @if($feedback->isEmpty())
            <div class="pr-empty">
                <div class="pr-empty__icon-wrap"><i class="bi bi-chat-square-heart"></i></div>
                <div class="pr-empty__title">No feedback submitted yet</div>
                <div class="pr-empty__sub">Use the form above to share your thoughts with the manager.</div>
            </div>
        @else
            <div class="pr-table-wrap">
                <table class="pr-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Rating</th>
                            <th>Feedback</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($feedback as $item)
                            <tr>
                                <td style="color:var(--pr-gray-500);font-size:.8rem;">{{ $loop->iteration }}</td>
                                <td style="color:var(--pr-gray-500);white-space:nowrap;">
                                    {{ $item->created_at->format('M d, Y') }}
                                </td>
                                <td><span class="pr-cat-tag">{{ $item->category }}</span></td>
                                <td style="white-space:nowrap;">
                                    @if($item->rating)
                                        <span class="pr-stars-readonly">
                                            @for($i = 1; $i <= 5; $i++)
                                                <i class="bi bi-star-fill {{ $i > $item->rating ? 'pr-star-empty' : '' }}"></i>
                                            @endfor
                                        </span>
                                    @else
                                        <span style="color:var(--pr-gray-500);font-size:.78rem;">—</span>
                                    @endif
                                </td>
                                <td style="max-width:280px;white-space:normal;">
                                    <div style="font-weight:600;color:var(--pr-navy);">{{ $item->subject }}</div>
                                    <div style="font-size:.78rem;color:var(--pr-gray-500);margin-top:2px;">
                                        {{ $item->message }}
                                    </div>
                                    @if($item->manager_response)
                                        <div style="font-size:.75rem;margin-top:6px;color:var(--pr-navy);">
                                            <strong>Manager:</strong> {{ $item->manager_response }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="pr-pill {{ $item->isReviewed() ? 'pr-pill--green' : 'pr-pill--amber' }}">
                                        {{ $item->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

@endsection

@push('scripts')
<script>
/* Star rating: the inputs sit in DOM order 5..1 so the CSS-only
   "checked ~ label" sibling trick would light up the wrong side for a
   left-to-right star row, so it's synced with a small script instead.
   Clicking a star sets that value; hovering previews it. */
document.addEventListener('DOMContentLoaded', function () {
    const wrap = document.getElementById('starRating');
    if (!wrap) return;

    const labels = Array.from(wrap.querySelectorAll('label'));

    function paint(value) {
        labels.forEach(label => {
            const filled = parseInt(label.dataset.value, 10) <= value;
            label.classList.toggle('pr-star--filled', filled);
        });
    }

    function currentValue() {
        const checked = wrap.querySelector('input:checked');
        return checked ? parseInt(checked.value, 10) : 0;
    }

    labels.forEach(label => {
        label.addEventListener('mouseenter', () => paint(parseInt(label.dataset.value, 10)));
        label.addEventListener('click', () => paint(parseInt(label.dataset.value, 10)));
    });

    wrap.addEventListener('mouseleave', () => paint(currentValue()));

    paint(currentValue());
});
</script>
@endpush