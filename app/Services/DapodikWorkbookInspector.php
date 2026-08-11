<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class DapodikWorkbookInspector
{
    private const HEADER_ALIASES = [
        'education_levels' => ['jenjang', 'bentuk pendidikan', 'bentuk_pendidikan'],
        'statuses' => ['status', 'status sekolah', 'status_sekolah'],
        'districts' => ['distrik', 'kecamatan', 'nama distrik', 'nama kecamatan'],
    ];

    /** @return array<string, mixed> */
    public function inspect(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File workbook tidak ditemukan: {$path}");
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $workbook = $reader->load($path);
        $sheets = [];

        foreach ($workbook->getWorksheetIterator() as $worksheet) {
            $sheets[] = $this->inspectSheet($worksheet);
        }

        $result = [
            'file' => $path,
            'sheets' => $sheets,
            'school_sheets' => array_values(array_column(array_filter($sheets, fn (array $sheet) => $sheet['is_school_data']), 'name')),
            'aggregate_sheets' => array_values(array_column(array_filter($sheets, fn (array $sheet) => $sheet['is_aggregate']), 'name')),
            'possible_duplicates' => $this->findPossibleDuplicates($sheets),
            'structure_differences' => $this->compareEducationStructures($sheets),
            'unique_values' => $this->combineUniqueValues($sheets),
        ];

        $workbook->disconnectWorksheets();

        return $result;
    }

    /** @return array<string, mixed> */
    private function inspectSheet(Worksheet $worksheet): array
    {
        $rows = $worksheet->toArray(null, true, true, true);
        $rows = array_filter($rows, fn (array $row) => $this->hasValue($row));
        $headerRowNumber = $this->detectHeaderRow($rows);
        [$headersByColumn, $lastHeaderRow] = $headerRowNumber === null
            ? [[], null]
            : $this->buildHeaders($worksheet, $rows, $headerRowNumber);

        $dataRows = array_filter(
            $rows,
            fn (array $row, int $rowNumber) => $lastHeaderRow !== null
                && $rowNumber > $lastHeaderRow
                && $this->hasValue($row),
            ARRAY_FILTER_USE_BOTH,
        );
        $normalizedHeaders = array_map($this->normalize(...), array_values($headersByColumn));
        $name = $worksheet->getTitle();
        $schoolScore = $this->schoolDataScore($normalizedHeaders);
        $isSchoolData = $this->containsAny($normalizedHeaders, ['npsn']) && $schoolScore >= 3;
        $isAggregate = ! $isSchoolData && (
            preg_match('/rekap|agregat|summary|ringkasan|jumlah/i', $name) === 1
            || count($dataRows) > 0
        );

        return [
            'name' => $name,
            'education_level' => $this->inferEducationLevel($name, $dataRows, $headersByColumn),
            'non_empty_rows' => count($rows),
            'data_rows' => count($dataRows),
            'header_row' => $headerRowNumber,
            'headers' => array_values($headersByColumn),
            'normalized_headers' => $normalizedHeaders,
            'samples' => $isSchoolData ? $this->sampleRows($dataRows, $headersByColumn) : [],
            'is_school_data' => $isSchoolData,
            'is_aggregate' => $isAggregate,
            'unique_values' => $this->uniqueValues($dataRows, $headersByColumn),
            'content_fingerprint' => $this->contentFingerprint($dataRows, $normalizedHeaders),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{0: array<string, string>, 1: int}
     */
    private function buildHeaders(Worksheet $worksheet, array $rows, int $headerRowNumber): array
    {
        $headerRow = $this->expandMergedHeaderRow($worksheet, $rows[$headerRowNumber] ?? [], $headerRowNumber);
        $nextRowNumber = $headerRowNumber + 1;
        $nextRow = $rows[$nextRowNumber] ?? [];
        $hasSecondaryHeader = $this->isSecondaryHeader($nextRow);
        $headers = [];
        $lastParent = '';

        foreach ($headerRow as $column => $value) {
            $parent = $this->clean($value);
            $child = $hasSecondaryHeader ? $this->clean($nextRow[$column] ?? null) : '';

            if ($parent !== '') {
                $lastParent = $parent;
            } elseif ($child !== '') {
                $parent = $lastParent;
            }

            $header = $parent;

            if ($child !== '') {
                $header = $parent === '' ? $child : "{$parent} - {$child}";
            }

            if ($header !== '') {
                $headers[$column] = $header;
            }
        }

        return [$headers, $hasSecondaryHeader ? $nextRowNumber : $headerRowNumber];
    }

    /** @return array<string, string> */
    private function expandMergedHeaderRow(Worksheet $worksheet, array $row, int $rowNumber): array
    {
        $lastColumn = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
        $expanded = [];

        for ($index = 1; $index <= $lastColumn; $index++) {
            $column = Coordinate::stringFromColumnIndex($index);
            $expanded[$column] = $this->clean($row[$column] ?? null);
        }

        foreach ($worksheet->getMergeCells() as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);
            if ($rowNumber < $start[1] || $rowNumber > $end[1]) {
                continue;
            }

            $value = $this->clean($worksheet->getCell([$start[0], $start[1]])->getCalculatedValue());
            for ($columnIndex = $start[0]; $columnIndex <= $end[0]; $columnIndex++) {
                $expanded[Coordinate::stringFromColumnIndex($columnIndex)] = $value;
            }
        }

        return $expanded;
    }

    private function isSecondaryHeader(array $row): bool
    {
        $values = array_values(array_filter(array_map($this->normalize(...), $row), fn (string $value) => $value !== ''));
        if ($values === []) {
            return false;
        }

        $knownSubheaders = ['l', 'p', 'total', 'jml', 'negeri', 'swasta', 'jumlah'];

        return count(array_diff($values, $knownSubheaders)) === 0;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function detectHeaderRow(array $rows): ?int
    {
        $bestRow = null;
        $bestScore = 0;

        foreach (array_slice($rows, 0, 25, true) as $rowNumber => $row) {
            $values = array_values(array_filter(array_map($this->clean(...), $row), fn (string $value) => $value !== ''));
            $normalized = array_map($this->normalize(...), $values);
            $keywordHits = 0;

            foreach ($normalized as $value) {
                if (preg_match('/npsn|sekolah|nama|jenjang|bentuk pendidikan|status|distrik|kecamatan|siswa|guru|rombel|ruang|laboratorium|perpustakaan/', $value)) {
                    $keywordHits++;
                }
            }

            $score = count($values) + ($keywordHits * 5);
            if (count($values) >= 2 && $score > $bestScore) {
                $bestScore = $score;
                $bestRow = (int) $rowNumber;
            }
        }

        return $bestRow;
    }

    /** @param array<int, string> $headers */
    private function schoolDataScore(array $headers): int
    {
        $groups = [
            ['npsn'],
            ['nama sekolah', 'sekolah'],
            ['jenjang', 'bentuk pendidikan'],
            ['status'],
            ['distrik', 'kecamatan'],
            ['siswa', 'peserta didik', 'pd'],
            ['guru', 'tendik', 'ptk'],
        ];

        return count(array_filter($groups, fn (array $needles) => $this->containsAny($headers, $needles)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $headersByColumn
     * @return array<int, array<string, mixed>>
     */
    private function sampleRows(array $rows, array $headersByColumn): array
    {
        $samples = [];
        foreach (array_slice($rows, 0, 3, true) as $row) {
            $sample = [];
            foreach ($headersByColumn as $column => $header) {
                $sample[$header] = $row[$column] ?? null;
            }
            $samples[] = $sample;
        }

        return $samples;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $headersByColumn
     * @return array<string, array<int, string>>
     */
    private function uniqueValues(array $rows, array $headersByColumn): array
    {
        $result = [];
        foreach (self::HEADER_ALIASES as $label => $aliases) {
            $column = $this->findColumn($headersByColumn, $aliases);
            if ($column === null) {
                continue;
            }

            $values = [];
            foreach ($rows as $row) {
                $value = $this->clean($row[$column] ?? null);
                if ($value !== '') {
                    $values[$this->normalize($value)] = $value;
                }
            }
            natcasesort($values);
            $result[$label] = array_values($values);
        }

        return $result;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function inferEducationLevel(string $sheetName, array $rows, array $headersByColumn): ?string
    {
        if (preg_match_all('/(?:PAUD|TK|KB|SD|SMP|SMA|SMK|SLB|SKB|PKBM)/i', $sheetName, $matches)) {
            return implode('/', array_unique(array_map(mb_strtoupper(...), $matches[0])));
        }

        $column = $this->findColumn($headersByColumn, self::HEADER_ALIASES['education_levels']);
        if ($column !== null) {
            $values = array_unique(array_filter(array_map(
                fn (array $row) => $this->clean($row[$column] ?? null),
                $rows,
            )));
            if (count($values) === 1) {
                return mb_strtoupper((string) reset($values));
            }
        }

        return null;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function contentFingerprint(array $rows, array $headers): string
    {
        return hash('sha256', json_encode([$headers, array_values($rows)], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    /** @param array<int, array<string, mixed>> $sheets */
    private function findPossibleDuplicates(array $sheets): array
    {
        $groups = [];
        foreach ($sheets as $sheet) {
            $groups[$sheet['content_fingerprint']][] = $sheet['name'];
        }

        return array_values(array_filter($groups, fn (array $names) => count($names) > 1));
    }

    /** @param array<int, array<string, mixed>> $sheets */
    private function compareEducationStructures(array $sheets): array
    {
        $byLevel = [];
        foreach ($sheets as $sheet) {
            if ($sheet['is_school_data'] && $sheet['education_level'] !== null) {
                $byLevel[$sheet['education_level']] = $sheet['normalized_headers'];
            }
        }

        if (count($byLevel) < 2) {
            return ['common' => [], 'unique_by_level' => [], 'note' => 'Kurang dari dua jenjang teridentifikasi untuk dibandingkan.'];
        }

        $common = array_values(array_intersect(...array_values($byLevel)));
        $unique = [];
        foreach ($byLevel as $level => $headers) {
            $unique[$level] = array_values(array_diff($headers, $common));
        }

        return ['common' => $common, 'unique_by_level' => $unique, 'note' => null];
    }

    /** @param array<int, array<string, mixed>> $sheets */
    private function combineUniqueValues(array $sheets): array
    {
        $combined = [];
        foreach ($sheets as $sheet) {
            foreach ($sheet['unique_values'] as $label => $values) {
                foreach ($values as $value) {
                    $combined[$label][$this->normalize($value)] = $value;
                }
            }
        }
        foreach ($combined as &$values) {
            natcasesort($values);
            $values = array_values($values);
        }

        return $combined;
    }

    /** @param array<string, string> $headersByColumn */
    private function findColumn(array $headersByColumn, array $aliases): ?string
    {
        foreach ($headersByColumn as $column => $header) {
            $normalized = $this->normalize($header);
            foreach ($aliases as $alias) {
                if ($normalized === $alias || str_contains($normalized, $alias)) {
                    return $column;
                }
            }
        }

        return null;
    }

    private function containsAny(array $haystack, array $needles): bool
    {
        foreach ($haystack as $value) {
            foreach ($needles as $needle) {
                if ($value === $needle || str_contains($value, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasValue(array $row): bool
    {
        return count(array_filter($row, fn ($value) => $this->clean($value) !== '')) > 0;
    }

    private function clean(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    }

    private function normalize(mixed $value): string
    {
        return mb_strtolower($this->clean($value));
    }
}
