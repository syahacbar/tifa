<?php

namespace App\Services;

class TifaLocalDataIntentService
{
    /**
     * Parse only unambiguous school-dataset aggregate questions.
     *
     * @return array{action: string, filters: array{education_level: ?string, status: ?string, district: ?string}}|null
     */
    public function parse(string $question): ?array
    {
        $text = mb_strtolower(trim($question));
        if (! preg_match('/\b(berapa|jumlah|total)\b/u', $text)) {
            return null;
        }

        $action = $this->action($text);
        if ($action === null) {
            return null;
        }

        return [
            'action' => $action,
            'filters' => [
                'education_level' => $this->educationLevel($text),
                'status' => $this->status($text),
                'district' => $this->district($question),
            ],
        ];
    }

    private function action(string $text): ?string
    {
        return match (true) {
            preg_match('/\b(siswa laki|siswa pria|murid laki)\b/u', $text) === 1 => 'student_male_total',
            preg_match('/\b(siswa perempuan|siswi|murid perempuan)\b/u', $text) === 1 => 'student_female_total',
            preg_match('/\b(siswa|murid)\b/u', $text) === 1 => 'student_total',
            preg_match('/\b(laboratorium|lab)\b/u', $text) === 1 => 'laboratory_total',
            preg_match('/\b(perpustakaan)\b/u', $text) === 1 => 'library_total',
            preg_match('/\b(ruang kelas|kelas)\b/u', $text) === 1 => 'classroom_total',
            preg_match('/\b(rombongan belajar|rombel)\b/u', $text) === 1 => 'study_group_total',
            preg_match('/\b(tenaga kependidikan|tendik)\b/u', $text) === 1 => 'education_staff_total',
            // "guru sekolah negeri" refers to the active school aggregate, not a teacher identity query.
            preg_match('/\b(guru)\b/u', $text) === 1 && preg_match('/\bsekolah\b/u', $text) === 1 => 'teacher_total',
            preg_match('/\b(guru|ptk|kepala sekolah|penugasan)\b/u', $text) !== 1
                && preg_match('/\b(sekolah|sd|smp|sma|smk|tk|kb|pkbm|skb)\b/u', $text) === 1 => 'school_count',
            default => null,
        };
    }

    private function educationLevel(string $text): ?string
    {
        foreach (['SD', 'SMP', 'SMA', 'SMK', 'TK', 'KB', 'PKBM', 'SKB'] as $level) {
            if (preg_match('/\b'.mb_strtolower($level).'\b/u', $text)) {
                return $level;
            }
        }

        return null;
    }

    private function status(string $text): ?string
    {
        return preg_match('/\bnegeri\b/u', $text) ? 'NEGERI' : (preg_match('/\bswasta\b/u', $text) ? 'SWASTA' : null);
    }

    private function district(string $question): ?string
    {
        if (! preg_match('/\b(?:di\s+)?distrik\s+([\pL\s]+?)(?:\?|$)/iu', $question, $match)) {
            return null;
        }

        return trim($match[1]) ?: null;
    }
}
