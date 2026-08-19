<x-layouts.app title="TIFAA">
    <section x-data="tifaAssistant()" x-init="init()" class="relative min-h-full overflow-hidden bg-[#f3f8fb] lg:h-[calc(100dvh-3.75rem)] lg:min-h-[36rem]">
        <div aria-hidden="true" class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_8%_2%,rgba(186,230,253,.72),transparent_28%),radial-gradient(circle_at_92%_12%,rgba(207,250,254,.64),transparent_24%),linear-gradient(135deg,#f8fbfd_0%,#edf7fb_50%,#f4f8fb_100%)]"></div>
        <div aria-hidden="true" class="absolute -right-24 top-20 -z-10 size-72 rotate-12 opacity-[0.08] [background:repeating-linear-gradient(45deg,#0e7490_0_2px,transparent_2px_14px),repeating-linear-gradient(-45deg,#f59e0b_0_2px,transparent_2px_18px)]"></div>

        <div class="mx-auto grid min-h-full max-w-[1600px] gap-4 px-4 py-4 sm:px-6 lg:h-full lg:grid-cols-[.66fr_1fr] lg:gap-4 lg:px-6 lg:py-4 xl:px-8">
            <section class="flex min-h-0 flex-col rounded-[1.75rem] border border-sky-100/80 bg-white/90 p-4 shadow-[0_20px_55px_-34px_rgba(14,116,144,.4)] transition-[padding] duration-200" :class="isResponseState() ? 'lg:p-4' : 'p-5 lg:p-6'" aria-label="Asisten TIFAA">
                <div class="relative min-h-32 overflow-hidden rounded-2xl border border-sky-100/70 bg-gradient-to-br from-sky-100 via-white to-cyan-50 px-4 py-4 pr-28 transition-[min-height,padding] duration-200 sm:min-h-36 sm:px-5 sm:pr-32 lg:min-h-60 lg:px-6 lg:py-5 lg:pr-52 xl:min-h-[17rem] xl:pr-64" :class="isResponseState() ? 'lg:!min-h-36 lg:!pr-40 xl:!min-h-40 xl:!pr-48' : ''">
                    <div aria-hidden="true" class="absolute -bottom-12 -left-10 size-40 rotate-12 opacity-[0.14] [background:repeating-linear-gradient(45deg,rgba(14,116,144,.8)_0_2px,transparent_2px_12px),repeating-linear-gradient(-45deg,rgba(245,158,11,.7)_0_2px,transparent_2px_16px)]"></div>
                    <div aria-hidden="true" class="absolute -right-4 top-2 size-28 rounded-full bg-cyan-200/35 blur-2xl"></div>
                    <div aria-hidden="true" class="absolute right-28 top-3 size-20 rotate-45 border-2 border-sky-300/50 opacity-50 lg:right-56 lg:top-5"></div>
                    <div class="relative z-10 min-w-0 max-w-[15rem] sm:max-w-[18rem] lg:max-w-[19rem]" :class="isResponseState() ? 'lg:max-w-[22rem]' : ''">
                        <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold tracking-[.12em] text-emerald-700"><span class="size-1.5 rounded-full bg-emerald-500"></span>TIFAA SIAP MELAYANI</div>
                        <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 xl:text-4xl" :class="isResponseState() ? 'lg:!mt-2 lg:!text-2xl xl:!text-3xl' : ''">Halo, saya TIFAA.</h1>
                        <p class="mt-2 text-sm leading-6 text-slate-600" :class="isResponseState() ? 'lg:!mt-1 lg:!leading-5' : ''">Tata Kelola dan Informasi Pendidikan Terintegrasi Kabupaten Teluk Bintuni</p>
                    </div>
                    <div class="pointer-events-none absolute bottom-0 right-0 z-10" data-tifaa-mascot-state="idle">
                        <img src="{{ Vite::asset('resources/images/branding/tifaa-mascot.png') }}" alt="Maskot TIFAA" class="h-32 w-auto object-contain drop-shadow-md transition-[height] duration-200 sm:h-40 lg:h-60 xl:h-[17rem]" :class="isResponseState() ? 'lg:!h-36 xl:!h-40' : ''">
                    </div>
                </div>

                <form @submit.prevent="ask()" class="mt-5 border-t border-sky-100/80 pt-4 transition-[margin] duration-200" :class="isResponseState() ? 'lg:!mt-3 lg:pt-3' : ''" aria-label="Form pertanyaan TIFAA">
                    <label for="question" class="mb-2 block text-sm font-bold text-slate-800">Apa yang ingin Anda ketahui?</label>
                    <div class="flex gap-2">
                        <input x-ref="question" x-model="question" id="question" type="text" maxlength="1000" autocomplete="off" placeholder="Tulis pertanyaan pendidikan..." class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100" :disabled="isLoading">
                        <button x-cloak x-show="voice?.recognitionSupported" type="button" @click="voice.startListening()" :aria-pressed="voice?.isListening" aria-label="Ajukan pertanyaan dengan suara" class="grid size-12 shrink-0 place-items-center rounded-2xl border border-sky-200 bg-sky-50 text-sky-700 transition hover:bg-sky-100 disabled:opacity-60" :disabled="isLoading || voice?.isListening"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v5M8 22h8"/></svg></button>
                        <button type="submit" class="inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-2xl bg-sky-700 px-4 text-sm font-bold text-white shadow-lg shadow-sky-200 transition hover:bg-sky-800 disabled:bg-sky-400" :disabled="isLoading"><svg x-show="!isLoading" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/></svg><svg x-cloak x-show="isLoading" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/></svg><span class="hidden sm:inline" x-text="isLoading ? 'Memproses' : 'Tanya'">Tanya</span></button>
                    </div>
                </form>

                <div class="mt-4 border-t border-slate-100 pt-3 transition-[margin] duration-200" :class="isResponseState() ? 'lg:!mt-2 lg:pt-2' : ''">
                    <div class="mt-2"><p class="mb-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-500">Pertanyaan cepat</p><div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3 xl:grid-cols-4" :class="isResponseState() ? 'lg:gap-1' : ''"><template x-for="item in quickQuestions" :key="item.question"><button type="button" @click="ask(item.question)" :disabled="isLoading" class="min-w-0 rounded-lg border border-sky-100 bg-sky-50 px-2 py-1.5 text-left text-[11px] font-semibold leading-4 text-sky-800 transition hover:border-sky-300 hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-1 disabled:opacity-60" :class="isResponseState() ? 'lg:!px-1.5 lg:!py-1 lg:text-[10px]' : ''" x-text="item.label"></button></template></div></div>
                </div>

                <div class="mt-4 min-h-0 flex-1 overflow-hidden rounded-2xl border border-sky-100/80 bg-slate-50/80 shadow-inner shadow-sky-50 transition-[min-height,margin] duration-200" :class="isResponseState() ? 'lg:!mt-3 lg:min-h-[18rem] xl:min-h-[20rem]' : 'lg:min-h-32'">
                    <div x-cloak x-show="voice?.isListening" class="flex h-full items-center gap-3 p-5 text-cyan-900"><span class="relative flex size-10"><span class="absolute inline-flex size-full animate-ping rounded-full bg-cyan-300 opacity-60"></span><span class="relative grid size-10 place-items-center rounded-full bg-cyan-600 text-white">♫</span></span><div><p class="font-bold">Mendengarkan...</p><p class="text-sm">Ucapkan pertanyaan Anda dengan jelas.</p><button type="button" @click="voice.cancelListening()" class="mt-2 text-xs font-bold text-cyan-700">Batalkan</button></div></div>
                    <div x-cloak x-show="isLoading" class="flex h-full items-center justify-center p-5 text-center"><div><div class="mx-auto grid size-10 place-items-center rounded-2xl bg-sky-100 text-sky-700">+</div><p class="mt-2 text-sm font-bold text-slate-900">TIFAA sedang menyiapkan jawaban</p></div></div>
                    <div x-cloak x-show="error" class="h-full overflow-y-auto p-5" role="alert"><p class="font-bold text-rose-800">TIFAA belum dapat menjawab</p><p class="mt-1 text-sm leading-6 text-rose-700" x-text="error"></p></div>
                    <div x-cloak x-show="response" x-transition.opacity class="flex h-full flex-col" aria-live="polite"><div class="bg-gradient-to-r from-sky-700 to-cyan-600 px-5 py-4 text-white"><p class="text-[11px] font-bold uppercase tracking-[.14em] text-sky-100">Jawaban TIFAA</p><p class="mt-1 text-sm font-semibold leading-6" x-text="response?.answer"></p></div><div class="min-h-0 flex-1 overflow-y-auto p-4"><div x-show="response?.presentation?.type === 'kpi' || (!response?.presentation && response?.visualization === 'kpi')" class="rounded-2xl bg-sky-50 p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-sky-700" x-text="response?.presentation?.title ?? 'Hasil utama'"></p><p class="mt-1 text-4xl font-black text-slate-950"><span x-text="formattedValue()"></span><span class="ml-1 text-sm font-bold text-slate-500" x-text="response?.presentation?.unit"></span></p></div><section x-show="response?.presentation?.type === 'bar_chart'" class="rounded-2xl border border-sky-100 bg-white p-3"><div class="flex items-baseline justify-between gap-2"><h3 class="text-xs font-bold text-slate-900" x-text="response?.presentation?.title"></h3><span class="text-[10px] font-semibold text-slate-500" x-text="response?.presentation?.value_label"></span></div><div class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1"><template x-for="item in presentationRows()" :key="item.label"><div><div class="mb-1 flex items-center justify-between gap-2 text-[11px]"><span class="truncate font-semibold text-slate-700" x-text="item.label"></span><span class="shrink-0 font-bold text-sky-800"><span x-text="new Intl.NumberFormat('id-ID').format(item.value)"></span> <span x-text="response?.presentation?.unit"></span></span></div><div class="h-2 overflow-hidden rounded-full bg-sky-50"><div class="h-full rounded-full bg-gradient-to-r from-sky-600 to-cyan-500" :style="{ width: presentationBarWidth(item.value) }"></div></div></div></template></div></section><section x-show="response?.presentation?.type === 'table'" class="overflow-hidden rounded-2xl border border-slate-200 bg-white"><h3 class="border-b border-slate-100 px-3 py-2 text-xs font-bold text-slate-900" x-text="response?.presentation?.title"></h3><div class="max-h-64 overflow-auto"><table class="min-w-full text-left text-[11px]"><thead class="sticky top-0 bg-slate-50 text-slate-500"><tr><template x-for="column in response?.presentation?.columns ?? []" :key="column.key"><th class="whitespace-nowrap px-3 py-2 font-bold" x-text="column.label"></th></template></tr></thead><tbody class="divide-y divide-slate-100"><template x-for="row in presentationRows()" :key="row.npsn ?? row.name"><tr><template x-for="column in response?.presentation?.columns ?? []" :key="column.key"><td class="whitespace-nowrap px-3 py-2 text-slate-700" x-text="presentationCell(row, column.key)"></td></template></tr></template></tbody></table></div></section><dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-600"><div><dt>Periode</dt><dd class="font-semibold text-slate-900" x-text="response?.source?.reference_period"></dd></div><div><dt>Sumber</dt><dd class="font-semibold text-slate-900" x-text="response?.source?.dataset ?? 'Data Pendidikan Terintegrasi Teluk Bintuni'"></dd></div></dl></div><div class="border-t border-slate-100 px-4 py-3"><button type="button" @click="askAgain()" class="text-xs font-bold text-sky-700">Tanya Lagi</button></div></div>
                    <div x-show="!response && !isLoading && !error && !voice?.isListening" class="flex h-full items-center justify-center p-8 text-center"><div><div class="mx-auto grid size-11 place-items-center rounded-2xl bg-sky-100 text-xl text-sky-700">✦</div><p class="mt-3 text-sm font-bold text-slate-700">Ruang jawaban TIFAA</p><p class="mt-1 text-xs leading-5 text-slate-500">Hasil pertanyaan Anda akan tampil di sini.</p></div></div>
                </div>
                <p x-cloak x-show="voice && !voice.recognitionSupported" class="mt-2 text-xs text-slate-500">Input suara belum didukung browser ini; Anda tetap dapat mengetik.</p>
                <p x-cloak x-show="voice?.error" class="mt-2 text-xs font-medium text-rose-700" x-text="voice?.error"></p>
            </section>

            <section class="grid min-h-[23rem] gap-4 lg:min-h-0 lg:grid-cols-[minmax(0,1fr)_17rem]" aria-label="Sebaran pendidikan">
                <script id="tifa-district-summary" type="application/json">@json($districtSummary['districts'])</script>
                <div x-data="tifaMapFilters()" class="flex min-h-[20rem] min-w-0 flex-col overflow-hidden rounded-[1.75rem] border border-sky-100 bg-sky-50 shadow-[0_18px_50px_-30px_rgba(14,116,144,.25)] lg:min-h-0" aria-label="Peta Kabupaten Teluk Bintuni">
                    <div class="shrink-0 border-b border-sky-100 bg-white/90 px-5 py-2.5">
                        <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-2">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[.15em] text-sky-700">Peta pendidikan</p>
                                <h2 class="mt-0.5 text-lg font-bold text-slate-900">Peta Sebaran Sekolah</h2>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="flex flex-wrap gap-1" aria-label="Filter jenjang">
                                    <template x-for="item in [{ key: 'all', label: 'Semua' }, { key: 'KB', label: 'KB' }, { key: 'TK', label: 'TK' }, { key: 'SD', label: 'SD' }, { key: 'SMP', label: 'SMP' }, { key: 'SMA', label: 'SMA' }, { key: 'SMK', label: 'SMK' }, { key: 'other', label: 'Lainnya' }]" :key="item.key">
                                        <button type="button" @click="filters.level = item.key; apply()" :class="filters.level === item.key ? 'bg-sky-700 text-white shadow-sm' : 'bg-sky-50 text-sky-800 hover:bg-sky-100'" class="rounded-lg px-2 py-1 text-[10px] font-bold transition" x-text="item.label"></button>
                                    </template>
                                </div>
                                <select x-model="filters.status" @change="apply()" class="rounded-lg border border-sky-100 bg-white px-2 py-1 text-[10px] font-semibold text-slate-700 outline-none focus:border-sky-500">
                                    <option value="all">Semua Status</option>
                                    <option value="Negeri">Negeri</option>
                                    <option value="Swasta">Swasta</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div x-data="tifaEducationMap()" x-init="init()" class="tifa-map relative min-h-0 flex-1">
                        <div x-ref="map" class="absolute inset-0" role="application" aria-label="Peta distrik Kabupaten Teluk Bintuni"></div>
                    </div>
                </div>
                <aside x-data="tifaDistrictSummary()" x-init="init()" class="flex min-h-0 flex-col rounded-[1.75rem] border border-white/80 bg-white/85 p-5 shadow-[0_18px_50px_-30px_rgba(14,116,144,.25)] lg:min-h-0">
                    <p class="text-[11px] font-bold uppercase tracking-[.15em] text-sky-700">Sebaran Sekolah</p>
                    <div class="flex items-baseline justify-between gap-2">
                        <h2 class="mt-1 text-lg font-bold text-slate-900">Ringkasan Distrik</h2>
                        <span class="text-[10px] font-semibold text-slate-500" x-text="districts.length + ' distrik'">{{ count($districtSummary['districts']) }} distrik</span>
                    </div>
                    <div x-ref="districtList" class="mt-4 min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
                        <template x-for="district in districts" :key="district.identifier">
                            <button type="button" @click="selectDistrict(district.identifier)" :data-district-identifier="district.identifier" :class="selectedIdentifier === district.identifier ? 'border-sky-500 bg-sky-50 ring-2 ring-sky-100' : 'border-slate-100 bg-slate-50 hover:border-sky-200'" class="block w-full rounded-xl border px-3 py-2.5 text-left transition">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="min-w-0 text-xs font-bold leading-4 text-slate-800" x-text="district.name"></p>
                                    <span class="shrink-0 text-lg font-black leading-4 text-sky-800" x-text="district.total_schools"></span>
                                </div>
                                <p x-show="district.has_school_data !== false" class="mt-1 text-[10px] text-slate-500"><span x-text="district.public_schools"></span> Negeri <span class="px-1 text-slate-300">•</span> <span x-text="district.private_schools"></span> Swasta</p>
                                <p x-show="district.has_school_data === false" class="mt-1 text-[10px] text-slate-500">Tidak ada sekolah pada dataset aktif</p>
                            </button>
                        </template>
                    </div>
                    <p x-cloak x-show="mapUnavailable" class="mt-3 text-[10px] leading-4 text-amber-700">Boundary distrik belum dapat dimuat.</p>
                    @if (($districtSummary['null_or_empty_districts'] ?? 0) > 0)
                        <p class="mt-3 text-[10px] leading-4 text-amber-700">{{ $districtSummary['null_or_empty_districts'] }} sekolah belum memiliki distrik.</p>
                    @endif
                </aside>
            </section>
        </div>
    </section>

    <!-- SECTION RUANG INFORMASI & PENGADUAN LAYANAN (50:50 LAYOUT) -->
    <section class="border-t border-sky-100 bg-gradient-to-b from-[#f3f8fb] via-white to-[#edf6fb] px-4 py-8 sm:px-6 sm:py-12 lg:px-8 xl:px-12" aria-label="Informasi Publik dan Pengaduan Layanan">
        <div class="mx-auto grid max-w-[1600px] items-start gap-8 lg:grid-cols-2 lg:gap-8 xl:gap-10">

            <!-- SUBSECTION: RUANG INFORMASI (50%) -->
            <div
                id="ruang-informasi"
                class="scroll-mt-20 flex flex-col self-start w-full"
                x-data="tifaPublicDocuments()"
                x-init="init()"
            >
                <script id="tifa-public-documents-data" type="application/json">@json($publicDocuments ?? [])</script>
                <script id="tifa-public-documents-total" type="application/json">{{ (int) ($publicDocumentsCount ?? count($publicDocuments ?? [])) }}</script>

                <div class="w-full rounded-[1.75rem] border border-sky-100 bg-white p-5 shadow-[0_20px_55px_-34px_rgba(14,116,144,.35)] sm:p-7 flex flex-col justify-between">
                    <div>
                        <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">RUANG INFORMASI</h2>
                        <p class="mt-1 text-xs sm:text-sm leading-relaxed text-slate-600">Dokumen resmi publik, rencana strategis, pedoman teknis, Surat Edaran, Juknis, standar pelayanan dan dokumen terkait lainnya di Dinas Pendidikan, Kebudayaan, Pemuda dan Olahraga Kabupaten Teluk Bintuni.</p>

                        <!-- 3-Column Responsive Document Grid (Max 6 documents per page) -->
                        <div class="relative mt-6 min-h-[14rem]">
                            <!-- Subtle Loading Overlay -->
                            <div
                                x-cloak
                                x-show="isLoading"
                                x-transition.opacity
                                class="absolute inset-0 z-10 flex items-center justify-center rounded-2xl bg-white/70 backdrop-blur-[1px]"
                            >
                                <div class="inline-flex items-center gap-2 rounded-full bg-sky-700 px-3.5 py-1.5 text-xs font-bold text-white shadow-md">
                                    <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                                    <span>Memuat dokumen...</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3 items-start">
                                <template x-for="doc in documents" :key="doc.id">
                                    <div
                                        role="button"
                                        tabindex="0"
                                        @click="openDocument(doc)"
                                        @keydown.enter="openDocument(doc)"
                                        class="group relative flex flex-col w-full overflow-hidden rounded-2xl border border-sky-100 bg-slate-50/60 p-3 shadow-2xs transition duration-200 hover:-translate-y-0.5 hover:border-sky-300 hover:bg-white hover:shadow-md focus:outline-none focus:ring-2 focus:ring-sky-500 cursor-pointer self-start"
                                    >
                                        <!-- Thumbnail / Cover Area -->
                                        <div class="relative aspect-[4/3] w-full overflow-hidden rounded-xl border border-sky-100 bg-white transition group-hover:border-sky-200">
                                            <img
                                                x-show="doc.thumbnail || doc.thumbnail_url"
                                                :src="doc.thumbnail || doc.thumbnail_url"
                                                :alt="doc.title"
                                                class="size-full object-cover transition duration-200 group-hover:scale-105"
                                            >
                                            <div
                                                x-show="!doc.thumbnail && !doc.thumbnail_url"
                                                class="flex size-full items-center justify-center p-4 bg-gradient-to-br from-sky-50 via-slate-50 to-cyan-50/70"
                                            >
                                                <div class="grid size-12 place-items-center rounded-xl bg-gradient-to-br from-sky-600 to-cyan-700 text-white shadow-md shadow-sky-700/20 transition duration-200 group-hover:scale-110">
                                                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                                        <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Document Title Only -->
                                        <h3 class="mt-2.5 text-xs sm:text-sm font-bold leading-snug text-slate-900 group-hover:text-sky-800 line-clamp-3 transition" x-text="doc.title"></h3>
                                    </div>
                                </template>
                            </div>

                            <!-- Empty state if no documents -->
                            <div x-show="documents.length === 0 && !isLoading" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-8 text-center text-slate-400">
                                <p class="text-xs font-semibold">Belum ada dokumen publik yang ditampilkan.</p>
                            </div>

                            <!-- Fetch Error Notice -->
                            <div x-cloak x-show="fetchError" class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-2.5 text-center text-xs font-semibold text-rose-700" x-text="fetchError"></div>
                        </div>
                    </div>

                    <!-- Previous / Next Pagination Navigation (Visible when > 6 documents) -->
                    <div x-cloak x-show="lastPage > 1" class="mt-6 flex items-center justify-between gap-3 border-t border-sky-100/80 pt-4">
                        <button
                            type="button"
                            @click="prevPage()"
                            :disabled="currentPage <= 1 || isLoading"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-xs transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-800 disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                            <span>Sebelumnya</span>
                        </button>

                        <div class="text-xs font-semibold text-slate-500">
                            Halaman <span class="font-bold text-slate-800" x-text="currentPage"></span> dari <span class="font-bold text-slate-800" x-text="lastPage"></span>
                        </div>

                        <button
                            type="button"
                            @click="nextPage()"
                            :disabled="currentPage >= lastPage || isLoading"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-xs transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-800 disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <span>Berikutnya</span>
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- SUBSECTION: PENGADUAN LAYANAN (50%) -->
            <div id="pengaduan" class="scroll-mt-20 flex flex-col self-start">
                <div class="w-full rounded-[1.75rem] border border-sky-100 bg-white p-5 shadow-[0_20px_55px_-34px_rgba(14,116,144,.35)] sm:p-7">
                    <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">PENGADUAN LAYANAN</h2>
                    <p class="mt-1 text-xs sm:text-sm leading-relaxed text-slate-600">Sampaikan pengaduan atau masukan terkait layanan pendidikan kepada Dinas Pendidikan Kabupaten Teluk Bintuni.</p>

                    @if (session('complaint_success'))
                        <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 shadow-sm" role="alert">
                            <div class="flex items-start gap-3">
                                <span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-600 text-white font-bold text-xs">✓</span>
                                <div>
                                    <p class="text-sm font-bold text-emerald-950">{{ session('complaint_success') }}</p>
                                    <p class="mt-0.5 text-xs text-emerald-800">Laporan Anda telah tercatat dan akan ditinjau oleh tim Dinas Pendidikan, Kebudayaan, Pemuda dan Olahraga Kabupaten Teluk Bintuni.</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <form action="{{ route('complaints.store') }}" method="POST" enctype="multipart/form-data" class="mt-5 space-y-4">
                        @csrf

                        <!-- Field: Nama -->
                        <div>
                            <label for="name" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Nama Lengkap <span class="text-rose-500">*</span></label>
                            <input
                                type="text"
                                name="name"
                                id="name"
                                value="{{ old('name') }}"
                                required
                                maxlength="255"
                                placeholder="Contoh: Yohanes Salossa"
                                class="mt-1.5 block w-full rounded-xl border @error('name') border-rose-400 bg-rose-50/50 @else border-slate-200 bg-slate-50 @enderror px-3.5 py-2.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100"
                            >
                            @error('name')
                                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Field: Nomor HP / WhatsApp -->
                        <div>
                            <label for="phone" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Nomor HP / WhatsApp <span class="text-rose-500">*</span></label>
                            <input
                                type="tel"
                                name="phone"
                                id="phone"
                                value="{{ old('phone') }}"
                                required
                                maxlength="25"
                                placeholder="Contoh: 081234567890"
                                class="mt-1.5 block w-full rounded-xl border @error('phone') border-rose-400 bg-rose-50/50 @else border-slate-200 bg-slate-50 @enderror px-3.5 py-2.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100"
                            >
                            @error('phone')
                                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Field: Jenis Pengaduan -->
                        <div>
                            <label for="complaint_type" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Jenis Pengaduan <span class="text-rose-500">*</span></label>
                            <div class="relative mt-1.5">
                                <select
                                    name="complaint_type"
                                    id="complaint_type"
                                    required
                                    class="block w-full appearance-none rounded-xl border @error('complaint_type') border-rose-400 bg-rose-50/50 @else border-slate-200 bg-slate-50 @enderror px-3.5 py-2.5 pr-10 text-sm text-slate-900 outline-none transition focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100"
                                >
                                    <option value="" disabled {{ old('complaint_type') ? '' : 'selected' }}>-- Pilih Jenis Pengaduan --</option>
                                    <option value="Pelayanan Pendidikan" {{ old('complaint_type') === 'Pelayanan Pendidikan' ? 'selected' : '' }}>Pelayanan Pendidikan</option>
                                    <option value="Data Pendidikan" {{ old('complaint_type') === 'Data Pendidikan' ? 'selected' : '' }}>Data Pendidikan</option>
                                    <option value="Sekolah" {{ old('complaint_type') === 'Sekolah' ? 'selected' : '' }}>Sekolah</option>
                                    <option value="Guru / Tenaga Kependidikan" {{ old('complaint_type') === 'Guru / Tenaga Kependidikan' ? 'selected' : '' }}>Guru / Tenaga Kependidikan</option>
                                    <option value="Sarana Prasarana" {{ old('complaint_type') === 'Sarana Prasarana' ? 'selected' : '' }}>Sarana Prasarana</option>
                                    <option value="Lainnya" {{ old('complaint_type') === 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500">
                                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                                </div>
                            </div>
                            @error('complaint_type')
                                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Field: Isi Pengaduan -->
                        <div>
                            <label for="complaint_text" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Isi Pengaduan <span class="text-rose-500">*</span></label>
                            <textarea
                                name="complaint_text"
                                id="complaint_text"
                                rows="4"
                                required
                                maxlength="5000"
                                placeholder="Jelaskan secara jelas kendala atau masukan yang ingin disampaikan..."
                                class="mt-1.5 block w-full rounded-xl border @error('complaint_text') border-rose-400 bg-rose-50/50 @else border-slate-200 bg-slate-50 @enderror px-3.5 py-2.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100"
                            >{{ old('complaint_text') }}</textarea>
                            @error('complaint_text')
                                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Field: Upload Berkas (Optional) -->
                        <div>
                            <label for="attachment" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Upload Berkas / Bukti Dukung <span class="text-xs font-normal text-slate-400">(Opsional)</span></label>
                            <input
                                type="file"
                                name="attachment"
                                id="attachment"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="mt-1.5 block w-full rounded-xl border @error('attachment') border-rose-400 bg-rose-50/50 @else border-slate-200 bg-slate-50 @enderror px-3 py-2 text-xs text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-100 file:px-3 file:py-1 file:text-xs file:font-bold file:text-sky-800 hover:file:bg-sky-200"
                            >
                            <p class="mt-1 text-[11px] text-slate-500">Menerima format PDF, JPG, JPEG, atau PNG (Maks. 5 MB).</p>
                            @error('attachment')
                                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-sky-700 via-sky-800 to-cyan-700 px-5 py-3 text-sm font-bold text-white shadow-md shadow-sky-800/25 transition duration-150 hover:from-sky-800 hover:to-cyan-800 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-sky-200 active:scale-[0.99]"
                            >
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/></svg>
                                <span>Kirim Pengaduan</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
