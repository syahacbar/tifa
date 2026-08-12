<?php

namespace App\Console\Commands;

use App\Models\TeacherImportBatch;
use App\Services\TeacherImportReviewService;
use App\Services\TeacherSchoolReconciliationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tifa:apply-reviewed-teacher-resolutions {--batch= : ID batch, default batch terbaru}')]
#[Description('Menerapkan keputusan review guru yang telah disetujui secara eksplisit')]
class ApplyReviewedTeacherResolutionsCommand extends Command
{
    /** @var array<string, int> */
    private const SCHOOL_LINKS = [
        'SCH-001' => 168, 'SCH-002' => 170, 'SCH-003' => 222, 'SCH-004' => 224, 'SCH-005' => 213,
        'SCH-006' => 161, 'SCH-007' => 191, 'SCH-008' => 275, 'SCH-009' => 277, 'SCH-010' => 236,
        'SCH-011' => 255, 'SCH-012' => 239, 'SCH-013' => 226, 'SCH-014' => 155, 'SCH-024' => 208,
        'SCH-028' => 220, 'SCH-029' => 173,
    ];

    public function handle(TeacherSchoolReconciliationService $schools, TeacherImportReviewService $duplicates): int
    {
        $batch = $this->option('batch') ? TeacherImportBatch::find($this->option('batch')) : TeacherImportBatch::query()->latest('id')->first();
        if (! $batch) { $this->error('Batch impor guru tidak ditemukan.'); return self::FAILURE; }
        $duplicateCount = 0;
        foreach ($duplicates->duplicateGroups($batch) as $group) {
            $duplicates->recordReview($batch, $group['fingerprint'], 'same_person_multiple_assignments', 'Identifier consistency verified; assignments retained separately.');
            $duplicateCount++;
        }
        $grouped = $schools->missingNpsnRecommendations($batch)->groupBy(fn ($item) => implode('|', [$item['source_school_name'], $item['education_level'], $item['district']]));
        $number = 1; $schoolCount = 0;
        foreach (['exact_match', 'high_confidence_candidate', 'no_candidate'] as $classification) foreach ($grouped->filter(fn ($items) => $items->first()['classification'] === $classification)->values() as $items) {
            $code = sprintf('SCH-%03d', $number++);
            if (! isset(self::SCHOOL_LINKS[$code])) continue;
            $note = in_array($code, ['SCH-024', 'SCH-029'], true)
                ? 'Approved link; source naming differs from master nomenclature and requires nomenclature review.'
                : 'Approved reviewed reconciliation link.';
            foreach ($items as $item) { $schools->recordResolution($batch->assignments()->findOrFail($item['assignment_id']), 'link_existing_school', self::SCHOOL_LINKS[$code], $note); $schoolCount++; }
        }
        foreach ($batch->assignments()->where('source_npsn', '60401946')->get() as $assignment) {
            $schools->recordResolution($assignment, 'link_existing_school', 260, 'Source workbook uses NPSN 60401946 while master school ID 260 uses 60725746; discrepancy retained for audit.');
            $schoolCount++;
        }
        foreach ($batch->assignments()->where('source_npsn', '60725746')->get() as $assignment) {
            $schools->recordResolution($assignment, 'link_existing_school', 259, 'Source SMPN TAROI matched by name, district, and education level; master NPSN collision retained for correction.');
            $schoolCount++;
        }
        $this->info("Applied {$duplicateCount} duplicate reviews and {$schoolCount} explicit school resolutions. Unsafe cases remain unresolved.");
        return self::SUCCESS;
    }
}
