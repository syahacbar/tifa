<?php

namespace App\Services;

use App\Models\TeacherImportBatch;

class TeacherSnapshotValidationService
{
    /** @var array<string, int> */
    private const EXPECTED_SHEETS = ['SKB' => 22, 'KB,TK,PAUD' => 109, 'SD' => 643, 'SMP' => 402, 'SMA.' => 281];

    /** @return array<string, mixed> */
    public function validate(TeacherImportBatch $batch): array
    {
        $assignments = fn () => $batch->assignments();
        $sheetCounts = $assignments()->selectRaw('source_sheet, count(*) as total')->groupBy('source_sheet')->pluck('total', 'source_sheet')->map(fn ($count) => (int) $count)->all();
        $checksumValid = false;
        $path = storage_path('app/imports/'.$batch->source_filename);
        if (is_file($path)) $checksumValid = hash_file('sha256', $path) === $batch->source_checksum;
        $unresolved = $assignments()->whereIn('school_resolution_status', ['unresolved', 'ambiguous', 'needs_master_school_correction'])->count();
        $malformed = $assignments()->where(fn ($query) => $query->whereNull('source_fingerprint')->orWhere('source_fingerprint', '')->orWhereNull('source_sheet')->orWhere('source_sheet', '')->orWhere('source_row', '<=', 0))->count();
        $duplicateFingerprints = $assignments()->select('source_fingerprint')->groupBy('source_fingerprint')->havingRaw('count(*) > 1')->get()->count();
        $schoolStatus = $assignments()->selectRaw('school_resolution_status, count(*) as total')->groupBy('school_resolution_status')->pluck('total', 'school_resolution_status')->map(fn ($count) => (int) $count)->all();
        $duplicateGroups = $batch->duplicateReviews()->where('review_status', 'reviewed')->where('resolution_type', 'same_person_multiple_assignments')->count();
        $validSheets = count($sheetCounts) === count(self::EXPECTED_SHEETS) && collect(self::EXPECTED_SHEETS)->every(fn ($total, $sheet) => ($sheetCounts[$sheet] ?? null) === $total);
        $hardBlockers = [];
        if (! $checksumValid) $hardBlockers[] = 'source_checksum_invalid';
        if ($malformed > 0) $hardBlockers[] = "malformed_source_records:{$malformed}";
        if ($duplicateFingerprints > 0) $hardBlockers[] = "duplicate_source_fingerprints:{$duplicateFingerprints}";
        if ($unresolved > 0) $hardBlockers[] = "unresolved_review_decisions:{$unresolved}";
        if (! $validSheets || $assignments()->count() !== 1457) $hardBlockers[] = 'authoritative_assignment_count_mismatch';
        return [
            'total_assignments' => $assignments()->count(), 'sheet_counts' => $sheetCounts, 'expected_sheet_counts' => self::EXPECTED_SHEETS,
            'school_resolution_statuses' => $schoolStatus, 'employment_statuses' => $this->counts($assignments(), 'employment_status'),
            'ptk_types' => $this->counts($assignments(), 'ptk_type'), 'ptk_positions' => $this->counts($assignments(), 'ptk_position'),
            'educations' => $this->counts($assignments(), 'education'), 'districts' => $this->counts($assignments(), 'district'),
            'unique_teacher_count' => $assignments()->whereNotNull('deduplication_fingerprint')->distinct('deduplication_fingerprint')->count('deduplication_fingerprint'),
            'duplicate_groups_reviewed' => $duplicateGroups, 'checksum_valid' => $checksumValid, 'malformed_records' => $malformed,
            'duplicate_source_fingerprints' => $duplicateFingerprints, 'hard_blockers' => $hardBlockers,
            'warnings' => ['master_data_warning: NPSN 60725746 collision remains in schools master'],
        ];
    }

    /** @return array<string, int> */
    private function counts($assignments, string $column): array
    {
        return $assignments->selectRaw("COALESCE({$column}, '(unavailable)') as value, count(*) as total")->groupBy($column)->orderBy('value')->pluck('total', 'value')->map(fn ($count) => (int) $count)->all();
    }
}
