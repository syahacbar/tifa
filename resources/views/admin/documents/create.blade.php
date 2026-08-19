<x-layouts.admin title="Tambah Dokumen">
    <div class="space-y-6 max-w-3xl">
        <!-- Page Header -->
        <div>
            <a href="{{ route('admin.documents.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-sky-700 hover:underline">
                &larr; Kembali ke Daftar Dokumen
            </a>
            <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Tambah Dokumen Publik</h1>
            <p class="mt-1 text-xs sm:text-sm text-slate-600">Unggah berkas PDF dan informasi dokumen resmi untuk ditampilkan pada Ruang Informasi.</p>
        </div>

        <!-- Form Card -->
        <div class="rounded-[1.75rem] border border-sky-100 bg-white p-6 shadow-xs sm:p-8">
            <form action="{{ route('admin.documents.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <!-- Title -->
                <div>
                    <label for="title" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Judul Dokumen <span class="text-rose-600">*</span>
                    </label>
                    <input
                        type="text"
                        name="title"
                        id="title"
                        value="{{ old('title') }}"
                        required
                        placeholder="Contoh: Rencana Strategis (Renstra) Dinas Pendidikan 2021–2026"
                        class="mt-1.5 block w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100"
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
                        value="{{ old('published_at', date('Y-m-d')) }}"
                        required
                        class="mt-1.5 block w-full max-w-xs rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100"
                    >
                    <p class="mt-1 text-[11px] text-slate-500">Digunakan sebagai dasar pengurutan dokumen terbaru di homepage.</p>
                    @error('published_at')
                        <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- PDF File Upload -->
                <div>
                    <label for="pdf_file" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        File Dokumen PDF <span class="text-rose-600">*</span>
                    </label>
                    <input
                        type="file"
                        name="pdf_file"
                        id="pdf_file"
                        accept="application/pdf"
                        required
                        class="mt-1.5 block w-full text-xs text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:bg-sky-50 file:px-4 file:py-2.5 file:text-xs file:font-bold file:text-sky-700 hover:file:bg-sky-100"
                    >
                    <p class="mt-1 text-[11px] text-slate-500">Format file wajib PDF. Ukuran maksimal 20 MB.</p>
                    @error('pdf_file')
                        <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Thumbnail Upload -->
                <div>
                    <label for="thumbnail_file" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Thumbnail / Cover Dokumen <span class="text-slate-400 font-normal">(Opsional)</span>
                    </label>
                    <input
                        type="file"
                        name="thumbnail_file"
                        id="thumbnail_file"
                        accept="image/jpeg,image/png,image/webp"
                        class="mt-1.5 block w-full text-xs text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:text-xs file:font-bold file:text-slate-700 hover:file:bg-slate-200"
                    >
                    <p class="mt-1 text-[11px] text-slate-500">Format gambar JPG, PNG, atau WebP. Ukuran maksimal 2 MB.</p>
                    @error('thumbnail_file')
                        <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Is Active -->
                <div class="pt-2">
                    <label class="inline-flex items-center gap-2.5 cursor-pointer">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            {{ old('is_active', true) ? 'checked' : '' }}
                            class="size-4 rounded-md border-slate-300 text-sky-700 focus:ring-sky-500"
                        >
                        <div>
                            <span class="text-xs font-bold text-slate-900 block">Tampilkan di Homepage (Aktif)</span>
                            <span class="text-[11px] text-slate-500 block">Jika tidak dicentang, dokumen disimpan namun disembunyikan dari publik.</span>
                        </div>
                    </label>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center gap-3 border-t border-slate-100 pt-5">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-sky-700 px-5 py-2.5 text-xs font-bold text-white shadow-xs shadow-sky-700/20 transition hover:bg-sky-800 focus:outline-none focus:ring-4 focus:ring-sky-200"
                    >
                        Simpan Dokumen
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
