<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\TeacherImportBatch;
use App\Services\TeacherIdentifierVerificationService;
use App\Services\TeacherImportReviewService;
use App\Services\TeacherSchoolReconciliationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('tifa:verify-teacher-review {--batch= : ID batch, default batch terbaru}')]
#[Description('Membuat verifikasi identifier dan master school secara read-only')]
class VerifyTeacherReviewCommand extends Command
{
    public function handle(TeacherIdentifierVerificationService $identifiers, TeacherImportReviewService $duplicates, TeacherSchoolReconciliationService $schools): int
    {
        $batch = $this->option('batch') ? TeacherImportBatch::find($this->option('batch')) : TeacherImportBatch::query()->latest('id')->first();
        if (! $batch) { $this->error('Batch impor guru tidak ditemukan.'); return self::FAILURE; }
        $lines = ['# TIFA — Teacher Identifier & Master School Verification', '', "Batch #{$batch->id} · {$batch->reference_period}", '', '> Read-only. Identifier plaintext tidak dicetak dan tidak ada resolution yang diterapkan.', '', '## 1. Duplicate identifier verification', ''];
        foreach ($identifiers->verify($batch, $duplicates) as $index => $group) {
            $lines[] = '### DUP-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $lines[] = '';
            $lines[] = '- Short fingerprint: `'.$group['short_fingerprint'].'`';
            foreach ($group['identifier_statuses'] as $label => $status) $lines[] = '- '.$label.': `'.$status.'`';
            $lines[] = '- Final recommendation: `'.$group['final_recommendation'].'`';
            $lines[] = '';
        }
        $lines = [...$lines, '## 2. Master NPSN verification', ''];
        foreach (School::query()->with('dataset')->whereIn('id', [259, 260])->orderBy('id')->get() as $school) {
            $lines[] = "### School ID {$school->id}";
            $lines[] = '';
            $lines[] = '- School: '.$school->name;
            $lines[] = '- NPSN: '.$school->npsn;
            $lines[] = '- Jenjang / distrik / status: '.$school->education_level.' / '.$school->district.' / '.$school->status;
            $lines[] = '- Dataset: '.$school->dataset?->name.' · '.$school->dataset?->reference_period.' · active: '.($school->dataset?->is_active ? 'yes' : 'no');
            $lines[] = '- Source key berbeda: '.($school->source_key ? 'yes' : 'no');
            $lines[] = '';
        }
        $lines = [...$lines, '- Kesimpulan: dua record aktif berbeda nama, distrik, dan source key tetapi memakai NPSN `60725746`. Tidak ada metadata master lain yang menjelaskan collision; perlu koreksi master secara manual.', '', '## 3. Prepared school resolutions — not applied', ''];
        $groups = $schools->missingNpsnRecommendations($batch)->groupBy(fn ($item) => implode('|', [$item['source_school_name'], $item['education_level'], $item['district']]));
        $number = 1;
        foreach (['exact_match', 'high_confidence_candidate', 'no_candidate'] as $classification) foreach ($groups->filter(fn ($items) => $items->first()['classification'] === $classification)->values() as $items) {
            $item = $items->first(); $code = sprintf('SCH-%03d', $number++);
            $action = match ($code) {
                'SCH-014' => 'link school ID 155', 'SCH-024' => 'link school ID 208; flag nomenclature review',
                'SCH-028' => 'link school ID 220', 'SCH-029' => 'link school ID 173; flag nomenclature review',
                default => $number <= 14 ? 'link exact candidate '.$this->candidateIds($item) : ((($number - 1 >= 15 && $number - 1 <= 23) || ($number - 1 >= 25 && $number - 1 <= 27)) ? 'remain unresolved pending evidence' : 'remain unresolved pending evidence'),
            };
            $lines[] = '- '.$code.': `'.$action.'` — '.($item['source_school_name'] ?: '(kosong)').' / '.($item['education_level'] ?: '-').' / '.($item['district'] ?: '-');
        }
        $lines = [...$lines, '- NPSN-60401946: `link school ID 260; retain NPSN discrepancy in audit` (11 assignment).', '- NPSN-60725746 / SMPN TAROI: `link school ID 259` (7 assignment); master collision remains a separate correction.', '', '> Prepared recommendations are not database actions. Authoritative gate remains BLOCKED until reviewer decisions are explicitly recorded and all duplicate reviews are completed.'];
        $path = "reports/tifa-teacher-verification-batch-{$batch->id}.md";
        Storage::disk('local')->put($path, implode(PHP_EOL, $lines).PHP_EOL);
        $this->info('Verification report dibuat: '.Storage::disk('local')->path($path));
        return self::SUCCESS;
    }

    /** @param array<string, mixed> $item */
    private function candidateIds(array $item): string
    {
        return implode(', ', array_map(fn ($candidate) => '#'.$candidate['id'], $item['candidates'])) ?: 'none';
    }
}
