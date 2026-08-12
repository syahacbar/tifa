<?php

namespace App\Services;

use App\Models\TeacherDuplicateReview;
use App\Models\TeacherImportBatch;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class TeacherImportReviewService
{
    /** @var array<int, string> */
    public const RESOLUTIONS = ['exact_duplicate', 'same_person_multiple_assignments', 'probable_duplicate', 'distinct_persons'];

    public function latestBatch(): ?TeacherImportBatch
    {
        return TeacherImportBatch::query()->latest('id')->first();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function schoolIssues(TeacherImportBatch $batch): Collection
    {
        return $batch->assignments()
            ->whereIn('school_resolution_status', ['unresolved', 'ambiguous'])
            ->selectRaw('source_npsn, school_resolution_status, count(*) as assignment_count')
            ->groupBy('source_npsn', 'school_resolution_status')
            ->orderBy('source_npsn')
            ->get()
            ->map(function ($issue): array {
                $schools = \App\Models\School::query()->with('dataset')->where('npsn', $issue->source_npsn)->orderBy('id')->get();

                return [
                    'npsn' => $issue->source_npsn,
                    'npsn_label' => $issue->source_npsn ?: '(kosong)',
                    'resolution' => $issue->school_resolution_status,
                    'assignment_count' => (int) $issue->assignment_count,
                    'schools' => $schools->map(fn ($school) => [
                        'id' => $school->id, 'name' => $school->name, 'education_level' => $school->education_level,
                        'district' => $school->district, 'status' => $school->status,
                        'dataset' => $school->dataset?->name, 'dataset_active' => $school->dataset?->is_active,
                        'reference_period' => $school->dataset?->reference_period,
                    ])->all(),
                ];
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    public function duplicateGroups(TeacherImportBatch $batch): Collection
    {
        $reviews = $batch->duplicateReviews()->get()->keyBy('deduplication_fingerprint');

        return $batch->assignments()->with('school')
            ->where('is_duplicate_candidate', true)
            ->whereNotNull('deduplication_fingerprint')
            ->orderBy('deduplication_fingerprint')->orderBy('id')->get()
            ->groupBy('deduplication_fingerprint')
            ->map(function (Collection $assignments, string $fingerprint) use ($reviews): array {
                $review = $reviews->get($fingerprint);
                $schoolIds = $assignments->pluck('school_id')->filter()->unique();
                $suggestion = $schoolIds->count() > 1 ? 'same_person_multiple_assignments' : 'probable_duplicate';
                $profiles = $assignments->map(fn ($assignment) => implode('|', [
                    $assignment->source_npsn, $assignment->ptk_type, $assignment->ptk_position,
                    $assignment->employment_status, $assignment->education, $assignment->district,
                ]))->unique();
                if ($profiles->count() === 1) {
                    $suggestion = 'exact_duplicate';
                }

                return [
                    'fingerprint' => $fingerprint, 'short_fingerprint' => substr($fingerprint, 0, 12),
                    'candidate_count' => $assignments->count(), 'suggested_resolution' => $suggestion,
                    'review_status' => $review?->review_status ?? 'pending', 'resolution_type' => $review?->resolution_type,
                    'reviewed_at' => $review?->reviewed_at,
                    'assignments' => $assignments->map(fn ($assignment) => [
                        'school' => $assignment->school?->name ?? '['.$assignment->school_resolution_status.']',
                        'npsn' => $assignment->source_npsn, 'education_level' => $assignment->school?->education_level,
                        'district' => $assignment->district, 'ptk_type' => $assignment->ptk_type,
                        'ptk_position' => $assignment->ptk_position, 'employment_status' => $assignment->employment_status,
                        'education' => $assignment->education, 'source_sheet' => $assignment->source_sheet, 'source_row' => $assignment->source_row,
                    ])->all(),
                ];
            })->values();
    }

    /** @return array<int, string> */
    public function blockers(TeacherImportBatch $batch): array
    {
        $blockers = [];
        $schoolGate = app(TeacherSchoolReconciliationService::class)->gateSummary($batch);
        $schoolSummary = app(TeacherSchoolReconciliationService::class)->resolutionSummary($batch);
        $groups = $this->duplicateGroups($batch);
        $pending = $groups->where('review_status', '!=', 'reviewed')->count()
            + $groups->filter(fn (array $group) => $group['review_status'] === 'reviewed' && ! in_array($group['resolution_type'], self::RESOLUTIONS, true))->count();
        if ($schoolGate['unreviewed'] > 0) $blockers[] = "{$schoolGate['unreviewed']} assignment school reference belum ditinjau.";
        if ($schoolGate['master_correction'] > 0) $blockers[] = "{$schoolGate['master_correction']} assignment menunggu koreksi master school.";
        if ($schoolGate['explicit_unresolved'] > 0) $blockers[] = "{$schoolGate['explicit_unresolved']} assignment tetap unresolved setelah review.";
        if ($pending > 0) $blockers[] = "{$pending} kelompok kandidat duplikat belum ditinjau.";

        return $blockers;
    }

    public function recordReview(TeacherImportBatch $batch, string $fingerprintPrefix, string $resolution, ?string $note): TeacherDuplicateReview
    {
        if (! in_array($resolution, self::RESOLUTIONS, true)) {
            throw new InvalidArgumentException('Resolution type tidak didukung.');
        }
        $matches = $this->duplicateGroups($batch)->filter(fn (array $group) => str_starts_with($group['fingerprint'], $fingerprintPrefix));
        if ($matches->count() !== 1) {
            throw new InvalidArgumentException('Fingerprint tidak ditemukan atau tidak unik. Gunakan prefix yang lebih panjang.');
        }
        $fingerprint = $matches->first()['fingerprint'];

        return TeacherDuplicateReview::query()->updateOrCreate(
            ['teacher_import_batch_id' => $batch->id, 'deduplication_fingerprint' => $fingerprint],
            ['review_status' => 'reviewed', 'resolution_type' => $resolution, 'reviewer_note' => $note, 'reviewed_at' => now()],
        );
    }
}
