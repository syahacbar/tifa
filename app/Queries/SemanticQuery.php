<?php

namespace App\Queries;

/**
 * Transport-neutral semantic representation for future TIFAA query adapters.
 *
 * This value object deliberately does not execute, normalize, or validate a
 * database query. SemanticQueryValidator owns consistency validation.
 */
final readonly class SemanticQuery
{
    public const VERSION = 'v2';

    public const FILTERS = [
        'education_level', 'district', 'school_id', 'status',
        'employment_status', 'ptk_type', 'ptk_position', 'education',
    ];

    /** @param array<string, mixed> $filters
     * @param array{field: ?string, direction: ?string}|null $sort
     * @param array<int, scalar>|null $comparisonValues
     */
    public function __construct(
        public string $domain,
        public string $operation,
        public string $metric,
        public ?string $groupBy = null,
        array $filters = [],
        public ?array $sort = null,
        public ?int $limit = null,
        public ?array $comparisonValues = null,
    ) {
        $this->filters = array_merge(array_fill_keys(self::FILTERS, null), $filters);
    }

    /** @var array<string, mixed> */
    public array $filters;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'domain' => $this->domain,
            'operation' => $this->operation,
            'metric' => $this->metric,
            'group_by' => $this->groupBy,
            'filters' => $this->filters,
            'sort' => $this->sort,
            'limit' => $this->limit,
            'comparison_values' => $this->comparisonValues,
        ];
    }
}
