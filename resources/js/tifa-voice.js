const TIFAA_VOICE_CONFIG = {
    lang: 'id-ID',
    rate: 1.0,
    pitch: 1.0,
    volume: 1.0,
    preferredNames: [
        'Microsoft Gadis Online (Natural) - Indonesian (Indonesia)',
        'Google Bahasa Indonesia',
        'Microsoft Gadis - Indonesian (Indonesia)',
    ],
};

const isDevelopment = import.meta.env.DEV;

export class TifaaVoiceOutput {
    constructor(config = TIFAA_VOICE_CONFIG) {
        this.config = config;
        this.synthesis = window.speechSynthesis;
        this.voices = [];
        this.selected = null;
        this.selectionReason = 'voices unavailable';

        this.refreshVoices = this.refreshVoices.bind(this);
        this.refreshVoices();
        this.synthesis?.addEventListener?.('voiceschanged', this.refreshVoices);
    }

    refreshVoices() {
        this.voices = [...(this.synthesis?.getVoices?.() ?? [])];
        const cachedStillAvailable = this.selected
            && this.voices.some((voice) => voice.voiceURI === this.selected.voiceURI && voice.name === this.selected.name);

        if (!cachedStillAvailable) {
            const result = this.selectVoice();
            this.selected = result.voice;
            this.selectionReason = result.reason;
        }

        this.logDiagnostics('voices loaded');
    }

    speak(answer, callbacks = {}) {
        if (!this.synthesis || !answer) return;

        this.synthesis.cancel();
        if (!this.selected) this.refreshVoices();

        // Keep the official all-caps brand in the UI, but use its spoken form
        // so browser engines do not spell the final A separately.
        const utterance = new SpeechSynthesisUtterance(answer.replace(/\bTIFAA\b/g, 'Tifa'));
        utterance.lang = this.config.lang;
        utterance.rate = this.config.rate;
        utterance.pitch = this.config.pitch;
        utterance.volume = this.config.volume;
        if (this.selected) utterance.voice = this.selected;
        utterance.onstart = callbacks.onstart;
        utterance.onend = callbacks.onend;
        utterance.onerror = callbacks.onerror;

        this.synthesis.speak(utterance);
    }

    cancel() {
        this.synthesis?.cancel();
    }

    diagnostics() {
        return {
            config: { lang: this.config.lang, rate: this.config.rate, pitch: this.config.pitch, volume: this.config.volume },
            selected: this.voiceDetails(this.selected),
            reason: this.selectionReason,
            voices: this.voices.map((voice) => this.voiceDetails(voice)),
        };
    }

    selectVoice() {
        if (this.voices.length === 0) return { voice: null, reason: 'voices unavailable' };

        const storedName = this.storedPreferredName();
        const preferred = this.config.preferredNames.map((name) => name.toLocaleLowerCase());
        const ranked = this.voices.map((voice) => {
            const name = voice.name.toLocaleLowerCase();
            const lang = voice.lang.toLocaleLowerCase();
            const preferredIndex = preferred.indexOf(name);
            const isIdId = lang === 'id-id';
            const isIndonesian = lang.startsWith('id');
            const natural = /natural|neural|online|female|wanita|gadis|perempuan/i.test(voice.name);
            let score = voice.default ? 10 : 0;
            let reason = voice.default ? 'browser default fallback' : 'fallback';

            if (storedName && name === storedName) {
                score = 10000;
                reason = 'stored preferred name';
            } else if (preferredIndex !== -1) {
                score = 9000 - preferredIndex;
                reason = 'configured preferred name';
            } else if (isIdId && natural) {
                score = 8000;
                reason = 'id-ID natural preference';
            } else if (isIdId) {
                score = 7000;
                reason = 'id-ID fallback';
            } else if (isIndonesian) {
                score = 6000;
                reason = 'id language fallback';
            }

            return { voice, score, reason };
        }).sort((left, right) => right.score - left.score || left.voice.name.localeCompare(right.voice.name, undefined, { sensitivity: 'base' }) || left.voice.voiceURI.localeCompare(right.voice.voiceURI));

        return { voice: ranked[0].voice, reason: ranked[0].reason };
    }

    storedPreferredName() {
        try {
            const name = window.localStorage.getItem('tifaa.preferredVoice');
            return name?.trim().toLocaleLowerCase() || null;
        } catch {
            return null;
        }
    }

    voiceDetails(voice) {
        if (!voice) return null;

        return { name: voice.name, lang: voice.lang, localService: voice.localService, default: voice.default, voiceURI: voice.voiceURI };
    }

    logDiagnostics(event) {
        if (!isDevelopment) return;
        const details = this.diagnostics();
        console.info(`[TIFAA Voice] ${event}: ${details.voices.length}`);
        console.info('[TIFAA Voice] selected:', details.selected?.name ?? 'browser fallback');
        console.info('[TIFAA Voice] lang:', details.selected?.lang ?? this.config.lang);
        console.info('[TIFAA Voice] localService:', details.selected?.localService ?? false);
        console.info('[TIFAA Voice] default:', details.selected?.default ?? false);
        console.info('[TIFAA Voice] reason:', details.reason);
        console.table(details.voices);
    }
}

export const createTifaaVoice = (onTranscript) => {
    const output = 'speechSynthesis' in window ? new TifaaVoiceOutput() : null;
    window.tifaVoiceDebug = () => output?.diagnostics() ?? { selected: null, reason: 'speech synthesis unsupported', voices: [] };

    return {
        recognition: null,
        recognitionSupported: Boolean(window.SpeechRecognition || window.webkitSpeechRecognition),
        synthesisSupported: output !== null,
        isListening: false,
        isSpeaking: false,
        error: '',

        startListening() {
            this.error = '';
            if (!this.recognitionSupported) {
                this.error = 'Input suara belum didukung oleh browser ini. Silakan gunakan kolom teks.';
                return;
            }
            this.stopSpeaking();
            const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new Recognition();
            this.recognition.lang = 'id-ID';
            this.recognition.continuous = false;
            this.recognition.interimResults = false;
            this.recognition.onstart = () => { this.isListening = true; };
            this.recognition.onresult = (event) => {
                const transcript = Array.from(event.results).map((result) => result[0].transcript).join(' ').trim();
                this.isListening = false;
                if (transcript !== '') onTranscript(transcript);
            };
            this.recognition.onerror = (event) => { this.isListening = false; this.error = this.recognitionError(event.error); };
            this.recognition.onend = () => { this.isListening = false; };
            try {
                this.recognition.start();
            } catch {
                this.isListening = false;
                this.error = 'Mikrofon belum dapat dimulai. Periksa izin mikrofon lalu coba lagi.';
            }
        },

        cancelListening() { this.recognition?.abort(); this.isListening = false; },

        speak(answer) {
            if (!output || !answer) return;
            this.stopSpeaking();
            output.speak(answer, {
                onstart: () => { this.isSpeaking = true; },
                onend: () => { this.isSpeaking = false; },
                onerror: () => { this.isSpeaking = false; this.error = 'Jawaban belum dapat dibacakan oleh browser ini.'; },
            });
        },

        stopSpeaking() { output?.cancel(); this.isSpeaking = false; },

        recognitionError(error) {
            return {
                'not-allowed': 'Izin mikrofon ditolak. Izinkan akses mikrofon di browser untuk menggunakan input suara.',
                'service-not-allowed': 'Layanan pengenalan suara tidak diizinkan oleh browser.',
                'no-speech': 'Suara tidak terdeteksi. Coba ucapkan pertanyaan sekali lagi.',
                'audio-capture': 'Mikrofon tidak ditemukan atau sedang digunakan aplikasi lain.',
                network: 'Layanan pengenalan suara tidak dapat dihubungi. Silakan gunakan input teks.',
            }[error] ?? 'Input suara belum dapat diproses. Silakan coba lagi atau gunakan input teks.';
        },
    };
};
