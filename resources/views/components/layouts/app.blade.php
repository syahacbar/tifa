<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="TIFAA — Tata Kelola dan Informasi Pendidikan Terintegrasi Dinas Pendidikan Kabupaten Teluk Bintuni.">

        <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#f3f8fb] lg:h-screen lg:overflow-hidden">
        <div class="flex min-h-screen flex-col lg:h-screen">
            <header class="relative z-[2000] isolate border-b-2 border-cyan-400 bg-gradient-to-r from-[#082f49] via-[#0b3d5c] to-[#0e4a68] shadow-[0_8px_20px_-16px_rgba(8,47,73,.8)]" x-data="{ open: false, renstraOpen: false, openRenstra() { this.renstraOpen = true; document.documentElement.classList.add('overflow-hidden'); document.body.classList.add('overflow-hidden'); this.$nextTick(() => this.$refs.renstraCloseButton?.focus()); }, closeRenstra() { this.renstraOpen = false; document.documentElement.classList.remove('overflow-hidden'); document.body.classList.remove('overflow-hidden'); this.$nextTick(() => this.$refs.renstraButton?.focus()); } }" @keydown.escape.window="renstraOpen && closeRenstra()">
                <div aria-hidden="true" class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-amber-300/80 to-transparent"></div>
                <nav class="mx-auto flex max-w-[1600px] items-center justify-between px-4 py-2.5 sm:px-6 lg:px-8" aria-label="Navigasi utama">
                    <a href="{{ route('home') }}" class="flex items-center gap-3">
                        <span class="grid size-9 place-items-center rounded-xl border border-cyan-200/30 bg-gradient-to-br from-sky-400 to-cyan-500 font-bold text-white shadow-md shadow-slate-950/20">T</span>
                        <span>
                            <span class="block font-extrabold leading-tight tracking-wide text-white">TIFAA</span>
                            <span class="block text-xs text-sky-100/80">Tata Kelola dan Informasi Pendidikan Terintegrasi</span>
                        </span>
                    </a>

                    <button type="button" class="rounded-lg p-2 text-sky-50 hover:bg-white/10 md:hidden" @click="open = ! open" :aria-expanded="open" aria-controls="mobile-menu">
                        <span class="sr-only">Buka navigasi</span>
                        <span aria-hidden="true" class="text-xl">&#9776;</span>
                    </button>

                    <div class="hidden items-center gap-6 text-sm font-medium text-sky-100 md:flex">
                        <a href="{{ route('home') }}" class="text-white">Beranda</a>
                        <button x-ref="renstraButton" type="button" @click="openRenstra()" aria-label="Lihat Renstra Dinas Pendidikan Kabupaten Teluk Bintuni" class="inline-flex items-center gap-1.5 rounded-lg border border-sky-100/35 bg-white/5 px-2.5 py-1.5 text-xs font-semibold text-sky-50 transition hover:border-sky-100/60 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-cyan-300 focus:ring-offset-2 focus:ring-offset-sky-950"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5M8 13h8M8 17h6"/></svg><span class="hidden lg:inline">Lihat Renstra</span><span class="lg:hidden">Renstra</span></button>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-300/30 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-100"><span class="size-1.5 rounded-full bg-emerald-300 shadow-[0_0_0_3px_rgba(110,231,183,.12)]"></span>Sistem Aktif</span>
                    </div>
                </nav>

                <div id="mobile-menu" class="border-t border-white/10 bg-[#082f49] px-6 py-3 md:hidden" x-cloak x-show="open" x-transition>
                    <a href="{{ route('home') }}" class="block py-2 text-sm font-medium text-white">Beranda</a>
                    <button type="button" @click="open = false; openRenstra()" aria-label="Lihat Renstra Dinas Pendidikan Kabupaten Teluk Bintuni" class="inline-flex items-center gap-2 py-2 text-sm font-medium text-sky-50"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5M8 13h8M8 17h6"/></svg>Lihat Renstra</button>
                </div>

                <div x-cloak x-show="renstraOpen" class="fixed inset-0 z-[2100] flex items-center justify-center p-3 sm:p-5" role="dialog" aria-modal="true" aria-labelledby="renstra-modal-title">
                    <div x-show="renstraOpen" x-transition.opacity class="absolute inset-0 bg-slate-950/65 backdrop-blur-[1px]" aria-hidden="true" @click="closeRenstra()"></div>
                    <section x-show="renstraOpen" x-transition.scale.origin.center class="relative z-10 flex h-[92dvh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:h-[88dvh] sm:w-[90vw]" @click.stop>
                        <header class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:px-5">
                            <h2 id="renstra-modal-title" class="text-sm font-bold text-slate-900 sm:text-base">Rencana Strategis Dinas Pendidikan Kabupaten Teluk Bintuni</h2>
                            <button x-ref="renstraCloseButton" type="button" @click="closeRenstra()" aria-label="Tutup preview Renstra" class="grid size-9 shrink-0 place-items-center rounded-lg text-slate-600 transition hover:bg-slate-200 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-sky-500"><span aria-hidden="true" class="text-2xl leading-none">&times;</span></button>
                        </header>
                        <div class="min-h-0 flex-1 bg-white p-2 sm:p-3">
                            <div class="size-full overflow-hidden rounded-lg border border-slate-200 bg-white">
                                <iframe x-show="renstraOpen" src="/documents/renstra-dinas-pendidikan-teluk-bintuni.pdf" title="Preview Rencana Strategis Dinas Pendidikan Kabupaten Teluk Bintuni" class="size-full bg-white" loading="lazy"></iframe>
                            </div>
                        </div>
                        <footer class="flex shrink-0 items-center justify-end gap-2 border-t border-slate-200 bg-white px-4 py-3 sm:px-5">
                            <a href="/documents/renstra-dinas-pendidikan-teluk-bintuni.pdf" download="renstra-dinas-pendidikan-teluk-bintuni.pdf" class="inline-flex items-center gap-2 rounded-lg bg-sky-700 px-3 py-2 text-xs font-bold text-white transition hover:bg-sky-800 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>Download PDF</a>
                            <button type="button" @click="closeRenstra()" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">Tutup</button>
                        </footer>
                    </section>
                </div>
            </header>

            <main class="flex-1 lg:min-h-0">
                {{ $slot }}
            </main>

            <footer class="border-t border-sky-100 bg-white lg:hidden">
                <div class="mx-auto max-w-7xl px-6 py-5 text-sm text-slate-500 lg:px-8">
                    &copy; {{ date('Y') }} Dinas Pendidikan Kabupaten Teluk Bintuni
                </div>
            </footer>
        </div>
    </body>
</html>
