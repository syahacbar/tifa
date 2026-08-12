<?php

namespace App\Console\Commands;

use App\Services\TeacherImportReviewService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tifa:review-teacher-schools {--batch= : ID batch yang akan ditinjau}')]
#[Description('Menampilkan referensi sekolah guru yang unresolved atau ambiguous')]
class ReviewTeacherSchoolsCommand extends Command
{
    public function handle(TeacherImportReviewService $review): int
    {
        $batch = $this->option('batch') ? \App\Models\TeacherImportBatch::find($this->option('batch')) : $review->latestBatch();
        if (! $batch) { $this->warn('Tidak ada batch impor guru untuk ditinjau.'); return self::FAILURE; }
        $this->info("Review sekolah batch #{$batch->id} ({$batch->reference_period}).");
        foreach ($review->schoolIssues($batch) as $issue) {
            $this->line("NPSN {$issue['npsn_label']} | {$issue['resolution']} | {$issue['assignment_count']} assignment");
            if ($issue['schools'] === []) { $this->comment('  Tidak ada record sekolah yang cocok.'); continue; }
            $this->table(['ID', 'Sekolah', 'Jenjang', 'Distrik', 'Status', 'Dataset aktif', 'Periode'], array_map(fn ($school) => [
                $school['id'], $school['name'], $school['education_level'], $school['district'], $school['status'],
                $school['dataset_active'] ? 'ya' : 'tidak', $school['reference_period'],
            ], $issue['schools']));
        }

        return self::SUCCESS;
    }
}
