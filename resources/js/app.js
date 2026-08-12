import Alpine from 'alpinejs';

window.Alpine = Alpine;

window.tifaVoice = (onTranscript) => ({
    recognition: null,
    recognitionSupported: Boolean(window.SpeechRecognition || window.webkitSpeechRecognition),
    synthesisSupported: 'speechSynthesis' in window,
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

        this.recognition.onstart = () => {
            this.isListening = true;
        };
        this.recognition.onresult = (event) => {
            const transcript = Array.from(event.results)
                .map((result) => result[0].transcript)
                .join(' ')
                .trim();

            this.isListening = false;

            if (transcript !== '') {
                onTranscript(transcript);
            }
        };
        this.recognition.onerror = (event) => {
            this.isListening = false;
            this.error = this.recognitionError(event.error);
        };
        this.recognition.onend = () => {
            this.isListening = false;
        };

        try {
            this.recognition.start();
        } catch (error) {
            this.isListening = false;
            this.error = 'Mikrofon belum dapat dimulai. Periksa izin mikrofon lalu coba lagi.';
        }
    },

    cancelListening() {
        this.recognition?.abort();
        this.isListening = false;
    },

    speak(answer) {
        if (!this.synthesisSupported || !answer) {
            return;
        }

        this.stopSpeaking();

        const utterance = new SpeechSynthesisUtterance(answer);
        utterance.lang = 'id-ID';
        utterance.rate = 0.95;
        utterance.onstart = () => {
            this.isSpeaking = true;
        };
        utterance.onend = () => {
            this.isSpeaking = false;
        };
        utterance.onerror = () => {
            this.isSpeaking = false;
            this.error = 'Jawaban belum dapat dibacakan oleh browser ini.';
        };

        window.speechSynthesis.speak(utterance);
    },

    stopSpeaking() {
        if (!this.synthesisSupported) {
            return;
        }

        window.speechSynthesis.cancel();
        this.isSpeaking = false;
    },

    recognitionError(error) {
        return {
            'not-allowed': 'Izin mikrofon ditolak. Izinkan akses mikrofon di browser untuk menggunakan input suara.',
            'service-not-allowed': 'Layanan pengenalan suara tidak diizinkan oleh browser.',
            'no-speech': 'Suara tidak terdeteksi. Coba ucapkan pertanyaan sekali lagi.',
            'audio-capture': 'Mikrofon tidak ditemukan atau sedang digunakan aplikasi lain.',
            network: 'Layanan pengenalan suara tidak dapat dihubungi. Silakan gunakan input teks.',
        }[error] ?? 'Input suara belum dapat diproses. Silakan coba lagi atau gunakan input teks.';
    },
});

window.tifaAssistant = () => ({
    question: '',
    response: null,
    error: '',
    isLoading: false,
    voice: null,
    quickQuestions: [
        'Berapa jumlah SD di Kabupaten Teluk Bintuni?',
        'Berapa total siswa SD?',
        'Berapa jumlah guru sekolah negeri?',
        'Berapa laboratorium SMP?',
    ],

    init() {
        this.voice = window.tifaVoice((transcript) => {
            this.question = transcript;
            this.ask(transcript);
        });
    },

    async ask(question = this.question) {
        this.question = question.trim();
        this.error = '';

        if (this.question === '') {
            this.error = 'Tulis pertanyaan tentang data pendidikan terlebih dahulu.';
            this.$nextTick(() => this.$refs.question.focus());

            return;
        }

        this.isLoading = true;

        try {
            const request = await fetch('/api/tifa/ask', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ question: this.question }),
            });
            const payload = await request.json();

            if (!request.ok) {
                throw new Error(payload.message ?? 'TIFA belum dapat menjawab pertanyaan ini.');
            }

            this.response = payload;
            this.voice?.speak(payload.answer);
        } catch (error) {
            this.response = null;
            this.error = error instanceof Error ? error.message : 'Terjadi gangguan saat menghubungi TIFA.';
        } finally {
            this.isLoading = false;
        }
    },

    askAgain() {
        this.voice?.cancelListening();
        this.voice?.stopSpeaking();
        this.response = null;
        this.error = '';
        this.question = '';
        this.$nextTick(() => this.$refs.question.focus());
    },

    formattedValue() {
        return new Intl.NumberFormat('id-ID').format(this.response?.data?.value ?? 0);
    },
});

Alpine.start();
