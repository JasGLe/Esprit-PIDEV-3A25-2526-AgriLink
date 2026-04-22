/**
 * Voice Input — Speech-to-Text for registration forms
 * Uses MediaRecorder + OpenAI Whisper (via @xenova/transformers in a Web Worker).
 * Works on any network — no Google servers involved.
 * Model (~74 MB) is downloaded from Hugging Face on first use and cached in the browser.
 *
 * Attach to any input/textarea with:  data-voice-input
 * Optional language override:         data-voice-lang="fr-FR"  (defaults to fr-FR)
 */
(function () {
    'use strict';

    if (!navigator.mediaDevices || !window.MediaRecorder || !window.AudioContext) return;

    // ── Shared worker (one instance for the whole page) ──────────────────
    let worker         = null;
    let pendingResolve = null;
    let pendingReject  = null;
    let progressToast  = null;

    function getWorker() {
        if (worker) return worker;

        worker = new Worker('/js/voice-worker.js', { type: 'module' });

        worker.addEventListener('message', function ({ data }) {
            if (data.type === 'progress') {
                const label = 'Chargement modèle vocal… ' + data.progress + '%';
                if (!progressToast) {
                    progressToast = showToast(label, 'info', true);
                } else {
                    progressToast.textContent = label;
                }
            } else if (data.type === 'result') {
                clearProgressToast();
                if (pendingResolve) {
                    var cb = pendingResolve;
                    pendingResolve = pendingReject = null;
                    cb(data.transcript);
                }
            } else if (data.type === 'error') {
                clearProgressToast();
                if (pendingReject) {
                    var cb = pendingReject;
                    pendingResolve = pendingReject = null;
                    cb(new Error(data.message));
                }
            }
        });

        worker.addEventListener('error', function (e) {
            clearProgressToast();
            if (pendingReject) {
                var cb = pendingReject;
                pendingResolve = pendingReject = null;
                cb(new Error('Worker error: ' + e.message));
            }
        });

        return worker;
    }

    function clearProgressToast() {
        if (progressToast) { progressToast.remove(); progressToast = null; }
    }

    function transcribeAudio(float32, lang) {
        return new Promise(function (resolve, reject) {
            pendingResolve = resolve;
            pendingReject  = reject;
            // Transfer buffer ownership (zero-copy) to the worker
            getWorker().postMessage({ type: 'transcribe', audio: float32, lang: lang }, [float32.buffer]);
        });
    }

    // ── Audio helpers ────────────────────────────────────────────────────
    async function audioToFloat32(blob) {
        var arrayBuffer = await blob.arrayBuffer();
        var ctx         = new (window.AudioContext || window.webkitAudioContext)();
        var decoded;
        try   { decoded = await ctx.decodeAudioData(arrayBuffer); }
        finally { ctx.close(); }

        // Whisper requires 16 kHz mono Float32Array
        var targetRate = 16000;
        var offCtx     = new OfflineAudioContext(
            1,
            Math.ceil(decoded.duration * targetRate),
            targetRate
        );
        var src = offCtx.createBufferSource();
        src.buffer = decoded;
        src.connect(offCtx.destination);
        src.start(0);
        var rendered = await offCtx.startRendering();
        return rendered.getChannelData(0);
    }

    // ── Per-button state machine ─────────────────────────────────────────
    // States: 'idle' | 'recording' | 'processing'
    var activeBtn     = null;
    var mediaStream   = null;
    var mediaRecorder = null;
    var audioChunks   = [];

    var STATE_TITLES = {
        idle:       'Dicter',
        recording:  'Enregistrement… (cliquez pour arrêter)',
        processing: 'Transcription en cours…',
    };

    function setState(btn, state) {
        btn.classList.remove('recording', 'processing');
        if (state === 'recording')  btn.classList.add('recording');
        if (state === 'processing') btn.classList.add('processing');
        btn.title = STATE_TITLES[state] || 'Dicter';
        activeBtn = (state === 'idle') ? null : btn;
    }

    function stopRecording() {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
    }

    async function handleButtonClick(input, btn) {
        // Toggle off
        if (activeBtn === btn) { stopRecording(); return; }
        // Cancel another active button
        if (activeBtn) stopRecording();

        // 1 — Request microphone
        try {
            mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch (err) {
            var msgs = {
                NotAllowedError:       'Microphone refusé. Autorisez-le dans les paramètres du navigateur.',
                PermissionDeniedError: 'Microphone refusé. Autorisez-le dans les paramètres du navigateur.',
                NotFoundError:         'Aucun microphone détecté.',
            };
            showToast(msgs[err.name] || ('Microphone indisponible : ' + err.message), 'danger');
            return;
        }

        // 2 — Pick best supported MIME type
        var mimeTypes = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/ogg', ''];
        var mimeType  = mimeTypes.find(function (t) { return t === '' || MediaRecorder.isTypeSupported(t); });

        audioChunks   = [];
        mediaRecorder = new MediaRecorder(mediaStream, mimeType ? { mimeType: mimeType } : undefined);

        mediaRecorder.addEventListener('dataavailable', function (e) {
            if (e.data.size > 0) audioChunks.push(e.data);
        });

        mediaRecorder.addEventListener('stop', async function () {
            // Release the microphone immediately
            mediaStream.getTracks().forEach(function (t) { t.stop(); });
            mediaStream = null;

            setState(btn, 'processing');

            try {
                var blob       = new Blob(audioChunks, { type: mimeType || 'audio/webm' });
                var float32    = await audioToFloat32(blob);
                var lang       = (input.dataset.voiceLang || 'fr-FR').split('-')[0].toLowerCase();
                var transcript = await transcribeAudio(float32, lang);

                if (!transcript) {
                    showToast('Aucune parole détectée. Réessayez.', 'warning');
                } else {
                    var cur     = input.value.trim();
                    input.value = cur ? cur + ' ' + transcript : transcript;
                    input.dispatchEvent(new Event('input',  { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            } catch (err) {
                showToast('Erreur de transcription : ' + err.message, 'danger');
            } finally {
                setState(btn, 'idle');
            }
        });

        // 3 — Start recording
        mediaRecorder.start();
        setState(btn, 'recording');
    }

    // ── UI helpers ───────────────────────────────────────────────────────
    function createMicButton() {
        var btn     = document.createElement('button');
        btn.type    = 'button';
        btn.className = 'voice-btn';
        btn.title   = 'Dicter';
        btn.setAttribute('aria-label', 'Dictée vocale');
        btn.innerHTML =
            // Mic icon — shown when idle
            '<svg class="voice-icon-mic" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/>' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/>' +
            '</svg>' +
            // Stop icon — shown when recording
            '<svg class="voice-icon-stop" width="14" height="14" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true" style="display:none">' +
                '<rect x="4" y="4" width="16" height="16" rx="2"/>' +
            '</svg>' +
            // Spinner icon — shown when processing
            '<svg class="voice-icon-spin" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" style="display:none">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" ' +
                    'd="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83' +
                    'M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>' +
            '</svg>';
        return btn;
    }

    /**
     * Show a toast notification.
     * @param {string}  message
     * @param {string}  type       'info' | 'warning' | 'danger'
     * @param {boolean} persistent If true the toast stays until manually removed.
     * @returns {HTMLElement}      The toast element.
     */
    function showToast(message, type, persistent) {
        var colors = { info: '#198754', warning: '#f59e0b', danger: '#ef4444' };
        var toast  = document.createElement('div');
        Object.assign(toast.style, {
            position:      'fixed',
            bottom:        '1.5rem',
            left:          '50%',
            transform:     'translateX(-50%)',
            zIndex:        '9999',
            padding:       '.65rem 1.25rem',
            borderRadius:  '10px',
            fontSize:      '.85rem',
            fontWeight:    '500',
            color:         '#fff',
            pointerEvents: 'none',
            transition:    'opacity .3s',
            boxShadow:     '0 4px 20px rgba(0,0,0,.18)',
            background:    colors[type] || colors.info,
        });
        toast.textContent = message;
        document.body.appendChild(toast);

        if (!persistent) {
            setTimeout(function () {
                toast.style.opacity = '0';
                setTimeout(function () { toast.remove(); }, 350);
            }, 4000);
        }

        return toast;
    }

    // ── Initialisation ───────────────────────────────────────────────────
    function initVoiceInputs() {
        document.querySelectorAll('[data-voice-input]').forEach(function (input) {
            if (input.type === 'password')      return;
            if (input.dataset.voiceInitialized) return;
            input.dataset.voiceInitialized = 'true';

            // Ensure a positioned wrapper for absolute button placement
            var wrap = input.closest('.reg-input-wrap, .voice-wrap');
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.className    = 'voice-wrap';
                wrap.style.cssText = 'position:relative;display:block;';
                input.parentNode.insertBefore(wrap, input);
                wrap.appendChild(input);
            } else {
                wrap.style.position = 'relative';
            }

            input.style.paddingRight = '2.5rem';

            var btn = createMicButton();
            wrap.appendChild(btn);

            btn.addEventListener('click', function (e) {
                e.preventDefault();
                handleButtonClick(input, btn);
            });

            // Sync the three icons with the current state class
            var observer = new MutationObserver(function () {
                var rec  = btn.classList.contains('recording');
                var proc = btn.classList.contains('processing');
                btn.querySelector('.voice-icon-mic').style.display  = (rec || proc) ? 'none' : '';
                btn.querySelector('.voice-icon-stop').style.display = rec  ? '' : 'none';
                btn.querySelector('.voice-icon-spin').style.display = proc ? '' : 'none';
            });
            observer.observe(btn, { attributes: true, attributeFilter: ['class'] });
        });

        // Pre-spawn the worker so it is ready on the first click.
        // The Whisper model itself loads lazily on the first transcription.
        getWorker();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVoiceInputs);
    } else {
        initVoiceInputs();
    }

    window.initVoiceInputs = initVoiceInputs;
}());
