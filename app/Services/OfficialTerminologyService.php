<?php

namespace App\Services;

class OfficialTerminologyService
{
    /** @var array<string, string> */
    private const TERMS = [
        'PNS' => 'Pegawai Negeri Sipil', 'PPPK' => 'Pegawai Pemerintah dengan Perjanjian Kerja',
        'NPSN' => 'Nomor Pokok Sekolah Nasional', 'NUPTK' => 'Nomor Unik Pendidik dan Tenaga Kependidikan',
        'PTK' => 'Pendidik dan Tenaga Kependidikan', 'DAPODIK' => 'Data Pokok Pendidikan',
    ];

    /** @return array<string, string> */
    public function foundIn(string $question): array
    {
        return array_filter(self::TERMS, fn ($full, $term) => preg_match('/\b'.preg_quote($term, '/').'\b/ui', $question) === 1, ARRAY_FILTER_USE_BOTH);
    }

    public function directDefinition(string $question): ?string
    {
        if (! preg_match('/\b(apa itu|kepanjangan)\b/ui', $question)) return null;
        $terms = $this->foundIn($question);
        if (count($terms) !== 1) return null;
        $term = array_key_first($terms);
        return "{$term} adalah {$terms[$term]}.";
    }

    public function promptContext(string $question): string
    {
        $terms = $this->foundIn($question);
        if ($terms === []) return 'Tidak ada istilah glossary yang relevan.';
        return implode('; ', array_map(fn ($term, $full) => "{$term} = {$full}", array_keys($terms), array_values($terms)));
    }
}
