<x-layouts.app title="Beranda">
    <section class="mx-auto max-w-7xl px-6 py-16 sm:py-24 lg:px-8">
        <div class="max-w-3xl">
            <p class="mb-4 text-sm font-semibold uppercase tracking-widest text-sky-700">Asisten Pintar Pendidikan</p>
            <h1 class="text-4xl font-bold tracking-tight text-slate-950 sm:text-6xl">Selamat datang di TIFA</h1>
            <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600">
                Fondasi layanan digital Dinas Pendidikan Kabupaten Teluk Bintuni untuk akses informasi yang lebih mudah, cepat, dan terarah.
            </p>
        </div>

        <div class="mt-12 grid gap-6 sm:grid-cols-3">
            @foreach ([
                ['Fondasi siap', 'Laravel, Blade, Tailwind CSS, dan Alpine.js telah disiapkan.'],
                ['Bertahap', 'Fitur layanan akan dibangun secara terukur sesuai kebutuhan dinas.'],
                ['Lokal', 'Lingkungan pengembangan berjalan mandiri melalui Laragon.'],
            ] as [$heading, $description])
                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="font-semibold text-slate-950">{{ $heading }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p>
                </article>
            @endforeach
        </div>
    </section>
</x-layouts.app>
