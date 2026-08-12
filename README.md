# TIFAA

TIFAA (Tata Kelola dan Informasi Pendidikan Terintegrasi) adalah fondasi layanan informasi pendidikan Dinas Pendidikan Kabupaten Teluk Bintuni. Tahap saat ini mencakup aplikasi Laravel, data sekolah Dapodik, inspeksi workbook, importer transaksional, dan pemeriksaan kualitas data.

## Stack

- PHP 8.3 dan Laravel 13
- Blade, Tailwind CSS 4, Alpine.js, dan Vite
- MySQL
- Laragon pada Windows

## Instalasi lokal

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm.cmd install
php artisan migrate
npm.cmd run build
```

Atur koneksi MySQL pada `.env`, lalu jalankan aplikasi melalui Laragon atau:

```powershell
composer run dev
```

## Data Dapodik

Workbook sumber bersifat lokal dan tidak disimpan dalam Git. Letakkan file pada:

```text
storage/app/imports/rekap-dapodik-juni-2026.xlsx
```

Command yang tersedia:

```powershell
php artisan tifa:inspect-dapodik
php artisan tifa:import-dapodik storage/app/imports/rekap-dapodik-juni-2026.xlsx --dry-run
php artisan tifa:import-dapodik storage/app/imports/rekap-dapodik-juni-2026.xlsx
php artisan tifa:data-check
```

## Verifikasi

```powershell
php artisan test
npm.cmd run build
```

Test yang membutuhkan workbook privat akan dilewati otomatis bila file tersebut tidak tersedia.
