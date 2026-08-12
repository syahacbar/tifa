<?php

namespace App\Services;

use App\Models\School;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class TeacherWorkbookInspector
{
    private const FIELD_ALIASES = [
        'employment_statuses' => ['status kepegawaian', 'status pegawai'],
        'ptk_types' => ['jenis ptk', 'jenis tenaga ptk', 'jenis'],
        'ptk_positions' => ['jabatan ptk', 'jabatan'],
        'educations' => ['pendidikan terakhir', 'pendidikan'],
        'districts' => ['kecamatan', 'distrik'],
        'npsn' => ['npsn'],
        'nik' => ['nik'],
        'nip' => ['nip'],
        'nuptk' => ['nuptk'],
        'name' => ['nama ptk', 'nama lengkap', 'nama'],
    ];

    private const IDENTIFIER_HEADER_PATTERN = '/\b(nik|nip|nuptk|hp|no\.?\s*hp|telepon|handphone|whatsapp)\b/i';

    private const PERSONAL_HEADER_PATTERN = '/\b(nama|tempat lahir|tanggal lahir)\b/i';

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

        $allRows = array_merge(...array_map(fn (array $sheet) => $sheet['raw_rows'], $sheets));
        $allHeaders = array_merge(...array_map(fn (array $sheet) => $sheet['headers_by_column'], $sheets));
        $npsns = $this->uniqueValues($allRows, $allHeaders, 'npsn');
        $existingNpsns = School::query()->whereIn('npsn', $npsns)->pluck('npsn')->map($this->normalize(...))->all();
        $existingLookup = array_fill_keys($existingNpsns, true);
        $unmatchedNpsns = array_values(array_filter($npsns, fn (string $npsn) => ! isset($existingLookup[$this->normalize($npsn)])));

        $result = [
            'file' => $path,
            'sheets' => $sheets,
            'unique_values' => [
                'employment_statuses' => $this->uniqueValues($allRows, $allHeaders, 'employment_statuses'),
                'ptk_types' => $this->uniqueValues($allRows, $allHeaders, 'ptk_types'),
                'ptk_positions' => $this->uniqueValues($allRows, $allHeaders, 'ptk_positions'),
                'educations' => $this->uniqueValues($allRows, $allHeaders, 'educations'),
                'districts' => $this->uniqueValues($allRows, $allHeaders, 'districts'),
            ],
            'unique_npsn_count' => count($npsns),
            'unmatched_npsns' => $unmatchedNpsns,
            'possible_duplicates' => $this->possibleDuplicates($allRows, $allHeaders),
            'empty_identifiers' => [
                'nip' => $this->emptyCount($allRows, $allHeaders, 'nip'),
                'nuptk' => $this->emptyCount($allRows, $allHeaders, 'nuptk'),
            ],
            'placeholder_values' => $this->placeholderValues($sheets),
            'structure_differences' => $this->structureDifferences($sheets),
        ];

        $workbook->disconnectWorksheets();

        return $result;
    }

    /** @return array<string, mixed> */
    private function inspectSheet(Worksheet $worksheet): array
    {
        $rows = $worksheet->toArray(null, true, true, true);
        $rows = array_filter($rows, fn (array $row) => $this->hasValue($row));
        $headerRow = $this->detectHeaderRow($rows);
        $headers = $headerRow === null ? [] : $this->headers($worksheet, $rows[$headerRow] ?? []);
        $dataRows = $headerRow === null ? [] : array_values(array_filter(
            $rows,
            fn (array $row, int $rowNumber) => $rowNumber > $headerRow && $this->hasValue($row),
            ARRAY_FILTER_USE_BOTH,
        ));

        return [
            'name' => $worksheet->getTitle(),
            'non_empty_rows' => count($rows),
            'data_rows' => count($dataRows),
            'header_row' => $headerRow,
            'headers' => array_values($headers),
            'normalized_headers' => array_values(array_map($this->normalize(...), $headers)),
            'headers_by_column' => $headers,
            'samples' => $this->safeSamples($dataRows, $headers),
            'raw_rows' => $dataRows,
            'placeholder_values' => $this->placeholderValuesForRows($dataRows, $headers),
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function detectHeaderRow(array $rows): ?int
    {
        $bestRow = null;
        $bestScore = 0;
        foreach (array_slice($rows, 0, 30, true) as $number => $row) {
            $values = array_values(array_filter(array_map($this->clean(...), $row), fn (string $value) => $value !== ''));
            $keywords = preg_match_all('/nama|nik|nip|nuptk|ptk|jabatan|pendidikan|sekolah|npsn|kecamatan|distrik|status/i', implode(' ', $values));
            $score = count($values) + ($keywords * 5);
            if (count($values) >= 2 && $score > $bestScore) {
                $bestScore = $score;
                $bestRow = (int) $number;
            }
        }

        return $bestRow;
    }

    /** @return array<string, string> */
    private function headers(Worksheet $worksheet, array $row): array
    {
        $headers = [];
        $lastColumn = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
        for ($index = 1; $index <= $lastColumn; $index++) {
            $column = Coordinate::stringFromColumnIndex($index);
            $header = $this->clean($row[$column] ?? null);
            if ($header !== '') {
                $headers[$column] = $header;
            }
        }

        return $headers;
    }

    /** @param array<int, array<string, mixed>> $rows
     * @param  array<string, string>  $headers
     * @return array<int, array<string, string|null>>
     */
    private function safeSamples(array $rows, array $headers): array
    {
        return array_map(function (array $row) use ($headers): array {
            $sample = [];
            foreach ($headers as $column => $header) {
                $value = $this->clean($row[$column] ?? null);
                $sample[$header] = match (true) {
                    preg_match(self::IDENTIFIER_HEADER_PATTERN, $header) === 1 => $this->mask($value),
                    preg_match(self::PERSONAL_HEADER_PATTERN, $header) === 1 => $value === '' ? null : '(disamarkan)',
                    default => $value === '' ? null : $value,
                };
            }

            return $sample;
        }, array_slice($rows, 0, 3));
    }

    /** @param array<int, array<string, mixed>> $rows
     * @param  array<string, string>  $headers
     * @return array<int, string>
     */
    private function uniqueValues(array $rows, array $headers, string $field): array
    {
        $columns = $this->findColumns($headers, self::FIELD_ALIASES[$field]);
        $values = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $value = $this->clean($row[$column] ?? null);
                if (! $this->isEmptyOrPlaceholder($value)) {
                    $values[$this->normalize($value)] = $value;
                }
            }
        }
        natcasesort($values);

        return array_values($values);
    }

    /** @param array<int, array<string, mixed>> $rows
     * @param  array<string, string>  $headers
     */
    private function emptyCount(array $rows, array $headers, string $field): int
    {
        $columns = $this->findColumns($headers, self::FIELD_ALIASES[$field]);
        if ($columns === []) {
            return 0;
        }

        return count(array_filter($rows, fn (array $row) => $this->isEmptyOrPlaceholder($this->clean($row[$columns[0]] ?? null))));
    }

    /** @param array<int, array<string, mixed>> $rows
     * @param  array<string, string>  $headers
     * @return array<int, array<string, mixed>>
     */
    private function possibleDuplicates(array $rows, array $headers): array
    {
        $identifierColumns = array_merge(
            $this->findColumns($headers, self::FIELD_ALIASES['nik']),
            $this->findColumns($headers, self::FIELD_ALIASES['nip']),
            $this->findColumns($headers, self::FIELD_ALIASES['nuptk']),
        );
        $nameColumns = $this->findColumns($headers, self::FIELD_ALIASES['name']);
        $groups = [];

        foreach ($rows as $row) {
            $identifier = '';
            foreach ($identifierColumns as $column) {
                $value = $this->clean($row[$column] ?? null);
                if (! $this->isEmptyOrPlaceholder($value)) {
                    $identifier = $value;
                    break;
                }
            }
            if ($identifier === '') {
                continue;
            }
            $name = $nameColumns === [] ? '' : $this->clean($row[$nameColumns[0]] ?? null);
            $key = $this->normalize($identifier).'|'.$this->normalize($name);
            $groups[$key]['identifier'] = $this->mask($identifier);
            $groups[$key]['total'] = ($groups[$key]['total'] ?? 0) + 1;
        }

        return array_values(array_filter($groups, fn (array $group) => $group['total'] > 1));
    }

    /** @param array<int, array<string, mixed>> $sheets */
    private function placeholderValues(array $sheets): array
    {
        $result = [];
        foreach ($sheets as $sheet) {
            foreach ($sheet['placeholder_values'] as $header => $count) {
                $result[$sheet['name'].' — '.$header] = $count;
            }
        }

        return $result;
    }

    /** @param array<int, array<string, mixed>> $rows
     * @param  array<string, string>  $headers
     * @return array<string, int>
     */
    private function placeholderValuesForRows(array $rows, array $headers): array
    {
        $counts = [];
        foreach ($headers as $column => $header) {
            $count = count(array_filter($rows, fn (array $row) => $this->isPlaceholder($this->clean($row[$column] ?? null))));
            if ($count > 0) {
                $counts[$header] = $count;
            }
        }

        return $counts;
    }

    /** @param array<int, array<string, mixed>> $sheets */
    private function structureDifferences(array $sheets): array
    {
        $headersBySheet = array_column($sheets, 'normalized_headers', 'name');
        if (count($headersBySheet) < 2) {
            return ['common' => [], 'unique_by_sheet' => [], 'note' => 'Hanya satu sheet yang dapat dibandingkan.'];
        }
        $common = array_values(array_intersect(...array_values($headersBySheet)));
        $unique = [];
        foreach ($headersBySheet as $sheet => $headers) {
            $unique[$sheet] = array_values(array_diff($headers, $common));
        }

        return ['common' => $common, 'unique_by_sheet' => $unique, 'note' => null];
    }

    /** @param array<string, string> $headers
     * @param  array<int, string>  $aliases
     * @return array<int, string>
     */
    private function findColumns(array $headers, array $aliases): array
    {
        $exact = array_keys(array_filter($headers, function (string $header) use ($aliases): bool {
            $normalized = $this->normalize($header);

            return in_array($normalized, $aliases, true);
        }));

        if ($exact !== []) {
            return $exact;
        }

        return array_keys(array_filter($headers, function (string $header) use ($aliases): bool {
            $normalized = $this->normalize($header);

            foreach ($aliases as $alias) {
                if ($normalized === $alias || str_contains($normalized, $alias)) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function mask(string $value): string
    {
        if ($value === '') {
            return '(kosong)';
        }

        $length = mb_strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, 2).str_repeat('*', max(4, $length - 4)).mb_substr($value, -2);
    }

    private function isEmptyOrPlaceholder(string $value): bool
    {
        return $value === '' || $this->isPlaceholder($value);
    }

    private function isPlaceholder(string $value): bool
    {
        return in_array($this->normalize($value), ['null', '1900-01-01'], true);
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

        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', (string) $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function normalize(mixed $value): string
    {
        return mb_strtolower($this->clean($value));
    }
}
