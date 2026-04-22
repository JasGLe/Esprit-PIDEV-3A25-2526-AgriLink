import { Controller } from '@hotwired/stimulus';

const ESTIMATE_SECONDS = 75;

export default class extends Controller {
    static targets = [
        'overlay',
        'stateInitial', 'stateLoading', 'stateResult', 'stateError',
        'progressBar', 'timerLabel',
        'posterImage', 'successLabel', 'errorLabel',
    ];

    static values = {
        eventId: Number,
    };

    connect() {
        this._timerInterval = null;
        this._currentImageUrl = null;
        this._downloadBlob = null;
        this._genAbort = null;
        this._boundKeydown = this._onKeydown.bind(this);
    }

    disconnect() {
        this._abortGeneration();
        this._stopTimer();
        this._revokeBlobUrlIfNeeded();
        this._currentImageUrl = null;
        this._downloadBlob = null;
        document.removeEventListener('keydown', this._boundKeydown);
    }

    // ── Open / close ─────────────────────────────────────────────────────────

    openModal() {
        this.overlayTarget.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', this._boundKeydown);
        this._showState('initial');
    }

    closeModal() {
        this.overlayTarget.style.display = 'none';
        document.body.style.overflow = '';
        document.removeEventListener('keydown', this._boundKeydown);
        this._abortGeneration();
        this._stopTimer();
        this._revokeBlobUrlIfNeeded();
        this._currentImageUrl = null;
        this._downloadBlob = null;
        if (this.hasPosterImageTarget) {
            this.posterImageTarget.onload = null;
            this.posterImageTarget.onerror = null;
            this.posterImageTarget.removeAttribute('src');
        }
    }

    // Close on overlay background click
    overlayTargetConnected(el) {
        el.addEventListener('click', (e) => {
            if (e.target === el) this.closeModal();
        });
    }

    _onKeydown(e) {
        if (e.key === 'Escape') this.closeModal();
    }

    _abortGeneration() {
        if (this._genAbort) {
            this._genAbort.abort();
            this._genAbort = null;
        }
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    async generate() {
        this._abortGeneration();
        this._genAbort = new AbortController();
        const { signal } = this._genAbort;

        this._showState('loading');
        this._startTimer();

        try {
            const res = await fetch(`/evenement/${this.eventIdValue}/generate-poster`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal,
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok || !data.success) {
                throw new Error(data.message || `Erreur serveur (${res.status})`);
            }

            const proxyRes = await fetch(`/evenement/${this.eventIdValue}/poster-image`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ image_url: data.image_url }),
                signal,
            });

            const proxyCt = proxyRes.headers.get('Content-Type') || '';
            if (!proxyRes.ok) {
                const errJson = await proxyRes.json().catch(() => ({}));
                throw new Error(errJson.message || `Erreur proxy (${proxyRes.status})`);
            }
            if (!proxyCt.includes('image/')) {
                throw new Error('Réponse invalide du serveur (image attendue).');
            }

            const blob = await proxyRes.blob();
            if (!blob.size) {
                throw new Error('Image vide reçue du serveur.');
            }

            this._revokeBlobUrlIfNeeded();
            this._downloadBlob = blob;
            const objectUrl = URL.createObjectURL(blob);
            await this._decodeImageFromUrl(objectUrl);
            this._currentImageUrl = objectUrl;

            this._stopTimer();
            const elapsed = parseInt(this.timerLabelTarget.textContent, 10) || 0;
            this.successLabelTarget.textContent = `✅ Affiche générée en ${elapsed}s`;
            this._showState('result');
        } catch (err) {
            this._stopTimer();
            if (err.name === 'AbortError') {
                return;
            }
            this._downloadBlob = null;
            this._showError(err.message || 'Erreur inconnue.');
        } finally {
            this._genAbort = null;
        }
    }

    /** Nouvelle génération complète (évite <img> caché + relance depuis zéro). */
    regenerate() {
        this._abortGeneration();
        this._revokeBlobUrlIfNeeded();
        this._currentImageUrl = null;
        this._downloadBlob = null;
        const img = this.posterImageTarget;
        img.onload = null;
        img.onerror = null;
        img.removeAttribute('src');
        void this.generate();
    }

    download() {
        if (!this._downloadBlob || !this._currentImageUrl) return;

        const ext =
            this._downloadBlob.type === 'image/png'
                ? 'png'
                : this._downloadBlob.type === 'image/webp'
                  ? 'webp'
                  : 'jpg';
        const safeName = `affiche-evenement-${this.eventIdValue}.${ext}`;

        const a = document.createElement('a');
        a.href = this._currentImageUrl;
        a.download = safeName;
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    _revokeBlobUrlIfNeeded() {
        if (this._currentImageUrl && this._currentImageUrl.startsWith('blob:')) {
            URL.revokeObjectURL(this._currentImageUrl);
        }
        this._currentImageUrl = null;
    }

    /**
     * Décode l’image hors <img> du panneau résultat (souvent display:none pendant le chargement) :
     * Chrome peut ne pas déclencher onload / afficher une erreur sur la 2ᵉ génération.
     */
    _decodeImageFromUrl(url) {
        return new Promise((resolve, reject) => {
            const probe = new Image();
            probe.onload = async () => {
                try {
                    const el = this.posterImageTarget;
                    el.src = url;
                    if (el.decode) {
                        await el.decode().catch(() => {});
                    }
                    resolve();
                } catch {
                    if (url.startsWith('blob:')) {
                        URL.revokeObjectURL(url);
                    }
                    reject(new Error("Impossible de charger l'image. Vérifiez votre connexion et réessayez."));
                }
            };
            probe.onerror = () => {
                if (url.startsWith('blob:')) {
                    URL.revokeObjectURL(url);
                }
                reject(new Error("Impossible de charger l'image. Vérifiez votre connexion et réessayez."));
            };
            probe.src = url;
        });
    }

    _startTimer() {
        let seconds = 0;
        this.timerLabelTarget.textContent = '0s';
        this.progressBarTarget.style.width = '5%';

        this._timerInterval = setInterval(() => {
            seconds += 1;
            this.timerLabelTarget.textContent = `${seconds}s`;
            const pct = Math.min(Math.round((seconds / ESTIMATE_SECONDS) * 88), 88);
            this.progressBarTarget.style.width = `${pct}%`;
        }, 1000);
    }

    _stopTimer() {
        clearInterval(this._timerInterval);
        this._timerInterval = null;
        if (this.hasProgressBarTarget) {
            this.progressBarTarget.style.width = '100%';
        }
    }

    _showState(name) {
        const map = {
            initial: this.stateInitialTarget,
            loading: this.stateLoadingTarget,
            result: this.stateResultTarget,
            error: this.stateErrorTarget,
        };
        Object.entries(map).forEach(([key, el]) => {
            el.style.display = key === name ? 'block' : 'none';
        });
    }

    _showError(message) {
        this.errorLabelTarget.textContent = message;
        this._showState('error');
    }
}
