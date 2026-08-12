<?php

namespace App\Services;

class TeacherDataNormalizer
{
    public static function nullable(mixed $value): ?string
    {
        $value = trim(preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\s]+/u', ' ', (string) $value) ?? '');

        return in_array(mb_strtolower($value), ['', '-', 'null', 'n/a', 'na'], true) ? null : $value;
    }

    public static function date(mixed $value): ?string
    {
        $value = self::nullable($value);

        return $value === null || $value === '1900-01-01' ? null : $value;
    }

    public static function employmentStatus(?string $value): ?string
    {
        return match (mb_strtoupper(self::nullable($value) ?? '')) {
            'PPPK TAHAP 2', 'PPPK TAHAP II' => 'PPPK Tahap II',
            'PNS' => 'PNS', 'CPNS' => 'CPNS', 'PPPK' => 'PPPK',
            default => self::nullable($value),
        };
    }

    public static function canonical(?string $value): ?string
    {
        $value = self::nullable($value);

        return $value === null ? null : mb_convert_case(mb_strtolower($value), MB_CASE_TITLE, 'UTF-8');
    }

    public static function fingerprint(array $parts): ?string
    {
        $parts = array_filter(array_map(self::nullable(...), $parts));

        return $parts === [] ? null : hash('sha256', implode('|', array_map(mb_strtolower(...), $parts)));
    }
}
