<x-layouts.admin title="Daftar Pengaduan Layanan">
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold tracking-[.12em] text-amber-800 border border-amber-200/60">
                    <span class="size-1.5 rounded-full bg-amber-500"></span>
                    PENGADUAN LAYANAN
                </div>
                <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Pengaduan Masuk</h1>
                <p class="mt-1 text-xs sm:text-sm text-slate-600">Daftar aspirasi, masukan, dan pengaduan layanan pendidikan dari masyarakat.</p>
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

        <!-- Filter Tabs -->
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200/80 pb-3">
            <a
                href="{{ route('admin.complaints.index') }}"
                class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-bold transition {{ $currentFilter === 'all' ? 'bg-sky-700 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100' }}"
            >
                <span>Semua</span>
                <span class="rounded-full px-2 py-0.5 text-[10px] {{ $currentFilter === 'all' ? 'bg-sky-800 text-white' : 'bg-slate-100 text-slate-600' }}">
                    {{ $totalCount }}
                </span>
            </a>

            <a
                href="{{ route('admin.complaints.index', ['status' => 'baru']) }}"
                class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-bold transition {{ $currentFilter === 'baru' ? 'bg-sky-700 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100' }}"
            >
                <span>Baru</span>
                <span class="rounded-full px-2 py-0.5 text-[10px] {{ $currentFilter === 'baru' ? 'bg-sky-800 text-white' : 'bg-sky-100 text-sky-800' }}">
                    {{ $baruCount }}
                </span>
            </a>

            <a
                href="{{ route('admin.complaints.index', ['status' => 'diproses']) }}"
                class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-bold transition {{ $currentFilter === 'diproses' ? 'bg-sky-700 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100' }}"
            >
                <span>Diproses</span>
                <span class="rounded-full px-2 py-0.5 text-[10px] {{ $currentFilter === 'diproses' ? 'bg-sky-800 text-white' : 'bg-amber-100 text-amber-800' }}">
                    {{ $diprosesCount }}
                </span>
            </a>

            <a
                href="{{ route('admin.complaints.index', ['status' => 'selesai']) }}"
                class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-bold transition {{ $currentFilter === 'selesai' ? 'bg-sky-700 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100' }}"
            >
                <span>Selesai</span>
                <span class="rounded-full px-2 py-0.5 text-[10px] {{ $currentFilter === 'selesai' ? 'bg-sky-800 text-white' : 'bg-emerald-100 text-emerald-800' }}">
                    {{ $selesaiCount }}
                </span>
            </a>
        </div>

        <!-- Table Card -->
        <div class="overflow-hidden rounded-[1.75rem] border border-sky-100 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-3.5 whitespace-nowrap">Tanggal Masuk</th>
                            <th scope="col" class="px-5 py-3.5">Nama Pelapor</th>
                            <th scope="col" class="px-5 py-3.5 whitespace-nowrap">Nomor HP / WA</th>
                            <th scope="col" class="px-5 py-3.5">Jenis Pengaduan</th>
                            <th scope="col" class="px-5 py-3.5 text-center">Status</th>
                            <th scope="col" class="px-5 py-3.5 text-center">Lampiran</th>
                            <th scope="col" class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($complaints as $c)
                            <tr class="transition hover:bg-slate-50/70">
                                <!-- Date -->
                                <td class="whitespace-nowrap px-5 py-3.5 font-semibold text-slate-500">
                                    {{ $c->created_at ? $c->created_at->translatedFormat('d M Y, H:i') : '-' }}
                                </td>

                                <!-- Name -->
                                <td class="px-5 py-3.5 font-bold text-slate-900">
                                    {{ $c->name }}
                                </td>

                                <!-- Phone -->
                                <td class="whitespace-nowrap px-5 py-3.5 font-medium text-slate-600">
                                    {{ $c->phone }}
                                </td>

                                <!-- Type -->
                                <td class="px-5 py-3.5 font-medium text-slate-800">
                                    <span class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">
                                        {{ $c->complaint_type }}
                                    </span>
                                </td>

                                <!-- Status Badge -->
                                <td class="whitespace-nowrap px-5 py-3.5 text-center">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold border {{ $c->status_badge_class }}">
                                        <span class="size-1.5 rounded-full {{ $c->status === 'baru' ? 'bg-sky-500' : ($c->status === 'diproses' ? 'bg-amber-500' : 'bg-emerald-500') }}"></span>
                                        {{ $c->status_label }}
                                    </span>
                                </td>

                                <!-- Attachment Indicator -->
                                <td class="whitespace-nowrap px-5 py-3.5 text-center">
                                    @if ($c->hasAttachment())
                                        <span class="inline-flex items-center gap-1 rounded-md bg-sky-50 px-2 py-0.5 text-[11px] font-bold text-sky-700 border border-sky-200">
                                            <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                            Ada
                                        </span>
                                    @else
                                        <span class="text-[11px] text-slate-400 font-medium">-</span>
                                    @endif
                                </td>

                                <!-- Action -->
                                <td class="whitespace-nowrap px-5 py-3.5 text-right font-medium">
                                    <a
                                        href="{{ route('admin.complaints.show', $c) }}"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-800"
                                    >
                                        <span>Lihat Detail</span>
                                        <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-slate-400">
                                    <p class="font-bold text-slate-600">Tidak ada pengaduan pada status ini.</p>
                                    <p class="mt-1 text-xs text-slate-400">Pengaduan baru dari masyarakat akan otomatis tampil di sini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            @if ($complaints->hasPages())
                <div class="border-t border-slate-100 bg-slate-50/50 px-5 py-4">
                    {{ $complaints->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
