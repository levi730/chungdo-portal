// Scanning at the check-in list: a hardware scanner (barcode_scanner.js) or,
// on a touch device, the camera. Both end at the same details URL, and nothing
// is written until someone taps Check In there.
(function () {
    const root = document.getElementById('check-in-camera');
    if (!root) {
        return;
    }

    const RESUME_KEY = 'checkInCameraUntil';
    const RESUME_MINUTES = 10;

    const button = document.getElementById('check-in-camera-button');
    const closeButton = document.getElementById('check-in-camera-close');
    const video = document.getElementById('check-in-camera-video');
    const message = document.getElementById('check-in-camera-message');
    const prompt = message.textContent;

    let scanner = null;
    let starting = false;
    let scanned = false;
    let messageTimer = null;

    function registrantsFrom(text) {
        try {
            const ids = JSON.parse(text).registrants;

            return Array.isArray(ids) && ids.length && ids.every(Number.isInteger) ? ids : null;
        } catch (e) {
            return null;
        }
    }

    function openDetails(ids) {
        location.href = root.dataset.checkInUrl + '/' + ids.join(',') + '?scan=1';
    }

    // Keep the camera going between people. Storage can be unavailable; the
    // only cost then is tapping Scan QR again.
    function rememberCamera() {
        try {
            sessionStorage.setItem(RESUME_KEY, Date.now() + RESUME_MINUTES * 60000);
        } catch (e) {}
    }

    function forgetCamera() {
        try {
            sessionStorage.removeItem(RESUME_KEY);
        } catch (e) {}
    }

    function cameraWasRunning() {
        try {
            return Number(sessionStorage.getItem(RESUME_KEY)) > Date.now();
        } catch (e) {
            return false;
        }
    }

    function showMessage(text, seconds) {
        clearTimeout(messageTimer);
        message.textContent = text;
        if (seconds) {
            messageTimer = setTimeout(function () {
                message.textContent = prompt;
            }, seconds * 1000);
        }
    }

    function onResult(result) {
        if (scanned) {
            return;
        }

        const ids = registrantsFrom(result.data);
        if (!ids) {
            showMessage('Not a check-in code', 2);

            return;
        }

        scanned = true;
        rememberCamera();
        scanner.stop();
        showMessage('Opening…');
        openDetails(ids);
    }

    async function openCamera() {
        if (starting) {
            return;
        }
        starting = true;
        scanned = false;
        root.hidden = false;
        document.body.style.overflow = 'hidden';
        showMessage(prompt);

        try {
            if (!scanner) {
                const QrScanner = (await import(root.dataset.scannerUrl)).default;
                scanner = new QrScanner(video, onResult, {
                    preferredCamera: 'environment',
                    returnDetailedScanResult: true,
                    highlightScanRegion: true,
                    onDecodeError: function () {},
                });
            }
            await scanner.start();
            // Close may have been tapped while the camera was still starting.
            if (root.hidden) {
                scanner.stop();
            } else {
                rememberCamera();
            }
        } catch (e) {
            forgetCamera();
            showMessage('Could not start the camera. Check that this site is allowed to use it.');
        } finally {
            starting = false;
        }
    }

    function closeCamera() {
        forgetCamera();
        if (scanner) {
            scanner.stop();
        }
        root.hidden = true;
        document.body.style.overflow = '';
    }

    document.addEventListener('scanComplete', function (e) {
        // Typing in the search box and pressing Enter lands here too, so
        // anything that is not a code is ignored without comment.
        const ids = registrantsFrom(e.detail);
        if (ids) {
            openDetails(ids);
        }
    });

    if (!window.matchMedia('(any-pointer: coarse)').matches || !navigator.mediaDevices?.getUserMedia) {
        return;
    }

    button.hidden = false;
    button.addEventListener('click', openCamera);
    closeButton.addEventListener('click', closeCamera);

    window.addEventListener('pagehide', function () {
        if (scanner) {
            scanner.stop();
        }
    });

    // Also fires when the back button restores this page from the browser's
    // cache, where a stopped camera would otherwise sit on a black screen.
    window.addEventListener('pageshow', function () {
        if (cameraWasRunning()) {
            openCamera();
        }
    });
})();
