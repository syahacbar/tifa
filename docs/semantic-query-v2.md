# Semantic Query Contract v2.1

`SemanticQuery` is a transport-neutral DTO for future TIFAA school and teacher query adapters. It does not replace any production parser, route, service, or tool in v2.1.

## Fields

`version` is always `v2`. The DTO carries `domain`, `operation`, `metric`, `group_by`, explicit nullable `filters`, nullable `sort`, nullable `limit`, and nullable `comparison_values`.

Supported domains are `school` and `teacher`. Operations are `count`, `lookup`, `aggregation`, `breakdown`, `ranking`, and `comparison`.

Teacher metrics: `unique_teacher_count`, `assignment_count`. School metrics mirror existing `TifaDataService` aggregate actions: `school_count`, student totals, teacher total, education staff, study groups, classrooms, laboratories, and libraries.

Filters are always serialized with these keys: `education_level`, `district`, `school_id`, `status`, `employment_status`, `ptk_type`, `ptk_position`, and `education`. The validator rejects non-null filters not allowed for the selected domain. `education_level` and `district` are shared; `status` is school-only in v2.1.

## Validation

Ranking requires `group_by`, `sort: {field: value, direction: asc|desc}`, and integer `limit` 1–20. Breakdown requires grouping and disallows limit. Count disallows grouping, sorting, limit, and comparison values. Comparison requires grouping plus at least two scalar `comparison_values`; those values are invalid for all other operations. A school ranking cannot include `school_id`.

Numeric predicates such as `teachers > 20` are deliberately unsupported in v2.1 and must not be represented as a free-form filter expression.

## Examples

```php
new SemanticQuery(
    domain: 'teacher', operation: 'ranking', metric: 'unique_teacher_count',
    groupBy: 'school', filters: ['education_level' => 'SMA'],
    sort: ['field' => 'value', 'direction' => 'desc'], limit: 5,
);

new SemanticQuery(
    domain: 'teacher', operation: 'comparison', metric: 'unique_teacher_count',
    groupBy: 'district', comparisonValues: ['Bintuni', 'Manimeri'],
);
```

## v2.2 direction

## v2.2 — teacher adapter

`TeacherAnalyticsIntentService` now emits `SemanticQuery` for teacher questions. It validates the v2 object before `TeacherSemanticQueryAdapter` maps it to the unchanged `TeacherDataTool` v1 contract. The public teacher intent remains v1-shaped for backward compatibility.

Semantic ranking carries `{field: value, direction: desc|asc}`. Phrases such as `terbanyak`, `tertinggi`, and `terbesar` are `desc`; `tersedikit`, `terendah`, and `terkecil` are `asc`. The adapter explicitly rejects ascending ranking because `TeacherAnalyticsService` only supports descending ordering today; v2.3 will add backend support.

The adapter maps `limit` to `top_n` and does not pass semantic-only fields such as `domain` or `sort` to the v1 tool. Production routing is still not unified: school intent parsing and routing remain unchanged.

## v2.3 — semantic execution

The existing teacher tool v1 now accepts optional `sort: {field: value, direction: asc|desc}` for rankings and optional `comparison_values` for the `comparison` operation. Existing v1 rankings without `sort` retain descending order.

`TeacherAnalyticsService` applies query-builder ordering by aggregate value followed by label ascending, so ties are deterministic. Ascending questions such as `paling sedikit` are now executed rather than rewritten as descending rankings.

Comparison values are resolved deterministically from the authoritative teacher districts, preserved in the order stated by the user, and queried one scope at a time. A valid requested district with no matching assignment is returned with value `0`; unresolved comparison values fail before tool execution. Numeric predicates remain unsupported. School-domain routing remains unchanged.
