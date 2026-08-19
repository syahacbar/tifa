<x-layouts.admin title="Detail Pengaduan #{{ $complaint->id }}">
    <div class="space-y-6 max-w-4xl">
        <!-- Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('admin.complaints.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-sky-700 hover:underline">
                    &larr; Kembali ke Daftar Pengaduan
                </a>
                <div class="mt-2 flex items-center gap-3">
                    <h1 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Pengaduan #{{ $complaint->id }}</h1>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold border {{ $complaint->status_badge_class }}">
                        <span class="size-1.5 rounded-full {{ $complaint->status === 'baru' ? 'bg-sky-500' : ($complaint->status === 'diproses' ? 'bg-amber-500' : 'bg-emerald-500') }}"></span>
                        {{ $complaint->status_label }}
                    </span>
                </div>
                <p class="mt-1 text-xs sm:text-sm text-slate-500">
                    Diterima pada {{ $complaint->created_at ? $complaint->created_at->translatedFormat('l, d F Y - H:i') : '-' }} WIT
                </p>
            </div>
        </div>

        <!-- Flash Success Notification -->
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900" role="alert">
                <div class="flex items-center gap-2.5">
                    <span class="grid size-5 place-items-center rounded-full bg-emerald-600 text-white text-xs font-bold">✓</span>
                    <p class="text-xs font-bold text-emerald-800">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        <!-- Status Update Box -->
        <div class="rounded-[1.75rem] border border-sky-100 bg-white p-5 shadow-xs sm:p-6">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Tindak Lanjut & Status Pengaduan</h2>
            <form action="{{ route('admin.complaints.status', $complaint) }}" method="POST" class="mt-3 flex flex-wrap items-center gap-3">
                @csrf
                @method('PATCH')

                <div class="min-w-48">
                    <select
                        name="status"
                        id="status"
                        class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-bold text-slate-900 outline-none transition focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100"
                    >
                        <option value="baru" {{ $complaint->status === 'baru' ? 'selected' : '' }}>Baru (Belum Ditindaklanjuti)</option>
                        <option value="diproses" {{ $complaint->status === 'diproses' ? 'selected' : '' }}>Diproses (Sedang Ditangani)</option>
                        <option value="selesai" {{ $complaint->status === 'selesai' ? 'selected' : '' }}>Selesai (Sudah Ditangani)</option>
                    </select>
                </div>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-sky-700 px-4 py-2.5 text-xs font-bold text-white shadow-xs shadow-sky-700/20 transition hover:bg-sky-800 focus:outline-none focus:ring-4 focus:ring-sky-200"
                >
                    Simpan Status
                </button>
            </form>
            @error('status')
                <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Detail Data Grid -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <!-- Profil Pelapor -->
            <div class="rounded-[1.75rem] border border-sky-100 bg-white p-5 shadow-xs sm:p-6 space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 border-b border-slate-100 pb-2.5">
                    Informasi Pelapor
                </h2>

                <div>
                    <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Nama Lengkap</span>
                    <span class="block text-sm font-bold text-slate-900 mt-0.5">{{ $complaint->name }}</span>
                </div>

                <div>
                    <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Nomor HP / WhatsApp</span>
                    <span class="block text-sm font-bold text-slate-900 mt-0.5">{{ $complaint->phone }}</span>
                </div>

                <div>
                    <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">Jenis Pengaduan</span>
                    <span class="inline-flex items-center rounded-lg bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-800 mt-1">
                        {{ $complaint->complaint_type }}
                    </span>
                </div>
            </div>

            <!-- Berkas Lampiran -->
            <div class="rounded-[1.75rem] border border-sky-100 bg-white p-5 shadow-xs sm:p-6 space-y-4 flex flex-col justify-between">
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 border-b border-slate-100 pb-2.5">
                        Berkas Lampiran
                    </h2>

                    @if ($complaint->hasAttachment())
                        <div class="mt-4 flex items-start gap-3 rounded-2xl border border-sky-100 bg-sky-50/50 p-4">
                            <span class="grid size-10 place-items-center rounded-xl bg-sky-700 text-white shrink-0">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            </span>
                            <div>
                                <p class="text-xs font-bold text-slate-900">Berkas Bukti / Lampiran Tersedia</p>
                                <p class="text-[11px] text-slate-500 mt-0.5">Tersimpan di private storage yang aman.</p>
                            </div>
                        </div>
                    @else
                        <div class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center text-slate-400">
                            <p class="text-xs font-semibold">Tidak ada berkas lampiran yang diunggah.</p>
                        </div>
                    @endif
                </div>

                @if ($complaint->hasAttachment())
                    <div class="pt-2">
                        <a
                            href="{{ route('admin.complaints.attachment', $complaint) }}"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-sky-700 px-4 py-2.5 text-xs font-bold text-white shadow-xs shadow-sky-700/20 transition hover:bg-sky-800 focus:outline-none focus:ring-4 focus:ring-sky-200"
                        >
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>
                            <span>Unduh Lampiran</span>
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Isi Pengaduan Lengkap -->
        <div class="rounded-[1.75rem] border border-sky-100 bg-white p-5 shadow-xs sm:p-7 space-y-3">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 border-b border-slate-100 pb-2.5">
                Isi Pesan / Pengaduan
            </h2>
            <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-5 text-sm leading-relaxed text-slate-800 whitespace-pre-wrap font-sans select-text">
                {{ $complaint->complaint_text }}
            </div>
        </div>
    </div>
</x-layouts.admin>
