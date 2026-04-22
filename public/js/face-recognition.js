/**
 * Face Recognition Utilities using face-api.js 0.22.2
 * Requires face-api.js to be loaded before this script.
 */

const FaceRecognition = {
    isInitialized: false,
    detectionOptions: null,

    /** Poll until the faceapi global is available (script may still be loading). */
    async waitForFaceAPI(timeout = 10000) {
        const start = Date.now();
        while (typeof faceapi === 'undefined') {
            if (Date.now() - start > timeout) {
                throw new Error('face-api.js failed to load. Check network connectivity.');
            }
            await new Promise(r => setTimeout(r, 100));
        }
    },

    /** Load models from CDN and configure detection options. */
    async init() {
        if (this.isInitialized) return true;

        try {
            await this.waitForFaceAPI();

            const MODEL_URL = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js/weights/';

            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
            ]);

            // isLoaded is a getter (not a method) — do NOT call it with ()
            if (!faceapi.nets.tinyFaceDetector.isLoaded ||
                !faceapi.nets.faceLandmark68Net.isLoaded ||
                !faceapi.nets.faceRecognitionNet.isLoaded) {
                throw new Error('One or more models failed to load.');
            }

            this.detectionOptions = new faceapi.TinyFaceDetectorOptions({
                inputSize: 416,
                scoreThreshold: 0.5,
            });

            this.isInitialized = true;
            return true;
        } catch (error) {
            console.error('FaceRecognition.init failed:', error);
            this.isInitialized = false;
            return false;
        }
    },

    /** Extract a 128-float descriptor array from a video/canvas element. */
    async extractDescriptor(imageElement) {
        if (!this.isInitialized) {
            throw new Error('Face recognition not initialised. Please wait for models to load.');
        }

        const detection = await faceapi
            .detectSingleFace(imageElement, this.detectionOptions)
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (!detection) {
            throw new Error('Aucun visage détecté. Assurez-vous que votre visage est bien visible.');
        }

        return Array.from(detection.descriptor);
    },

    /** Start the webcam stream and attach it to a <video> element. */
    async startWebcam(videoElement) {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false,
            });
            videoElement.srcObject = stream;
            return new Promise(resolve => {
                videoElement.onloadedmetadata = () => { videoElement.play(); resolve(stream); };
            });
        } catch (error) {
            if (error.name === 'NotAllowedError') throw new Error('Accès caméra refusé. Autorisez-le dans les paramètres du navigateur.');
            if (error.name === 'NotFoundError')  throw new Error('Aucune caméra trouvée.');
            throw error;
        }
    },

    /** Stop all tracks on the video element's stream. */
    stopWebcam(videoElement) {
        const stream = videoElement.srcObject;
        if (stream) {
            stream.getTracks().forEach(t => t.stop());
            videoElement.srcObject = null;
        }
    },

    /**
     * Detect faces in a video frame, draw landmarks + bounding boxes on the canvas.
     * Returns true if at least one face is visible.
     */
    async drawDetection(videoElement, canvasElement) {
        if (!this.isInitialized) return false;

        const displaySize = {
            width:  videoElement.videoWidth,
            height: videoElement.videoHeight,
        };

        if (!displaySize.width || !displaySize.height) return false;

        faceapi.matchDimensions(canvasElement, displaySize);

        const detections = await faceapi
            .detectAllFaces(videoElement, this.detectionOptions)
            .withFaceLandmarks();

        const resized = faceapi.resizeResults(detections, displaySize);

        const ctx = canvasElement.getContext('2d');
        ctx.clearRect(0, 0, canvasElement.width, canvasElement.height);

        if (resized.length > 0) {
            faceapi.draw.drawDetections(canvasElement, resized);
            faceapi.draw.drawFaceLandmarks(canvasElement, resized);
        }

        return resized.length > 0;
    },
};

if (typeof module !== 'undefined' && module.exports) {
    module.exports = FaceRecognition;
}
