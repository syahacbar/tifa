<?php

namespace App\Console\Commands;

use App\Models\TeacherImportBatch;
use App\Services\TeacherSnapshotValidationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tifa:validate-teacher-snapshot {--batch= : ID batch, default batch terbaru}')]
#[Description('Memvalidasi statistik dan integrity snapshot guru')]
class ValidateTeacherSnapshotCommand extends Command
{
    public function handle(TeacherSnapshotValidationService $validation): int
    {
        $batch = $this->option('batch') ? TeacherImportBatch::find($this->option('batch')) : TeacherImportBatch::query()->latest('id')->first();
        if (! $batch) { $this->error('Batch impor guru tidak ditemukan.'); return self::FAILURE; }
        $report = $validation->validate($batch);
        $this->components->twoColumnDetail('Total assignment', (string) $report['total_assignments']);
        $this->table(['Sheet', 'Actual', 'Expected'], array_map(fn ($sheet, $expected) => [$sheet, $report['sheet_counts'][$sheet] ?? 0, $expected], array_keys($report['expected_sheet_counts']), array_values($report['expected_sheet_counts'])));
        $this->table(['School relation status', 'Total'], array_map(fn ($key, $value) => [$key, $value], array_keys($report['school_resolution_statuses']), array_values($report['school_resolution_statuses'])));
        $this->components->twoColumnDetail('Unique teacher count (dedup identity)', (string) $report['unique_teacher_count']);
        $this->components->twoColumnDetail('Reviewed multiple-assignment groups', (string) $report['duplicate_groups_reviewed']);
        $this->line('Warnings: '.implode('; ', $report['warnings']));
        if ($report['hard_blockers'] !== []) { $this->error('Validation failed: '.implode(', ', $report['hard_blockers'])); return self::FAILURE; }
        $this->info('Validation passed.'); return self::SUCCESS;
    }
}
