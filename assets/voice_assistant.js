/* global SpeechRecognition, webkitSpeechRecognition */

(() => {
  const ENABLED_PATH_PREFIXES = ['/marketplace', '/boutique', '/mes-commandes', '/statistiques-ventes'];

  function shouldEnableOnThisPage() {
    const path = window.location.pathname || '';
    return ENABLED_PATH_PREFIXES.some((p) => path.startsWith(p));
  }

  if (!shouldEnableOnThisPage()) return;

  const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
  const hasRecognition = typeof SpeechRec === 'function';
  const hasTts = typeof window.speechSynthesis !== 'undefined';

  function normalizeText(text) {
    return (text || '')
      .toString()
      .trim()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, ''); // remove accents
  }

  // Simple keyword-based intent detection (English + French)
  function detectIntent(rawText) {
    const text = normalizeText(rawText);

    // navigation / orders
    if (/(show|voir|mes)\s+(orders|commandes)/.test(text) || /mes commandes/.test(text)) {
      return { intent: 'view_orders', entities: {} };
    }
    if (/(go|aller)\s+(to\s+)?(dashboard|tableau de bord|statistiques)/.test(text) || /statistiques ventes/.test(text)) {
      return { intent: 'go_dashboard', entities: {} };
    }
    if (/(go|aller)\s+(to\s+)?(marketplace|marche|boutique publique)/.test(text) || /\bmarketplace\b/.test(text)) {
      return { intent: 'go_marketplace', entities: {} };
    }
    if (/(go|aller)\s+(to\s+)?(my\s+)?(shop|boutique)/.test(text) || /\bma boutique\b/.test(text)) {
      return { intent: 'go_boutique', entities: {} };
    }

    // product actions (non-destructive by default)
    if (/(add|ajouter|create|creer)\s+(a\s+)?(product|produit)/.test(text) || /mettre en vente/.test(text)) {
      return { intent: 'add_product', entities: {} };
    }
    if (/(delete|supprimer)\s+(a\s+)?(product|produit)/.test(text)) {
      const m = text.match(/(?:product|produit)\s*#?\s*(\d+)/);
      return { intent: 'delete_product', entities: { productId: m ? parseInt(m[1], 10) : null } };
    }

    return { intent: 'unknown', entities: {} };
  }

  function speak(text) {
    if (!hasTts) return;
    const msg = new SpeechSynthesisUtterance(text);
    msg.lang = document.documentElement.lang || 'fr-FR';
    msg.rate = 1.0;
    msg.pitch = 1.0;
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(msg);
  }

  function createUi() {
    const wrap = document.createElement('div');
    wrap.id = 'agrilink-voice-assistant';
    wrap.innerHTML = `
      <style>
        #agrilink-voice-assistant { position: fixed; right: 16px; bottom: 16px; z-index: 2147483000; font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
        .ava-panel { width: 320px; max-width: calc(100vw - 32px); background: rgba(255,255,255,.96); border: 1px solid rgba(15,23,42,.10); box-shadow: 0 20px 60px rgba(0,0,0,.18); border-radius: 16px; overflow: hidden; display: none; }
        .ava-header { display:flex; align-items:center; justify-content:space-between; gap:10px; padding: 10px 12px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; }
        .ava-title { font-weight: 700; font-size: 13px; letter-spacing:.2px; }
        .ava-close { border:0; background: transparent; color:#fff; width:32px; height:32px; border-radius:10px; display:flex; align-items:center; justify-content:center; cursor:pointer; }
        .ava-close:hover { background: rgba(255,255,255,.18); }
        .ava-body { padding: 12px; }
        .ava-pill { display:inline-flex; gap:8px; align-items:center; padding:6px 10px; border-radius:999px; background: #f1f5f9; color:#0f172a; font-size:12px; }
        .ava-text { margin-top:10px; padding:10px; border:1px solid rgba(15,23,42,.12); border-radius:12px; background:#fff; font-size:12px; min-height: 44px; color:#0f172a; }
        .ava-sub { margin-top:8px; font-size: 11px; color: #64748b; }
        .ava-actions { display:flex; gap:10px; margin-top:12px; }
        .ava-btn { flex:1; border:0; border-radius: 12px; padding: 10px 12px; font-weight: 700; font-size: 12px; cursor:pointer; }
        .ava-btn-primary { background: #16a34a; color:#fff; }
        .ava-btn-primary:hover { filter: brightness(.96); }
        .ava-btn-secondary { background: #e2e8f0; color:#0f172a; }
        .ava-btn-secondary:hover { filter: brightness(.98); }
        .ava-fab { width: 54px; height:54px; border-radius: 18px; border: 1px solid rgba(15,23,42,.12); background: #16a34a; color:#fff; box-shadow: 0 18px 45px rgba(0,0,0,.22); cursor:pointer; display:flex; align-items:center; justify-content:center; }
        .ava-fab:active { transform: translateY(1px); }
        .ava-dot { width:10px; height:10px; border-radius:999px; background: #94a3b8; display:inline-block; }
        .ava-dot.listening { background:#ef4444; box-shadow: 0 0 0 6px rgba(239,68,68,.14); }
      </style>
      <button class="ava-fab" type="button" aria-label="Assistant vocal" title="Assistant vocal">🎙️</button>
      <div class="ava-panel" role="dialog" aria-label="Assistant vocal">
        <div class="ava-header">
          <div class="ava-title">Assistant vocal AgriLink</div>
          <button type="button" class="ava-close" aria-label="Fermer">✕</button>
        </div>
        <div class="ava-body">
          <div class="ava-pill"><span class="ava-dot" id="avaDot"></span><span id="avaState">Prêt</span></div>
          <div class="ava-text" id="avaText">Clique sur “Écouter”, puis parle (ex: “voir mes commandes”, “aller au dashboard”, “aller marketplace”).</div>
          <div class="ava-sub" id="avaHint">${hasRecognition ? 'Reconnaissance vocale disponible.' : 'Reconnaissance vocale non supportée par ce navigateur.'}</div>
          <div class="ava-actions">
            <button class="ava-btn ava-btn-primary" type="button" id="avaListenBtn" ${hasRecognition ? '' : 'disabled'}>Écouter</button>
            <button class="ava-btn ava-btn-secondary" type="button" id="avaStopBtn" ${hasRecognition ? '' : 'disabled'}>Stop</button>
          </div>
        </div>
      </div>
    `;

    document.body.appendChild(wrap);

    const fab = wrap.querySelector('.ava-fab');
    const panel = wrap.querySelector('.ava-panel');
    const close = wrap.querySelector('.ava-close');
    const listenBtn = wrap.querySelector('#avaListenBtn');
    const stopBtn = wrap.querySelector('#avaStopBtn');
    const dot = wrap.querySelector('#avaDot');
    const state = wrap.querySelector('#avaState');
    const textEl = wrap.querySelector('#avaText');

    function openPanel() { panel.style.display = 'block'; }
    function closePanel() { panel.style.display = 'none'; }

    fab.addEventListener('click', () => { panel.style.display === 'block' ? closePanel() : openPanel(); });
    close.addEventListener('click', closePanel);

    return { listenBtn, stopBtn, dot, state, textEl, openPanel };
  }

  const ui = createUi();

  if (!hasRecognition) {
    ui.openPanel();
    speak("Votre navigateur ne supporte pas la reconnaissance vocale.");
    return;
  }

  const recognition = new SpeechRec();
  recognition.lang = document.documentElement.lang?.startsWith('ar') ? 'ar' : 'fr-FR';
  recognition.interimResults = true;
  recognition.continuous = false;
  recognition.maxAlternatives = 1;

  let isListening = false;
  let lastFinal = '';

  function setListening(on) {
    isListening = on;
    ui.dot.classList.toggle('listening', on);
    ui.state.textContent = on ? 'Écoute…' : 'Prêt';
  }

  async function sendCommand(text) {
    const detected = detectIntent(text);
    const payload = {
      text,
      intent: detected.intent,
      entities: detected.entities || {},
    };

    const res = await fetch('/api/voice-command', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    let data = null;
    try { data = await res.json(); } catch (e) {}

    if (!res.ok) {
      const msg = (data && data.message) ? data.message : "Je n'ai pas pu traiter la commande.";
      ui.textEl.textContent = msg;
      speak(msg);
      return;
    }

    const msg = (data && data.message) ? data.message : 'OK.';
    ui.textEl.textContent = msg;
    speak(msg);

    if (data && data.action && data.action.type === 'navigate' && data.action.url) {
      window.location.href = data.action.url;
    }
  }

  recognition.onstart = () => setListening(true);
  recognition.onend = () => setListening(false);
  recognition.onerror = (e) => {
    setListening(false);
    const msg = `Erreur micro: ${e.error || 'unknown'}`;
    ui.textEl.textContent = msg;
    speak(msg);
  };

  recognition.onresult = (event) => {
    let interim = '';
    let final = '';

    for (let i = event.resultIndex; i < event.results.length; i += 1) {
      const result = event.results[i];
      const transcript = result[0]?.transcript || '';
      if (result.isFinal) final += transcript;
      else interim += transcript;
    }

    const display = (final || interim).trim();
    if (display) ui.textEl.textContent = display;

    if (final && final.trim() && final.trim() !== lastFinal) {
      lastFinal = final.trim();
      // Only send final transcript
      sendCommand(lastFinal).catch(() => {
        const msg = "Erreur réseau: impossible d'envoyer la commande.";
        ui.textEl.textContent = msg;
        speak(msg);
      });
    }
  };

  ui.listenBtn.addEventListener('click', () => {
    if (isListening) return;
    try {
      recognition.start();
    } catch (e) {
      // Some browsers throw if start called twice quickly
    }
  });

  ui.stopBtn.addEventListener('click', () => {
    try { recognition.stop(); } catch (e) {}
  });
})();

