<?php

namespace App\Console\Commands;

use App\Services\TeacherWorkbookInspector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('tifa:inspect-teachers')]
#[Description('Inspeksi read-only workbook nominatif guru tanpa menampilkan data pribadi lengkap')]
class InspectTeachersCommand extends Command
{
    public function handle(TeacherWorkbookInspector $inspector): int
    {
        $path = storage_path('app/imports/nominatif-guru-maret-2026.xlsx');

        try {
            $result = $inspector->inspect($path);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Inspeksi workbook nominatif guru (read-only, data pribadi dimasking)');
        $this->line("File: {$path}");
        $this->components->twoColumnDetail('Jumlah sheet', (string) count($result['sheets']));
        $this->table(['Sheet', 'Baris terisi', 'Record', 'Header'], array_map(fn (array $sheet) => [
            $sheet['name'], $sheet['non_empty_rows'], $sheet['data_rows'], $sheet['header_row'] ?? '-',
        ], $result['sheets']));

        foreach ($result['sheets'] as $sheet) {
            $this->newLine();
            $this->info("Sheet: {$sheet['name']}");
            $this->line('Kolom: '.($sheet['headers'] === [] ? '(tidak terdeteksi)' : implode(' | ', $sheet['headers'])));
            foreach ($sheet['samples'] as $index => $sample) {
                $this->line(sprintf('  Contoh %d: %s', $index + 1, json_encode($sample, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)));
            }
        }

        $this->newLine();
        $this->info('Nilai unik yang dikenali');
        foreach ([
            'employment_statuses' => 'Status kepegawaian',
            'ptk_types' => 'Jenis PTK',
            'ptk_positions' => 'Jabatan PTK',
            'educations' => 'Pendidikan',
            'districts' => 'Kecamatan/distrik',
        ] as $key => $label) {
            $values = $result['unique_values'][$key];
            $this->line("  {$label}: ".($values === [] ? '(tidak dikenali)' : implode(', ', $values)));
        }

        $this->components->twoColumnDetail('NPSN unik', (string) $result['unique_npsn_count']);
        $this->newLine();
        $this->info('NPSN tidak cocok dengan tabel schools');
        $this->line($result['unmatched_npsns'] === [] ? '  Tidak ditemukan.' : '  '.implode(', ', $result['unmatched_npsns']));

        $this->components->twoColumnDetail('Kemungkinan guru duplikat', (string) count($result['possible_duplicates']));
        foreach ($result['possible_duplicates'] as $duplicate) {
            $this->line("  {$duplicate['identifier']} ({$duplicate['total']} record)");
        }
        $this->components->twoColumnDetail('NIP kosong/placeholder', (string) $result['empty_identifiers']['nip']);
        $this->components->twoColumnDetail('NUPTK kosong/placeholder', (string) $result['empty_identifiers']['nuptk']);

        $this->newLine();
        $this->info('Nilai null/placeholder (null atau 1900-01-01)');
        $this->table(['Kolom', 'Jumlah'], array_map(fn (string $field, int $total) => [$field, $total], array_keys($result['placeholder_values']), array_values($result['placeholder_values'])));

        $this->newLine();
        $this->info('Perbedaan struktur antar sheet');
        $differences = $result['structure_differences'];
        if ($differences['note'] !== null) {
            $this->line('  '.$differences['note']);
        } else {
            $this->line('  Kolom umum: '.implode(', ', $differences['common']));
            foreach ($differences['unique_by_sheet'] as $sheet => $headers) {
                $this->line("  {$sheet} khusus: ".($headers === [] ? '(tidak ada)' : implode(', ', $headers)));
            }
        }

        return self::SUCCESS;
    }
}
