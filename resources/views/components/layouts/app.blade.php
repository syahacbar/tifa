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
            <header class="relative border-b-2 border-cyan-400 bg-gradient-to-r from-[#082f49] via-[#0b3d5c] to-[#0e4a68] shadow-[0_8px_20px_-16px_rgba(8,47,73,.8)]" x-data="{ open: false }">
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
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-300/30 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-100"><span class="size-1.5 rounded-full bg-emerald-300 shadow-[0_0_0_3px_rgba(110,231,183,.12)]"></span>Sistem Aktif</span>
                    </div>
                </nav>

                <div id="mobile-menu" class="border-t border-white/10 bg-[#082f49] px-6 py-3 md:hidden" x-cloak x-show="open" x-transition>
                    <a href="{{ route('home') }}" class="block py-2 text-sm font-medium text-white">Beranda</a>
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
