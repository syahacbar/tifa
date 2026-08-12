<?php

namespace App\Services;

class TifaResponseFormatter
{
    /** @param array<string, mixed> $data */
    public function formatTeacher(array $data): string
    {
        $source = 'Berdasarkan data pendidikan terintegrasi Kabupaten Teluk Bintuni periode '.$data['batch']['source_period'];
        $metric = $data['metric'] === 'unique_teacher_count' ? 'guru' : 'penugasan guru';
        if (isset($data['value'])) return $source.', terdapat '.number_format($data['value'], 0, ',', '.').' '.$this->teacherSubject($metric, $data['filters']).'.';
        $dimension = ['district' => 'distrik', 'employment_status' => 'status kepegawaian', 'education_level' => 'jenjang', 'ptk_type' => 'jenis PTK', 'school' => 'sekolah'][$data['group_by']] ?? $data['group_by'];
        $records = $data['data']['records'] ?? [];
        if (($data['visualization'] ?? '') === 'bar_chart' && $records !== []) {
            if (count($records) === 1) {
                $top = $records[0];
                return $source.', '.$dimension.' dengan '.$metric.' terbanyak adalah '.$top['label'].' sebanyak '.number_format($top['value'], 0, ',', '.').'.';
            }
            return $this->withTeacherQuality($source.', berikut '.count($records).' '.$dimension.' dengan '.$metric.' terbanyak: '.$this->rows($records, $metric).'.', $data);
        }
        if ($data['group_by'] === 'employment_status' && count($records) === 2) {
            return $source.', terdapat '.$this->rows($records, $metric).'.';
        }
        return $this->withTeacherQuality($source.', berikut sebaran '.$metric.' berdasarkan '.$dimension.': '.$this->rows($records, $metric).'.', $data);
    }

    /** @param array<string, mixed> $data */
    private function withTeacherQuality(string $answer, array $data): string
    {
        return ($data['group_by'] ?? null) === 'school' && ! ($data['quality']['complete_for_requested_dimension'] ?? true)
            ? $answer.' Catatan: statistik per sekolah belum mencakup beberapa penugasan yang belum terhubung ke sekolah master.' : $answer;
    }

    /** @param array<string, mixed> $filters */
    private function teacherSubject(string $metric, array $filters): string
    {
        $parts = [$metric];
        if ($filters['ptk_type'] === 'Kepala Sekolah') $parts = ['kepala sekolah'];
        if ($filters['employment_status']) $parts[] = $filters['employment_status'];
        if ($filters['education_level']) $parts[] = 'pada jenjang '.$filters['education_level'];
        if ($filters['education']) $parts[] = 'berpendidikan '.$filters['education'];
        if ($filters['district']) $parts[] = 'di Distrik '.mb_convert_case(mb_strtolower($filters['district']), MB_CASE_TITLE, 'UTF-8');
        if ($filters['school_id']) {
            $school = \App\Models\School::find($filters['school_id']);
            if ($school) $parts[] = 'di '.$school->name;
        }
        return implode(' ', $parts);
    }

    /** @param array<int, array{label:string,value:int}> $rows */
    private function rows(array $rows, string $metric): string
    {
        return implode('; ', array_map(fn ($row) => $row['label'].' '.number_format($row['value'], 0, ',', '.').' '.$metric, $rows));
    }
    /** @param array<string, mixed> $intent
     * @param  array<string, mixed>  $data
     */
    public function formatAnalytic(array $intent, array $data): string
    {
        $total = count($data['data']['records'] ?? []);

        return match ($intent['action']) {
            'school_list' => "Ditemukan {$total} sekolah sesuai filter yang dipilih.",
            'school_ranking' => "Berikut {$total} sekolah teratas berdasarkan {$data['data']['ranking_by']}.",
            'district_breakdown' => 'Berikut sebaran sekolah berdasarkan distrik.',
            'education_level_breakdown' => 'Berikut sebaran sekolah berdasarkan jenjang pendidikan.',
            'status_breakdown' => 'Berikut perbandingan sekolah negeri dan swasta.',
        };
    }

    /**
     * @param  array{action: string, filters: array{education_level: ?string, status: ?string, district: ?string}}  $intent
     * @param  array<string, mixed>  $data
     */
    public function format(array $intent, array $data): string
    {
        $metric = $this->metricLabel($intent['action']);
        $subject = $this->subject($metric, $intent['filters']);
        $source = preg_replace('/^Rekap\s+/iu', 'Data ', (string) $data['dataset']['name']) ?: $data['dataset']['name'];
        $value = number_format((int) $data['value'], 0, ',', '.');

        return "Berdasarkan {$source}, terdapat {$value} {$subject}.";
    }

    private function metricLabel(string $action): string
    {
        return [
            'school_count' => 'sekolah',
            'student_total' => 'siswa',
            'student_male_total' => 'siswa laki-laki',
            'student_female_total' => 'siswa perempuan',
            'teacher_total' => 'guru',
            'education_staff_total' => 'tenaga kependidikan',
            'study_group_total' => 'rombongan belajar',
            'classroom_total' => 'ruang kelas',
            'laboratory_total' => 'laboratorium',
            'library_total' => 'perpustakaan',
        ][$action];
    }

    /** @param array{education_level: ?string, status: ?string, district: ?string} $filters */
    private function subject(string $metric, array $filters): string
    {
        $educationLevel = $this->educationLevelLabel($filters['education_level']);
        $status = $filters['status'] === null ? '' : ' '.mb_strtolower($filters['status']);
        $location = $filters['district'] === null
            ? ' di Kabupaten Teluk Bintuni'
            : ' di Distrik '.mb_convert_case(mb_strtolower($filters['district']), MB_CASE_TITLE, 'UTF-8');

        if ($metric === 'sekolah' && $educationLevel !== null) {
            return "{$educationLevel}{$status}{$location}";
        }

        $educationContext = $educationLevel === null ? '' : " pada {$educationLevel}";

        return "{$metric}{$status}{$educationContext}{$location}";
    }

    private function educationLevelLabel(?string $educationLevel): ?string
    {
        if ($educationLevel === null) {
            return null;
        }

        return [
            'KB' => 'Kelompok Bermain',
            'PKBM' => 'PKBM',
            'SD' => 'Sekolah Dasar',
            'SKB' => 'Sanggar Kegiatan Belajar',
            'SMA' => 'Sekolah Menengah Atas',
            'SMK' => 'Sekolah Menengah Kejuruan',
            'SMP' => 'Sekolah Menengah Pertama',
            'TK' => 'Taman Kanak-kanak',
        ][$educationLevel] ?? $educationLevel;
    }
}
