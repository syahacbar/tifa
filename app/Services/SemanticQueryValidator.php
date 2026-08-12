<?php

namespace App\Services;

use App\Exceptions\SemanticQueryValidationException;
use App\Queries\SemanticQuery;

/** Validates semantic consistency only; it never reads data or executes tools. */
class SemanticQueryValidator
{
    private const DOMAINS = ['school', 'teacher'];
    private const OPERATIONS = ['count', 'lookup', 'aggregation', 'breakdown', 'ranking', 'comparison'];
    private const GROUPS = ['school', 'district', 'education_level', 'status', 'employment_status', 'ptk_type', 'ptk_position', 'education', 'school_resolution_status'];
    private const TEACHER_METRICS = ['unique_teacher_count', 'assignment_count'];
    private const SCHOOL_METRICS = ['school_count', 'student_total', 'student_male_total', 'student_female_total', 'teacher_total', 'education_staff_total', 'study_group_total', 'classroom_total', 'laboratory_total', 'library_total'];
    private const FILTERS = [
        'teacher' => ['education_level', 'district', 'school_id', 'employment_status', 'ptk_type', 'ptk_position', 'education'],
        'school' => ['education_level', 'district', 'status'],
    ];

    public function validate(SemanticQuery $query): void
    {
        $this->ensure(in_array($query->domain, self::DOMAINS, true), "Invalid domain {$query->domain}.");
        $this->ensure(in_array($query->operation, self::OPERATIONS, true), "Invalid operation {$query->operation}.");
        $metrics = $query->domain === 'teacher' ? self::TEACHER_METRICS : self::SCHOOL_METRICS;
        $this->ensure(in_array($query->metric, $metrics, true), "Metric {$query->metric} is not valid for {$query->domain} domain.");

        if ($query->groupBy !== null) {
            $this->ensure(in_array($query->groupBy, self::GROUPS, true), "Invalid group_by {$query->groupBy}.");
        }
        foreach ($query->filters as $field => $value) {
            if ($value !== null) {
                $this->ensure(in_array($field, self::FILTERS[$query->domain], true), "Filter {$field} is not valid for {$query->domain} domain.");
            }
        }
        $this->validateSort($query);

        match ($query->operation) {
            'ranking' => $this->validateRanking($query),
            'breakdown' => $this->validateBreakdown($query),
            'count' => $this->validateCount($query),
            'comparison' => $this->validateComparison($query),
            default => $this->validateNonRankingLimit($query),
        };

        if ($query->operation === 'ranking' && $query->groupBy === 'school' && $query->filters['school_id'] !== null) {
            throw new SemanticQueryValidationException('School ranking cannot use school_id filter.');
        }
    }

    private function validateSort(SemanticQuery $query): void
    {
        if ($query->sort === null) return;
        $this->ensure(array_keys($query->sort) === ['field', 'direction'], 'Sort must contain field and direction.');
        $this->ensure($query->sort['field'] === 'value', 'Invalid sort field '.$query->sort['field'].'.');
        $this->ensure(in_array($query->sort['direction'], ['asc', 'desc'], true), 'Invalid sort direction '.$query->sort['direction'].'.');
    }

    private function validateRanking(SemanticQuery $query): void
    {
        $this->ensure($query->groupBy !== null, 'Ranking operation requires group_by.');
        $this->ensure($query->sort !== null, 'Ranking operation requires sort.');
        $this->ensure($query->limit !== null, 'Ranking operation requires limit.');
        $this->ensure($query->limit >= 1 && $query->limit <= 20, 'Ranking limit must be between 1 and 20.');
        $this->ensure($query->comparisonValues === null, 'Comparison values are only valid for comparison operation.');
    }

    private function validateBreakdown(SemanticQuery $query): void
    {
        $this->ensure($query->groupBy !== null, 'Breakdown operation requires group_by.');
        $this->ensure($query->limit === null, 'Breakdown operation does not allow limit.');
        $this->ensure($query->sort === null, 'Breakdown operation does not allow sort.');
        $this->ensure($query->comparisonValues === null, 'Comparison values are only valid for comparison operation.');
    }

    private function validateCount(SemanticQuery $query): void
    {
        $this->ensure($query->groupBy === null, 'Count operation does not allow group_by.');
        $this->ensure($query->sort === null, 'Count operation does not allow sort.');
        $this->ensure($query->limit === null, 'Count operation does not allow limit.');
        $this->ensure($query->comparisonValues === null, 'Count operation does not allow comparison_values.');
    }

    private function validateComparison(SemanticQuery $query): void
    {
        $this->ensure($query->groupBy !== null, 'Comparison operation requires group_by.');
        $this->ensure(is_array($query->comparisonValues) && count($query->comparisonValues) >= 2, 'Comparison requires at least two comparison values.');
        $this->ensure($query->limit === null, 'Comparison operation does not allow limit.');
        $this->ensure($query->sort === null, 'Comparison operation does not allow sort.');
    }

    private function validateNonRankingLimit(SemanticQuery $query): void
    {
        $this->ensure($query->limit === null, ucfirst($query->operation).' operation does not allow limit.');
        $this->ensure($query->sort === null, ucfirst($query->operation).' operation does not allow sort.');
        $this->ensure($query->comparisonValues === null, 'Comparison values are only valid for comparison operation.');
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) throw new SemanticQueryValidationException($message);
    }
}
