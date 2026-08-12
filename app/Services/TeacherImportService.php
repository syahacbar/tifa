<?php

namespace App\Services;

use App\Models\School;
use App\Models\TeacherAssignment;
use App\Models\TeacherImportBatch;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class TeacherImportService
{
    /** @return array<string, mixed> */
    public function import(string $path, bool $dryRun = false): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File workbook tidak ditemukan: {$path}");
        }

        $records = $this->read($path);
        $report = $this->validate($records);
        $report += ['dry_run' => $dryRun, 'source_filename' => basename($path), 'source_checksum' => hash_file('sha256', $path)];
        if ($dryRun) {
            return $report;
        }

        return DB::transaction(function () use ($records, $report): array {
            $batch = TeacherImportBatch::query()->firstOrCreate(
                ['source_checksum' => $report['source_checksum']],
                ['source_filename' => $report['source_filename'], 'reference_period' => 'Maret 2026'],
            );
            $created = 0;
            $updated = 0;
            foreach ($records as $record) {
                $model = $batch->assignments()->where('source_fingerprint', $record['source_fingerprint'])->first();
                if (! $model) {
                    $batch->assignments()->create($record);
                    $created++;
                    continue;
                }
                if (in_array($model->school_resolution_status, ['resolved', 'accepted_unresolved', 'accepted_incomplete_source', 'accepted_without_school'], true)) {
                    $record['school_id'] = $model->school_id;
                    $record['school_resolution_status'] = $model->school_resolution_status;
                }
                $model->update($record);
                $updated++;
            }
            $batch->update([
                'record_count' => $report['records'], 'valid_count' => $report['valid'], 'unresolved_count' => $report['unresolved'],
                'duplicate_candidate_count' => $report['duplicate_candidate_rows'], 'metadata' => $report,
            ]);

            return $report + ['batch_id' => $batch->id, 'created' => $created, 'updated' => $updated];
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function read(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        $records = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            $rows = $sheet->toArray(null, true, true, true);
            $headers = array_map(fn ($value) => mb_strtolower(trim((string) $value)), $rows[1] ?? []);
            foreach ($rows as $rowNumber => $row) {
                if ($rowNumber === 1 || ! array_filter($row, fn ($v) => TeacherDataNormalizer::nullable($v) !== null)) {
                    continue;
                }
                $value = fn (string $header) => $row[array_search($header, $headers, true)] ?? null;
                $npsn = TeacherDataNormalizer::nullable($value('npsn'));
                $source = [
                    'full_name' => TeacherDataNormalizer::nullable($value('nama')), 'nik' => TeacherDataNormalizer::nullable($value('nik')),
                    'nip' => TeacherDataNormalizer::nullable($value('nip')), 'nuptk' => TeacherDataNormalizer::nullable($value('nuptk')),
                    'phone' => TeacherDataNormalizer::nullable($value('nomor hp')), 'birth_place' => TeacherDataNormalizer::nullable($value('tempat lahir')),
                    'birth_date' => TeacherDataNormalizer::date($value('tanggal lahir')), 'employment_status_source' => TeacherDataNormalizer::nullable($value('status kepegawaian')),
                    'ptk_type_source' => TeacherDataNormalizer::nullable($value('jenis ptk')), 'ptk_position_source' => TeacherDataNormalizer::nullable($value('jabatan ptk')),
                    'education_source' => TeacherDataNormalizer::nullable($value('pendidikan')), 'district_source' => TeacherDataNormalizer::nullable($value('kecamatan')),
                ];
                $dedup = TeacherDataNormalizer::fingerprint([$source['nik'], $source['nip'], $source['nuptk'], $source['full_name'], $source['birth_date']]);
                $records[] = [
                    'source_sheet' => $sheet->getTitle(), 'source_row' => $rowNumber, 'source_npsn' => $npsn,
                    'source_fingerprint' => hash('sha256', json_encode([$sheet->getTitle(), $rowNumber, $source, $npsn])),
                    'deduplication_fingerprint' => $dedup, 'is_duplicate_candidate' => false,
                    ...$source,
                    'employment_status' => TeacherDataNormalizer::employmentStatus($source['employment_status_source']),
                    'ptk_type' => TeacherDataNormalizer::canonical($source['ptk_type_source']),
                    'ptk_position' => TeacherDataNormalizer::canonical($source['ptk_position_source']),
                    'education' => TeacherDataNormalizer::canonical($source['education_source']),
                    'district' => TeacherDataNormalizer::canonical($source['district_source']),
                    'source_payload' => ['status_tugas' => TeacherDataNormalizer::nullable($value('status tugas')), 'tempat_tugas' => TeacherDataNormalizer::nullable($value('tempat tugas'))],
                ];
            }
        }
        $book->disconnectWorksheets();

        return $records;
    }

    /** @param array<int, array<string, mixed>> $records
     * @return array<string, mixed>
     */
    private function validate(array &$records): array
    {
        $npsns = array_values(array_unique(array_filter(array_column($records, 'source_npsn'))));
        $schools = School::query()->whereIn('npsn', $npsns)->get()->groupBy('npsn');
        $fingerprints = array_count_values(array_filter(array_column($records, 'deduplication_fingerprint')));
        $unresolved = [];
        $ambiguous = [];
        $unresolvedRows = 0;
        $ambiguousRows = 0;
        $duplicates = 0;
        foreach ($records as &$record) {
            $matches = $schools[$record['source_npsn']] ?? collect();
            $record['school_id'] = $matches->count() === 1 ? $matches->first()->id : null;
            $record['school_resolution_status'] = $matches->count() === 1 ? 'resolved' : ($matches->count() > 1 ? 'ambiguous' : 'unresolved');
            if ($record['school_resolution_status'] === 'unresolved') {
                $unresolvedRows++;
                if ($record['source_npsn'] !== null) {
                    $unresolved[$record['source_npsn']] = true;
                }
            }
            if ($record['school_resolution_status'] === 'ambiguous') {
                $ambiguousRows++;
                if ($record['source_npsn'] !== null) {
                    $ambiguous[$record['source_npsn']] = true;
                }
            }
            $record['is_duplicate_candidate'] = $record['deduplication_fingerprint'] !== null && ($fingerprints[$record['deduplication_fingerprint']] ?? 0) > 1;
            $duplicates += (int) $record['is_duplicate_candidate'];
        }

        return [
            'records' => count($records), 'valid' => count($records) - $unresolvedRows - $ambiguousRows, 'unresolved' => $unresolvedRows,
            'unresolved_npsns' => array_keys($unresolved), 'ambiguous' => $ambiguousRows, 'ambiguous_npsns' => array_keys($ambiguous), 'duplicate_candidate_rows' => $duplicates,
            'duplicate_candidate_groups' => count(array_filter($fingerprints, fn (int $count) => $count > 1)),
            'normalization' => ['employment_status_pppk_tahap_ii' => count(array_filter($records, fn ($r) => $r['employment_status'] === 'PPPK Tahap II'))],
        ];
    }
}
