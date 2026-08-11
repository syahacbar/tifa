<?php

namespace App\Console\Commands;

use App\Services\DapodikImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('tifa:import-dapodik {file : Path workbook XLSX} {--dry-run : Validasi tanpa menulis ke database}')]
#[Description('Import data sekolah dari workbook Dapodik ke dataset TIFA')]
class ImportDapodikCommand extends Command
{
    public function handle(DapodikImportService $importer): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));
        $dryRun = (bool) $this->option('dry-run');

        try {
            $summary = $importer->import($path, $dryRun);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry-run import Dapodik berhasil.' : 'Import Dapodik berhasil.');
        $this->components->twoColumnDetail('Dataset', $summary['dataset']);
        $this->components->twoColumnDetail('Periode', $summary['reference_period']);
        $this->newLine();
        $this->table(
            ['Jenjang', 'Jumlah sekolah'],
            array_map(
                fn (string $level, int $total) => [$level, $total],
                array_keys($summary['by_education_level']),
                array_values($summary['by_education_level']),
            ),
        );
        $this->components->twoColumnDetail('Total sekolah', (string) $summary['total']);
        $this->components->twoColumnDetail('Duplikat source_key dilewati', (string) $summary['source_duplicates_skipped']);
        if ($summary['npsn_collisions'] !== []) {
            $this->warn('Warning collision NPSN:');
            foreach ($summary['npsn_collisions'] as $collision) {
                $this->line("  - {$collision}");
            }
        }

        if (! $dryRun) {
            $this->components->twoColumnDetail('Baru', (string) $summary['created']);
            $this->components->twoColumnDetail('Diperbarui', (string) $summary['updated']);
        } else {
            $this->comment('Tidak ada perubahan database (--dry-run).');
        }

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        $path = trim($path);

        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)
            ? $path
            : base_path($path);
    }
}
