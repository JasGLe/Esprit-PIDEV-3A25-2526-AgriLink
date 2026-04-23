/**
 * Voice Input — Speech-to-Text for registration forms
 * Uses MediaRecorder + OpenAI Whisper (via @xenova/transformers in a Web Worker).
 * Works on any network — no Google servers involved.
 * Model (~74 MB) is downloaded from Hugging Face on first use and cached in the browser.
 *
 * ENHANCED FEATURES:
 * ✨ French Language Optimization: Optimized for French dialect and vocabulary
 * 📱 Phone Number Processing: Converts spoken digits to clean format (e.g., "zéro cinq cinq..." → "0551234567")
 * 📧 Email Processing: Handles French email words (arobase, point) and special characters
 * 🎯 Smart Field Detection: Automatically detects field type (phone/email/text) and processes accordingly
 *
 * Attach to any input/textarea with:  data-voice-input
 * Optional language override:         data-voice-lang="fr-FR"  (defaults to fr-FR)
 *
 * Examples:
 *  <input type="tel" data-voice-input />              <!-- Phone field, auto-detected -->
 *  <input type="email" data-voice-input />            <!-- Email field, auto-detected -->
 *  <input type="text" name="ville" data-voice-input /> <!-- Text field, appends text -->
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

    // ── Post-processing helpers (French field-specific) ─────────────────────
    /**
     * Detect field type based on input attributes and name
     */
    function detectFieldType(input) {
        var type = input.getAttribute('type') || 'text';
        var name = (input.name || '').toLowerCase();

        // Check for phone number fields
        if (type === 'tel' || name.includes('telephone') || name.includes('phone') || name.includes('tel')) {
            return 'phone';
        }
        // Check for email fields
        if (type === 'email' || name.includes('email') || name.includes('mail')) {
            return 'email';
        }
        return 'text';
    }

    /**
     * Process transcribed text based on field type
     * Handles French language, phone numbers, emails
     */
    function processTranscript(transcript, fieldType) {
        if (!transcript) return '';

        // Replace French accented word separations (common Whisper artifacts)
        var processed = transcript
            // French-specific cleanups
            .replace(/\bà\s+le\b/gi, 'à le')
            .replace(/\bdu\s+/gi, 'du ')
            .replace(/\bdes\s+/gi, 'des ')
            .trim();

        // Field-specific processing
        if (fieldType === 'phone') {
            processed = processPhoneNumber(processed);
        } else if (fieldType === 'email') {
            processed = processEmail(processed);
        }

        return processed;
    }

    /**
     * Clean phone numbers: Extract digits only, remove all separators
     * Whisper may transcribe: "0 5 5 1 2 3 4 5 6 7" or "zéro cinq cinq..."
     * We want: "0551234567"
     */
    function processPhoneNumber(text) {
        // French number words (common Whisper outputs)
        var frenchNumbers = {
            // Basic digits
            'zéro': '0', 'zero': '0',
            'un': '1', 'une': '1',
            'deux': '2',
            'trois': '3',
            'quatre': '4',
            'cinq': '5',
            'six': '6',
            'sept': '7',
            'huit': '8',
            'neuf': '9',
            // Alternative spellings/pronunciations
            'oh': '0', 'o': '0',
        };

        var result = text.toLowerCase();

        // Replace French number words with priority (longer words first to avoid partial matches)
        var words = Object.keys(frenchNumbers).sort(function(a, b) { return b.length - a.length; });
        words.forEach(function(word) {
            // Use word boundaries to avoid partial replacements
            result = result.replace(new RegExp('\\b' + word + '\\b', 'g'), frenchNumbers[word]);
        });

        // Remove all non-digit characters (spaces, hyphens, dots, slashes, etc.)
        result = result.replace(/[^\d]/g, '');

        // Ensure it looks like a valid phone number (8-15 digits for international)
        if (result.length >= 8 && result.length <= 15) {
            return result;
        }

        // If cleaning failed, return original text (too short/long or couldn't parse)
        return text;
    }

    /**
     * Clean email addresses: Handle @ and common French TLDs
     * Whisper may transcribe: "john arobase example point com"
     * We want: "john@example.com"
     */
    function processEmail(text) {
        var result = text.toLowerCase();

        // STEP 1: Replace @ symbol variations FIRST (arobase is French for @)
        // Must do this before 'point' to avoid interference with names like "dupont"
        result = result
            .replace(/\barobase\b/gi, '@')
            .replace(/\barrobe\b/gi, '@')
            .replace(/\barobase\s+signe\b/gi, '@')
            .replace(/\bat\b/gi, '@');

        // STEP 2: Replace . symbol variations (point is French for .)
        // Only replace 'point' when it's surrounded by spaces (standalone word)
        result = result
            .replace(/\s+point\s+/g, '.')
            .replace(/\bponctouel\b/gi, '.')
            .replace(/\bpunct\b/gi, '.');

        // STEP 3: Replace hyphen word with actual hyphen
        result = result.replace(/\bhyphen\b|\btiret\b/gi, '-');

        // STEP 4: Clean up spaces around special characters
        result = result
            .replace(/\s*@\s*/g, '@')
            .replace(/\s*\.\s*/g, '.')
            .replace(/\s*-\s*/g, '-');

        // STEP 5: Remove any remaining spaces
        result = result.replace(/\s+/g, '');

        // STEP 6: Validate email structure
        var hasAt = result.indexOf('@') !== -1;
        var hasDot = result.indexOf('.') !== -1;
        var atPos = result.indexOf('@');
        var dotPos = result.lastIndexOf('.');
        var hasValidStructure = hasAt && hasDot && atPos > 0 && dotPos > atPos;

        if (hasValidStructure) {
            // Additional cleanup: remove any leftover punctuation except @ . and hyphen
            result = result.replace(/[^a-z0-9@.\-_]/g, '');
            return result;
        }

        // If not valid email format, return original text (user may correct)
        return text;
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
                    // Detect field type and process transcript accordingly
                    var fieldType = detectFieldType(input);
                    var processed = processTranscript(transcript, fieldType);

                    // Insert processed text (no append for phone/email fields, replace instead)
                    if (fieldType === 'phone' || fieldType === 'email') {
                        input.value = processed;
                    } else {
                        var cur = input.value.trim();
                        input.value = cur ? cur + ' ' + processed : processed;
                    }

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
