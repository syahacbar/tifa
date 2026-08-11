<?php

namespace App\Services;

use App\Models\Dataset;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class DapodikImportService
{
    public const DATASET_NAME = 'Rekap Dapodik Juni 2026';

    public const REFERENCE_PERIOD = 'Semester 2 Tahun Pelajaran 2025/2026';

    private const IGNORED_SHEETS = ['REKAP', 'SD (2)'];

    /** @var array<int, string> */
    private array $npsnCollisions = [];

    /** @var array<int, string> */
    private array $sourceDuplicates = [];

    /** @return array<string, mixed> */
    public function import(string $path, bool $dryRun = false): array
    {
        $schools = $this->readSchools($path);
        $summary = $this->summarize($schools);
        $summary['dry_run'] = $dryRun;
        $summary['dataset'] = self::DATASET_NAME;
        $summary['reference_period'] = self::REFERENCE_PERIOD;
        $summary['npsn_collisions'] = $this->npsnCollisions;
        $summary['source_duplicates_skipped'] = count($this->sourceDuplicates);
        $summary['source_duplicates'] = $this->sourceDuplicates;

        if ($dryRun) {
            return $summary;
        }

        return DB::transaction(function () use ($path, $schools, $summary): array {
            $dataset = Dataset::query()->firstOrCreate(
                [
                    'name' => self::DATASET_NAME,
                    'reference_period' => self::REFERENCE_PERIOD,
                ],
                [
                    'source_organization' => 'Dinas Pendidikan Kabupaten Teluk Bintuni',
                    'published_at' => '2026-06-30',
                    'description' => 'Rekap data Dapodik Juni 2026.',
                    'is_active' => true,
                ],
            );

            $dataset->update([
                'metadata' => [
                    'source_file' => basename($path),
                    'imported_sheets' => array_values(array_unique(array_column($schools, '_sheet'))),
                ],
                'is_active' => true,
            ]);

            $created = 0;
            $updated = 0;
            foreach ($schools as $school) {
                unset($school['_sheet'], $school['_row']);
                $model = $dataset->schools()->updateOrCreate(
                    ['source_key' => $school['source_key']],
                    $school,
                );
                $model->wasRecentlyCreated ? $created++ : $updated++;
            }

            return [
                ...$summary,
                'dataset_id' => $dataset->id,
                'created' => $created,
                'updated' => $updated,
            ];
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function readSchools(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File workbook tidak ditemukan: {$path}");
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $workbook = $reader->load($path);
        $schools = [];
        $seenNpsn = [];
        $seenSourceKeys = [];
        $this->npsnCollisions = [];
        $this->sourceDuplicates = [];

        foreach ($workbook->getWorksheetIterator() as $worksheet) {
            if (in_array(mb_strtoupper(trim($worksheet->getTitle())), self::IGNORED_SHEETS, true)) {
                continue;
            }

            foreach ($this->readSheet($worksheet) as $school) {
                $location = "{$school['_sheet']}:{$school['_row']}";
                $sourceKey = $school['source_key'];
                if (isset($seenSourceKeys[$sourceKey])) {
                    $this->sourceDuplicates[] = "{$school['name']} ({$seenSourceKeys[$sourceKey]} dan {$location})";

                    continue;
                }

                $npsn = $school['npsn'];
                if (isset($seenNpsn[$npsn])) {
                    $this->npsnCollisions[] = "{$npsn} ({$seenNpsn[$npsn]} dan {$location})";
                }

                $seenNpsn[$npsn] ??= $location;
                $seenSourceKeys[$sourceKey] = $location;
                $schools[] = $school;
            }
        }

        $workbook->disconnectWorksheets();

        return $schools;
    }

    /** @return array<int, array<string, mixed>> */
    private function readSheet(Worksheet $worksheet): array
    {
        $rows = $worksheet->toArray(null, true, true, true);
        $headerRowNumber = $this->findHeaderRow($rows);
        if ($headerRowNumber === null) {
            return [];
        }

        $header = $rows[$headerRowNumber];
        $columns = $this->mapColumns($header, $rows[$headerRowNumber + 1] ?? []);
        $dataStartsAt = $columns['_has_subheader'] ? $headerRowNumber + 2 : $headerRowNumber + 1;
        unset($columns['_has_subheader']);
        $schools = [];

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber < $dataStartsAt || ! $this->hasValue($row)) {
                continue;
            }

            $npsn = $this->normalizeNpsn($row[$columns['npsn']] ?? null);
            if ($npsn === '') {
                continue;
            }
            if (preg_match('/^[A-Z0-9]{8}$/', $npsn) !== 1) {
                throw new RuntimeException("NPSN tidak valid pada {$worksheet->getTitle()}:{$rowNumber}: {$npsn}");
            }

            $male = $this->normalizeNumber($row[$columns['students_male']] ?? 0);
            $female = $this->normalizeNumber($row[$columns['students_female']] ?? 0);
            $reportedTotal = $this->normalizeNumber($row[$columns['students_total']] ?? 0);

            $school = [
                '_sheet' => $worksheet->getTitle(),
                '_row' => $rowNumber,
                'npsn' => $npsn,
                'name' => $this->normalizeText($row[$columns['name']] ?? null),
                'education_level' => $this->normalizeEducationLevel($row[$columns['education_level']] ?? null),
                'district' => $this->normalizeDistrict($row[$columns['district']] ?? null),
                'status' => $this->normalizeStatus($row[$columns['status']] ?? null),
                'students_male' => $male,
                'students_female' => $female,
                'students_total' => $reportedTotal > 0 ? $reportedTotal : $male + $female,
                'study_groups' => $this->normalizeNumber($row[$columns['study_groups']] ?? 0),
                'teachers' => $this->normalizeNumber($row[$columns['teachers']] ?? 0),
                'education_staff' => $this->normalizeNumber($row[$columns['education_staff']] ?? 0),
                'classrooms' => $this->normalizeNumber($row[$columns['classrooms']] ?? 0),
                'laboratories' => $this->normalizeNumber($row[$columns['laboratories']] ?? 0),
                'libraries' => $this->normalizeNumber($row[$columns['libraries']] ?? 0),
            ];
            $school['source_key'] = School::sourceKeyFor(
                $school['education_level'],
                $school['name'],
                $school['district'],
            );
            $schools[] = $school;
        }

        return $schools;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function findHeaderRow(array $rows): ?int
    {
        foreach (array_slice($rows, 0, 25, true) as $rowNumber => $row) {
            $headers = array_map($this->normalizeKey(...), $row);
            if (in_array('npsn', $headers, true) && in_array('nama sekolah', $headers, true)) {
                return (int) $rowNumber;
            }
        }

        return null;
    }

    /** @return array<string, string|bool> */
    private function mapColumns(array $header, array $subheader): array
    {
        $columns = [];
        foreach ($header as $column => $value) {
            $key = $this->normalizeKey($value);
            $mapped = match ($key) {
                'nama sekolah' => 'name',
                'distrik', 'kecamatan' => 'district',
                'npsn' => 'npsn',
                'bp', 'jenjang', 'bentuk pendidikan' => 'education_level',
                'status' => 'status',
                'rombel' => 'study_groups',
                'guru' => 'teachers',
                'tendik' => 'education_staff',
                'r kelas', 'ruang kelas' => 'classrooms',
                'r lab', 'laboratorium' => 'laboratories',
                'r perpus', 'perpustakaan' => 'libraries',
                default => null,
            };
            if ($mapped !== null) {
                $columns[$mapped] = $column;
            }
        }

        $subheaders = array_map($this->normalizeKey(...), $subheader);
        $columns['_has_subheader'] = count(array_intersect($subheaders, ['l', 'p', 'total', 'jml'])) >= 2;
        if ($columns['_has_subheader']) {
            foreach ($subheaders as $column => $key) {
                match ($key) {
                    'l' => $columns['students_male'] = $column,
                    'p' => $columns['students_female'] = $column,
                    'total', 'jml' => $columns['students_total'] = $column,
                    default => null,
                };
            }
        }

        $required = ['name', 'district', 'npsn', 'education_level', 'status'];
        $missing = array_diff($required, array_keys($columns));
        if ($missing !== []) {
            throw new RuntimeException('Kolom wajib tidak ditemukan: '.implode(', ', $missing));
        }

        return $columns;
    }

    /** @param array<int, array<string, mixed>> $schools */
    private function summarize(array $schools): array
    {
        $byEducationLevel = [];
        foreach ($schools as $school) {
            $byEducationLevel[$school['education_level']] = ($byEducationLevel[$school['education_level']] ?? 0) + 1;
        }
        ksort($byEducationLevel, SORT_NATURAL);

        return [
            'total' => count($schools),
            'by_education_level' => $byEducationLevel,
        ];
    }

    private function normalizeNpsn(mixed $value): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', '', $this->normalizeText($value)) ?? '');
    }

    private function normalizeEducationLevel(mixed $value): string
    {
        $value = mb_strtoupper($this->normalizeText($value));

        return match ($value) {
            'PAUD/TK', 'TAMAN KANAK-KANAK' => 'TK',
            'SEKOLAH DASAR' => 'SD',
            'SEKOLAH MENENGAH PERTAMA' => 'SMP',
            'SEKOLAH MENENGAH ATAS' => 'SMA',
            'SEKOLAH MENENGAH KEJURUAN' => 'SMK',
            default => $value,
        };
    }

    private function normalizeStatus(mixed $value): string
    {
        return match (mb_strtolower($this->normalizeText($value))) {
            'negeri', 'n' => 'Negeri',
            'swasta', 's' => 'Swasta',
            default => mb_convert_case($this->normalizeText($value), MB_CASE_TITLE, 'UTF-8'),
        };
    }

    private function normalizeDistrict(mixed $value): string
    {
        return mb_convert_case(mb_strtolower($this->normalizeText($value)), MB_CASE_TITLE, 'UTF-8');
    }

    private function normalizeNumber(mixed $value): int
    {
        if (is_int($value) || is_float($value)) {
            return max(0, (int) round($value));
        }

        $digits = preg_replace('/[^0-9-]/', '', $this->normalizeText($value));

        return max(0, (int) ($digits === '' ? 0 : $digits));
    }

    private function normalizeText(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) ($value ?? '')) ?? '');
    }

    private function normalizeKey(mixed $value): string
    {
        $value = mb_strtolower($this->normalizeText($value));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '');
    }

    private function hasValue(array $row): bool
    {
        return count(array_filter($row, fn ($value) => $this->normalizeText($value) !== '')) > 0;
    }
}
