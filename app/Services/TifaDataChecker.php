<?php

namespace App\Services;

use App\Models\Dataset;
use Illuminate\Database\Eloquent\Builder;

class TifaDataChecker
{
    private const IMPORTANT_FIELDS = [
        'source_key',
        'npsn',
        'name',
        'education_level',
        'district',
        'status',
    ];

    private const STATISTIC_FIELDS = [
        'students_male',
        'students_female',
        'students_total',
        'study_groups',
        'teachers',
        'education_staff',
        'classrooms',
        'laboratories',
        'libraries',
    ];

    private const EXTREME_LIMITS = [
        'students_total' => 10000,
        'study_groups' => 500,
        'teachers' => 1000,
        'education_staff' => 500,
        'classrooms' => 500,
        'laboratories' => 100,
        'libraries' => 50,
    ];

    /** @return array<string, mixed>|null */
    public function check(): ?array
    {
        $dataset = Dataset::current();
        if ($dataset === null) {
            return null;
        }

        $schools = $dataset->schools()->getQuery();
        $statistics = $this->statistics(clone $schools);
        $collisions = $this->npsnCollisions(clone $schools);
        $emptyValues = $this->emptyValues(clone $schools);
        $negativeStatistics = $this->negativeStatistics(clone $schools);
        $unreasonableStatistics = $this->unreasonableStatistics(clone $schools);
        $errorCount = array_sum($emptyValues) + count($negativeStatistics);
        $warningCount = count($collisions) + count($unreasonableStatistics);

        return [
            'dataset' => $dataset,
            'total_schools' => (clone $schools)->count(),
            'by_education_level' => $this->groupedCount(clone $schools, 'education_level'),
            'by_status' => $this->groupedCount(clone $schools, 'status'),
            'by_district' => $this->groupedCount(clone $schools, 'district'),
            'statistics' => $statistics,
            'npsn_collisions' => $collisions,
            'empty_values' => $emptyValues,
            'negative_statistics' => $negativeStatistics,
            'unreasonable_statistics' => $unreasonableStatistics,
            'warnings' => $warningCount,
            'errors' => $errorCount,
        ];
    }

    /** @return array<string, int> */
    private function groupedCount(Builder $query, string $column): array
    {
        return $query
            ->select($column)
            ->selectRaw('COUNT(*) AS total')
            ->groupBy($column)
            ->orderBy($column)
            ->pluck('total', $column)
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /** @return array<string, int> */
    private function statistics(Builder $query): array
    {
        $selects = array_map(
            fn (string $field) => "COALESCE(SUM({$field}), 0) AS {$field}",
            self::STATISTIC_FIELDS,
        );
        $totals = $query->selectRaw(implode(', ', $selects))->first();

        return collect(self::STATISTIC_FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => (int) ($totals?->{$field} ?? 0)])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function npsnCollisions(Builder $query): array
    {
        $detailQuery = clone $query;
        $npsns = (clone $query)
            ->select('npsn')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('npsn')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('npsn')
            ->pluck('total', 'npsn');

        return $npsns->map(function ($total, string $npsn) use ($detailQuery): array {
            $schools = (clone $detailQuery)
                ->where('npsn', $npsn)
                ->orderBy('name')
                ->get(['name', 'education_level', 'district'])
                ->map(fn ($school) => "{$school->name} ({$school->education_level}, {$school->district})")
                ->all();

            return ['npsn' => $npsn, 'total' => (int) $total, 'schools' => $schools];
        })->values()->all();
    }

    /** @return array<string, int> */
    private function emptyValues(Builder $query): array
    {
        $result = [];
        foreach (self::IMPORTANT_FIELDS as $field) {
            $result[$field] = (clone $query)
                ->where(fn (Builder $nested) => $nested->whereNull($field)->orWhereRaw("TRIM({$field}) = ''"))
                ->count();
        }

        return $result;
    }

    /** @return array<int, array<string, string>> */
    private function negativeStatistics(Builder $query): array
    {
        return $query
            ->where(function (Builder $nested): void {
                foreach (self::STATISTIC_FIELDS as $field) {
                    $nested->orWhere($field, '<', 0);
                }
            })
            ->get(['npsn', 'name', ...self::STATISTIC_FIELDS])
            ->map(function ($school): array {
                $fields = collect(self::STATISTIC_FIELDS)
                    ->filter(fn (string $field) => $school->{$field} < 0)
                    ->implode(', ');

                return ['npsn' => $school->npsn, 'school' => $school->name, 'issue' => "Nilai negatif: {$fields}"];
            })->all();
    }

    /** @return array<int, array<string, string>> */
    private function unreasonableStatistics(Builder $query): array
    {
        $issues = [];
        $this->appendIssues(
            $issues,
            (clone $query)->whereRaw('students_total <> students_male + students_female'),
            'Total siswa tidak sama dengan L + P',
        );
        $this->appendIssues(
            $issues,
            (clone $query)->where('students_total', '>', 0)->where('teachers', 0),
            'Memiliki siswa tetapi guru = 0',
        );
        $this->appendIssues(
            $issues,
            (clone $query)->where('students_total', '>', 0)->where('study_groups', 0),
            'Memiliki siswa tetapi rombel = 0',
        );

        foreach (self::EXTREME_LIMITS as $field => $limit) {
            $this->appendIssues(
                $issues,
                (clone $query)->where($field, '>', $limit),
                "{$field} melebihi {$limit}",
            );
        }

        return $issues;
    }

    /** @param array<int, array<string, string>> $issues */
    private function appendIssues(array &$issues, Builder $query, string $message): void
    {
        foreach ($query->get(['npsn', 'name']) as $school) {
            $issues[] = ['npsn' => $school->npsn, 'school' => $school->name, 'issue' => $message];
        }
    }
}
