import Alpine from 'alpinejs';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import districtBoundaryUrl from '../geojson/teluk-bintuni-districts.big.geojson?url';
import { createTifaaVoice } from './tifa-voice';

window.Alpine = Alpine;

window.tifaVoice = createTifaaVoice;

window.tifaAssistant = () => ({
    question: '',
    response: null,
    teacherContext: null,
    error: '',
    isLoading: false,
    voice: null,
    quickQuestions: [
        { label: 'Sekolah tiap jenjang', question: 'Berapa jumlah sekolah di setiap jenjang pendidikan?' },
        { label: '5 distrik sekolah terbanyak', question: 'Sebutkan 5 distrik dengan jumlah sekolah terbanyak.' },
        { label: 'Jenjang sekolah Manimeri', question: 'Berapa jumlah sekolah di Distrik Manimeri berdasarkan jenjang?' },
        { label: 'SMP Negeri di Bintuni', question: 'Tampilkan SMP Negeri di Distrik Bintuni.' },
        { label: 'Daftar SMA Teluk Bintuni', question: 'Tampilkan SMA di Kabupaten Teluk Bintuni.' },
        { label: 'Guru tiap jenjang', question: 'Berapa jumlah guru di setiap jenjang pendidikan?' },
        { label: '5 sekolah guru terbanyak', question: 'Sebutkan 5 sekolah dengan jumlah guru terbanyak.' },
        { label: 'Guru di Manimeri', question: 'Berapa jumlah guru yang mengajar di Distrik Manimeri?' },
        { label: 'Guru SMP di Bintuni', question: 'Berapa jumlah guru SMP di Distrik Bintuni?' },
        { label: '5 SMP guru terbanyak', question: 'Tampilkan 5 SMP dengan jumlah guru terbanyak.' },
        { label: 'Status sekolah', question: 'Berapa jumlah sekolah negeri dan swasta di Kabupaten Teluk Bintuni?' },
        { label: 'Jumlah siswa', question: 'Berapa jumlah siswa di Kabupaten Teluk Bintuni?' },
        { label: 'Sekolah Babo', question: 'Tampilkan sekolah yang ada di Distrik Babo.' },
        { label: 'Guru PNS SMP', question: 'Berapa jumlah guru PNS yang mengajar di tingkat SMP?' },
        { label: 'SD Tuhiba', question: 'Tampilkan SD di Distrik Tuhiba.' },
        { label: '5 SD guru terbanyak', question: 'Tampilkan 5 SD dengan jumlah guru terbanyak.' },
        { label: 'SMP Sumuri', question: 'Tampilkan SMP di Distrik Sumuri.' },
        { label: 'Guru Bintuni', question: 'Berapa jumlah guru di Distrik Bintuni?' },
        { label: '5 distrik guru terbanyak', question: 'Sebutkan 5 distrik dengan jumlah guru terbanyak.' },
        { label: 'Status guru', question: 'Berapa jumlah guru berdasarkan status kepegawaian?' },
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
                throw new Error(payload.message ?? 'TIFAA belum dapat menjawab pertanyaan ini.');
            }

            this.response = payload;
            this.teacherContext = payload.teacher_context ?? null;
            this.voice?.speak(payload.answer);
        } catch (error) {
            this.response = null;
            this.error = error instanceof Error ? error.message : 'Terjadi gangguan saat menghubungi TIFAA.';
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
        return new Intl.NumberFormat('id-ID').format(this.response?.presentation?.value ?? this.response?.data?.value ?? 0);
    },

    presentationRows() {
        return this.response?.presentation?.data ?? this.response?.presentation?.rows ?? [];
    },

    presentationBarWidth(value) {
        const maximum = Math.max(...this.presentationRows().map((item) => Number(item.value) || 0), 1);

        return `${Math.max(3, (Number(value) / maximum) * 100)}%`;
    },

    presentationCell(row, key) {
        return row?.[key] ?? '—';
    },

    isResponseState() {
        return this.isLoading || this.response !== null || this.error !== '';
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

window.tifaPublicDocuments = (initialData = {}) => ({
    documents: [],
    currentPage: 1,
    lastPage: 1,
    total: 0,
    isLoading: false,
    fetchError: null,

    init() {
        const scriptEl = document.getElementById('tifa-public-documents-data');
        if (scriptEl && scriptEl.textContent.trim()) {
            try {
                this.documents = JSON.parse(scriptEl.textContent);
            } catch (e) {
                this.documents = [];
            }
        } else if (initialData.documents) {
            this.documents = initialData.documents;
        }

        const countEl = document.getElementById('tifa-public-documents-total');
        if (countEl) {
            this.total = parseInt(countEl.textContent || '0', 10) || this.documents.length;
        } else {
            this.total = initialData.total || this.documents.length;
        }

        this.lastPage = Math.max(1, Math.ceil(this.total / 6));
        this.currentPage = initialData.currentPage || 1;
    },

    async loadPage(page) {
        if (page < 1 || page > this.lastPage || this.isLoading) return;
        this.isLoading = true;
        this.fetchError = null;

        try {
            const res = await fetch(`/api/ruang-informasi?page=${page}`);
            if (!res.ok) {
                throw new Error('Gagal memuat dokumen');
            }
            const data = await res.json();
            this.documents = data.data || [];
            this.currentPage = data.current_page;
            this.lastPage = data.last_page;
            this.total = data.total;
        } catch (err) {
            this.fetchError = 'Gagal memuat dokumen. Silakan coba lagi.';
        } finally {
            this.isLoading = false;
        }
    },

    prevPage() {
        if (this.currentPage > 1) {
            this.loadPage(this.currentPage - 1);
        }
    },

    nextPage() {
        if (this.currentPage < this.lastPage) {
            this.loadPage(this.currentPage + 1);
        }
    },

    openDocument(doc) {
        window.dispatchEvent(new CustomEvent('open-pdf', {
            detail: {
                title: doc.title,
                file: doc.file || doc.file_url,
                downloadName: doc.download_name,
            }
        }));
    },
});

Alpine.start();


