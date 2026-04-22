/**
 * Voice Worker — Whisper speech-to-text via @xenova/transformers
 * Runs in a dedicated Web Worker so inference never blocks the UI thread.
 * The model (Xenova/whisper-base, ~74 MB) is fetched from Hugging Face Hub
 * on first use and cached automatically in the browser's Cache Storage.
 */
import { pipeline, env } from 'https://cdn.jsdelivr.net/npm/@xenova/transformers@2.17.2';

env.allowLocalModels = false;
env.useBrowserCache  = true;

let transcriber = null;

async function getTranscriber(onProgress) {
    if (transcriber) return transcriber;
    transcriber = await pipeline(
        'automatic-speech-recognition',
        'Xenova/whisper-base',
        { progress_callback: onProgress }
    );
    return transcriber;
}

// ISO 639-1 → Whisper language name
const LANG_MAP = {
    fr: 'french',  en: 'english', ar: 'arabic',
    de: 'german',  es: 'spanish', it: 'italian',
    pt: 'portuguese', nl: 'dutch', ru: 'russian',
};

self.addEventListener('message', async ({ data }) => {
    if (data.type !== 'transcribe') return;

    try {
        const model = await getTranscriber((p) => {
            if (p.status === 'progress') {
                self.postMessage({
                    type:     'progress',
                    file:     p.file,
                    progress: Math.round(p.progress),
                });
            }
        });

        const lang   = LANG_MAP[data.lang] || 'french';
        const result = await model(data.audio, {
            language:          lang,
            task:              'transcribe',
            return_timestamps: false,
        });

        self.postMessage({ type: 'result', transcript: result.text.trim() });
    } catch (err) {
        self.postMessage({ type: 'error', message: err.message });
    }
});
