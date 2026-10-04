{{--
    Valid-ID camera capture (v19).

    Usage: put data-id-camera on a file input, then @include('partials.id-camera')
    once anywhere on the page.

        <input type="file" name="valid_id" data-id-camera accept="image/*" capture="environment" required>

    What it does:
      - Hides the file input and puts a "Capture Valid ID" box in its place.
      - Pressing it opens the device camera (rear camera on phones) with an
        ID-card guide frame. Capture → review → "Use photo" or "Retake".
      - The photo is written INTO the original file input as valid_id_<time>.jpg,
        then a normal 'change' event is fired. The form, the server validation
        and RegisterController do not know or care that it came from a camera.

    v19 fixes:
      - The captured photo now shows on the review screen. An author
        `display:block` was overriding the [hidden] attribute, so the stopped
        (black) <video> stayed on top of the captured <img>.
      - The native `required` attribute is removed from the visually-hidden
        input (the browser cannot focus a 1px invisible input, which caused the
        page to jump). The page's JS validation + the server enforce "required"
        and the error is shown right under the visible camera box.

    When the camera cannot be opened — the browser has no camera API, the page
    is not on HTTPS/localhost (browsers refuse camera access on plain http:// —
    this is the common one on a LAN IP), permission was denied, or there is no
    camera — it says why and offers "Take / choose a photo", which clicks the
    original input. On a phone, capture="environment" makes that open the
    camera app directly.

    Nothing is recorded: the stream is only a live preview and is stopped the
    moment the camera box closes. Only the single still you accept is uploaded.
--}}
@once
<style>
    .idcam-input {
        position: absolute !important; width: 1px !important; height: 1px !important;
        opacity: 0 !important; pointer-events: none !important; overflow: hidden !important;
        clip: rect(0 0 0 0) !important; margin: 0 !important; padding: 0 !important; border: 0 !important;
    }
    .idcam {
        border: 1.5px dashed rgba(12,26,58,.22); border-radius: 12px; background: #f8f9fb;
        padding: 14px; display: flex; align-items: center; gap: 14px; transition: border-color .2s, background .2s;
    }
    .idcam:hover { border-color: #0c1a3a; }
    .idcam-input.is-invalid ~ .idcam { border-color: #dc3545; background: #fff5f5; }
    .idcam.has-photo { border-style: solid; border-color: #16a34a; background: #f0fdf4; }
    .idcam__thumb {
        width: 86px; height: 56px; border-radius: 8px; flex-shrink: 0; overflow: hidden;
        background: rgba(12,26,58,.07); display: flex; align-items: center; justify-content: center;
        color: #0c1a3a; font-size: 1.4rem;
    }
    .idcam__thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .idcam__body { flex: 1; min-width: 0; }
    .idcam__title { font-weight: 600; font-size: .88rem; color: #0c1a3a; }
    .idcam__sub   { font-size: .74rem; color: #6b7280; margin-top: 2px; }
    .idcam__btn {
        display: inline-flex; align-items: center; gap: 6px; border: none; cursor: pointer;
        padding: 8px 14px; border-radius: 10px; font-size: .8rem; font-weight: 600; white-space: nowrap;
        background: linear-gradient(135deg, #0c1a3a, #132044); color: #fff; font-family: inherit;
    }
    .idcam__btn:hover { filter: brightness(1.15); }
    .idcam__btn--ghost { background: transparent; color: #0c1a3a; border: 1.5px solid rgba(12,26,58,.2); }
    .idcam__btn--ghost:hover { background: rgba(12,26,58,.05); filter: none; }
    .idcam__actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
    @media (max-width: 480px) {
        .idcam { flex-wrap: wrap; }
        .idcam__actions { width: 100%; justify-content: stretch; }
        .idcam__actions .idcam__btn { flex: 1; justify-content: center; }
    }

    /* ---------- Camera overlay ---------- */
    .idcam-modal {
        position: fixed; inset: 0; z-index: 3000; background: rgba(6,12,28,.92);
        display: none; flex-direction: column; align-items: center; justify-content: center;
        padding: 16px; color: #fff; font-family: 'DM Sans', system-ui, sans-serif;
    }
    .idcam-modal.is-open { display: flex; }
    .idcam-modal__head { width: 100%; max-width: 760px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .idcam-modal__title { font-weight: 700; font-size: 1rem; }
    .idcam-modal__x { background: rgba(255,255,255,.12); border: none; color: #fff; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; font-size: 1.1rem; }
    .idcam-stage {
        position: relative; width: 100%; max-width: 760px; aspect-ratio: 4 / 3; max-height: calc(100vh - 190px);
        background: #000; border-radius: 16px; overflow: hidden;
    }
    /* FIX: video and still are stacked in the same spot, and [hidden] is
       restored explicitly because `display:block` would otherwise override it. */
    .idcam-stage video, .idcam-stage img {
        position: absolute; inset: 0;
        width: 100%; height: 100%;
        object-fit: contain; display: block; background: #000;
    }
    .idcam-stage video[hidden], .idcam-stage img[hidden] { display: none; }
    .idcam-guide {
        position: absolute; left: 50%; top: 50%; width: 82%; aspect-ratio: 1.586 / 1; max-height: 86%;
        transform: translate(-50%, -50%); border: 3px solid rgba(201,168,76,.95); border-radius: 14px;
        box-shadow: 0 0 0 9999px rgba(0,0,0,.35); pointer-events: none;
    }
    .idcam-guide[hidden] { display: none; }
    .idcam-hint {
        position: absolute; left: 0; right: 0; bottom: 10px; text-align: center; font-size: .82rem;
        text-shadow: 0 1px 3px rgba(0,0,0,.8); pointer-events: none;
    }
    .idcam-hint[hidden] { display: none; }
    .idcam-status {
        position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center;
        text-align: center; padding: 24px; gap: 12px; background: #0c1a3a; z-index: 2;
    }
    .idcam-status[hidden] { display: none; }
    .idcam-status__icon { font-size: 2.2rem; opacity: .85; }
    .idcam-status__msg { font-size: .9rem; max-width: 440px; line-height: 1.5; color: rgba(255,255,255,.85); }
    .idcam-spin { width: 38px; height: 38px; border-radius: 50%; border: 3px solid rgba(255,255,255,.2); border-top-color: #c9a84c; animation: idcamSpin .8s linear infinite; }
    @keyframes idcamSpin { to { transform: rotate(360deg); } }
    .idcam-bar { width: 100%; max-width: 760px; display: flex; align-items: center; justify-content: center; gap: 14px; margin-top: 14px; flex-wrap: wrap; }
    .idcam-bar[hidden] { display: none; }
    .idcam-shutter {
        width: 68px; height: 68px; border-radius: 50%; border: 4px solid #fff; background: #c9a84c; cursor: pointer;
        box-shadow: 0 0 0 4px rgba(201,168,76,.35); transition: transform .1s;
    }
    .idcam-shutter:active { transform: scale(.94); }
    .idcam-shutter:disabled { opacity: .4; cursor: not-allowed; }
    .idcam-round { background: rgba(255,255,255,.12); color: #fff; border: none; width: 46px; height: 46px; border-radius: 50%; cursor: pointer; font-size: 1.1rem; }
    .idcam-round[hidden] { display: none; }
    .idcam-pill { border: none; cursor: pointer; padding: 11px 20px; border-radius: 999px; font-weight: 600; font-size: .88rem; display: inline-flex; align-items: center; gap: 7px; font-family: inherit; }
    .idcam-pill--gold  { background: #c9a84c; color: #0c1a3a; }
    .idcam-pill--ghost { background: rgba(255,255,255,.12); color: #fff; }
    .idcam-flash { position: absolute; inset: 0; background: #fff; opacity: 0; pointer-events: none; transition: opacity .25s; z-index: 3; }
    .idcam-flash.on { opacity: .8; transition: none; }
</style>

<script>
(function () {
    'use strict';

    var MAX_BYTES = 5 * 1024 * 1024;   // matches the server's max:5120
    var MAX_SIDE  = 2000;              // plenty to read an ID; keeps the upload small

    var modal, video, still, statusBox, statusIcon, statusMsg, statusActions,
        liveBar, reviewBar, shutter, switchBtn, flash, hint, guide;
    var stream = null, devices = [], deviceIndex = 0, currentInput = null, capturedBlob = null;

    function el(tag, attrs, html) {
        var n = document.createElement(tag);
        if (attrs) Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });
        if (html != null) n.innerHTML = html;
        return n;
    }

    /* ------------------------------------------------------------------
     |  Camera overlay (one per page, built on first use)
     * ------------------------------------------------------------------ */
    function buildModal() {
        if (modal) return;
        modal = el('div', { 'class': 'idcam-modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Capture valid ID' });
        modal.innerHTML =
            '<div class="idcam-modal__head">' +
                '<div class="idcam-modal__title" data-idcam-title>' + '<i class="bi bi-person-vcard"></i> Capture your Valid ID' + '</div>' +
                '<button type="button" class="idcam-modal__x" data-idcam="close" aria-label="Close">&times;</button>' +
            '</div>' +
            '<div class="idcam-stage">' +
                '<video playsinline muted autoplay></video>' +
                '<img alt="Captured ID" hidden>' +
                '<div class="idcam-guide"></div>' +
                '<div class="idcam-hint" data-idcam-hint>' +
                    'Place the ID inside the frame · good light, no glare' +
                '</div>' +
                '<div class="idcam-flash"></div>' +
                '<div class="idcam-status" hidden>' +
                    '<div class="idcam-status__icon"></div>' +
                    '<div class="idcam-status__msg"></div>' +
                    '<div class="idcam-status__actions" style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;"></div>' +
                '</div>' +
            '</div>' +
            '<div class="idcam-bar" data-bar="live">' +
                '<button type="button" class="idcam-round" data-idcam="close" aria-label="Cancel"><i class="bi bi-x-lg"></i></button>' +
                '<button type="button" class="idcam-shutter" data-idcam="shoot" aria-label="Take photo"></button>' +
                '<button type="button" class="idcam-round" data-idcam="switch" aria-label="Switch camera" hidden><i class="bi bi-arrow-repeat"></i></button>' +
            '</div>' +
            '<div class="idcam-bar" data-bar="review" hidden>' +
                '<button type="button" class="idcam-pill idcam-pill--ghost" data-idcam="retake"><i class="bi bi-arrow-counterclockwise"></i> Retake</button>' +
                '<button type="button" class="idcam-pill idcam-pill--gold" data-idcam="use"><i class="bi bi-check-lg"></i> Use this photo</button>' +
            '</div>';

        video         = modal.querySelector('video');
        still         = modal.querySelector('.idcam-stage img');
        guide         = modal.querySelector('.idcam-guide');
        hint          = modal.querySelector('.idcam-hint');
        flash         = modal.querySelector('.idcam-flash');
        statusBox     = modal.querySelector('.idcam-status');
        statusIcon    = modal.querySelector('.idcam-status__icon');
        statusMsg     = modal.querySelector('.idcam-status__msg');
        statusActions = modal.querySelector('.idcam-status__actions');
        liveBar       = modal.querySelector('[data-bar="live"]');
        reviewBar     = modal.querySelector('[data-bar="review"]');
        shutter       = modal.querySelector('[data-idcam="shoot"]');
        switchBtn     = modal.querySelector('[data-idcam="switch"]');

        modal.addEventListener('click', function (e) {
            var b = e.target.closest('[data-idcam]');
            if (!b) return;
            var a = b.getAttribute('data-idcam');
            if (a === 'close')    close();
            if (a === 'shoot')    shoot();
            if (a === 'retake')   retake();
            if (a === 'use')      usePhoto();
            if (a === 'switch')   switchCamera();
            if (a === 'fallback') fallback();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) { e.stopPropagation(); close(); }
        }, true);
    }

    function showStatus(icon, msg, actionsHtml) {
        statusBox.hidden = false;
        statusIcon.innerHTML = icon;
        statusMsg.innerHTML = msg;
        statusActions.innerHTML = actionsHtml || '';
        guide.hidden = true; hint.hidden = true;
        liveBar.hidden = true; reviewBar.hidden = true;
    }

    function showLive() {
        statusBox.hidden = true;
        video.hidden = false; still.hidden = true;
        guide.hidden = false; hint.hidden = false;
        liveBar.hidden = false; reviewBar.hidden = true;
        shutter.disabled = false;
        switchBtn.hidden = devices.length < 2;
    }

    function showReview(url) {
        statusBox.hidden = true;
        still.src = url;
        still.hidden = false;
        video.hidden = true;
        guide.hidden = true; hint.hidden = true;
        liveBar.hidden = true; reviewBar.hidden = false;
    }

    function fallbackButton(label) {
        return '<button type="button" class="idcam-pill idcam-pill--gold" data-idcam="fallback">' +
               '<i class="bi bi-camera"></i> ' + (label || 'Take / choose a photo instead') + '</button>' +
               '<button type="button" class="idcam-pill idcam-pill--ghost" data-idcam="close">Cancel</button>';
    }

    /* ------------------------------------------------------------------
     |  Open / close
     * ------------------------------------------------------------------ */
    function open(input) {
        buildModal();
        currentInput = input;
        capturedBlob = null;

        var side = input.dataset.idSide || 'front';
        var sideTitle = side === 'back' ? 'Back' : 'Front';

        modal.querySelector('[data-idcam-title]').innerHTML =
            '<i class="bi bi-person-vcard"></i> Capture Valid ID - ' + sideTitle;

        modal.querySelector('[data-idcam-hint]').textContent =
            'Place the ' + side.toLowerCase() +
            ' of your ID inside the frame · good light, no glare';

        // Inside a Bootstrap modal, live in that modal so its focus trap
        // doesn't fight us; otherwise on <body>.
        var host = input.closest('.modal') || document.body;
        if (modal.parentNode !== host) host.appendChild(modal);

        modal.classList.add('is-open');
        document.documentElement.style.overflow = 'hidden';

        if (!window.isSecureContext) {
            showStatus('<i class="bi bi-shield-lock"></i>',
                'Your browser only allows the camera on a secure (https://) page, so it can\'t be opened here.' +
                '<br><small>On a phone, the button below opens your camera app.</small>',
                fallbackButton());
            return;
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showStatus('<i class="bi bi-camera-video-off"></i>',
                'This browser can\'t open the camera.', fallbackButton());
            return;
        }
        startStream();
    }

    function startStream() {
        stopStream();
        showStatus('<div class="idcam-spin"></div>', 'Opening camera… allow camera access if your browser asks.');

        var constraints = { audio: false, video: { width: { ideal: 1920 }, height: { ideal: 1080 } } };
        if (devices.length && devices[deviceIndex]) {
            constraints.video.deviceId = { exact: devices[deviceIndex].deviceId };
        } else {
            constraints.video.facingMode = { ideal: 'environment' };   // rear camera on phones
        }

        navigator.mediaDevices.getUserMedia(constraints).then(function (s) {
            stream = s;
            video.srcObject = s;
            var p = video.play();
            if (p && p.catch) p.catch(function () {});

            // Labels/ids are only available after permission is granted.
            navigator.mediaDevices.enumerateDevices().then(function (list) {
                devices = list.filter(function (d) { return d.kind === 'videoinput'; });
                var track = s.getVideoTracks()[0];
                var id = track && track.getSettings ? track.getSettings().deviceId : null;
                devices.forEach(function (d, i) { if (d.deviceId === id) deviceIndex = i; });
                switchBtn.hidden = devices.length < 2;
            }).catch(function () {});

            showLive();
        }).catch(function (err) {
            var name = err && err.name;
            var msg = name === 'NotAllowedError' || name === 'SecurityError'
                ? 'Camera access was blocked. Allow the camera for this site in your browser settings, then try again.'
                : name === 'NotFoundError' || name === 'OverconstrainedError'
                    ? 'No camera was found on this device.'
                    : name === 'NotReadableError'
                        ? 'The camera is being used by another app. Close it and try again.'
                        : 'The camera could not be started.';
            showStatus('<i class="bi bi-camera-video-off"></i>', msg,
                '<button type="button" class="idcam-pill idcam-pill--ghost" data-idcam="retake"><i class="bi bi-arrow-repeat"></i> Try again</button>' +
                fallbackButton());
        });
    }

    function stopStream() {
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
        if (video) video.srcObject = null;
    }

    function revokeStill() {
        if (still && still.src && still.src.indexOf('blob:') === 0) URL.revokeObjectURL(still.src);
    }

    function close() {
        stopStream();
        if (modal) modal.classList.remove('is-open');
        document.documentElement.style.overflow = '';
        revokeStill();
        capturedBlob = null;
    }

    function switchCamera() {
        if (devices.length < 2) return;
        deviceIndex = (deviceIndex + 1) % devices.length;
        startStream();
    }

    /* ------------------------------------------------------------------
     |  Capture
     * ------------------------------------------------------------------ */
    function shoot() {
        if (!stream || !video.videoWidth) return;
        shutter.disabled = true;

        var w = video.videoWidth, h = video.videoHeight;
        var scale = Math.min(1, MAX_SIDE / Math.max(w, h));
        var canvas = document.createElement('canvas');
        canvas.width = Math.round(w * scale);
        canvas.height = Math.round(h * scale);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

        flash.classList.add('on');
        setTimeout(function () { flash.classList.remove('on'); }, 60);

        toJpeg(canvas, 0.92, function (blob) {
            if (!blob) {
                showStatus('<i class="bi bi-exclamation-triangle"></i>', 'The photo could not be taken.', fallbackButton());
                return;
            }
            capturedBlob = blob;
            stopStream();                      // freeze: nothing keeps streaming while they review
            revokeStill();
            showReview(URL.createObjectURL(blob));
        });
    }

    // Step the quality down until it fits under the server limit.
    function toJpeg(canvas, q, done) {
        canvas.toBlob(function (blob) {
            if (blob && blob.size > MAX_BYTES && q > 0.5) return toJpeg(canvas, q - 0.1, done);
            done(blob);
        }, 'image/jpeg', q);
    }

    function retake() {
        revokeStill();
        capturedBlob = null;
        startStream();
    }

    function usePhoto() {
        if (!capturedBlob || !currentInput) return;
        var file = new File([capturedBlob], 'valid_id_' + Date.now() + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
        try {
            var dt = new DataTransfer();
            dt.items.add(file);
            currentInput.files = dt.files;
        } catch (e) {
            // Very old browsers can't set input.files programmatically.
            showStatus('<i class="bi bi-exclamation-triangle"></i>',
                'This browser can\'t attach the photo automatically.', fallbackButton('Choose the photo instead'));
            return;
        }
        var input = currentInput;
        close();
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function fallback() {
        var input = currentInput;
        close();
        if (input) input.click();
    }

    /* ------------------------------------------------------------------
     |  The inline box that replaces each file input
     * ------------------------------------------------------------------ */
    function render(input, box) {
        var file = input.files && input.files[0];

        var side = input.dataset.idSide || 'front';
        var sideText = side === 'back' ? 'back' : 'front';
        var sideTitle = side === 'back' ? 'Back' : 'Front';

        var thumb = box.querySelector('.idcam__thumb');
        var title = box.querySelector('.idcam__title');
        var sub   = box.querySelector('.idcam__sub');
        var main  = box.querySelector('[data-role="open"]');

        if (thumb.dataset.url) {
            URL.revokeObjectURL(thumb.dataset.url);
            delete thumb.dataset.url;
        }

        if (!file) {
            box.classList.remove('has-photo');

            thumb.innerHTML = '<i class="bi bi-person-vcard"></i>';

            title.textContent = 'Capture Valid ID - ' + sideTitle;

            sub.textContent =
                'Your camera will open. Government-issued ID, ' +
                sideText + ' side.';

            main.innerHTML =
                '<i class="bi bi-camera-fill"></i> Open Camera';

            return;
        }

        box.classList.add('has-photo');

        if (file.type && file.type.indexOf('image/') === 0) {
            var url = URL.createObjectURL(file);

            thumb.dataset.url = url;

            thumb.innerHTML =
                '<img alt="Valid ID ' + sideTitle + ' preview" src="' + url + '">';
        } else {
            thumb.innerHTML =
                '<i class="bi bi-file-earmark-pdf"></i>';
        }

        title.textContent = 'Valid ID ' + sideTitle + ' captured';

        var kb = Math.round(file.size / 1024);

        sub.textContent =
            (kb > 1024
                ? (kb / 1024).toFixed(1) + ' MB'
                : kb + ' KB'
            ) + ' · make sure all text is readable';

        main.innerHTML =
            '<i class="bi bi-arrow-counterclockwise"></i> Retake';
    }

    function enhance(input) {
        if (input.dataset.idcamReady) return;
        input.dataset.idcamReady = '1';
        input.classList.add('idcam-input');
        input.setAttribute('tabindex', '-1');

        // A visually-hidden input can't show the native "required" bubble or
        // be focused, so the browser would block the submit silently / jump
        // the page around. The page's JS validation and the server enforce
        // "required" instead, and the error appears under the visible box.
        if (input.required) {
            input.dataset.idcamRequired = '1';
            input.required = false;
        }

        var box = el('div', { 'class': 'idcam' });
        box.innerHTML =
            '<div class="idcam__thumb"></div>' +
            '<div class="idcam__body"><div class="idcam__title"></div><div class="idcam__sub"></div></div>' +
            '<div class="idcam__actions">' +
                '<button type="button" class="idcam__btn" data-role="open"></button>' +
            '</div>';
        input.insertAdjacentElement('afterend', box);

        box.querySelector('[data-role="open"]').addEventListener('click', function () { open(input); });
        input.addEventListener('change', function () { render(input, box); });
        if (input.form) input.form.addEventListener('reset', function () { setTimeout(function () { render(input, box); }, 0); });

        render(input, box);
    }

    function init() {
        document.querySelectorAll('input[type="file"][data-id-camera]').forEach(enhance);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

    // Leaving the page (or bfcache) must never leave the camera light on.
    window.addEventListener('pagehide', stopStream);
    document.addEventListener('visibilitychange', function () {
        if (document.hidden && stream) close();
    });
})();
</script>
@endonce