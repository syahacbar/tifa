<x-layouts.app title="TIFA">
    <section x-data="tifaAssistant()" x-init="init()" class="relative overflow-hidden">
        <div aria-hidden="true" class="absolute inset-x-0 top-0 -z-10 h-[34rem] bg-[radial-gradient(circle_at_20%_10%,rgba(186,230,253,.9),transparent_30%),radial-gradient(circle_at_80%_25%,rgba(207,250,254,.9),transparent_27%),linear-gradient(to_bottom,#f0f9ff,transparent)]"></div>

        <div class="mx-auto max-w-7xl px-5 py-10 sm:px-8 sm:py-16 lg:px-8 lg:py-20">
            <div class="grid items-center gap-10 lg:grid-cols-[1.05fr_.95fr] lg:gap-16">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-white/80 px-3 py-1.5 text-xs font-semibold tracking-wide text-sky-800 shadow-sm">
                        <span class="size-2 rounded-full bg-emerald-500"></span>
                        LAYANAN INFORMASI PENDIDIKAN
                    </div>
                    <h1 class="mt-6 text-5xl font-black tracking-tight text-slate-950 sm:text-6xl">TIFA</h1>
                    <p class="mt-4 max-w-xl text-xl font-medium leading-8 text-slate-700 sm:text-2xl">
                        Asisten Pintar Dinas Pendidikan Kabupaten Teluk Bintuni
                    </p>
                    <p class="mt-5 max-w-xl text-base leading-7 text-slate-600">
                        Tanyakan ringkasan data sekolah, peserta didik, tenaga pendidik, dan sarana pendidikan secara cepat dengan sumber data yang jelas.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-slate-600">
                        <span class="flex items-center gap-2"><span class="grid size-6 place-items-center rounded-full bg-sky-100 text-sky-700">✓</span> Berbasis data Dapodik</span>
                        <span class="flex items-center gap-2"><span class="grid size-6 place-items-center rounded-full bg-cyan-100 text-cyan-700">✓</span> Jawaban terverifikasi</span>
                    </div>
                </div>

                <aside class="relative rounded-[2rem] border border-white/80 bg-white/90 p-5 shadow-[0_24px_70px_-25px_rgba(14,116,144,.35)] backdrop-blur sm:p-7" aria-label="Panduan singkat TIFA">
                    <div class="absolute -right-3 -top-3 grid size-16 place-items-center rounded-3xl bg-amber-300 text-2xl shadow-lg shadow-amber-100" aria-hidden="true">✦</div>
                    <p class="text-sm font-bold uppercase tracking-[.16em] text-sky-700">Mulai dari data</p>
                    <h2 class="mt-2 text-2xl font-bold text-slate-900">Apa yang ingin Anda ketahui?</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Gunakan pertanyaan sederhana. TIFA akan menampilkan angka dan sumber dataset yang digunakan.</p>
                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl bg-sky-50 p-4"><span class="block text-lg font-bold text-sky-800">Sekolah</span><span class="text-xs text-slate-500">Jenjang & distrik</span></div>
                        <div class="rounded-2xl bg-cyan-50 p-4"><span class="block text-lg font-bold text-cyan-800">Siswa</span><span class="text-xs text-slate-500">Laki-laki & perempuan</span></div>
                    </div>
                </aside>
            </div>

            <div class="mx-auto mt-12 max-w-4xl rounded-[2rem] border border-sky-100 bg-white p-5 shadow-xl shadow-sky-100/60 sm:p-8">
                <form @submit.prevent="ask()" class="space-y-4" aria-label="Form pertanyaan TIFA">
                    <label for="question" class="block text-base font-bold text-slate-900">Ajukan pertanyaan Anda</label>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <input x-ref="question" x-model="question" id="question" type="text" maxlength="1000" autocomplete="off" placeholder="Contoh: Berapa jumlah SD di Kabupaten Teluk Bintuni?" class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100" :disabled="isLoading">
                        <button x-cloak x-show="voice?.recognitionSupported" type="button" @click="voice.startListening()" :aria-pressed="voice?.isListening" aria-label="Ajukan pertanyaan dengan suara" class="inline-flex min-h-14 items-center justify-center rounded-2xl border border-sky-200 bg-sky-50 px-4 text-sky-700 transition hover:bg-sky-100 focus:outline-none focus:ring-4 focus:ring-sky-100 disabled:cursor-not-allowed disabled:opacity-60" :disabled="isLoading || voice?.isListening">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v5M8 22h8"/></svg>
                            <span class="sr-only">Mulai input suara</span>
                        </button>
                        <button type="submit" class="inline-flex min-h-14 items-center justify-center gap-2 rounded-2xl bg-sky-700 px-6 py-3 font-bold text-white shadow-lg shadow-sky-200 transition hover:bg-sky-800 focus:outline-none focus:ring-4 focus:ring-sky-200 disabled:cursor-not-allowed disabled:bg-sky-400" :disabled="isLoading">
                            <svg x-show="!isLoading" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                            <svg x-cloak x-show="isLoading" class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4Z"/></svg>
                            <span x-text="isLoading ? 'TIFA sedang mencari...' : 'Tanya TIFA'"></span>
                        </button>
                    </div>
                </form>

                <p x-cloak x-show="voice && !voice.recognitionSupported" class="mt-3 text-sm text-slate-500">Input suara belum didukung browser ini. Anda tetap dapat mengetik pertanyaan.</p>
                <p x-cloak x-show="voice?.error" class="mt-3 text-sm font-medium text-rose-700" role="alert" x-text="voice?.error"></p>

                <div class="mt-5 border-t border-slate-100 pt-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Pertanyaan cepat</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <template x-for="item in quickQuestions" :key="item">
                            <button type="button" @click="ask(item)" :disabled="isLoading" class="rounded-full border border-sky-100 bg-sky-50 px-3 py-2 text-left text-sm font-medium text-sky-800 transition hover:border-sky-300 hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-60" x-text="item"></button>
                        </template>
                    </div>
                </div>
            </div>

            <section x-cloak x-show="voice?.isListening" x-transition class="mx-auto mt-8 max-w-4xl rounded-3xl border border-cyan-200 bg-cyan-50 p-5 sm:flex sm:items-center sm:justify-between" aria-live="assertive">
                <div class="flex items-center gap-3"><span class="relative flex size-11"><span class="absolute inline-flex size-full animate-ping rounded-full bg-cyan-300 opacity-60"></span><span class="relative grid size-11 place-items-center rounded-full bg-cyan-600 text-white"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v5"/></svg></span></span><div><p class="font-bold text-cyan-950">Mendengarkan...</p><p class="text-sm text-cyan-800">Ucapkan pertanyaan Anda dengan jelas.</p></div></div>
                <button type="button" @click="voice.cancelListening()" class="mt-4 rounded-xl border border-cyan-300 bg-white px-4 py-2 text-sm font-bold text-cyan-800 transition hover:bg-cyan-100 sm:mt-0">Batalkan</button>
            </section>

            <section x-cloak x-show="isLoading" x-transition class="mx-auto mt-8 max-w-4xl rounded-3xl border border-sky-100 bg-white p-6 text-center shadow-sm" aria-live="polite">
                <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-sky-100 text-sky-700"><svg class="size-6 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v18M3 12h18"/></svg></div>
                <p class="mt-3 font-bold text-slate-900">TIFA sedang menyiapkan jawaban</p>
                <p class="mt-1 text-sm text-slate-500">Memahami pertanyaan dan memeriksa dataset aktif.</p>
            </section>

            <section x-cloak x-show="error" x-transition class="mx-auto mt-8 max-w-4xl rounded-3xl border border-rose-200 bg-rose-50 p-5 sm:p-6" role="alert">
                <div class="flex gap-3"><span class="grid size-9 shrink-0 place-items-center rounded-full bg-rose-100 font-bold text-rose-700">!</span><div><h2 class="font-bold text-rose-950">TIFA belum dapat menjawab</h2><p class="mt-1 text-sm leading-6 text-rose-800" x-text="error"></p></div></div>
            </section>

            <section x-cloak x-show="response" x-transition.opacity class="mx-auto mt-8 max-w-4xl" aria-live="polite">
                <div class="overflow-hidden rounded-[2rem] border border-sky-100 bg-white shadow-xl shadow-sky-100/60">
                    <div class="bg-gradient-to-r from-sky-700 to-cyan-600 px-6 py-5 text-white sm:px-8">
                        <p class="text-xs font-bold uppercase tracking-[.16em] text-sky-100">Jawaban TIFA</p>
                        <p class="mt-2 text-lg font-semibold leading-7" x-text="response?.answer"></p>
                    </div>
                    <div class="grid gap-5 p-5 sm:p-8 md:grid-cols-[.9fr_1.1fr]">
                        <div class="rounded-3xl bg-sky-50 p-6" x-show="response?.visualization === 'kpi'">
                            <p class="text-xs font-bold uppercase tracking-[.15em] text-sky-700">Hasil utama</p>
                            <p class="mt-3 text-5xl font-black tracking-tight text-slate-950" x-text="formattedValue()"></p>
                            <p class="mt-2 text-sm font-medium text-slate-600" x-text="response?.intent?.action?.replaceAll('_', ' ')"></p>
                        </div>
                        <div class="rounded-3xl border border-slate-100 p-6">
                            <p class="text-xs font-bold uppercase tracking-[.15em] text-slate-500">Sumber data</p>
                            <dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-500">Dataset</dt><dd class="font-semibold text-slate-900" x-text="response?.source?.dataset"></dd></div><div><dt class="text-slate-500">Periode referensi</dt><dd class="font-semibold text-slate-900" x-text="response?.source?.reference_period"></dd></div><div><dt class="text-slate-500">Tanggal sumber</dt><dd class="font-semibold text-slate-900" x-text="response?.source?.source_date"></dd></div></dl>
                        </div>
                    </div>
                    <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                        <p class="text-sm text-slate-500">Angka ditampilkan dari dataset aktif TIFA.</p>
                        <div class="flex flex-wrap gap-2"><button x-cloak x-show="voice?.synthesisSupported" type="button" @click="voice.speak(response?.answer)" class="rounded-xl border border-cyan-200 px-4 py-2 text-sm font-bold text-cyan-700 transition hover:bg-cyan-50"><span x-text="voice?.isSpeaking ? 'Membacakan...' : 'Ulangi Suara'"></span></button><button x-cloak x-show="voice?.isSpeaking" type="button" @click="voice.stopSpeaking()" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-50">Hentikan Suara</button><button type="button" @click="askAgain()" class="rounded-xl border border-sky-200 px-4 py-2 text-sm font-bold text-sky-700 transition hover:bg-sky-50">Tanya Lagi</button></div>
                    </div>
                </div>
            </section>
        </div>
    </section>
</x-layouts.app>
