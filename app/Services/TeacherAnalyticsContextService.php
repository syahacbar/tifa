<?php

namespace App\Services;

class TeacherAnalyticsContextService
{
    /** @return array<string, mixed>|null */
    public function normalize(?array $context): ?array
    {
        if (! is_array($context) || ($context['domain'] ?? null) !== 'teacher_analytics' || ! is_array($context['filters'] ?? null)) return null;
        $allowed = array_fill_keys(['education_level', 'district', 'school_id', 'employment_status', 'ptk_type', 'ptk_position', 'education'], null);
        foreach ($context['filters'] as $key => $value) if (! array_key_exists($key, $allowed) || (! is_null($value) && ! is_string($value) && ! is_int($value))) return null;
        return ['domain' => 'teacher_analytics', 'metric' => in_array($context['metric'] ?? null, TeacherAnalyticsService::METRICS, true) ? $context['metric'] : 'unique_teacher_count', 'filters' => array_merge($allowed, $context['filters']), 'group_by' => in_array($context['group_by'] ?? null, TeacherAnalyticsService::DIMENSIONS, true) ? $context['group_by'] : null, 'top_n' => is_int($context['top_n'] ?? null) && $context['top_n'] >= 1 && $context['top_n'] <= 20 ? $context['top_n'] : null];
    }

    /** @param array<string, mixed> $intent */
    public function fromIntent(array $intent): array
    {
        return ['domain' => 'teacher_analytics', 'metric' => $intent['metric'], 'filters' => $intent['filters'], 'group_by' => $intent['group_by'], 'top_n' => $intent['top_n']];
    }
}
