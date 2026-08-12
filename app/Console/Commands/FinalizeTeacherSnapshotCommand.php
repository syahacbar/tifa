<?php

namespace App\Console\Commands;

use App\Models\TeacherImportBatch;
use App\Services\TeacherSchoolReconciliationService;
use App\Services\TeacherSnapshotValidationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tifa:finalize-teacher-snapshot {--batch= : ID batch, default batch terbaru}')]
#[Description('Menerapkan keputusan akhir reviewer dan menandai snapshot guru authoritative bila valid')]
class FinalizeTeacherSnapshotCommand extends Command
{
    public function handle(TeacherSchoolReconciliationService $schools, TeacherSnapshotValidationService $validation): int
    {
        $batch = $this->option('batch') ? TeacherImportBatch::find($this->option('batch')) : TeacherImportBatch::query()->latest('id')->first();
        if (! $batch) { $this->error('Batch impor guru tidak ditemukan.'); return self::FAILURE; }
        $accepted = 0; $incomplete = 0;
        foreach ($batch->assignments()->whereIn('school_resolution_status', ['unresolved', 'ambiguous'])->get() as $assignment) {
            $name = $assignment->source_payload['tempat_tugas'] ?? null;
            $type = $name && $assignment->district ? 'accepted_unresolved' : 'accepted_incomplete_source';
            $note = $type === 'accepted_unresolved'
                ? 'Final reviewer decision: source school name, level, and district retained; no safe master-school link.'
                : 'Final reviewer decision: incomplete source retained without fabricated school, district, or NPSN values.';
            $schools->recordResolution($assignment, $type, null, $note);
            $type === 'accepted_unresolved' ? $accepted++ : $incomplete++;
        }
        $report = $validation->validate($batch->fresh());
        if ($report['hard_blockers'] !== []) { $this->error('Snapshot tetap non-authoritative: '.implode(', ', $report['hard_blockers'])); return self::FAILURE; }
        $batch->update(['is_authoritative' => true, 'status' => 'authoritative']);
        $this->info("Authoritative snapshot approved: {$report['total_assignments']} assignment; accepted_unresolved {$accepted}; accepted_incomplete_source {$incomplete}.");
        return self::SUCCESS;
    }
}
