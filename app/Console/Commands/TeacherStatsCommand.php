<?php

namespace App\Console\Commands;

use App\Services\TeacherAnalyticsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('tifa:teacher-stats {--metric=assignment : assignment|unique} {--group-by= : dimension} {--level= : jenjang} {--district= : distrik} {--school-id= : master school ID} {--employment-status= : status} {--ptk-type= : jenis PTK} {--ptk-position= : jabatan PTK} {--education= : pendidikan} {--top= : top-N 1-20}')]
#[Description('QA read-only statistik guru dari batch authoritative')]
class TeacherStatsCommand extends Command
{
    public function handle(TeacherAnalyticsService $service): int
    {
        try {
            $option = $this->option('metric');
            $metric = $option === 'unique' ? 'unique_teacher_count' : ($option === 'assignment' ? 'assignment_count' : $option);
            $result = $service->query(['metric' => $metric, 'group_by' => $this->option('group-by'), 'top_n' => $this->option('top'), 'filters' => array_filter(['education_level' => $this->option('level'), 'district' => $this->option('district'), 'school_id' => $this->option('school-id'), 'employment_status' => $this->option('employment-status'), 'ptk_type' => $this->option('ptk-type'), 'ptk_position' => $this->option('ptk-position'), 'education' => $this->option('education')], fn ($v) => $v !== null)]);
        } catch (ValidationException $exception) { $this->error(implode(' ', array_merge(...array_values($exception->errors())))); return self::FAILURE; }
        $this->line("Batch #{$result['batch']['id']} · {$result['batch']['source_period']} · authoritative");
        if (isset($result['value'])) $this->components->twoColumnDetail($result['metric'], (string) $result['value']);
        else $this->table([$result['group_by'], $result['metric']], array_map(fn ($row) => [$row['label'], $row['value']], $result['data']['records']));
        return self::SUCCESS;
    }
}
