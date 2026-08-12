<?php

namespace App\Services;

use App\Models\School;

class TifaResponseFormatter
{
    /** @param array<string, mixed> $data */
    public function formatTeacher(array $data): string
    {
        $metric = $data['metric'] === 'unique_teacher_count' ? 'guru' : 'penugasan guru';
        $records = $data['data']['records'] ?? [];
        $dimension = $this->teacherDimensionLabel((string) ($data['group_by'] ?? ''));

        if (array_key_exists('value', $data)) {
            $value = $this->number((int) $data['value']);
            $subject = $this->teacherSubject($metric, $data['filters']);

            return $data['value'] === 0
                ? "Tidak ditemukan data {$subject}."
                : "Jumlah {$subject} sebanyak {$value} {$this->teacherUnit($metric)}.";
        }

        if ($records === []) {
            return "Tidak ditemukan data {$metric} berdasarkan {$dimension}.";
        }

        if (($data['visualization'] ?? '') === 'bar_chart') {
            $count = count($records);
            $direction = $data['sort']['direction'] ?? 'desc';
            $extreme = $direction === 'asc' ? 'paling sedikit' : 'terbanyak';
            if ($count === 1) {
                $top = $records[0];

                return "{$this->title($dimension)} dengan jumlah {$metric} {$extreme} adalah {$top['label']} ({$this->number($top['value'])} {$metric}).";
            }

            return $this->withTeacherQuality(
                "{$count} {$dimension} dengan jumlah {$metric} {$extreme} adalah {$this->rows($records, $metric)}.",
                $data,
            );
        }

        if (($data['group_by'] ?? null) === 'employment_status' && count($records) === 2) {
            return "Perbandingan jumlah {$metric} berdasarkan status kepegawaian: {$this->rows($records, $metric, ' dan ')}.";
        }

        if (($data['comparison'] ?? false) && count($records) >= 2) {
            return "Perbandingan jumlah {$metric} berdasarkan {$dimension}: {$this->rows($records, $metric, ' dan ')}.";
        }

        return $this->withTeacherQuality(
            "Jumlah {$metric} berdasarkan {$dimension}: {$this->rows($records, $metric)}.",
            $data,
        );
    }

    /** @param array<string, mixed> $data */
    private function withTeacherQuality(string $answer, array $data): string
    {
        return ($data['group_by'] ?? null) === 'school' && ! ($data['quality']['complete_for_requested_dimension'] ?? true)
            ? $answer.' Catatan: statistik per sekolah belum mencakup beberapa penugasan yang belum terhubung ke sekolah master.'
            : $answer;
    }

    /** @param array<string, mixed> $filters */
    private function teacherSubject(string $metric, array $filters): string
    {
        $parts = [$filters['ptk_type'] === 'Kepala Sekolah' ? 'kepala sekolah' : $metric];
        if ($filters['employment_status']) $parts[] = $filters['employment_status'];
        if ($filters['education_level']) $parts[] = 'pada jenjang '.$filters['education_level'];
        if ($filters['education']) $parts[] = 'berpendidikan '.$filters['education'];
        if ($filters['district']) $parts[] = 'di Distrik '.$this->title((string) $filters['district']);
        if ($filters['school_id']) {
            $school = School::find($filters['school_id']);
            if ($school) $parts[] = 'di '.$school->name;
        }
        if (! $filters['district'] && ! $filters['school_id']) $parts[] = 'di Kabupaten Teluk Bintuni';

        return implode(' ', $parts);
    }

    /** @param array<int, array{label:string,value:int}> $rows */
    private function rows(array $rows, string $metric, string $separator = ', '): string
    {
        return implode($separator, array_map(
            fn (array $row) => $row['label'].' ('.$this->number((int) $row['value']).' '.$metric.')',
            $rows,
        ));
    }

    /** @param array<string, mixed> $intent
     * @param array<string, mixed> $data
     */
    public function formatAnalytic(array $intent, array $data): string
    {
        $records = $data['data']['records'] ?? [];
        $total = count($records);

        if ($total === 0) {
            return match ($intent['action']) {
                'school_list' => 'Tidak ditemukan data sekolah sesuai filter yang dipilih dalam dataset aktif.',
                default => 'Tidak ditemukan data sekolah untuk analisis yang diminta dalam dataset aktif.',
            };
        }

        return match ($intent['action']) {
            'school_list' => $this->schoolListAnswer($records, $data['data']['total'] ?? $total, $intent['filters']),
            'school_ranking' => $this->schoolRankingAnswer($records, (string) ($data['data']['ranking_by'] ?? 'students_total')),
            'district_breakdown' => ($data['data']['limit'] ?? null) !== null
                ? count($records).' distrik dengan jumlah sekolah terbanyak adalah '.$this->schoolRows($records).'.'
                : 'Jumlah sekolah berdasarkan distrik: '.$this->schoolRows($records).'.',
            'education_level_breakdown' => 'Jumlah sekolah berdasarkan jenjang: '.$this->schoolRows($records).'.',
            'status_breakdown' => 'Perbandingan jumlah sekolah negeri dan swasta: '.$this->schoolRows($records, ' dan ').'.',
        };
    }

    /** @param array<int, array<string, mixed>> $records */
    private function schoolListAnswer(array $records, int $total, array $filters): string
    {
        $scope = '';
        if ($filters['education_level'] !== null) $scope .= ' jenjang '.$filters['education_level'];
        if ($filters['status'] !== null) $scope .= ' '.mb_strtolower($filters['status']);
        if ($filters['district'] !== null) $scope .= ' di Distrik '.$this->title($filters['district']);

        return "Terdapat {$total} sekolah{$scope}. Berikut daftarnya:";
    }

    /** @param array<int, array<string, mixed>> $records */
    private function schoolRankingAnswer(array $records, string $rankingBy): string
    {
        $metric = [
            'students_total' => 'siswa',
            'teachers' => 'guru',
            'classrooms' => 'ruang kelas',
            'laboratories' => 'laboratorium',
            'libraries' => 'perpustakaan',
        ][$rankingBy] ?? $rankingBy;
        $rows = implode(', ', array_map(
            fn (array $record) => $record['name'].' ('.$this->number((int) $record['value']).' '.$metric.')',
            $records,
        ));

        return count($records).' sekolah dengan '.$metric.' terbanyak adalah '.$rows.'.';
    }

    /** @param array<int, array{label:string,value:int}> $records */
    private function schoolRows(array $records, string $separator = ', '): string
    {
        return implode($separator, array_map(
            fn (array $record) => $record['label'].' ('.$this->number((int) $record['value']).' sekolah)',
            $records,
        ));
    }

    /** @param array<string, mixed> $intent
     * @param array<string, mixed> $data
     */
    public function format(array $intent, array $data): string
    {
        $metric = $this->metricLabel($intent['action']);
        $value = (int) $data['value'];
        $subject = $this->subject($metric, $intent['filters']);

        if ($value === 0) {
            return "Tidak ditemukan data {$subject} dalam dataset aktif.";
        }

        if ($intent['action'] === 'school_count' && isset($data['composition']) && $intent['filters']['status'] === null) return $this->schoolCountAnswer($intent['filters'], $value, $data['composition']);

        return "Jumlah {$subject} sebanyak {$this->number($value)} {$this->unit($intent['action'])}.";
    }

    private function metricLabel(string $action): string
    {
        return [
            'school_count' => 'sekolah', 'student_total' => 'siswa', 'student_male_total' => 'siswa laki-laki',
            'student_female_total' => 'siswa perempuan', 'teacher_total' => 'guru', 'education_staff_total' => 'tenaga kependidikan',
            'study_group_total' => 'rombongan belajar', 'classroom_total' => 'ruang kelas', 'laboratory_total' => 'laboratorium',
            'library_total' => 'perpustakaan',
        ][$action];
    }

    private function unit(string $action): string
    {
        return match ($action) {
            'teacher_total', 'education_staff_total' => 'orang',
            'classroom_total', 'laboratory_total', 'library_total', 'study_group_total' => 'unit',
            default => $this->metricLabel($action),
        };
    }

    private function teacherUnit(string $metric): string
    {
        return $metric === 'penugasan guru' ? 'penugasan' : 'orang';
    }

    /** @param array{education_level: ?string, status: ?string, district: ?string} $filters
     * @param array{public_schools: int, private_schools: int} $composition
     */
    private function schoolCountAnswer(array $filters, int $value, array $composition): string
    {
        $location = $filters['district'] === null ? 'Kabupaten Teluk Bintuni' : 'Distrik '.$this->title($filters['district']);
        $level = $filters['education_level'];
        $prefix = $level === null
            ? "{$location} memiliki"
            : "Jumlah {$level} di {$location} sebanyak";
        $public = $this->number((int) $composition['public_schools']);
        $private = $this->number((int) $composition['private_schools']);

        return "{$prefix} {$this->number($value)} sekolah, terdiri dari {$public} sekolah negeri dan {$private} sekolah swasta.";
    }

    /** @param array{education_level: ?string, status: ?string, district: ?string} $filters */
    private function subject(string $metric, array $filters): string
    {
        $educationLevel = $this->educationLevelLabel($filters['education_level']);
        $status = $filters['status'] === null ? '' : ' '.mb_strtolower($filters['status']);
        $location = $filters['district'] === null ? ' di Kabupaten Teluk Bintuni' : ' di Distrik '.$this->title($filters['district']);

        if ($metric === 'sekolah' && $educationLevel !== null) return "{$educationLevel}{$status}{$location}";
        $educationContext = $educationLevel === null ? '' : " pada jenjang {$educationLevel}";

        return "{$metric}{$status}{$educationContext}{$location}";
    }

    private function teacherDimensionLabel(string $dimension): string
    {
        return ['district' => 'distrik', 'employment_status' => 'status kepegawaian', 'education_level' => 'jenjang', 'ptk_type' => 'jenis PTK', 'school' => 'sekolah'][$dimension] ?? $dimension;
    }

    private function educationLevelLabel(?string $educationLevel): ?string
    {
        if ($educationLevel === null) return null;

        return [
            'KB' => 'Kelompok Bermain', 'PKBM' => 'PKBM', 'SD' => 'Sekolah Dasar', 'SKB' => 'Sanggar Kegiatan Belajar',
            'SMA' => 'Sekolah Menengah Atas', 'SMK' => 'Sekolah Menengah Kejuruan', 'SMP' => 'Sekolah Menengah Pertama', 'TK' => 'Taman Kanak-kanak',
        ][$educationLevel] ?? $educationLevel;
    }

    private function number(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private function title(string $value): string
    {
        return mb_convert_case(mb_strtolower($value), MB_CASE_TITLE, 'UTF-8');
    }
}
