<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="TIFAA — Tata Kelola dan Informasi Pendidikan Terintegrasi Dinas Pendidikan Kabupaten Teluk Bintuni.">

        <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-sky-50 lg:h-screen lg:overflow-hidden">
        <div class="flex min-h-screen flex-col lg:h-screen">
            <header class="border-b border-sky-100 bg-white/90 backdrop-blur" x-data="{ open: false }">
                <nav class="mx-auto flex max-w-[1600px] items-center justify-between px-4 py-3 sm:px-6 lg:px-8" aria-label="Navigasi utama">
                    <a href="{{ route('home') }}" class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-2xl bg-gradient-to-br from-sky-600 to-cyan-500 font-bold text-white shadow-lg shadow-sky-200">T</span>
                        <span>
                            <span class="block font-semibold leading-tight text-slate-950">TIFAA</span>
                            <span class="block text-xs text-slate-500">Tata Kelola dan Informasi Pendidikan Terintegrasi</span>
                        </span>
                    </a>

                    <button type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 md:hidden" @click="open = ! open" :aria-expanded="open" aria-controls="mobile-menu">
                        <span class="sr-only">Buka navigasi</span>
                        <span aria-hidden="true" class="text-xl">&#9776;</span>
                    </button>

                    <div class="hidden items-center gap-6 text-sm font-medium text-slate-600 md:flex">
                        <a href="{{ route('home') }}" class="text-sky-700">Beranda</a>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Layanan data pendidikan</span>
                    </div>
                </nav>

                <div id="mobile-menu" class="border-t border-slate-100 px-6 py-3 md:hidden" x-cloak x-show="open" x-transition>
                    <a href="{{ route('home') }}" class="block py-2 text-sm font-medium text-sky-700">Beranda</a>
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
