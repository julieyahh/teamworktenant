{{--
    Prevents double submission on every POST/PATCH form in the app.

    Why this exists: the server-side guards in TenantLeaseController,
    UnitController and ManagerController are the real protection against
    duplicate records. This is the UX layer on top of them — it stops the
    request from being fired twice in the first place, so the tenant sees a
    spinner instead of an "already pending" message.

    Notes:
      - The submit button is disabled on a setTimeout(0), NOT synchronously.
        Disabling it inside the handler would drop its name/value from the
        payload, which would break the Approve/Deny buttons that submit
        name="decision".
      - Forms are re-enabled after 12s so a failed/cancelled navigation
        doesn't leave the page permanently stuck.
--}}
<script>
(function () {
    'use strict';

    // Load once per page. Two copies (layout + a page that also includes
    // this partial) block each other: the second sees the form already
    // locked by the first and cancels the submit, so nothing ever saves.
    if (window.__prSingleSubmit) return;
    window.__prSingleSubmit = true;

    var LOCK_MS = 12000;

    function lockForm(form) {
        if (form.dataset.prSubmitting === '1') {
            return false;
        }
        form.dataset.prSubmitting = '1';

        var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');

        // Deferred so the clicked submitter is still serialised into the request.
        setTimeout(function () {
            buttons.forEach(function (btn) {
                btn.disabled = true;
                if (!btn.dataset.prOriginalHtml) {
                    btn.dataset.prOriginalHtml = btn.innerHTML;
                }
                if (btn.tagName === 'BUTTON') {
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Please wait…';
                }
                btn.style.opacity = '.7';
                btn.style.cursor = 'not-allowed';
            });
        }, 0);

        // Safety valve: release the lock if we're still on the page later.
        setTimeout(function () {
            form.dataset.prSubmitting = '0';
            buttons.forEach(function (btn) {
                btn.disabled = false;
                if (btn.dataset.prOriginalHtml) {
                    btn.innerHTML = btn.dataset.prOriginalHtml;
                }
                btn.style.opacity = '';
                btn.style.cursor = '';
            });
        }, LOCK_MS);

        return true;
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;

        if (!(form instanceof HTMLFormElement)) return;

        // Opt out with <form data-allow-multi-submit>
        if (form.hasAttribute('data-allow-multi-submit')) return;

        // GET forms (search / filter) are harmless to repeat.
        var method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method === 'get') return;

        if (!lockForm(form)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            return false;
        }
    }, true);

    // Also swallow rapid repeat clicks directly on the button.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('button[type="submit"], input[type="submit"]');
        if (!btn) return;

        var form = btn.form;
        if (!form || form.hasAttribute('data-allow-multi-submit')) return;

        if (form.dataset.prSubmitting === '1') {
            e.preventDefault();
            e.stopImmediatePropagation();
            return false;
        }
    }, true);
})();
</script>
