<?php

namespace App\Console\Commands;

use App\Models\TeacherAssignment;
use App\Services\TeacherSchoolReconciliationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('tifa:record-teacher-school-resolution {assignment : ID assignment} {resolution : link_existing_school|accepted_unresolved|accepted_incomplete_source|intentionally_without_school|needs_master_school_correction|unresolved} {--school= : ID sekolah, wajib hanya untuk link_existing_school} {--note= : Catatan reviewer tanpa data pribadi}')]
#[Description('Mencatat keputusan reviewer school reference dan audit trail')]
class RecordTeacherSchoolResolutionCommand extends Command
{
    public function handle(TeacherSchoolReconciliationService $service): int
    {
        $assignment = TeacherAssignment::find($this->argument('assignment'));
        if (! $assignment) { $this->error('Assignment tidak ditemukan.'); return self::FAILURE; }
        try { $review = $service->recordResolution($assignment, $this->argument('resolution'), $this->option('school') ? (int) $this->option('school') : null, $this->option('note')); }
        catch (InvalidArgumentException $exception) { $this->error($exception->getMessage()); return self::FAILURE; }
        $this->info("Resolution {$review->resolution_type} dicatat untuk assignment #{$assignment->id}.");
        return self::SUCCESS;
    }
}
