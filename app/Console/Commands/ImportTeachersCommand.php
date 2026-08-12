<?php

namespace App\Console\Commands;

use App\Services\TeacherImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tifa:import-teachers {--dry-run : Validasi tanpa menulis database}')]
#[Description('Import batch nominatif guru dengan normalisasi dan validasi aman')]
class ImportTeachersCommand extends Command
{
    public function handle(TeacherImportService $importer): int
    {
        $report = $importer->import(storage_path('app/imports/nominatif-guru-maret-2026.xlsx'), (bool) $this->option('dry-run'));
        $this->info($report['dry_run'] ? 'Dry-run import guru berhasil.' : 'Import batch guru berhasil.');
        foreach (['records' => 'Record', 'valid' => 'Valid', 'unresolved' => 'NPSN unresolved', 'ambiguous' => 'NPSN ambigu', 'duplicate_candidate_rows' => 'Kandidat duplikat', 'duplicate_candidate_groups' => 'Kelompok kandidat'] as $key => $label) {
            $this->components->twoColumnDetail($label, (string) $report[$key]);
        }
        $this->line('NPSN unresolved: '.($report['unresolved_npsns'] === [] ? '-' : implode(', ', $report['unresolved_npsns'])));
        $this->line('NPSN ambigu: '.($report['ambiguous_npsns'] === [] ? '-' : implode(', ', $report['ambiguous_npsns'])));
        $this->line('Normalisasi PPPK Tahap II: '.$report['normalization']['employment_status_pppk_tahap_ii']);
        if ($report['dry_run']) {
            $this->comment('Tidak ada perubahan database (--dry-run).');
        }

        return self::SUCCESS;
    }
}
