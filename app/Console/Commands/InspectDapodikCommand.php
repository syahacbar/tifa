<?php

namespace App\Console\Commands;

use App\Services\DapodikWorkbookInspector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('tifa:inspect-dapodik {--file= : Path workbook relatif terhadap project atau absolut}')]
#[Description('Inspeksi struktur workbook Dapodik tanpa mengubah file atau database')]
class InspectDapodikCommand extends Command
{
    public function handle(DapodikWorkbookInspector $inspector): int
    {
        $path = $this->resolvePath($this->option('file'));

        try {
            $result = $inspector->inspect($path);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Inspeksi workbook Dapodik (read-only)');
        $this->line("File: {$path}");
        $this->newLine();

        $this->components->twoColumnDetail('Jumlah sheet', (string) count($result['sheets']));
        $this->table(
            ['Sheet', 'Baris terisi', 'Baris data', 'Header', 'Klasifikasi'],
            array_map(fn (array $sheet) => [
                $sheet['name'],
                $sheet['non_empty_rows'],
                $sheet['data_rows'],
                $sheet['header_row'] ?? '-',
                $sheet['is_school_data'] ? 'Data sekolah' : ($sheet['is_aggregate'] ? 'Rekap/agregat' : 'Lainnya'),
            ], $result['sheets']),
        );

        foreach ($result['sheets'] as $sheet) {
            $this->newLine();
            $this->info("Sheet: {$sheet['name']}");
            $this->line('Kolom: '.($sheet['headers'] === [] ? '(tidak terdeteksi)' : implode(' | ', $sheet['headers'])));

            if ($sheet['samples'] !== []) {
                $this->line('Contoh 3 baris data:');
                foreach ($sheet['samples'] as $index => $sample) {
                    $this->line(sprintf('  %d. %s', $index + 1, json_encode($sample, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)));
                }
            }
        }

        $this->sectionList('Sheet data sekolah', $result['school_sheets']);
        $this->sectionList('Sheet rekap/agregat', $result['aggregate_sheets']);

        $this->newLine();
        $this->info('Kemungkinan sheet duplikat');
        if ($result['possible_duplicates'] === []) {
            $this->line('  Tidak ada sheet dengan struktur dan isi yang identik.');
        } else {
            foreach ($result['possible_duplicates'] as $group) {
                $this->line('  - '.implode(' = ', $group));
            }
        }

        $this->newLine();
        $this->info('Perbedaan struktur kolom antar jenjang');
        $differences = $result['structure_differences'];
        if ($differences['note'] !== null) {
            $this->line('  '.$differences['note']);
        } else {
            $this->line('  Kolom umum: '.implode(', ', $differences['common']));
            foreach ($differences['unique_by_level'] as $level => $headers) {
                $this->line("  {$level} khusus: ".($headers === [] ? '(tidak ada)' : implode(', ', $headers)));
            }
        }

        $this->newLine();
        $this->info('Nilai unik yang dikenali');
        foreach ([
            'education_levels' => 'Jenjang',
            'statuses' => 'Status',
            'districts' => 'Distrik',
        ] as $key => $label) {
            $values = $result['unique_values'][$key] ?? [];
            $this->line("  {$label}: ".($values === [] ? '(tidak dikenali)' : implode(', ', $values)));
        }

        return self::SUCCESS;
    }

    private function resolvePath(?string $option): string
    {
        if ($option === null || trim($option) === '') {
            return storage_path('app/imports/rekap-dapodik-juni-2026.xlsx');
        }

        $option = trim($option);

        return str_starts_with($option, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $option)
            ? $option
            : base_path($option);
    }

    private function sectionList(string $title, array $items): void
    {
        $this->newLine();
        $this->info($title);
        $this->line($items === [] ? '  (tidak ada)' : '  - '.implode(PHP_EOL.'  - ', $items));
    }
}
