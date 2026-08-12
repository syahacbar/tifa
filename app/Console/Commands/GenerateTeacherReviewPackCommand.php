<?php

namespace App\Console\Commands;

use App\Models\TeacherImportBatch;
use App\Services\TeacherImportReviewService;
use App\Services\TeacherSchoolReconciliationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('tifa:generate-teacher-review-pack {--batch= : ID batch, default batch terbaru}')]
#[Description('Membuat markdown review pack read-only untuk keputusan manual data guru')]
class GenerateTeacherReviewPackCommand extends Command
{
    public function handle(TeacherSchoolReconciliationService $schools, TeacherImportReviewService $duplicates): int
    {
        $batch = $this->option('batch') ? TeacherImportBatch::find($this->option('batch')) : TeacherImportBatch::query()->latest('id')->first();
        if (! $batch) { $this->error('Batch impor guru tidak ditemukan.'); return self::FAILURE; }
        $lines = [
            '# TIFA — Teacher Data Review Pack', '', "Batch #{$batch->id} · {$batch->reference_period}", '',
            '> Read-only review pack. Tidak ada data guru, schools, workbook, atau status batch yang diubah.', '',
            '## A. Missing-NPSN school review', '',
        ];
        $groups = $schools->missingNpsnRecommendations($batch)->groupBy(fn ($item) => implode('|', [$item['source_school_name'], $item['education_level'], $item['district']]));
        $number = 1;
        foreach (['exact_match', 'high_confidence_candidate', 'no_candidate'] as $classification) {
            $classified = $groups->filter(fn ($items) => $items->first()['classification'] === $classification)->values();
            $lines[] = '### '.str_replace('_', ' ', $classification).' ('.$classified->count().')';
            $lines[] = '';
            foreach ($classified as $items) {
                $item = $items->first();
                $lines = [...$lines, ...$this->reviewItem(sprintf('SCH-%03d', $number++), $item, $items->count())];
            }
        }
        $lines = [...$lines, '## B. NPSN issue review', ''];
        foreach (['60401946', '60725746'] as $npsn) {
            $items = $schools->npsnRecommendations($batch, $npsn);
            $first = $items->first();
            $lines[] = "### NPSN-{$npsn}";
            $lines[] = '';
            $lines[] = '- Source school: '.($first['source_school_name'] ?: '(kosong)');
            $lines[] = '- Jenjang / distrik: '.($first['education_level'] ?: '-').' / '.($first['district'] ?: '-');
            $lines[] = '- Jumlah assignment: '.$items->count();
            $lines[] = '- Kandidat master: '.$this->candidates($first);
            $lines[] = '- Indikasi: '.($npsn === '60401946'
                ? 'probable existing school with different NPSN; kandidat nama/distrik/jenjang tepat tetapi NPSN master berbeda.'
                : 'NPSN collision kemungkinan kesalahan master: kandidat tepat berdasarkan nama/distrik berbeda dari record sekolah lain yang memakai NPSN sama.');
            $lines = [...$lines, '', 'Decision:', '', 'Resolution:', '', 'Note:', ''];
        }
        $lines = [...$lines, '## C. Duplicate review pack', ''];
        foreach ($duplicates->duplicateGroups($batch)->values() as $index => $group) {
            $lines[] = '### DUP-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $lines[] = '';
            $lines[] = '- Heuristic: `'.$group['suggested_resolution'].'`';
            $lines[] = '- Jumlah rows: '.$group['candidate_count'];
            $lines[] = '- Short fingerprint: `'.$group['short_fingerprint'].'`';
            $lines[] = '';
            $lines[] = '| Sheet/baris | Sekolah | Jenjang | Distrik | Jenis PTK | Jabatan/mapel | Status | Pendidikan |';
            $lines[] = '|---|---|---|---|---|---|---|---|';
            foreach ($group['assignments'] as $assignment) {
                $lines[] = '| '.$this->cell($assignment['source_sheet'].'/'.$assignment['source_row']).' | '.$this->cell($assignment['school']).' | '.$this->cell($assignment['education_level']).' | '.$this->cell($assignment['district']).' | '.$this->cell($assignment['ptk_type']).' | '.$this->cell($assignment['ptk_position']).' | '.$this->cell($assignment['employment_status']).' | '.$this->cell($assignment['education']).' |';
            }
            $lines = [...$lines, '', 'Decision:', '', 'Resolution:', '', 'Note:', ''];
        }
        $lines[] = '> Identifier pribadi (nama, NIK, NIP, NUPTK, telepon, dan data kelahiran) tidak dimasukkan dalam review pack ini.';
        $path = "reports/tifa-teacher-review-batch-{$batch->id}.md";
        Storage::disk('local')->put($path, implode(PHP_EOL, $lines).PHP_EOL);
        $this->info('Review pack dibuat: '.Storage::disk('local')->path($path));
        return self::SUCCESS;
    }

    /** @param array<string, mixed> $item
     * @return array<int, string>
     */
    private function reviewItem(string $code, array $item, int $count): array
    {
        return [
            "### {$code}", '', '- Klasifikasi: `'.$item['classification'].'`', '- Sekolah sumber: '.($item['source_school_name'] ?: '(kosong)'),
            '- Jenjang / distrik: '.($item['education_level'] ?: '-').' / '.($item['district'] ?: '-'), '- Jumlah assignment: '.$count,
            '- Kandidat master: '.$this->candidates($item), '- Confidence/reason: '.$item['reason'], '', 'Decision:', '', 'Resolution:', '', 'Note:', '',
        ];
    }

    /** @param array<string, mixed> $item */
    private function candidates(array $item): string
    {
        $candidates = $item['candidates'] !== [] ? $item['candidates'] : $item['fuzzy_suggestions'];
        if ($candidates === []) return '-';
        return implode('; ', array_map(fn ($school) => '#'.$school['id'].' '.$school['name'].' (NPSN '.$school['npsn'].')'.(isset($school['similarity']) ? ' · fuzzy '.$school['similarity'].'%' : ''), $candidates));
    }

    private function cell(?string $value): string
    {
        return str_replace('|', '\\|', $value ?: '-');
    }
}
