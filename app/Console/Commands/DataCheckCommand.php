<?php

namespace App\Console\Commands;

use App\Services\TifaDataChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tifa:data-check')]
#[Description('Periksa ringkasan dan kualitas data pada dataset TIFAA aktif')]
class DataCheckCommand extends Command
{
    public function handle(TifaDataChecker $checker): int
    {
        $result = $checker->check();
        if ($result === null) {
            $this->error('Tidak ada dataset aktif.');

            return self::FAILURE;
        }

        $this->info('Pemeriksaan data TIFAA (read-only)');
        $this->components->twoColumnDetail('Dataset aktif', $result['dataset']->name);
        $this->components->twoColumnDetail('Periode', $result['dataset']->reference_period ?? '-');
        $this->components->twoColumnDetail('Total sekolah', (string) $result['total_schools']);

        $this->sectionCountTable('Sekolah per jenjang', 'Jenjang', $result['by_education_level']);
        $this->sectionCountTable('Sekolah menurut status', 'Status', $result['by_status']);

        $statistics = $result['statistics'];
        $this->newLine();
        $this->info('Statistik utama');
        $this->table(['Indikator', 'Total'], [
            ['Siswa', $statistics['students_total']],
            ['Siswa laki-laki', $statistics['students_male']],
            ['Siswa perempuan', $statistics['students_female']],
            ['Guru', $statistics['teachers']],
            ['Tendik', $statistics['education_staff']],
            ['Rombel', $statistics['study_groups']],
            ['Ruang kelas', $statistics['classrooms']],
            ['Laboratorium', $statistics['laboratories']],
            ['Perpustakaan', $statistics['libraries']],
        ]);

        $this->sectionCountTable('Sekolah per distrik', 'Distrik', $result['by_district']);

        $this->newLine();
        $this->info('NPSN collision');
        if ($result['npsn_collisions'] === []) {
            $this->line('  Tidak ditemukan.');
        } else {
            foreach ($result['npsn_collisions'] as $collision) {
                $this->warn("  {$collision['npsn']} ({$collision['total']} sekolah)");
                foreach ($collision['schools'] as $school) {
                    $this->line("    - {$school}");
                }
            }
        }

        $this->newLine();
        $this->info('Nilai null/kosong penting');
        $this->table(
            ['Kolom', 'Jumlah'],
            array_map(fn (string $field, int $total) => [$field, $total], array_keys($result['empty_values']), array_values($result['empty_values'])),
        );

        $this->issueTable('Error statistik negatif', $result['negative_statistics']);
        $this->issueTable('Warning statistik tidak wajar', $result['unreasonable_statistics']);

        $this->newLine();
        $this->info('Ringkasan kualitas');
        $this->components->twoColumnDetail('Warning', (string) $result['warnings']);
        $this->components->twoColumnDetail('Error', (string) $result['errors']);

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function sectionCountTable(string $title, string $label, array $values): void
    {
        $this->newLine();
        $this->info($title);
        $this->table(
            [$label, 'Jumlah'],
            array_map(fn (string $key, int $total) => [$key, $total], array_keys($values), array_values($values)),
        );
    }

    private function issueTable(string $title, array $issues): void
    {
        $this->newLine();
        $this->info($title);
        if ($issues === []) {
            $this->line('  Tidak ditemukan.');

            return;
        }

        $this->table(
            ['NPSN', 'Sekolah', 'Masalah'],
            array_map(fn (array $issue) => [$issue['npsn'], $issue['school'], $issue['issue']], $issues),
        );
    }
}
