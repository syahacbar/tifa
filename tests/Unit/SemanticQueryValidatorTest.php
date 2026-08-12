<?php

namespace Tests\Unit;

use App\Exceptions\SemanticQueryValidationException;
use App\Queries\SemanticQuery;
use App\Services\SemanticQueryValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SemanticQueryValidatorTest extends TestCase
{
    public function test_it_serializes_a_valid_teacher_ranking_deterministically(): void
    {
        $query = new SemanticQuery(
            domain: 'teacher', operation: 'ranking', metric: 'unique_teacher_count',
            groupBy: 'school', filters: ['education_level' => 'SMA'],
            sort: ['field' => 'value', 'direction' => 'desc'], limit: 5,
        );

        app(SemanticQueryValidator::class)->validate($query);

        $this->assertSame([
            'version' => 'v2', 'domain' => 'teacher', 'operation' => 'ranking', 'metric' => 'unique_teacher_count', 'group_by' => 'school',
            'filters' => ['education_level' => 'SMA', 'district' => null, 'school_id' => null, 'status' => null, 'employment_status' => null, 'ptk_type' => null, 'ptk_position' => null, 'education' => null],
            'sort' => ['field' => 'value', 'direction' => 'desc'], 'limit' => 5, 'comparison_values' => null,
        ], $query->toArray());
    }

    public function test_it_accepts_valid_teacher_breakdown_school_count_and_comparison(): void
    {
        $validator = app(SemanticQueryValidator::class);
        $validator->validate(new SemanticQuery('teacher', 'breakdown', 'unique_teacher_count', 'district'));
        $validator->validate(new SemanticQuery('school', 'count', 'school_count', filters: ['education_level' => 'SD']));
        $validator->validate(new SemanticQuery('teacher', 'comparison', 'unique_teacher_count', 'district', comparisonValues: ['Bintuni', 'Manimeri']));

        $this->addToAssertionCount(3);
    }

    #[DataProvider('invalidQueries')]
    public function test_it_rejects_invalid_semantic_combinations(SemanticQuery $query, string $message): void
    {
        $this->expectException(SemanticQueryValidationException::class);
        $this->expectExceptionMessage($message);

        app(SemanticQueryValidator::class)->validate($query);
    }

    /** @return array<string, array{SemanticQuery, string}> */
    public static function invalidQueries(): array
    {
        $teacherRanking = fn (array $changes = []) => new SemanticQuery(
            domain: $changes['domain'] ?? 'teacher', operation: $changes['operation'] ?? 'ranking', metric: $changes['metric'] ?? 'unique_teacher_count',
            groupBy: array_key_exists('groupBy', $changes) ? $changes['groupBy'] : 'school', filters: $changes['filters'] ?? [], sort: $changes['sort'] ?? ['field' => 'value', 'direction' => 'desc'],
            limit: array_key_exists('limit', $changes) ? $changes['limit'] : 5, comparisonValues: $changes['comparisonValues'] ?? null,
        );

        return [
            'ranking without group' => [$teacherRanking(['groupBy' => null]), 'requires group_by'],
            'ranking without limit' => [new SemanticQuery('teacher', 'ranking', 'unique_teacher_count', 'school', sort: ['field' => 'value', 'direction' => 'desc']), 'requires limit'],
            'ranking limit zero' => [$teacherRanking(['limit' => 0]), 'between 1 and 20'],
            'ranking limit above maximum' => [$teacherRanking(['limit' => 21]), 'between 1 and 20'],
            'school with teacher metric' => [$teacherRanking(['domain' => 'school']), 'not valid for school domain'],
            'teacher with school-only filter' => [$teacherRanking(['filters' => ['status' => 'NEGERI']]), 'Filter status is not valid for teacher domain'],
            'count with group' => [new SemanticQuery('teacher', 'count', 'unique_teacher_count', 'district'), 'does not allow group_by'],
            'count with limit' => [new SemanticQuery('teacher', 'count', 'unique_teacher_count', limit: 1), 'does not allow limit'],
            'breakdown with limit' => [new SemanticQuery('teacher', 'breakdown', 'unique_teacher_count', 'district', limit: 1), 'does not allow limit'],
            'comparison without values' => [new SemanticQuery('teacher', 'comparison', 'unique_teacher_count', 'district'), 'requires at least two'],
            'comparison with one value' => [new SemanticQuery('teacher', 'comparison', 'unique_teacher_count', 'district', comparisonValues: ['Bintuni']), 'requires at least two'],
            'comparison values on ranking' => [$teacherRanking(['comparisonValues' => ['Bintuni', 'Manimeri']]), 'only valid for comparison'],
            'school id with school ranking' => [$teacherRanking(['filters' => ['school_id' => 1]]), 'cannot use school_id'],
            'invalid sort direction' => [$teacherRanking(['sort' => ['field' => 'value', 'direction' => 'sideways']]), 'Invalid sort direction'],
            'invalid domain' => [$teacherRanking(['domain' => 'student']), 'Invalid domain'],
            'invalid operation' => [$teacherRanking(['operation' => 'sql']), 'Invalid operation'],
            'invalid group' => [$teacherRanking(['groupBy' => 'nik']), 'Invalid group_by'],
        ];
    }
}
