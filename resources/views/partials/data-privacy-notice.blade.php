{{--
    Data Privacy Notice — RA 10173, the Data Privacy Act of 2012 (v18).

    Included once by layouts.app, so it is on every public page. Any element
    with data-privacy-open opens it:

        <a href="#" data-privacy-open>Data Privacy Notice</a>

    It is a plain overlay rather than a Bootstrap modal on purpose: the
    registration form already lives inside a Bootstrap modal (#unitModal), and
    stacking a second one there is what forced the hide/re-show juggling the
    Terms modal does. This one moves itself into whichever .modal opened it.

    If the notice has an "I agree" button (data-privacy-agree) and the page has
    a checkbox named data_privacy, agreeing ticks it for them.

    Business details (contact, retention periods, version) come from
    config/privacy.php — confirm them before going live.
--}}
@once
@php
    $pv = config('privacy');
    $contactEmail = $pv['contact_email'] ?? null;
@endphp
<style>
    .dpn { position: fixed; inset: 0; z-index: 2900; display: none; align-items: center; justify-content: center; padding: 16px; background: rgba(12,26,58,.6); backdrop-filter: blur(2px); }
    .dpn.is-open { display: flex; }
    .dpn__box { background: #fff; border-radius: 20px; width: 100%; max-width: 760px; max-height: calc(100vh - 32px); display: flex; flex-direction: column; box-shadow: 0 32px 80px rgba(12,26,58,.35); overflow: hidden; font-family: 'DM Sans', system-ui, sans-serif; }
    .dpn__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 24px; background: linear-gradient(135deg, #0c1a3a, #132044); color: #fff; }
    .dpn__eyebrow { font-size: .66rem; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.65); font-weight: 600; }
    .dpn__title { font-family: 'Playfair Display', Georgia, serif; font-size: 1.15rem; font-weight: 700; margin: 0; }
    .dpn__x { width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,.12); border: none; color: #fff; font-size: 1.1rem; cursor: pointer; flex-shrink: 0; }
    .dpn__body { padding: 20px 24px; overflow-y: auto; font-size: .86rem; line-height: 1.65; color: #333; }
    .dpn__body h6 { font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #0c1a3a; margin: 18px 0 6px; }
    .dpn__body h6:first-child { margin-top: 0; }
    .dpn__body ul { padding-left: 18px; margin: 0 0 6px; }
    .dpn__body li { margin-bottom: 3px; }
    .dpn__body p { margin: 0 0 8px; }
    .dpn__lead { background: #faf8f3; border: 1px solid rgba(201,168,76,.3); border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; }
    .dpn__foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; padding: 14px 24px; border-top: 1px solid rgba(12,26,58,.08); background: rgba(12,26,58,.02); }
    .dpn__ver { font-size: .72rem; color: #7a7a7a; }
    .dpn__btn { border: none; cursor: pointer; border-radius: 10px; padding: 10px 18px; font-weight: 600; font-size: .85rem; font-family: inherit; }
    .dpn__btn--ghost { background: transparent; border: 1.5px solid rgba(12,26,58,.18); color: #0c1a3a; }
    .dpn__btn--gold { background: linear-gradient(135deg, #c9a84c, #e2c580); color: #0c1a3a; }
    .dpn__btn[hidden] { display: none; }
    /* the consent row on the forms */
    .dpn-consent small { display: block; color: #7a7a7a; font-size: .74rem; margin-top: 2px; }
</style>

<div class="dpn" id="privacyNotice" role="dialog" aria-modal="true" aria-labelledby="privacyNoticeTitle" aria-hidden="true">
    <div class="dpn__box">
        <div class="dpn__head">
            <div>
                <div class="dpn__eyebrow">Republic Act No. 10173</div>
                <h5 class="dpn__title" id="privacyNoticeTitle">Data Privacy Notice</h5>
            </div>
            <button type="button" class="dpn__x" data-privacy-close aria-label="Close">&times;</button>
        </div>

        <div class="dpn__body">
            <div class="dpn__lead">
                Pinikitan Rental respects your privacy. This notice explains what personal information we
                collect when you register and apply for a unit, why we need it, how we protect it, and
                your rights under the <strong>Data Privacy Act of 2012 (RA 10173)</strong>.
            </div>

            <h6>1. What we collect</h6>
            <ul>
                <li><strong>Identity &amp; contact:</strong> full name, email address, contact number, birthdate and current address.</li>
                <li><strong>Application details:</strong> preferred unit and move-in date, reason for renting, employment status, employer or school, source of income and monthly income (if given).</li>
                <li><strong>Emergency contact:</strong> the name, number and relationship of the person you list. Please let them know you have shared their details with us.</li>
                <li><strong>Identification:</strong> a photo of your government-issued valid ID and your 1&times;1 ID picture.</li>
                <li><strong>Tenancy records</strong> (once approved): lease details, payments and proofs of payment, utility bills, maintenance and renovation requests, and notices sent to you.</li>
            </ul>

            <h6>2. Why we collect it</h6>
            <ul>
                <li>To evaluate your application and verify your identity.</li>
                <li>To prepare and manage your lease, billing, payments and receipts.</li>
                <li>To contact you by email or SMS about your application, bills, due dates and property matters.</li>
                <li>To reach your emergency contact if something happens.</li>
                <li>To keep the records the law requires of a lessor.</li>
            </ul>
            <p>We do not sell your personal information or use it for advertising.</p>

            <h6>3. Your ID photo and the camera</h6>
            <p>
                When you capture your valid ID, your device camera is used only while the capture window
                is open. Nothing is recorded — only the single photo you choose to use is uploaded with
                your application. You can retake it as many times as you need before submitting.
            </p>

            <h6>4. Who can see it</h6>
            <p>
                Only the property manager and authorized staff of Pinikitan Rental. We share it with others
                only when required by law or a lawful order, or with your consent. Email and SMS providers
                we use to send you messages receive only what is needed to deliver them.
            </p>

            <h6>5. How we protect and keep it</h6>
            <p>
                Your data is stored on our secured system and is accessible only through authorized
                accounts. We keep tenancy records for the duration of your lease and up to
                <strong>{{ $pv['retention_years_after_lease'] ?? 5 }} years</strong> after it ends, for legal and
                accounting purposes. Documents from applications that are rejected or withdrawn are kept
                for up to <strong>{{ $pv['rejected_retention_months'] ?? 6 }} months</strong>. After that, they are
                securely deleted.
            </p>

            <h6>6. Your rights</h6>
            <p>As a data subject you have the right to:</p>
            <ul>
                <li>be informed about how your data is processed;</li>
                <li>access your personal data and ask for a copy;</li>
                <li>have inaccurate or incomplete data corrected;</li>
                <li>object to processing, or withdraw your consent;</li>
                <li>have your data blocked or erased when it is no longer needed or was unlawfully processed;</li>
                <li>data portability;</li>
                <li>be compensated for damages caused by inaccurate, false or unlawfully obtained data; and</li>
                <li>file a complaint with the <strong>National Privacy Commission</strong> (privacy.gov.ph).</li>
            </ul>
            <p>
                Withdrawing consent may mean we can no longer process your application or manage your lease,
                but it will not affect anything done lawfully before you withdrew it.
            </p>

            <h6>7. Contact us</h6>
            <p>
                For questions or to exercise your rights, contact
                <strong>{{ $pv['contact_name'] ?? 'the management office' }}</strong>@if($contactEmail)
                at <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>@endif @if(!empty($pv['contact_phone'])) or {{ $pv['contact_phone'] }}@endif.
            </p>

            <h6>8. Consent</h6>
            <p>
                By ticking the Data Privacy consent box, you confirm that you have read this notice and you
                freely give your consent to Pinikitan Rental collecting and processing your personal
                information for the purposes above.
            </p>
        </div>

        <div class="dpn__foot">
            <span class="dpn__ver">Notice version {{ $pv['version'] ?? '' }}</span>
            <div style="display:flex;gap:8px;">
                <button type="button" class="dpn__btn dpn__btn--ghost" data-privacy-close>Close</button>
                <button type="button" class="dpn__btn dpn__btn--gold" data-privacy-agree hidden>I have read this and I agree</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var notice = document.getElementById('privacyNotice');
    if (!notice) return;
    var agreeBtn = notice.querySelector('[data-privacy-agree]');
    var lastTrigger = null;

    function consentBox() {
        var host = lastTrigger && (lastTrigger.closest('form') || lastTrigger.closest('.modal'));
        return (host && host.querySelector('input[name="data_privacy"]'))
            || document.querySelector('input[name="data_privacy"]');
    }

    function open(trigger) {
        lastTrigger = trigger || null;
        // Live inside the Bootstrap modal that opened us, so its focus trap
        // doesn't pull focus back out.
        var host = (trigger && trigger.closest('.modal')) || document.body;
        if (notice.parentNode !== host) host.appendChild(notice);
        agreeBtn.hidden = !consentBox();
        notice.classList.add('is-open');
        notice.setAttribute('aria-hidden', 'false');
        notice.querySelector('.dpn__body').scrollTop = 0;
        notice.querySelector('.dpn__x').focus();
    }

    function close() {
        notice.classList.remove('is-open');
        notice.setAttribute('aria-hidden', 'true');
        if (lastTrigger && lastTrigger.focus) lastTrigger.focus();
    }

    window.openPrivacyNotice = open;

    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-privacy-open]');
        if (t) { e.preventDefault(); open(t); return; }
        if (e.target.closest('[data-privacy-close]') || e.target === notice) { close(); return; }
        if (e.target.closest('[data-privacy-agree]')) {
            var box = consentBox();
            if (box) {
                box.checked = true;
                box.dispatchEvent(new Event('change', { bubbles: true }));
            }
            close();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && notice.classList.contains('is-open')) { e.stopPropagation(); close(); }
    }, true);
})();
</script>
@endonce
