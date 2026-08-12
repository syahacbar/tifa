<?php

namespace App\Services;

class TifaLocalDataIntentService
{
    /**
     * Parse only unambiguous school-dataset aggregate questions.
     *
     * @return array<string, mixed>|null
     */
    public function parse(string $question): ?array
    {
        $text = mb_strtolower(trim($question));
        if ($this->isSchoolDistrictRanking($text, $question, $match)) {
            return ['action' => 'district_breakdown', 'filters' => ['education_level' => $this->educationLevel($text), 'status' => $this->status($text), 'district' => null], 'options' => ['limit' => (int) $match[1]]];
        }
        if ($this->isSchoolList($question, $text)) {
            return ['action' => 'school_list', 'filters' => ['education_level' => $this->educationLevel($text), 'status' => $this->status($text), 'district' => $this->district($question)]];
        }
        if ($this->isSchoolStatusBreakdown($text)) {
            return ['action' => 'status_breakdown', 'filters' => ['education_level' => $this->educationLevel($text), 'status' => null, 'district' => $this->district($question)]];
        }
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
            preg_match('/\b(?:guru(?:nya)?|ptk|kepala sekolah|penugasan|tenaga\s+pengajar(?:nya)?|para\s+pengajar|pengajar(?:nya)?|pendidik(?:nya)?|tenaga\s+pendidik(?:nya)?)\b/u', $text) !== 1
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
        if (preg_match('/\b(?:di\s+)?distrik\s+([\pL\s]+?)(?:\?|$)/iu', $question, $match)) return trim($match[1]) ?: null;
        if (! preg_match('/\bdi\s+([\pL]+)(?:\?|$)/iu', $question, $match)) return null;

        $district = trim($match[1]);
        return in_array(mb_strtolower($district), ['kabupaten', 'teluk'], true) ? null : ($district ?: null);
    }

    private function isSchoolList(string $question, string $text): bool
    {
        if (preg_match('/\b(?:berapa|jumlah|total|ada\s+berapa)\b/u', $text) === 1) return false;
        if (preg_match('/\b(?:guru|ptk|tenaga\s+pengajar|pendidik)\b/u', $text) === 1) return false;

        $hasSchoolCategory = preg_match('/\bsekolah\b/u', $text) === 1 || $this->educationLevel($text) !== null;
        $hasListSignal = preg_match('/\b(?:tampilkan|sebutkan|daftar|list|apa\s+saja|mana\s+saja)\b/u', $text) === 1;

        $hasScope = $this->district($question) !== null || $this->educationLevel($text) !== null || $this->status($text) !== null;

        return $hasSchoolCategory && $hasScope && ($hasListSignal || $this->district($question) !== null);
    }

    private function isSchoolDistrictRanking(string $text, string $question, ?array &$match): bool
    {
        return preg_match('/\b(?:sebutkan|tampilkan|top)\s+(\d+)\s+distrik\b/u', $text, $match)
            && preg_match('/\bsekolah\b/u', $text) === 1
            && preg_match('/\b(?:terbanyak|teratas)\b/u', $text) === 1;
    }

    private function isSchoolStatusBreakdown(string $text): bool
    {
        return preg_match('/\bnegeri\b/u', $text) === 1 && preg_match('/\bswasta\b/u', $text) === 1
            && preg_match('/\bsekolah\b/u', $text) === 1;
    }
}
