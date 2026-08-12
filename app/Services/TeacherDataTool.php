<?php

namespace App\Services;

use App\Exceptions\TeacherDataToolException;
use App\Models\TeacherImportBatch;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeacherDataTool
{
    private const FILTERS = ['education_level', 'district', 'school_id', 'employment_status', 'ptk_type', 'ptk_position', 'education'];

    /** @return array<string, mixed> */
    public function execute(array $contract): array
    {
        try {
            $data = Validator::make($contract, [
                'version' => ['required', Rule::in(['v1'])], 'operation' => ['required', Rule::in(['count', 'breakdown', 'ranking'])],
                'entity' => ['required', Rule::in(['teacher_assignment', 'teacher_identity'])], 'metric' => ['required', Rule::in(TeacherAnalyticsService::METRICS)],
                'filters' => ['required', 'array:'.implode(',', self::FILTERS)], 'filters.*' => ['nullable'],
                'group_by' => ['nullable', Rule::in(TeacherAnalyticsService::DIMENSIONS)], 'top_n' => ['nullable', 'integer', 'min:1', 'max:20'],
            ])->validate();
        } catch (ValidationException $exception) {
            throw new TeacherDataToolException($this->validationCode($exception), 'Teacher tool request tidak valid.');
        }
        if (($data['entity'] === 'teacher_assignment') !== ($data['metric'] === 'assignment_count')) throw new TeacherDataToolException('invalid_metric', 'Metric tidak sesuai entity.');
        if ($data['operation'] === 'count' && ($data['group_by'] !== null || $data['top_n'] !== null)) throw new TeacherDataToolException('invalid_combination', 'Count tidak menerima group_by atau top_n.');
        if ($data['operation'] === 'breakdown' && ($data['group_by'] === null || $data['top_n'] !== null)) throw new TeacherDataToolException('invalid_combination', 'Breakdown memerlukan group_by tanpa top_n.');
        if ($data['operation'] === 'ranking' && ($data['group_by'] === null || $data['top_n'] === null)) throw new TeacherDataToolException('invalid_combination', 'Ranking memerlukan group_by dan top_n.');
        $analytics = app(TeacherAnalyticsService::class)->query(['metric' => $data['metric'], 'filters' => $data['filters'], 'group_by' => $data['group_by'], 'top_n' => $data['top_n']]);
        $analytics['comparison'] = (bool) ($contract['comparison'] ?? false);
        $quality = $this->quality($analytics, $data['group_by']);
        return ['version' => 'v1', 'status' => 'ok', 'operation' => $data['operation'], 'entity' => $data['entity'], 'metric' => $data['metric'], 'data' => isset($analytics['value']) ? ['value' => $analytics['value']] : $analytics['data'], 'context' => ['filters' => $analytics['filters'], 'group_by' => $analytics['group_by'], 'top_n' => $data['top_n']], 'provenance' => ['source' => 'teacher_authoritative_dataset', 'batch_id' => $analytics['batch']['id'], 'reference_period' => $analytics['batch']['source_period'], 'authoritative' => true], 'quality' => $quality, 'presentation' => $analytics];
    }

    /** @param array<string, mixed> $analytics
     * @return array<string, mixed>
     */
    private function quality(array $analytics, ?string $dimension): array
    {
        $batch = TeacherImportBatch::findOrFail($analytics['batch']['id']);
        $statuses = $batch->assignments()->selectRaw('school_resolution_status, count(*) total')->groupBy('school_resolution_status')->pluck('total', 'school_resolution_status');
        $unresolvedSchool = (int) ($statuses['accepted_unresolved'] ?? 0) + (int) ($statuses['accepted_incomplete_source'] ?? 0);
        $unavailable = match ($dimension) {
            'district' => $batch->assignments()->whereNull('district')->count(),
            'employment_status' => $batch->assignments()->whereNull('employment_status')->count(),
            'ptk_type' => $batch->assignments()->whereNull('ptk_type')->count(),
            'ptk_position' => $batch->assignments()->whereNull('ptk_position')->count(),
            'education' => $batch->assignments()->whereNull('education')->count(), default => 0,
        };
        return ['total_assignments' => $batch->assignments()->count(), 'school_resolution' => ['resolved' => (int) ($statuses['resolved'] ?? 0), 'accepted_unresolved' => (int) ($statuses['accepted_unresolved'] ?? 0), 'accepted_incomplete_source' => (int) ($statuses['accepted_incomplete_source'] ?? 0)], 'complete_for_requested_dimension' => $dimension === 'school' ? $unresolvedSchool === 0 : $unavailable === 0, 'unavailable_for_requested_dimension' => $unavailable];
    }

    private function validationCode(ValidationException $exception): string
    {
        $keys = array_keys($exception->errors());
        if (str_starts_with($keys[0] ?? '', 'filters')) return 'invalid_filter';
        return str_starts_with($keys[0] ?? '', 'group_by') ? 'invalid_group_by' : 'invalid_operation';
    }
}
