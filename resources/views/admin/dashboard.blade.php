<x-layouts.admin title="Dashboard">
    <div class="space-y-6">
        <!-- Page Header -->
        <div>
            <div class="inline-flex items-center gap-2 rounded-full bg-sky-100 px-3 py-1 text-[11px] font-bold tracking-[.12em] text-sky-800">
                <span class="size-1.5 rounded-full bg-sky-600"></span>
                DASHBOARD
            </div>
            <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Administrasi TIFAA</h1>
            <p class="mt-1 text-sm text-slate-600">Kelola layanan dan informasi publik TIFAA.</p>
        </div>

        <!-- Welcome Banner -->
        <div class="relative overflow-hidden rounded-[1.75rem] border border-sky-100 bg-gradient-to-br from-sky-700 via-sky-800 to-cyan-800 p-6 text-white shadow-md shadow-sky-900/10 sm:p-8">
            <div aria-hidden="true" class="absolute -right-10 -top-10 size-48 rounded-full bg-cyan-400/20 blur-2xl"></div>
            <div class="relative z-10 max-w-2xl">
                <p class="text-xs font-bold uppercase tracking-[.15em] text-sky-200">Selamat Datang</p>
                <h2 class="mt-1 text-xl font-black tracking-tight sm:text-2xl">{{ $user->name ?? 'Administrator' }}</h2>
                <p class="mt-2 text-xs sm:text-sm leading-relaxed text-sky-100/90">
                    Anda masuk sebagai pengelola data dan layanan publik TIFAA (Tata Kelola dan Informasi Pendidikan Terintegrasi Kabupaten Teluk Bintuni).
                </p>
            </div>
        </div>

        <!-- Modules Grid -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <!-- Module 1: Ruang Informasi -->
            <div class="relative flex flex-col justify-between overflow-hidden rounded-[1.75rem] border border-sky-100 bg-white p-6 shadow-xs">
                <div>
                    <div class="flex items-center justify-between gap-2">
                        <div class="grid size-11 place-items-center rounded-2xl bg-sky-100 text-sky-700">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                            </svg>
                        </div>
                        <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-[11px] font-bold text-emerald-700">
                            Aktif
                        </span>
                    </div>
                    <h3 class="mt-4 text-base font-bold text-slate-900">Ruang Informasi</h3>
                    <p class="mt-1.5 text-xs leading-relaxed text-slate-600">
                        Kelola dokumen publik yang ditampilkan pada homepage.
                    </p>
                </div>
                <div class="mt-6 border-t border-slate-100 pt-3 flex items-center justify-between">
                    <span class="text-[11px] font-semibold text-slate-400">Manajemen Dokumen & Upload PDF</span>
                    <a href="{{ route('admin.documents.index') }}" class="inline-flex items-center gap-1 text-xs font-bold text-sky-700 hover:underline">
                        <span>Kelola Dokumen</span>
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>

            <!-- Module 2: Pengaduan Layanan -->
            <div class="relative flex flex-col justify-between overflow-hidden rounded-[1.75rem] border border-sky-100 bg-white p-6 shadow-xs">
                <div>
                    <div class="flex items-center justify-between gap-2">
                        <div class="grid size-11 place-items-center rounded-2xl bg-amber-100 text-amber-700">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                        </div>
                        @if (($newComplaintsCount ?? 0) > 0)
                            <span class="rounded-full bg-amber-50 border border-amber-200 px-2.5 py-1 text-[11px] font-bold text-amber-800">
                                {{ $newComplaintsCount }} Pengaduan Baru
                            </span>
                        @else
                            <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-[11px] font-bold text-emerald-700">
                                Aktif
                            </span>
                        @endif
                    </div>
                    <h3 class="mt-4 text-base font-bold text-slate-900">Pengaduan Layanan</h3>
                    <p class="mt-1.5 text-xs leading-relaxed text-slate-600">
                        Kelola dan tindak lanjuti pengaduan layanan yang dikirim melalui homepage.
                    </p>
                </div>
                <div class="mt-6 border-t border-slate-100 pt-3 flex items-center justify-between">
                    <span class="text-[11px] font-semibold text-slate-400">Monitoring & Unduh Berkas</span>
                    <a href="{{ route('admin.complaints.index') }}" class="inline-flex items-center gap-1 text-xs font-bold text-sky-700 hover:underline">
                        <span>Kelola Pengaduan</span>
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
