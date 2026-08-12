<?php

namespace App\Services;

use App\Exceptions\SemanticQueryValidationException;
use App\Queries\SemanticQuery;

/** Maps validated teacher SemanticQuery v2 objects to the existing tool v1 contract. */
class TeacherSemanticQueryAdapter
{
    /** @return array<string, mixed> */
    public function toToolContract(SemanticQuery $query): array
    {
        if ($query->domain !== 'teacher') {
            throw new SemanticQueryValidationException('Teacher adapter only accepts teacher domain queries.');
        }
        return [
            'version' => 'v1',
            'operation' => $query->operation,
            'entity' => $query->metric === 'assignment_count' ? 'teacher_assignment' : 'teacher_identity',
            'metric' => $query->metric,
            'filters' => array_intersect_key($query->filters, array_flip([
                'education_level', 'district', 'school_id', 'employment_status', 'ptk_type', 'ptk_position', 'education',
            ])),
            'group_by' => $query->groupBy,
            'top_n' => $query->limit,
            'sort' => $query->sort,
            'comparison_values' => $query->comparisonValues,
            'comparison' => $query->operation === 'comparison',
        ];
    }
}
