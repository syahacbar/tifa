<?php

namespace App\Console\Commands;

use App\Services\TeacherImportReviewService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('tifa:record-teacher-duplicate-review {fingerprint : Prefix fingerprint dari command review} {resolution : exact_duplicate|same_person_multiple_assignments|probable_duplicate|distinct_persons} {--batch= : ID batch} {--note= : Catatan review tanpa data pribadi}')]
#[Description('Mencatat keputusan reviewer untuk satu kelompok kandidat duplikat')]
class RecordTeacherDuplicateReviewCommand extends Command
{
    public function handle(TeacherImportReviewService $review): int
    {
        $batch = $this->option('batch') ? \App\Models\TeacherImportBatch::find($this->option('batch')) : $review->latestBatch();
        if (! $batch) { $this->error('Batch impor guru tidak ditemukan.'); return self::FAILURE; }
        try {
            $record = $review->recordReview($batch, $this->argument('fingerprint'), $this->argument('resolution'), $this->option('note'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage()); return self::FAILURE;
        }
        $this->info("Review {$record->resolution_type} dicatat untuk fingerprint ".substr($record->deduplication_fingerprint, 0, 12).'.');
        return self::SUCCESS;
    }
}
