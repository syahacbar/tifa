<x-layouts.admin title="Edit Dokumen">
    <div class="space-y-6 max-w-3xl">
        <!-- Page Header -->
        <div>
            <a href="{{ route('admin.documents.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-sky-700 hover:underline">
                &larr; Kembali ke Daftar Dokumen
            </a>
            <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Edit Dokumen Publik</h1>
            <p class="mt-1 text-xs sm:text-sm text-slate-600">Perbarui informasi, ganti file PDF, atau ubah cover dokumen publik.</p>
        </div>

        <!-- Form Card -->
        <div class="rounded-[1.75rem] border border-sky-100 bg-white p-6 shadow-xs sm:p-8">
            <form action="{{ route('admin.documents.update', $document) }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @method('PUT')

                <!-- Title -->
                <div>
                    <label for="title" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Judul Dokumen <span class="text-rose-600">*</span>
                    </label>
                    <input
                        type="text"
                        name="title"
                        id="title"
                        value="{{ old('title', $document->title) }}"
                        required
                        class="mt-1.5 block w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100"
                    >
                    @error('title')
                        <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Published At -->
                <div>
                    <label for="published_at" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Tanggal Publikasi <span class="text-rose-600">*</span>
                    </label>
                    <input
                        type="date"
                        name="published_at"
                        id="published_at"
                        value="{{ old('published_at', $document->published_at ? $document->published_at->format('Y-m-d') : '') }}"
                        required
                        class="mt-1.5 block w-full max-w-xs rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100"
                    >
                    <p class="mt-1 text-[11px] text-slate-500">Digunakan sebagai dasar pengurutan dokumen terbaru di homepage.</p>
                    @error('published_at')
                        <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- PDF File Section -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        File Dokumen PDF
                    </label>

                    <!-- Existing PDF Indicator -->
                    <div class="mt-2 flex items-center justify-between rounded-xl border border-sky-100 bg-sky-50/60 p-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-sky-700 text-white">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-xs font-bold text-slate-900">{{ $document->download_name }}</p>
                                <p class="text-[11px] text-slate-500">PDF saat ini sudah tersimpan</p>
                            </div>
                        </div>
                        <a href="{{ $document->file_url }}" target="_blank" rel="noopener noreferrer" class="shrink-0 rounded-lg bg-white px-3 py-1.5 text-xs font-bold text-sky-700 border border-sky-200 hover:bg-sky-100 transition">
                            Lihat PDF
                        </a>
                    </div>

                    <!-- Replace PDF Input -->
                    <div class="mt-3">
                        <label for="pdf_file" class="block text-xs font-semibold text-slate-600">
                            Ganti Berkas PDF <span class="text-slate-400 font-normal">(Kosongkan jika tidak ingin mengubah file)</span>
                        </label>
                        <input
                            type="file"
                            name="pdf_file"
                            id="pdf_file"
                            accept="application/pdf"
                            class="mt-1.5 block w-full text-xs text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-xs file:font-bold file:text-slate-700 hover:file:bg-slate-200"
                        >
                        <p class="mt-1 text-[11px] text-slate-500">Maksimal 20 MB. File PDF lama akan otomatis digantikan.</p>
                    </div>
                    @error('pdf_file')
                        <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Thumbnail Section -->
                <div class="border-t border-slate-100 pt-4">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Thumbnail / Cover Dokumen
                    </label>

                    @if ($document->thumbnail_url)
                        <div class="mt-2 flex items-start gap-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <img src="{{ $document->thumbnail_url }}" alt="Thumbnail" class="size-16 rounded-lg object-cover border border-slate-200 shrink-0">
                            <div class="space-y-1.5">
                                <p class="text-xs font-bold text-slate-800">Thumbnail saat ini aktif</p>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="remove_thumbnail"
                                        value="1"
                                        class="size-4 rounded border-rose-300 text-rose-600 focus:ring-rose-500"
                                    >
                                    <span class="text-xs font-semibold text-rose-700">Hapus thumbnail (gunakan icon standar)</span>
                                </label>
                            </div>
                        </div>
                    @endif

                    <div class="mt-3">
                        <label for="thumbnail_file" class="block text-xs font-semibold text-slate-600">
                            {{ $document->thumbnail_url ? 'Ganti Thumbnail' : 'Unggah Thumbnail' }} <span class="text-slate-400 font-normal">(Opsional)</span>
                        </label>
                        <input
                            type="file"
                            name="thumbnail_file"
                            id="thumbnail_file"
                            accept="image/jpeg,image/png,image/webp"
                            class="mt-1.5 block w-full text-xs text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-xs file:font-bold file:text-slate-700 hover:file:bg-slate-200"
                        >
                        <p class="mt-1 text-[11px] text-slate-500">Format gambar JPG, PNG, atau WebP. Maksimal 2 MB.</p>
                    </div>
                    @error('thumbnail_file')
                        <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Is Active -->
                <div class="border-t border-slate-100 pt-4">
                    <label class="inline-flex items-center gap-2.5 cursor-pointer">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            {{ old('is_active', $document->is_active) ? 'checked' : '' }}
                            class="size-4 rounded-md border-slate-300 text-sky-700 focus:ring-sky-500"
                        >
                        <div>
                            <span class="text-xs font-bold text-slate-900 block">Tampilkan di Homepage (Aktif)</span>
                            <span class="text-[11px] text-slate-500 block">Jika dinonaktifkan, dokumen disembunyikan dari Ruang Informasi publik.</span>
                        </div>
                    </label>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center gap-3 border-t border-slate-100 pt-5">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-sky-700 px-5 py-2.5 text-xs font-bold text-white shadow-xs shadow-sky-700/20 transition hover:bg-sky-800 focus:outline-none focus:ring-4 focus:ring-sky-200"
                    >
                        Perbarui Dokumen
                    </button>
                    <a
                        href="{{ route('admin.documents.index') }}"
                        class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
                    >
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
