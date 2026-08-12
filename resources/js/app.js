import Alpine from 'alpinejs';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import districtBoundaryUrl from '../geojson/teluk-bintuni-districts.big.geojson?url';

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
    teacherContext: null,
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
                body: JSON.stringify({ question: this.question, teacher_context: this.teacherContext }),
            });
            const payload = await request.json();

            if (!request.ok) {
                throw new Error(payload.message ?? 'TIFA belum dapat menjawab pertanyaan ini.');
            }

            this.response = payload;
            this.teacherContext = payload.teacher_context ?? null;
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
        this.teacherContext = null;
        this.$nextTick(() => this.$refs.question.focus());
    },

    formattedValue() {
        return new Intl.NumberFormat('id-ID').format(this.response?.data?.value ?? 0);
    },
});

// The only non-exact mapping approved for this snapshot. Source values stay unchanged.
const districtNameMapping = {
    Aranday: 'Arandai',
};

const districtStyle = (totalSchools) => ({
    color: '#0c4a6e',
    weight: 1.15,
    fillColor: totalSchools === 0
        ? '#cbd5e1'
        : totalSchools <= 5
            ? '#bae6fd'
            : totalSchools <= 10
                ? '#7dd3fc'
                : totalSchools <= 20
                    ? '#38bdf8'
                    : totalSchools <= 40
                        ? '#0ea5e9'
                        : '#0369a1',
    fillOpacity: totalSchools === 0 ? 0.42 : 0.7,
});

const initialDistrictSummaries = () => {
    try {
        return JSON.parse(document.getElementById('tifa-district-summary')?.textContent ?? '[]');
    } catch (error) {
        return [];
    }
};

window.tifaEducationMap = () => ({
    map: null,
    districtLayer: null,
    districts: initialDistrictSummaries(),
    selectedIdentifier: null,
    handleResize: null,

    init() {
        this.$nextTick(() => {
            this.map = L.map(this.$refs.map, {
                attributionControl: true,
                zoomControl: true,
                scrollWheelZoom: false,
            }).setView([-2.2, 133.15], 8);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this.map);
            this.map.getPane('tilePane').style.opacity = '0.62';

            this.handleResize = () => this.map?.invalidateSize();
            window.addEventListener('resize', this.handleResize);
            window.addEventListener('tifa-district-selected', (event) => this.selectDistrict(event.detail.identifier, event.detail.focus));
            this.map.invalidateSize();
            this.loadDistricts();
        });
    },

    async loadDistricts() {
        try {
            const response = await fetch(districtBoundaryUrl);

            if (!response.ok) {
                throw new Error('Snapshot boundary tidak dapat dimuat.');
            }

            const boundary = await response.json();
            const summaries = new Map(this.districts.map((district) => [district.identifier, district]));
            const mappedDistricts = boundary.features.map((feature) => {
                const identifier = feature.properties.WADMKC;
                const sourceIdentifier = districtNameMapping[identifier] ?? identifier;
                const summary = summaries.get(sourceIdentifier);

                return {
                    identifier,
                    name: identifier,
                    sourceIdentifier: summary ? sourceIdentifier : null,
                    total_schools: summary?.total_schools ?? 0,
                    public_schools: summary?.public_schools ?? 0,
                    private_schools: summary?.private_schools ?? 0,
                    has_school_data: Boolean(summary),
                };
            }).sort((first, second) => second.total_schools - first.total_schools || first.name.localeCompare(second.name, 'id'));

            const summariesByIdentifier = new Map(mappedDistricts.map((district) => [district.identifier, district]));
            this.districts = mappedDistricts;
            this.districtLayer = L.geoJSON(boundary, {
                style: (feature) => districtStyle(summariesByIdentifier.get(feature.properties.WADMKC)?.total_schools ?? 0),
                onEachFeature: (feature, layer) => {
                    const district = summariesByIdentifier.get(feature.properties.WADMKC);
                    const detail = district.has_school_data
                        ? `${district.total_schools} sekolah · ${district.public_schools} Negeri · ${district.private_schools} Swasta`
                        : 'Tidak ada sekolah pada dataset aktif';

                    layer.bindTooltip(
                        `<div class="tifa-district-tooltip__name">${district.name}</div><div class="tifa-district-tooltip__total">${district.total_schools} sekolah</div><div class="tifa-district-tooltip__meta">${district.public_schools} Negeri <span>•</span> ${district.private_schools} Swasta</div>`,
                        { className: 'tifa-district-tooltip', sticky: true, direction: 'top', offset: [0, -4] },
                    );
                    layer.on({
                        mouseover: () => {
                            if (this.selectedIdentifier !== district.identifier) {
                                layer.setStyle({ color: '#075985', weight: 1.8, fillOpacity: 0.82 });
                            }
                        },
                        mouseout: () => {
                            if (this.selectedIdentifier !== district.identifier) {
                                this.districtLayer.resetStyle(layer);
                            }
                        },
                        click: () => {
                            this.selectDistrict(district.identifier, true);
                            window.dispatchEvent(new CustomEvent('district-selected', { detail: { identifier: district.identifier } }));
                        },
                    });
                },
            }).addTo(this.map);

            this.map.fitBounds(this.districtLayer.getBounds(), { padding: [8, 8], maxZoom: 10 });
            window.dispatchEvent(new CustomEvent('districts-ready', { detail: { districts: mappedDistricts } }));
        } catch (error) {
            window.dispatchEvent(new CustomEvent('district-map-error'));
        }
    },

    selectDistrict(identifier, focus = false) {
        if (!this.districtLayer) {
            return;
        }

        this.selectedIdentifier = identifier;
        this.districtLayer.eachLayer((layer) => {
            const layerIdentifier = layer.feature.properties.WADMKC;
            const district = this.districts.find((item) => item.identifier === layerIdentifier);

            if (layerIdentifier === identifier) {
                layer.setStyle({ color: '#0f172a', weight: 2.8, fillOpacity: 0.9, dashArray: null });
                layer.bringToFront();
                layer.openTooltip();

                if (focus) {
                    this.map.fitBounds(layer.getBounds(), { padding: [40, 40], maxZoom: 10 });
                }
            } else {
                layer.setStyle(districtStyle(district?.total_schools ?? 0));
            }
        });
    },

    destroy() {
        window.removeEventListener('resize', this.handleResize);
        this.map?.remove();
        this.map = null;
    },
});

window.tifaDistrictSummary = () => ({
    districts: initialDistrictSummaries(),
    selectedIdentifier: null,
    mapUnavailable: false,

    init() {
        window.addEventListener('districts-ready', (event) => {
            this.districts = event.detail.districts;
        });
        window.addEventListener('district-selected', (event) => this.selectDistrict(event.detail.identifier, false));
        window.addEventListener('district-map-error', () => {
            this.mapUnavailable = true;
        });
    },

    selectDistrict(identifier, focus = true) {
        this.selectedIdentifier = identifier;

        if (focus) {
            window.dispatchEvent(new CustomEvent('tifa-district-selected', { detail: { identifier, focus: true } }));
        }

        this.$nextTick(() => {
            const item = Array.from(this.$refs.districtList.querySelectorAll('[data-district-identifier]'))
                .find((element) => element.dataset.districtIdentifier === identifier);
            item?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
    },
});

Alpine.start();
