<?php

namespace App\Services;

use App\Models\School;
use App\Models\TeacherAssignment;
use App\Models\TeacherAssignmentSchoolReview;
use App\Models\TeacherImportBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TeacherSchoolReconciliationService
{
    /** @var array<int, string> */
    public const RESOLUTIONS = ['link_existing_school', 'accepted_unresolved', 'accepted_incomplete_source', 'intentionally_without_school', 'needs_master_school_correction', 'unresolved'];

    /** @return Collection<int, array<string, mixed>> */
    public function missingNpsnRecommendations(TeacherImportBatch $batch): Collection
    {
        return $batch->assignments()->whereNull('source_npsn')->orderBy('source_sheet')->orderBy('source_row')->get()
            ->map(fn (TeacherAssignment $assignment) => $this->recommendation($assignment));
    }

    /** @return Collection<int, array<string, mixed>> */
    public function npsnRecommendations(TeacherImportBatch $batch, string $npsn): Collection
    {
        return $batch->assignments()->where('source_npsn', $npsn)->orderBy('source_sheet')->orderBy('source_row')->get()
            ->map(fn (TeacherAssignment $assignment) => $this->recommendation($assignment));
    }

    /** @return array<string, mixed> */
    public function recommendation(TeacherAssignment $assignment): array
    {
        $sourceName = $assignment->source_payload['tempat_tugas'] ?? null;
        $level = $this->educationLevel($assignment->source_sheet);
        $district = $assignment->district;
        $name = $this->normalizeSchoolName($sourceName);
        $base = School::query();
        $levelMatches = $level ? (clone $base)->whereRaw('LOWER(TRIM(education_level)) = ?', [mb_strtolower($level)]) : clone $base;
        $exact = $name === '' ? collect() : $levelMatches->get()->filter(fn (School $school) => $this->normalizeSchoolName($school->name) === $name);
        $exactDistrict = $exact->filter(fn (School $school) => $this->same($school->district, $district));
        $candidates = $exactDistrict->isNotEmpty() ? $exactDistrict : $exact;
        $reason = $exactDistrict->isNotEmpty() ? 'exact normalized name + district + education level' : ($exact->isNotEmpty() ? 'exact normalized name + education level' : '');
        $classification = $candidates->count() === 1 ? 'exact_match' : ($candidates->count() > 1 ? 'multiple_candidates' : 'no_candidate');

        if ($candidates->isEmpty() && $name !== '') {
            $alias = $levelMatches->get()->filter(fn (School $school) => $this->same($this->aliasSchoolName($school->name), $this->aliasSchoolName($sourceName)) && $this->same($school->district, $district));
            if ($alias->count() === 1) { $candidates = $alias; $classification = 'high_confidence_candidate'; $reason = 'normalized alias name + district + education level'; }
            elseif ($alias->count() > 1) { $candidates = $alias; $classification = 'multiple_candidates'; $reason = 'multiple normalized alias matches'; }
        }

        $fuzzy = collect();
        if ($candidates->isEmpty() && $name !== '') {
            $fuzzy = $levelMatches->get()->map(function (School $school) use ($name, $district): array {
                similar_text($name, $this->normalizeSchoolName($school->name), $percent);
                return ['school' => $school, 'score' => round($percent), 'district_match' => $this->same($school->district, $district)];
            })->filter(fn (array $item) => $item['score'] >= 65)->sortByDesc(fn (array $item) => $item['score'] + ($item['district_match'] ? 10 : 0))->take(3);
            if ($fuzzy->isNotEmpty()) { $classification = 'no_candidate'; $reason = 'fuzzy suggestion only; reviewer action required'; }
        }

        return [
            'assignment_id' => $assignment->id, 'source_sheet' => $assignment->source_sheet, 'source_row' => $assignment->source_row,
            'source_npsn' => $assignment->source_npsn, 'source_school_name' => $sourceName, 'education_level' => $level,
            'district' => $district, 'classification' => $classification, 'reason' => $reason ?: 'source school name or matching master school unavailable',
            'candidates' => $candidates->map(fn (School $school) => $this->schoolData($school))->values()->all(),
            'fuzzy_suggestions' => $fuzzy->map(fn (array $item) => $this->schoolData($item['school']) + ['similarity' => $item['score']])->values()->all(),
        ];
    }

    public function recordResolution(TeacherAssignment $assignment, string $resolution, ?int $schoolId, ?string $note): TeacherAssignmentSchoolReview
    {
        if (! in_array($resolution, self::RESOLUTIONS, true)) throw new InvalidArgumentException('Resolution type tidak didukung.');
        $school = $schoolId ? School::find($schoolId) : null;
        if ($resolution === 'link_existing_school' && ! $school) throw new InvalidArgumentException('Target school wajib ada untuk link_existing_school.');
        if ($resolution !== 'link_existing_school' && $schoolId !== null) throw new InvalidArgumentException('Target school hanya diperbolehkan untuk link_existing_school.');

        return DB::transaction(function () use ($assignment, $resolution, $school, $note): TeacherAssignmentSchoolReview {
            $current = $assignment->schoolReviews()->where('is_current', true)->first();
            if ($current && $current->resolution_type === $resolution && $current->school_id === $school?->id && $current->reviewer_note === $note) {
                return $current;
            }
            $assignment->schoolReviews()->where('is_current', true)->update(['is_current' => false]);
            $review = $assignment->schoolReviews()->create(['resolution_type' => $resolution, 'school_id' => $school?->id, 'reviewer_note' => $note, 'is_current' => true, 'reviewed_at' => now()]);
            $assignment->update(match ($resolution) {
                'link_existing_school' => ['school_id' => $school->id, 'school_resolution_status' => 'resolved'],
                'accepted_unresolved' => ['school_id' => null, 'school_resolution_status' => 'accepted_unresolved'],
                'accepted_incomplete_source' => ['school_id' => null, 'school_resolution_status' => 'accepted_incomplete_source'],
                'intentionally_without_school' => ['school_id' => null, 'school_resolution_status' => 'accepted_without_school'],
                'needs_master_school_correction' => ['school_id' => null, 'school_resolution_status' => 'needs_master_school_correction'],
                default => ['school_id' => null, 'school_resolution_status' => 'unresolved'],
            });
            return $review;
        });
    }

    /** @return array<string, int> */
    public function gateSummary(TeacherImportBatch $batch): array
    {
        $issues = $batch->assignments()->whereIn('school_resolution_status', ['unresolved', 'ambiguous', 'needs_master_school_correction']);
        return [
            'unreviewed' => (clone $issues)->whereDoesntHave('schoolReviews', fn ($query) => $query->where('is_current', true))->count(),
            'master_correction' => (clone $issues)->whereHas('schoolReviews', fn ($query) => $query->where('is_current', true)->where('resolution_type', 'needs_master_school_correction'))->count(),
            'explicit_unresolved' => (clone $issues)->whereHas('schoolReviews', fn ($query) => $query->where('is_current', true)->where('resolution_type', 'unresolved'))->count(),
        ];
    }

    /** @return array<string, int> */
    public function resolutionSummary(TeacherImportBatch $batch): array
    {
        return [
            'resolved' => $batch->assignments()->where('school_resolution_status', 'resolved')->count(),
            'accepted_without_school' => $batch->assignments()->where('school_resolution_status', 'accepted_without_school')->count(),
            'accepted_unresolved' => $batch->assignments()->where('school_resolution_status', 'accepted_unresolved')->count(),
            'accepted_incomplete_source' => $batch->assignments()->where('school_resolution_status', 'accepted_incomplete_source')->count(),
            'unresolved' => $batch->assignments()->where('school_resolution_status', 'unresolved')->count(),
            'ambiguous' => $batch->assignments()->where('school_resolution_status', 'ambiguous')->count(),
            'master_school_npsn_collisions' => School::query()->select('npsn')->whereNotNull('npsn')->groupBy('npsn')->havingRaw('count(*) > 1')->get()->count(),
        ];
    }

    private function educationLevel(string $sheet): ?string
    {
        return match (mb_strtoupper(trim($sheet))) { 'KB,TK,PAUD' => 'PAUD', 'SD' => 'SD', 'SMP' => 'SMP', 'SMA.' => 'SMA', 'SKB' => 'SKB', default => null };
    }

    private function normalizeSchoolName(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = str_replace(['smpn', 'sdn', 'sman', 'smkn'], ['smp negeri', 'sd negeri', 'sma negeri', 'smk negeri'], $value);
        return trim(preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? '');
    }

    private function aliasSchoolName(?string $value): string
    {
        return preg_replace('/\b(kabupaten|kab|teluk|bintuni)\b/u', '', $this->normalizeSchoolName($value)) ?? '';
    }

    private function same(?string $one, ?string $two): bool
    {
        return mb_strtolower(trim((string) $one)) === mb_strtolower(trim((string) $two)) && trim((string) $one) !== '';
    }

    /** @return array<string, mixed> */
    private function schoolData(School $school): array
    {
        return ['id' => $school->id, 'name' => $school->name, 'npsn' => $school->npsn, 'education_level' => $school->education_level, 'district' => $school->district, 'status' => $school->status];
    }
}
