<?php

namespace App\Services;

use App\Models\School;

class TeacherAnalyticsIntentService
{
    /** @return array<string, mixed>|null */
    public function parse(string $question): ?array
    {
        $text = mb_strtolower(trim($question));
        if (! preg_match('/\b(berapa|jumlah|sebaran|bandingkan|tampilkan|mana|sebutkan|terbanyak|top)\b/u', $text) || ! preg_match('/\b(guru|ptk|kepala sekolah|penugasan|assignment|nominatif)\b/u', $text)) return null;
        if (preg_match('/\b(nik|nip|nuptk|nomor hp|telepon|tanggal lahir|alamat)\b/u', $text)) return ['blocked' => 'privacy'];
        $filters = array_fill_keys(['education_level','district','school_id','employment_status','ptk_type','ptk_position','education'], null);
        foreach (['SD','SMP','SMA','SMK','PAUD','SKB'] as $level) if (preg_match('/\b'.mb_strtolower($level).'\b/u', $text)) $filters['education_level'] = $level;
        if (str_contains($text, 'pppk')) $filters['employment_status'] = str_contains($text, 'tahap ii') ? 'PPPK Tahap II' : 'PPPK';
        elseif (preg_match('/\bpns\b/u', $text)) $filters['employment_status'] = 'PNS';
        if (str_contains($text, 'kepala sekolah')) $filters['ptk_type'] = 'Kepala Sekolah';
        if (preg_match('/\b(s1|s2|d1|d2|d3|d4)\b/u', $text, $match)) $filters['education'] = mb_strtoupper($match[1]);
        if (preg_match('/\bdi\s+distrik\s+([\pL\s]+?)(?:\?|$|\b(guru|ptk|dengan)\b)/u', $text, $match)) $filters['district'] = trim($match[1]);
        $school = $this->school($text);
        if ($school) { $filters['school_id'] = $school->id; $filters['education_level'] = null; }
        $group = null; $top = null;
        if (preg_match('/(per|berdasarkan)\s+distrik/u', $text) || str_contains($text, 'distrik mana') || (str_contains($text, 'distrik') && (str_contains($text, 'terbanyak') || str_contains($text, 'paling banyak')))) $group = 'district';
        elseif (str_contains($text, 'bandingkan') && (str_contains($text, 'pns') || str_contains($text, 'pppk'))) { $group = 'employment_status'; $filters['employment_status'] = null; }
        elseif (preg_match('/(per|berdasarkan)\s+jenjang/u', $text)) $group = 'education_level';
        elseif (preg_match('/(per|berdasarkan)\s+jenis ptk/u', $text)) $group = 'ptk_type';
        elseif (preg_match('/(per|berdasarkan)\s+sekolah/u', $text) || (str_contains($text, 'sekolah') && (str_contains($text, 'terbanyak') || str_contains($text, 'paling banyak')))) $group = 'school';
        $hasRankCount = preg_match('/\b(top|sebutkan)\s*(\d+)?/u', $text, $rankMatch) === 1;
        if ($group && (str_contains($text, 'terbanyak') || str_contains($text, 'paling banyak') || $hasRankCount)) $top = isset($rankMatch[2]) && $rankMatch[2] !== '' ? min(20, (int) $rankMatch[2]) : 1;
        return ['metric' => preg_match('/\b(penugasan|assignment|record nominatif)\b/u', $text) ? 'assignment_count' : 'unique_teacher_count', 'filters' => $filters, 'group_by' => $group, 'top_n' => $top, 'confidence' => 'deterministic'];
    }

    private function school(string $text): ?School
    {
        if (! preg_match('/\bdi\s+((?:sd|smp|sma|smk)[\pL\pN\s.\-]+?)(?:\?|$)/u', $text, $match)) return null;
        $name = $this->normalize($match[1]);
        $matches = School::query()->get()->filter(fn (School $school) => $this->normalize($school->name) === $name);
        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace(['smpn', 'sman', 'smkn', 'sdn'], ['smp negeri', 'sma negeri', 'smk negeri', 'sd negeri'], $name);
        return trim(preg_replace('/[^\pL\pN]+/u', ' ', $name) ?? '');
    }
}
