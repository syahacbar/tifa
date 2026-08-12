<?php

namespace App\Services;

use App\Exceptions\DatasetUnavailableException;
use App\Models\Dataset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TifaDataService
{
    private const AGGREGATE_ACTIONS = [
        'school_count' => null,
        'student_total' => 'students_total',
        'student_male_total' => 'students_male',
        'student_female_total' => 'students_female',
        'teacher_total' => 'teachers',
        'education_staff_total' => 'education_staff',
        'study_group_total' => 'study_groups',
        'classroom_total' => 'classrooms',
        'laboratory_total' => 'laboratories',
        'library_total' => 'libraries',
    ];

    private const ANALYTIC_ACTIONS = [
        'school_list',
        'school_ranking',
        'district_breakdown',
        'education_level_breakdown',
        'status_breakdown',
    ];

    private const RANKING_METRICS = [
        'students_total',
        'teachers',
        'classrooms',
        'laboratories',
        'libraries',
    ];

    /** @return array<int, string> */
    public static function supportedActions(): array
    {
        return [...array_keys(self::AGGREGATE_ACTIONS), ...self::ANALYTIC_ACTIONS];
    }

    /** @return array<int, string> */
    public static function rankingMetrics(): array
    {
        return self::RANKING_METRICS;
    }

    /**
     * Jalankan agregasi atau query analitik pada dataset aktif.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function query(array $input): array
    {
        $validated = Validator::make($input, [
            'action' => ['required', 'string', Rule::in(self::supportedActions())],
            'filters' => ['sometimes', 'array:education_level,status,district'],
            'filters.education_level' => ['nullable', 'string'],
            'filters.status' => ['nullable', 'string'],
            'filters.district' => ['nullable', 'string'],
            'options' => ['sometimes', 'array:ranking_by,limit'],
            'options.ranking_by' => ['nullable', 'string', Rule::in(self::rankingMetrics())],
            'options.limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ])->validate();

        $dataset = Dataset::current();
        if ($dataset === null) {
            throw new DatasetUnavailableException('Dataset aktif tidak ditemukan.');
        }

        $filters = array_merge([
            'education_level' => null,
            'status' => null,
            'district' => null,
        ], $validated['filters'] ?? []);
        $options = $validated['options'] ?? [];

        if ($validated['action'] !== 'school_ranking' && $options !== []) {
            Validator::make(['options' => $options], [
                'options' => ['prohibited'],
            ])->validate();
        }

        $schools = $this->schoolsQuery($dataset, $filters);

        return match ($validated['action']) {
            'school_list' => $this->schoolList($dataset, $filters, $schools),
            'school_ranking' => $this->schoolRanking($dataset, $filters, $schools, $options),
            'district_breakdown' => $this->breakdown($dataset, $filters, $schools, 'district', 'district', 'bar_chart'),
            'education_level_breakdown' => $this->breakdown($dataset, $filters, $schools, 'education_level', 'education_level', 'bar_chart'),
            'status_breakdown' => $this->breakdown($dataset, $filters, $schools, 'status', 'status', 'comparison'),
            default => $this->aggregate($dataset, $validated['action'], $filters, $schools),
        };
    }

    /** @param array<string, ?string> $filters */
    private function schoolsQuery(Dataset $dataset, array $filters): Builder
    {
        return $dataset->schools()->getQuery()
            ->byEducationLevel($filters['education_level'])
            ->byStatus($filters['status'])
            ->byDistrict($filters['district']);
    }

    /** @param array<string, ?string> $filters */
    private function aggregate(Dataset $dataset, string $action, array $filters, Builder $schools): array
    {
        $column = self::AGGREGATE_ACTIONS[$action];
        $value = $column === null ? $schools->count() : (int) $schools->sum($column);

        return [
            ...$this->baseResult($dataset, $action, $filters),
            'value' => $value,
            'visualization' => 'kpi',
        ];
    }

    /** @param array<string, ?string> $filters */
    private function schoolList(Dataset $dataset, array $filters, Builder $schools): array
    {
        $records = $schools->orderBy('name')->get($this->schoolColumns())
            ->map(fn ($school) => $this->schoolRecord($school))->all();

        return [
            ...$this->baseResult($dataset, 'school_list', $filters),
            'data' => ['total' => count($records), 'records' => $records],
            'visualization' => 'table',
        ];
    }

    /** @param array<string, ?string> $filters
     * @param  array<string, mixed>  $options
     */
    private function schoolRanking(Dataset $dataset, array $filters, Builder $schools, array $options): array
    {
        $metric = $options['ranking_by'] ?? 'students_total';
        $limit = $options['limit'] ?? 10;
        $records = $schools->orderByDesc($metric)->orderBy('name')->limit($limit)->get($this->schoolColumns())
            ->values()->map(fn ($school, int $index) => [
                'rank' => $index + 1,
                ...$this->schoolRecord($school),
                'metric' => $metric,
                'value' => (int) $school->{$metric},
            ])->all();

        return [
            ...$this->baseResult($dataset, 'school_ranking', $filters),
            'data' => [
                'ranking_by' => $metric,
                'limit' => $limit,
                'records' => $records,
            ],
            'visualization' => 'table',
        ];
    }

    /** @param array<string, ?string> $filters */
    private function breakdown(Dataset $dataset, array $filters, Builder $schools, string $column, string $label, string $visualization): array
    {
        $records = $schools->select($column)->selectRaw('COUNT(*) AS value')->groupBy($column)->orderBy($column)
            ->get()->map(fn ($row) => ['label' => $row->{$column}, 'value' => (int) $row->value])->all();

        return [
            ...$this->baseResult($dataset, "{$column}_breakdown", $filters),
            'data' => ['dimension' => $label, 'records' => $records],
            'visualization' => $visualization,
        ];
    }

    /** @param array<string, ?string> $filters */
    private function baseResult(Dataset $dataset, string $action, array $filters): array
    {
        return [
            'action' => $action,
            'filters' => $filters,
            'dataset' => [
                'id' => $dataset->id,
                'name' => $dataset->name,
                'reference_period' => $dataset->reference_period,
            ],
            'source_date' => $dataset->published_at?->toDateString(),
        ];
    }

    /** @return array<int, string> */
    private function schoolColumns(): array
    {
        return [
            'npsn', 'name', 'education_level', 'district', 'status', 'students_total',
            'teachers', 'classrooms', 'laboratories', 'libraries',
        ];
    }

    /** @return array<string, int|string> */
    private function schoolRecord(object $school): array
    {
        return [
            'npsn' => $school->npsn,
            'name' => $school->name,
            'education_level' => $school->education_level,
            'district' => $school->district,
            'status' => $school->status,
            'students_total' => (int) $school->students_total,
            'teachers' => (int) $school->teachers,
            'classrooms' => (int) $school->classrooms,
            'laboratories' => (int) $school->laboratories,
            'libraries' => (int) $school->libraries,
        ];
    }
}
