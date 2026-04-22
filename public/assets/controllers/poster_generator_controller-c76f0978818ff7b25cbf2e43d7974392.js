import { Controller } from '@hotwired/stimulus';

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
        this._boundKeydown = this._onKeydown.bind(this);
    }

    disconnect() {
        this._stopTimer();
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
        this._stopTimer();
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

    // ── Actions ───────────────────────────────────────────────────────────────

    async generate() {
        this._showState('loading');
        this._startTimer();

        try {
            const res = await fetch(`/evenement/${this.eventIdValue}/generate-poster`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok || !data.success) {
                throw new Error(data.message || `Erreur serveur (${res.status})`);
            }

            await this._loadImage(data.image_url);

        } catch (err) {
            this._stopTimer();
            this._showError(err.message);
        }
    }

    regenerate() {
        this._currentImageUrl = null;
        this.posterImageTarget.src = '';
        this._showState('initial');
    }

    download() {
        if (!this._currentImageUrl) return;
        // Open in new tab — direct download blocked cross-origin on Pollinations
        window.open(this._currentImageUrl, '_blank', 'noopener');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    _loadImage(url) {
        return new Promise((resolve, reject) => {
            const img = this.posterImageTarget;

            img.onload = () => {
                this._stopTimer();
                this._currentImageUrl = url;
                const elapsed = parseInt(this.timerLabelTarget.textContent, 10) || 0;
                this.successLabelTarget.textContent = `✅ Affiche générée en ${elapsed}s`;
                this._showState('result');
                resolve();
            };

            img.onerror = () => {
                reject(new Error("Impossible de charger l'image. Vérifiez votre connexion et réessayez."));
            };

            img.src = url;
        });
    }

    _startTimer() {
        let seconds = 0;
        const maxEstimate = 25;
        this.timerLabelTarget.textContent = '0s';
        this.progressBarTarget.style.width = '5%';

        this._timerInterval = setInterval(() => {
            seconds += 1;
            this.timerLabelTarget.textContent = `${seconds}s`;
            const pct = Math.min(Math.round((seconds / maxEstimate) * 88), 88);
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
            result:  this.stateResultTarget,
            error:   this.stateErrorTarget,
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
