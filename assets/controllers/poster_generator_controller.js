import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['spinner', 'counter', 'image', 'error'];
    static values = {
        eventId: Number,
        pollInterval: { type: Number, default: 2000 },
        maxWaitTime: { type: Number, default: 40000 },
    };

    connect() {
        // Auto-attach event listeners to buttons with data-action
        this.element.querySelectorAll('[data-action*="generatePoster"]').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleGenerateClick(e));
        });
    }

    async handleGenerateClick(event) {
        event.preventDefault();
        event.stopPropagation();
        await this.generatePoster();
    }

    async generatePoster() {
        try {
            // Get event ID from the data-controller element
            const eventId = this.element.querySelector('button[data-bs-target]')
                ?.getAttribute('data-bs-target')
                ?.match(/posterModal(\d+)/)?.[1] || this.eventIdValue;

            if (!eventId || eventId === '0') {
                throw new Error('Event ID not found');
            }

            // Show modal and spinner
            const modalId = `posterModal${eventId}`;
            const modal = new bootstrap.Modal(document.getElementById(modalId));
            modal.show();

            this.showSpinner();
            this.resetCounter();
            this.clearErrors();

            // Trigger generation endpoint
            const response = await fetch(`/evenement/${eventId}/generate-poster`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Erreur lors de la génération');
            }

            const predictionId = data.prediction_id;
            console.log('Generation started with prediction ID:', predictionId);

            // Poll for status
            await this.pollPredictionStatus(predictionId);
        } catch (error) {
            this.showError(error.message);
            this.hideSpinner();
        }
    }

    async pollPredictionStatus(predictionId) {
        const startTime = Date.now();
        let elapsedSeconds = 0;

        const pollInterval = setInterval(async () => {
            try {
                elapsedSeconds = Math.floor((Date.now() - startTime) / 1000);
                this.updateCounter(elapsedSeconds);

                // Check if exceeded max wait time
                if (elapsedSeconds >= this.maxWaitTimeValue) {
                    clearInterval(pollInterval);
                    throw new Error(
                        `Génération dépassée le délai maximal (${this.maxWaitTimeValue}s)`
                    );
                }

                // Check status
                const response = await fetch(`/evenement/api/poster-status/${predictionId}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const data = await response.json();

                if (!data.success) {
                    clearInterval(pollInterval);
                    throw new Error(data.message || 'Erreur lors de la vérification du statut');
                }

                // Check generation status
                if (data.status === 'succeeded') {
                    clearInterval(pollInterval);
                    this.showSuccess(data.output, elapsedSeconds);
                } else if (data.status === 'failed') {
                    clearInterval(pollInterval);
                    throw new Error(`Génération échouée: ${data.error || 'Erreur inconnue'}`);
                }
                // If status is 'processing', continue polling
            } catch (error) {
                clearInterval(pollInterval);
                this.showError(error.message);
            }
        }, this.pollIntervalValue);

        // Safety timeout
        setTimeout(() => clearInterval(pollInterval), this.maxWaitTimeValue + 5000);
    }

    showSpinner() {
        if (this.hasModalTarget) {
            this.modalTarget.classList.remove('d-none');
        }
        if (this.hasSpinnerTarget) {
            this.spinnerTarget.classList.remove('d-none');
        }
        if (this.hasCounterTarget) {
            this.counterTarget.classList.remove('d-none');
        }
        if (this.hasImageTarget) {
            this.imageTarget.classList.add('d-none');
        }
    }

    hideSpinner() {
        if (this.hasSpinnerTarget) {
            this.spinnerTarget.classList.add('d-none');
        }
        if (this.hasCounterTarget) {
            this.counterTarget.classList.add('d-none');
        }
    }

    updateCounter(seconds) {
        if (this.hasCounterTarget) {
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            const timeStr = minutes > 0 ? `${minutes}m ${secs}s` : `${secs}s`;
            
            // Estimate: 30-40 seconds typical
            const estimated = Math.max(seconds + 10, 30);
            this.counterTarget.innerHTML = `
                <div class="countdown" style="font-size: 2rem; font-weight: bold; color: #0d6efd;">
                    ${timeStr} / ~${estimated}s
                </div>
                <div class="progress" style="height: 4px; margin-top: 1rem;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" 
                         style="width: ${Math.min((seconds / estimated) * 100, 100)}%;" 
                         aria-valuenow="${Math.min(seconds, estimated)}" aria-valuemin="0" 
                         aria-valuemax="${estimated}"></div>
                </div>
            `;
        }
    }

    resetCounter() {
        if (this.hasCounterTarget) {
            this.counterTarget.textContent = '0s / ~30s';
        }
    }

    showSuccess(imageUrl, elapsedSeconds) {
        this.hideSpinner();

        if (this.hasImageTarget) {
            // Set image source
            this.imageTarget.src = imageUrl;
            this.imageTarget.classList.remove('d-none');

            // Add download functionality
            const btn = this.imageTarget.nextElementSibling?.querySelector('.btn-primary');
            if (btn) {
                btn.onclick = () => this.downloadImage(imageUrl);
            }
        }

        // Show success message
        const message = `✅ Affiche générée avec succès en ${elapsedSeconds}s`;
        this.showNotification(message, 'success');
    }

    downloadImage(imageUrl) {
        // Download image from URL
        const a = document.createElement('a');
        a.href = imageUrl;
        a.download = `affiche-${Date.now()}.png`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    showNotification(message, type = 'error') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type === 'error' ? 'danger' : 'success'} mt-3`;
        alert.textContent = message;
        
        const modalBody = this.element.querySelector('.modal-body');
        if (modalBody) {
            const existingAlert = modalBody.querySelector('.alert');
            if (existingAlert) {
                existingAlert.remove();
            }
            modalBody.appendChild(alert);
        }
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    clearErrors() {
        const alert = this.element?.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }
}
