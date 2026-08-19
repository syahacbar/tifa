<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Masuk ke Panel Administrasi TIFAA — Dinas Pendidikan Kabupaten Teluk Bintuni.">

        <title>Masuk - Administrasi TIFAA</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-full items-center justify-center bg-[#f3f8fb] px-4 py-12 text-slate-900 antialiased sm:px-6 lg:px-8">
        <div class="w-full max-w-md">
            <!-- Branding Header -->
            <div class="text-center">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5">
                    <span class="grid size-12 place-items-center rounded-2xl bg-gradient-to-br from-sky-600 to-cyan-700 font-black text-xl text-white shadow-lg shadow-sky-600/30">T</span>
                </a>
                <h1 class="mt-4 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Administrasi TIFAA</h1>
                <p class="mt-1 text-xs sm:text-sm text-slate-600">Tata Kelola dan Informasi Pendidikan Terintegrasi</p>
                <p class="mt-0.5 text-xs text-sky-800 font-semibold">Kabupaten Teluk Bintuni</p>
            </div>

            <!-- Login Card -->
            <div class="mt-8 rounded-[1.75rem] border border-sky-100 bg-white p-6 shadow-[0_20px_55px_-34px_rgba(14,116,144,.35)] sm:p-8">
                <!-- Generic / Validation Error Alert -->
                @if ($errors->any())
                    <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-900" role="alert">
                        <div class="flex items-start gap-2.5">
                            <span class="grid size-5 shrink-0 place-items-center rounded-full bg-rose-600 text-white text-xs font-bold">!</span>
                            <div class="text-xs font-semibold leading-relaxed text-rose-800">
                                @if ($errors->has('email') && count($errors->all()) === 1)
                                    {{ $errors->first('email') }}
                                @else
                                    <ul class="list-disc pl-4 space-y-0.5">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <form action="{{ route('admin.login.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <!-- Email Field -->
                    <div>
                        <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Alamat Email</label>
                        <input
                            type="email"
                            name="email"
                            id="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="admin@telukbintunikab.go.id"
                            class="mt-1.5 block w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100"
                        >
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Kata Sandi</label>
                        <input
                            type="password"
                            name="password"
                            id="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="mt-1.5 block w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-100"
                        >
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between pt-1">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                name="remember"
                                id="remember"
                                {{ old('remember') ? 'checked' : '' }}
                                class="size-4 rounded-md border-slate-300 text-sky-700 focus:ring-sky-500"
                            >
                            <span class="text-xs text-slate-600 font-medium">Ingat saya</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-sky-700 via-sky-800 to-cyan-700 px-5 py-3 text-sm font-bold text-white shadow-md shadow-sky-800/25 transition duration-150 hover:from-sky-800 hover:to-cyan-800 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-sky-200 active:scale-[0.99]"
                        >
                            <span>Masuk ke Panel Admin</span>
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Footer Note -->
            <div class="mt-6 text-center text-xs text-slate-400">
                <a href="{{ route('home') }}" class="font-semibold text-sky-700 hover:underline">&larr; Kembali ke Beranda TIFAA</a>
            </div>
        </div>
    </body>
</html>
