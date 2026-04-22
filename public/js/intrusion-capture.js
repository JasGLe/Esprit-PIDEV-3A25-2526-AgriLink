/**
 * Intrusion Capture Service
 * Handles camera capture and transmission during failed login attempts
 */

class IntrusionCaptureService {
    constructor() {
        this.stream = null;
        this.canvas = null;
        this.video = null;
    }

    /**
     * Check if the browser supports WebRTC
     */
    isWebRTCSupported() {
        return !!(
            navigator.mediaDevices &&
            navigator.mediaDevices.getUserMedia &&
            HTMLCanvasElement.prototype.getContext
        );
    }

    /**
     * Request camera permission and start video stream
     */
    async requestCameraPermission() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user' },
                audio: false,
            });

            this.stream = stream;
            return true;
        } catch (error) {
            console.log('Camera permission denied or unavailable:', error.message);
            return false;
        }
    }

    /**
     * Capture a single frame from the camera
     */
    async captureFrame() {
        if (!this.stream) {
            throw new Error('No camera stream available');
        }

        // Create video element to capture from
        const video = document.createElement('video');
        video.srcObject = this.stream;
        video.play();

        // Wait for video to be ready
        await new Promise(resolve => {
            video.onloadedmetadata = resolve;
        });

        // Create canvas and capture frame
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const context = canvas.getContext('2d');
        context.drawImage(video, 0, 0);

        // Stop the stream
        this.stopCamera();

        // Convert to base64
        return canvas.toDataURL('image/jpeg', 0.8);
    }

    /**
     * Stop the camera stream
     */
    stopCamera() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
    }

    /**
     * Send captured image to the backend
     */
    async sendCapturedImage(base64Image, email) {
        try {
            const response = await fetch('/profile/api/security/capture-intrusion', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    image: base64Image,
                    email: email,
                }),
            });

            return response.ok;
        } catch (error) {
            console.error('Failed to send captured image:', error);
            return false;
        }
    }

    /**
     * Handle intrusion capture on failed login
     * Should be called when login fails with trigger_capture response
     */
    async handleIntrusionCapture(email) {
        console.log('[IntrusionCapture] Starting capture for:', email);
        
        if (!this.isWebRTCSupported()) {
            console.warn('[IntrusionCapture] WebRTC not supported, skipping intrusion capture');
            return;
        }

        // Request camera permission
        const hasPermission = await this.requestCameraPermission();
        if (!hasPermission) {
            console.log('[IntrusionCapture] Camera permission not granted');
            return;
        }

        try {
            console.log('[IntrusionCapture] Capturing frame...');
            // Capture frame
            const base64Image = await this.captureFrame();
            
            if (!base64Image) {
                console.error('[IntrusionCapture] Failed to capture frame');
                return;
            }

            console.log('[IntrusionCapture] Captured frame, sending to server...');
            // Send to backend
            const sent = await this.sendCapturedImage(base64Image, email);
            if (sent) {
                console.log('[IntrusionCapture] Intrusion capture image sent successfully');
            } else {
                console.error('[IntrusionCapture] Failed to send image');
            }
        } catch (error) {
            console.error('[IntrusionCapture] Error during intrusion capture:', error);
        } finally {
            this.stopCamera();
        }
    }
}

// Export for use in login form
const intrusionCaptureService = new IntrusionCaptureService();
