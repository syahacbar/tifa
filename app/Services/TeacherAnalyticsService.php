<?php

namespace App\Services;

use App\Models\TeacherImportBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class TeacherAnalyticsService
{
    public const METRICS = ['assignment_count', 'unique_teacher_count'];
    public const DIMENSIONS = ['education_level', 'district', 'school', 'employment_status', 'ptk_type', 'ptk_position', 'education', 'school_resolution_status'];

    /** @return array<string, mixed> */
    public function query(array $input): array
    {
        $data = Validator::make($input, [
            'metric' => ['required', 'string', Rule::in(self::METRICS)],
            'filters' => ['sometimes', 'array:education_level,district,school_id,employment_status,ptk_type,ptk_position,education'],
            'filters.education_level' => ['nullable', 'string'], 'filters.district' => ['nullable', 'string'], 'filters.school_id' => ['nullable', 'integer'],
            'filters.employment_status' => ['nullable', 'string'], 'filters.ptk_type' => ['nullable', 'string'], 'filters.ptk_position' => ['nullable', 'string'], 'filters.education' => ['nullable', 'string'],
            'group_by' => ['nullable', 'string', Rule::in(self::DIMENSIONS)], 'top_n' => ['nullable', 'integer', 'min:1', 'max:20'],
            'sort' => ['nullable', 'array:field,direction'], 'sort.field' => ['required_with:sort', Rule::in(['value'])], 'sort.direction' => ['required_with:sort', Rule::in(['asc', 'desc'])],
            'comparison_values' => ['nullable', 'array', 'min:2'], 'comparison_values.*' => ['string'],
        ])->validate();
        $batch = TeacherImportBatch::query()->where('is_authoritative', true)->latest('id')->first();
        if (! $batch) throw new RuntimeException('Batch guru authoritative tidak ditemukan.');
        $filters = array_merge(array_fill_keys(['education_level','district','school_id','employment_status','ptk_type','ptk_position','education'], null), $data['filters'] ?? []);
        $query = $this->filtered($batch, $filters);
        $metric = $data['metric'];
        $group = $data['group_by'] ?? null;
        $sort = $data['sort'] ?? null;
        $comparisonValues = $data['comparison_values'] ?? null;
        $result = ['metric' => $metric, 'filters' => $filters, 'group_by' => $group, 'sort' => $sort, 'comparison_values' => $comparisonValues, 'batch' => ['id' => $batch->id, 'source_period' => $batch->reference_period, 'authoritative' => true], 'generated_at' => now()->toIso8601String(), 'data_quality_notes' => ['school relations distinguish resolved master school, accepted unresolved source, and incomplete source; null dimension values are labelled Tidak tersedia.']];
        if (! $group) return $result + ['value' => $this->metric($query, $metric), 'visualization' => 'kpi'];
        $rows = $comparisonValues !== null
            ? $this->comparison($query, $metric, $group, $comparisonValues)
            : $this->breakdown($query, $metric, $group, $data['top_n'] ?? null, $sort['direction'] ?? 'desc');
        return $result + ['data' => ['records' => $rows], 'visualization' => ($data['top_n'] ?? null) ? 'bar_chart' : 'table'];
    }

    /** @param array<string, mixed> $filters */
    private function filtered(TeacherImportBatch $batch, array $filters): Builder
    {
        $query = $batch->assignments()->getQuery();
        foreach (['district','employment_status','ptk_type','ptk_position','education'] as $field) if ($filters[$field] !== null) $query->whereRaw("LOWER(TRIM({$field})) = ?", [mb_strtolower(trim($filters[$field]))]);
        if ($filters['school_id'] !== null) $query->where('school_id', $filters['school_id']);
        if ($filters['education_level'] !== null) $query->whereIn('source_sheet', $this->sheetsForLevel($filters['education_level']));
        return $query;
    }

    private function metric(Builder $query, string $metric): int
    {
        return $metric === 'assignment_count' ? $query->count() : $query->whereNotNull('deduplication_fingerprint')->distinct('deduplication_fingerprint')->count('deduplication_fingerprint');
    }

    /** @return array<int, array<string, int|string>> */
    private function breakdown(Builder $query, string $metric, string $dimension, ?int $top, string $direction = 'desc'): array
    {
        if ($dimension === 'school') {
            $query->leftJoin('schools', 'teacher_assignments.school_id', '=', 'schools.id');
            $label = "CASE WHEN teacher_assignments.school_id IS NOT NULL THEN schools.name WHEN teacher_assignments.school_resolution_status = 'accepted_unresolved' THEN 'Unresolved source school' ELSE 'Incomplete source' END";
        } elseif ($dimension === 'education_level') {
            $label = "CASE source_sheet WHEN 'KB,TK,PAUD' THEN 'PAUD' WHEN 'SMA.' THEN 'SMA' ELSE source_sheet END";
        } else $label = "COALESCE(NULLIF({$dimension}, ''), 'Tidak tersedia')";
        $aggregate = $metric === 'assignment_count' ? 'COUNT(*)' : 'COUNT(DISTINCT deduplication_fingerprint)';
        $rows = $query->selectRaw("{$label} as label, {$aggregate} as value")->groupByRaw($label)->orderBy('value', $direction)->orderBy('label');
        if ($top) $rows->limit($top);
        return $rows->get()->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->value])->all();
    }

    /** @param array<int, string> $values
     * @return array<int, array{label:string,value:int}>
     */
    private function comparison(Builder $query, string $metric, string $dimension, array $values): array
    {
        return collect($values)->map(function (string $value) use ($query, $metric, $dimension): array {
            $scoped = clone $query;
            if ($dimension === 'school') $scoped->whereHas('school', fn ($schools) => $schools->where('name', $value));
            else $scoped->whereRaw("LOWER(TRIM({$dimension})) = ?", [mb_strtolower(trim($value))]);

            return ['label' => $value, 'value' => $this->metric($scoped, $metric)];
        })->all();
    }

    /** @return array<int, string> */
    private function sheetsForLevel(string $level): array
    {
        return match (mb_strtoupper(trim($level))) { 'PAUD' => ['KB,TK,PAUD'], 'SMA' => ['SMA.'], default => [mb_strtoupper(trim($level))] };
    }
}
