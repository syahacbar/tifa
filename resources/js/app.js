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
    quickQuestionPage: 0,
    quickQuestionPageSize: 5,
    quickQuestions: [
        'Berapa jumlah sekolah di Kabupaten Teluk Bintuni?',
        'Berapa jumlah SD di Kabupaten Teluk Bintuni?',
        'Berapa jumlah sekolah negeri?',
        'Berapa jumlah sekolah swasta?',
        'Berapa jumlah sekolah di Distrik Bintuni?',
        'Berapa total siswa SD?',
        'Berapa jumlah guru sekolah negeri?',
        'Berapa laboratorium SMP?',
        'Berapa jumlah ruang kelas SD?',
        'Berapa jumlah perpustakaan SMA?',
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

    visibleQuickQuestions() {
        const start = this.quickQuestionPage * this.quickQuestionPageSize;

        return this.quickQuestions.slice(start, start + this.quickQuestionPageSize);
    },

    quickQuestionPageCount() {
        return Math.ceil(this.quickQuestions.length / this.quickQuestionPageSize);
    },

    changeQuickQuestionPage(direction) {
        this.quickQuestionPage = (this.quickQuestionPage + direction + this.quickQuestionPageCount()) % this.quickQuestionPageCount();
    },

    formattedValue() {
        return new Intl.NumberFormat('id-ID').format(this.response?.data?.value ?? 0);
    },
});

// The only non-exact mapping approved for this snapshot. Source values stay unchanged.
const districtNameMapping = {
    Aranday: 'Arandai',
};

const districtPalette = {
    '92.06.01': '#8ecae6', '92.06.02': '#bde0fe', '92.06.03': '#a8dadc', '92.06.04': '#cce3de',
    '92.06.05': '#90dbf4', '92.06.06': '#b9fbc0', '92.06.07': '#c7ceea', '92.06.08': '#ffd6a5',
    '92.06.09': '#fdffb6', '92.06.10': '#d9c2f0', '92.06.11': '#f8c8dc', '92.06.12': '#c8e7ed',
    '92.06.13': '#b8e0d2', '92.06.14': '#f6d6ad', '92.06.15': '#cddafd', '92.06.16': '#d8f3dc',
    '92.06.17': '#f7cad0', '92.06.18': '#c5dedd', '92.06.19': '#d7c0d0', '92.06.20': '#ffe5b4',
    '92.06.21': '#b8def5', '92.06.22': '#b9e4c9', '92.06.23': '#f4c2c2', '92.06.24': '#d0d1ff',
};

const districtStyle = (district) => ({
    color: '#0c4a6e',
    weight: 1.15,
    fillColor: districtPalette[district?.code] ?? '#cbd5e1',
    fillOpacity: 0.74,
});

const initialDistrictSummaries = () => {
    try {
        return JSON.parse(document.getElementById('tifa-district-summary')?.textContent ?? '[]');
    } catch (error) {
        return [];
    }
};

const defaultMapFilters = { level: 'all', status: 'all' };
const filterDistrictSummary = (district, filters) => {
    const filteredSchools = (district.schools ?? []).filter((school) => {
        const levelMatches = filters.level === 'all'
            || (filters.level === 'other' ? ['PKBM', 'SKB'].includes(school.education_level) : school.education_level === filters.level);
        const statusMatches = filters.status === 'all' || school.status?.toLowerCase() === filters.status.toLowerCase();

        return levelMatches && statusMatches;
    });

    if (district.schools) {
        return {
            ...district,
            schools: filteredSchools,
            total_schools: filteredSchools.length,
            public_schools: filteredSchools.filter((school) => school.status?.toLowerCase() === 'negeri').length,
            private_schools: filteredSchools.filter((school) => school.status?.toLowerCase() === 'swasta').length,
        };
    }

    const levels = ['KB', 'TK', 'SD', 'SMP', 'SMA', 'SMK'];
    const statusKey = filters.status === 'all' ? null : filters.status;
    const sourceLevels = statusKey ? (district.levels_by_status?.[statusKey] ?? {}) : (district.levels ?? {});
    const statusTotal = statusKey === 'Negeri'
        ? district.public_schools
        : statusKey === 'Swasta'
            ? district.private_schools
            : district.total_schools;
    const selectedTotal = filters.level === 'all'
        ? statusTotal
        : filters.level === 'other'
            ? statusTotal - levels.reduce((total, level) => total + (sourceLevels[level] ?? 0), 0)
            : (sourceLevels[filters.level] ?? 0);
    const publicSchools = filters.status === 'Swasta'
        ? 0
        : filters.level === 'all'
            ? district.public_schools
            : filters.level === 'other'
                ? district.public_schools - levels.reduce((total, level) => total + (district.levels_by_status?.Negeri?.[level] ?? 0), 0)
                : (district.levels_by_status?.Negeri?.[filters.level] ?? 0);
    const privateSchools = filters.status === 'Negeri'
        ? 0
        : filters.level === 'all'
            ? district.private_schools
            : filters.level === 'other'
                ? district.private_schools - levels.reduce((total, level) => total + (district.levels_by_status?.Swasta?.[level] ?? 0), 0)
                : (district.levels_by_status?.Swasta?.[filters.level] ?? 0);

    return { ...district, total_schools: selectedTotal, public_schools: publicSchools, private_schools: privateSchools };
};

const districtTooltip = (district) => `<div class="tifa-district-tooltip__name">${district.name}</div><div class="tifa-district-tooltip__total">${district.total_schools} sekolah</div><div class="tifa-district-tooltip__meta">${district.public_schools} Negeri <span>•</span> ${district.private_schools} Swasta</div>`;
const schoolLevelOrder = ['KB', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'PKBM', 'SKB'];
const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[character]);
const districtPopup = (district) => {
    if (district.schools.length === 0) {
        return `<div class="tifa-district-popup"><div class="tifa-district-popup__name">${escapeHtml(district.name)}</div><div class="tifa-district-popup__total">0 sekolah</div><p class="tifa-district-popup__empty">Tidak ada sekolah pada dataset aktif untuk filter ini.</p></div>`;
    }

    const groups = schoolLevelOrder.map((level) => {
        const schools = district.schools.filter((school) => school.education_level === level).sort((first, second) => first.name.localeCompare(second.name, 'id'));
        if (schools.length === 0) return '';

        return `<section class="tifa-district-popup__group"><h3>${level} <span>· ${schools.length}</span></h3>${schools.map((school) => `<div class="tifa-district-popup__school"><span>${escapeHtml(school.name)}</span><small>${escapeHtml(school.status)}</small></div>`).join('')}</section>`;
    }).join('');

    return `<div class="tifa-district-popup"><div class="tifa-district-popup__name">${escapeHtml(district.name)}</div><div class="tifa-district-popup__total">${district.total_schools} sekolah</div><div class="tifa-district-popup__meta">${district.public_schools} Negeri <span>•</span> ${district.private_schools} Swasta</div><div class="tifa-district-popup__list">${groups}</div></div>`;
};

window.tifaEducationMap = () => ({
    map: null,
    districtLayer: null,
    districts: initialDistrictSummaries(),
    baseDistricts: [],
    filters: { ...defaultMapFilters },
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
            window.addEventListener('tifa-map-filters', (event) => this.applyFilters(event.detail));
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
                    code: feature.properties.KDCPUM,
                    name: identifier,
                    sourceIdentifier: summary ? sourceIdentifier : null,
                    total_schools: summary?.total_schools ?? 0,
                    public_schools: summary?.public_schools ?? 0,
                    private_schools: summary?.private_schools ?? 0,
                    schools: summary?.schools ?? [],
                    has_school_data: Boolean(summary),
                };
            }).sort((first, second) => second.total_schools - first.total_schools || first.name.localeCompare(second.name, 'id'));

            const summariesByIdentifier = new Map(mappedDistricts.map((district) => [district.identifier, district]));
            this.baseDistricts = mappedDistricts;
            this.districtLayer = L.geoJSON(boundary, {
                style: (feature) => districtStyle(summariesByIdentifier.get(feature.properties.WADMKC)),
                onEachFeature: (feature, layer) => {
                    const district = summariesByIdentifier.get(feature.properties.WADMKC);
                    const detail = district.has_school_data
                        ? `${district.total_schools} sekolah · ${district.public_schools} Negeri · ${district.private_schools} Swasta`
                        : 'Tidak ada sekolah pada dataset aktif';

                    layer.bindTooltip(districtTooltip(district), { className: 'tifa-district-tooltip', sticky: true, direction: 'top', offset: [0, -4] });
                    layer.bindPopup(districtPopup(district), { className: 'tifa-district-popup-shell', minWidth: 280, maxWidth: 360, autoPanPadding: [20, 20] });
                    layer.on({
                        mouseover: () => {
                            if (this.selectedIdentifier !== district.identifier) {
                                layer.setStyle({ color: '#075985', weight: 1.8, fillOpacity: 0.88 });
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
            this.applyFilters(this.filters);
        } catch (error) {
            window.dispatchEvent(new CustomEvent('district-map-error'));
        }
    },

    applyFilters(filters) {
        this.filters = { ...defaultMapFilters, ...filters };

        if (!this.districtLayer) {
            return;
        }

        this.districts = this.baseDistricts.map((district) => filterDistrictSummary(district, this.filters));
        const byIdentifier = new Map(this.districts.map((district) => [district.identifier, district]));

        this.districtLayer.eachLayer((layer) => {
            const district = byIdentifier.get(layer.feature.properties.WADMKC);
            layer.setTooltipContent(districtTooltip(district));
            layer.setPopupContent(districtPopup(district));
            layer.setStyle(districtStyle(district));
        });

        if (this.selectedIdentifier) {
            this.selectDistrict(this.selectedIdentifier, false);
        }

        window.dispatchEvent(new CustomEvent('districts-ready', { detail: { districts: this.districts } }));
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
                layer.openPopup();

                if (focus) {
                    this.map.fitBounds(layer.getBounds(), { padding: [40, 40], maxZoom: 10 });
                }
            } else {
                layer.setStyle(districtStyle(district));
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

window.tifaMapFilters = () => ({
    filters: { ...defaultMapFilters },

    apply() {
        window.dispatchEvent(new CustomEvent('tifa-map-filters', { detail: this.filters }));
    },
});

Alpine.start();
