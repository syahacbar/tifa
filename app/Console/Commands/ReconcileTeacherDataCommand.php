<?php

namespace App\Console\Commands;

use App\Models\TeacherImportBatch;
use App\Services\TeacherSchoolReconciliationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tifa:reconcile-teacher-data {--recommend : Hanya tampilkan rekomendasi read-only} {--batch= : ID batch}')]
#[Description('Menyusun rekomendasi rekonsiliasi school reference guru tanpa perubahan data')]
class ReconcileTeacherDataCommand extends Command
{
    public function handle(TeacherSchoolReconciliationService $service): int
    {
        if (! $this->option('recommend')) { $this->error('Gunakan --recommend. Command ini hanya untuk laporan rekomendasi.'); return self::FAILURE; }
        $batch = $this->option('batch') ? TeacherImportBatch::find($this->option('batch')) : TeacherImportBatch::query()->latest('id')->first();
        if (! $batch) { $this->error('Batch impor guru tidak ditemukan.'); return self::FAILURE; }
        $this->info("Rekomendasi rekonsiliasi batch #{$batch->id}; tidak ada perubahan database.");
        $missing = $service->missingNpsnRecommendations($batch);
        $this->info('A. Assignment tanpa NPSN: '.$missing->count());
        $this->table(['ID', 'Sekolah sumber', 'Jenjang', 'Distrik', 'Klasifikasi', 'Kandidat', 'Alasan'], $missing->map(fn ($item) => [
            $item['assignment_id'], $item['source_school_name'] ?: '(kosong)', $item['education_level'], $item['district'], $item['classification'],
            $this->candidates($item), $item['reason'],
        ])->all());
        foreach (['60401946' => 'B. NPSN 60401946', '60725746' => 'C. Collision NPSN 60725746'] as $npsn => $title) {
            $recommendations = $service->npsnRecommendations($batch, $npsn);
            $this->info("{$title}: {$recommendations->count()} assignment");
            $this->table(['ID', 'Sekolah sumber', 'Jenjang', 'Distrik', 'Klasifikasi', 'Kandidat', 'Alasan'], $recommendations->map(fn ($item) => [
                $item['assignment_id'], $item['source_school_name'] ?: '(kosong)', $item['education_level'], $item['district'], $item['classification'],
                $this->candidates($item), $item['reason'],
            ])->all());
        }
        $this->comment('Fuzzy match, bila ada, hanya suggestion dan tidak pernah melakukan auto-link. Tidak ada data pribadi ditampilkan.');
        return self::SUCCESS;
    }

    /** @param array<string, mixed> $item */
    private function candidates(array $item): string
    {
        $rows = $item['candidates'] !== [] ? $item['candidates'] : $item['fuzzy_suggestions'];
        return $rows === [] ? '-' : implode('; ', array_map(fn ($school) => "#{$school['id']} {$school['name']} ({$school['npsn']}; {$school['education_level']}; {$school['district']}; {$school['status']})".(isset($school['similarity']) ? " {$school['similarity']}%" : ''), $rows));
    }
}
