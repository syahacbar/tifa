<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Panel Administrasi TIFAA — Dinas Pendidikan Kabupaten Teluk Bintuni.">

        <title>{{ isset($title) ? $title.' - Administrasi TIFAA' : 'Administrasi TIFAA' }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-[#f3f8fb] text-slate-900 antialiased" x-data="{ sidebarOpen: false }">
        <div class="min-h-full flex flex-col">
            <!-- Top Navigation Header -->
            <header class="sticky top-0 z-40 border-b border-sky-100 bg-white/95 shadow-xs backdrop-blur-md">
                <div class="mx-auto flex max-w-[1600px] items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <!-- Mobile Hamburger Button -->
                        <button
                            type="button"
                            @click="sidebarOpen = !sidebarOpen"
                            class="grid size-9 place-items-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 lg:hidden"
                            aria-label="Buka menu navigasi"
                        >
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>

                        <!-- Brand Logo -->
                        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5">
                            <span class="grid size-9 place-items-center rounded-xl bg-gradient-to-br from-sky-600 to-cyan-700 font-black text-white shadow-md shadow-sky-600/20">T</span>
                            <div>
                                <span class="block font-black leading-tight tracking-tight text-slate-950">TIFAA</span>
                                <span class="block text-[11px] font-semibold text-sky-700">Panel Administrasi</span>
                            </div>
                        </a>
                    </div>

                    <!-- Right Side: User Profile & Quick Logout -->
                    <div class="flex items-center gap-3">
                        <div class="hidden items-center gap-2 sm:flex">
                            <div class="grid size-8 place-items-center rounded-full bg-sky-100 font-bold text-xs text-sky-800">
                                {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                            </div>
                            <div class="text-left text-xs">
                                <span class="block font-bold text-slate-900 leading-tight">{{ auth()->user()->name ?? 'Administrator' }}</span>
                                <span class="block text-[11px] text-slate-500">{{ auth()->user()->email ?? '' }}</span>
                            </div>
                        </div>

                        <form action="{{ route('admin.logout') }}" method="POST" class="inline">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-xs transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-400"
                            >
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                                </svg>
                                <span>Keluar</span>
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <!-- Main Body with Sidebar -->
            <div class="mx-auto flex w-full max-w-[1600px] flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <!-- Mobile Backdrop -->
                <div
                    x-cloak
                    x-show="sidebarOpen"
                    x-transition.opacity
                    @click="sidebarOpen = false"
                    class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-xs lg:hidden"
                    aria-hidden="true"
                ></div>

                <!-- Sidebar (Responsive Drawer on Mobile, Static on Desktop) -->
                <aside
                    x-cloak
                    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                    class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-sky-100 bg-white p-5 shadow-xl transition-transform duration-200 lg:static lg:z-auto lg:w-60 lg:rounded-[1.75rem] lg:border lg:p-4 lg:shadow-xs lg:transition-none shrink-0"
                >
                    <!-- Mobile Sidebar Header -->
                    <div class="mb-4 flex items-center justify-between lg:hidden">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-400">Navigasi Admin</span>
                        <button type="button" @click="sidebarOpen = false" class="grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-slate-100">
                            <span class="text-xl">&times;</span>
                        </button>
                    </div>

                    <!-- Navigation Links -->
                    <nav class="flex-1 space-y-1.5" aria-label="Navigasi admin">
                        <a
                            href="{{ route('admin.dashboard') }}"
                            class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-xs font-bold transition {{ request()->routeIs('admin.dashboard') ? 'bg-sky-700 text-white shadow-xs shadow-sky-700/20' : 'text-slate-700 hover:bg-sky-50 hover:text-sky-800' }}"
                        >
                            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                            </svg>
                            <span>Dashboard</span>
                        </a>

                        <!-- Ruang Informasi -->
                        <a
                            href="{{ route('admin.documents.index') }}"
                            class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-xs font-bold transition {{ request()->routeIs('admin.documents.*') ? 'bg-sky-700 text-white shadow-xs shadow-sky-700/20' : 'text-slate-700 hover:bg-sky-50 hover:text-sky-800' }}"
                        >
                            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                            </svg>
                            <span>Ruang Informasi</span>
                        </a>

                        <!-- Pengaduan Layanan -->
                        <a
                            href="{{ route('admin.complaints.index') }}"
                            class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-xs font-bold transition {{ request()->routeIs('admin.complaints.*') ? 'bg-sky-700 text-white shadow-xs shadow-sky-700/20' : 'text-slate-700 hover:bg-sky-50 hover:text-sky-800' }}"
                        >
                            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                            <span>Pengaduan Layanan</span>
                        </a>
                    </nav>

                    <!-- Sidebar Footer -->
                    <div class="border-t border-slate-100 pt-4 text-center text-[11px] text-slate-400">
                        <span>TIFAA Admin v1.0</span>
                    </div>
                </aside>

                <!-- Content Slot -->
                <main class="min-w-0 flex-1 lg:pl-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
