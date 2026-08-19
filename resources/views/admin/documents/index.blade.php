<x-layouts.admin title="Kelola Dokumen Ruang Informasi">
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full bg-sky-100 px-3 py-1 text-[11px] font-bold tracking-[.12em] text-sky-800">
                    <span class="size-1.5 rounded-full bg-sky-600"></span>
                    RUANG INFORMASI
                </div>
                <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Dokumen Publik</h1>
                <p class="mt-1 text-xs sm:text-sm text-slate-600">Kelola dokumen PDF resmi yang ditampilkan di Ruang Informasi homepage TIFAA.</p>
            </div>

            <div>
                <a
                    href="{{ route('admin.documents.create') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-sky-700 px-4 py-2.5 text-xs font-bold text-white shadow-xs shadow-sky-700/20 transition hover:bg-sky-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
                >
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    <span>Tambah Dokumen</span>
                </a>
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

        <!-- Documents Table Card -->
        <div class="overflow-hidden rounded-[1.75rem] border border-sky-100 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-3.5 w-16 text-center">Cover</th>
                            <th scope="col" class="px-5 py-3.5">Judul Dokumen</th>
                            <th scope="col" class="px-5 py-3.5">Tanggal Publikasi</th>
                            <th scope="col" class="px-5 py-3.5 text-center">Status</th>
                            <th scope="col" class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($documents as $doc)
                            <tr class="transition hover:bg-slate-50/70">
                                <!-- Thumbnail -->
                                <td class="px-5 py-3.5 text-center">
                                    <div class="mx-auto grid size-11 place-items-center overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                                        @if ($doc->thumbnail_url)
                                            <img src="{{ $doc->thumbnail_url }}" alt="Thumbnail {{ $doc->title }}" class="size-full object-cover">
                                        @else
                                            <svg class="size-5 text-sky-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                                            </svg>
                                        @endif
                                    </div>
                                </td>

                                <!-- Title & PDF Link -->
                                <td class="px-5 py-3.5 font-medium text-slate-900">
                                    <div class="max-w-md">
                                        <p class="font-bold text-slate-900 leading-snug">{{ $doc->title }}</p>
                                        <a href="{{ $doc->file_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-sky-700 hover:underline">
                                            <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                            <span>Lihat PDF</span>
                                        </a>
                                    </div>
                                </td>

                                <!-- Publication Date -->
                                <td class="whitespace-nowrap px-5 py-3.5 text-slate-600 font-semibold">
                                    {{ $doc->published_at ? $doc->published_at->translatedFormat('d M Y') : '-' }}
                                </td>

                                <!-- Status Badge -->
                                <td class="whitespace-nowrap px-5 py-3.5 text-center">
                                    @if ($doc->is_active)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 border border-emerald-200">
                                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600 border border-slate-200">
                                            <span class="size-1.5 rounded-full bg-slate-400"></span>
                                            Nonaktif
                                        </span>
                                    @endif
                                </td>

                                <!-- Actions -->
                                <td class="whitespace-nowrap px-5 py-3.5 text-right font-medium">
                                    <div class="inline-flex items-center gap-2">
                                        <a
                                            href="{{ route('admin.documents.edit', $doc) }}"
                                            class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-100 hover:text-slate-900"
                                        >
                                            Edit
                                        </a>

                                        <form action="{{ route('admin.documents.destroy', $doc) }}" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-bold text-rose-700 transition hover:bg-rose-50 hover:text-rose-800"
                                            >
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-slate-400">
                                    <p class="font-bold text-slate-600">Belum ada dokumen publik.</p>
                                    <p class="mt-1 text-xs text-slate-400">Silakan klik tombol "Tambah Dokumen" untuk mengunggah dokumen baru.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            @if ($documents->hasPages())
                <div class="border-t border-slate-100 bg-slate-50/50 px-5 py-4">
                    {{ $documents->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
