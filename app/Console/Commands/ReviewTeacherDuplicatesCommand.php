<?php

namespace App\Console\Commands;

use App\Services\TeacherImportReviewService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tifa:review-teacher-duplicates {--batch= : ID batch yang akan ditinjau}')]
#[Description('Menampilkan kandidat duplikat guru tanpa identifier pribadi')]
class ReviewTeacherDuplicatesCommand extends Command
{
    public function handle(TeacherImportReviewService $review): int
    {
        $batch = $this->option('batch') ? \App\Models\TeacherImportBatch::find($this->option('batch')) : $review->latestBatch();
        if (! $batch) { $this->warn('Tidak ada batch impor guru untuk ditinjau.'); return self::FAILURE; }
        $groups = $review->duplicateGroups($batch);
        $this->info("Review {$groups->count()} kelompok kandidat duplikat pada batch #{$batch->id}.");
        foreach ($groups as $group) {
            $this->line("Fingerprint {$group['short_fingerprint']} | {$group['candidate_count']} assignment | saran: {$group['suggested_resolution']} | review: {$group['review_status']}".($group['resolution_type'] ? " ({$group['resolution_type']})" : ''));
            $this->table(['Sekolah', 'NPSN', 'Jenjang', 'Distrik', 'Jenis PTK', 'Jabatan/mapel', 'Kepegawaian', 'Pendidikan', 'Sheet/baris'], array_map(fn ($record) => [
                $record['school'], $record['npsn'], $record['education_level'], $record['district'], $record['ptk_type'],
                $record['ptk_position'], $record['employment_status'], $record['education'], $record['source_sheet'].'/'.$record['source_row'],
            ], $group['assignments']));
        }
        $this->comment('Tidak ada nama, NIK, NIP, NUPTK, nomor HP, atau data kelahiran yang ditampilkan. Saran bukan keputusan merge.');

        return self::SUCCESS;
    }
}
