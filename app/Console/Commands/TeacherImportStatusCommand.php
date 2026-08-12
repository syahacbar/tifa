<?php

namespace App\Console\Commands;

use App\Services\TeacherImportReviewService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tifa:teacher-import-status {--batch= : ID batch yang akan diperiksa}')]
#[Description('Memeriksa kelayakan batch guru untuk persetujuan authoritative')]
class TeacherImportStatusCommand extends Command
{
    public function handle(TeacherImportReviewService $review): int
    {
        $batch = $this->option('batch') ? \App\Models\TeacherImportBatch::find($this->option('batch')) : $review->latestBatch();
        if (! $batch) { $this->error('BLOCKED'); $this->line('Tidak ada batch impor guru yang tersedia.'); return self::FAILURE; }
        $blockers = $review->blockers($batch);
        $schools = app(\App\Services\TeacherSchoolReconciliationService::class)->resolutionSummary($batch);
        $groups = $review->duplicateGroups($batch);
        $completed = $groups->where('review_status', 'reviewed')->count();
        $this->line("Batch #{$batch->id} | authoritative: ".($batch->is_authoritative ? 'ya' : 'tidak'));
        $this->components->twoColumnDetail('School references resolved', (string) $schools['resolved']);
        $this->components->twoColumnDetail('School references accepted unresolved', (string) $schools['accepted_unresolved']);
        $this->components->twoColumnDetail('School references accepted incomplete source', (string) $schools['accepted_incomplete_source']);
        $this->components->twoColumnDetail('School references unresolved', (string) ($schools['unresolved'] + $schools['ambiguous']));
        $this->components->twoColumnDetail('Duplicate reviews completed', (string) $completed);
        $this->components->twoColumnDetail('Duplicate reviews pending', (string) ($groups->count() - $completed));
        $this->components->twoColumnDetail('Master-school warnings', (string) $schools['master_school_npsn_collisions']);
        if ($blockers !== []) {
            $this->error('BLOCKED');
            foreach ($blockers as $blocker) $this->line('- '.$blocker);
            return self::FAILURE;
        }
        $this->info('READY FOR AUTHORITATIVE IMPORT');
        $this->comment('Status ini hanya kelayakan review; command tidak mengubah batch menjadi authoritative.');
        return self::SUCCESS;
    }
}
