<?php

namespace Tests\Feature;

use App\Models\TeacherAssignment;
use App\Models\TeacherImportBatch;
use App\Models\School;
use App\Models\Dataset;
use App\Services\TifaAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeacherConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.tifa_ai.provider' => 'ollama']);
    }

    public function test_deterministic_teacher_question_uses_analytics_without_ollama(): void
    {
        $batch = TeacherImportBatch::create(['source_filename' => 'x.xlsx', 'source_checksum' => hash('sha256', 'x'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        foreach (['a', 'b'] as $fingerprint) TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => 'SD', 'source_row' => random_int(1, 999), 'source_fingerprint' => hash('sha256', uniqid('', true)), 'deduplication_fingerprint' => $fingerprint, 'school_resolution_status' => 'resolved', 'employment_status' => 'PPPK']);
        $result = app(TifaAssistantService::class)->ask('Berapa guru PPPK di SD?');
        $this->assertSame('teacher_analytics', $result['intent']['type']);
        $this->assertSame(2, $result['data']['value']);
        $this->assertSame('Jumlah guru PPPK pada jenjang SD di Kabupaten Teluk Bintuni sebanyak 2 orang.', $result['answer']);
        Http::assertNothingSent();
    }

    public function test_privacy_request_is_not_a_teacher_analytics_result(): void
    {
        $this->postJson('/api/tifa/ask', ['question' => 'Berapa NIK guru?'])->assertOk()->assertJsonPath('intent.type', 'privacy_guard');
    }

    public function test_grouping_ranking_comparison_and_subject_labels_render_database_rows(): void
    {
        $batch = TeacherImportBatch::create(['source_filename' => 'group.xlsx', 'source_checksum' => hash('sha256', 'group'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        foreach ([['Bintuni','PNS','a'], ['Bintuni','PPPK','b'], ['Manimeri','PPPK','c'], ['Sumuri','PNS','d'], ['Babo','PNS','e'], ['Tomu','PPPK','f']] as [$district, $status, $fingerprint]) TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => 'SD', 'source_row' => random_int(1000, 9999), 'source_fingerprint' => hash('sha256', uniqid('', true)), 'deduplication_fingerprint' => $fingerprint, 'school_resolution_status' => 'resolved', 'district' => $district, 'employment_status' => $status]);
        $assistant = app(TifaAssistantService::class);
        $grouping = $assistant->ask('Tampilkan jumlah guru berdasarkan distrik.');
        $this->assertStringContainsString('Jumlah guru berdasarkan distrik:', $grouping['answer']);
        $this->assertStringContainsString('Bintuni (2 guru)', $grouping['answer']);
        $this->assertStringContainsString('Manimeri (1 guru)', $grouping['answer']);
        $ranking = $assistant->ask('Sebutkan 3 distrik dengan guru terbanyak.');
        $this->assertStringContainsString('3 distrik dengan jumlah guru terbanyak adalah', $ranking['answer']);
        $this->assertStringContainsString('Bintuni (2 guru)', $ranking['answer']);
        $this->assertStringContainsString('Manimeri (1 guru)', $ranking['answer']);
        $topFive = $assistant->ask('Sebutkan 5 distrik dengan guru terbanyak.');
        $this->assertSame(5, $topFive['intent']['top_n']);
        foreach (['Bintuni', 'Babo', 'Manimeri', 'Sumuri', 'Tomu'] as $district) $this->assertStringContainsString($district.' (', $topFive['answer']);
        $comparison = $assistant->ask('Bandingkan jumlah guru PNS dan PPPK.');
        $this->assertStringContainsString('Jumlah guru berdasarkan status kepegawaian terdiri dari', $comparison['answer']);
        $this->assertStringContainsString('PNS sebanyak 3 guru', $comparison['answer']);
        $this->assertStringContainsString('PPPK sebanyak 3 guru', $comparison['answer']);
        $top = $assistant->ask('Distrik mana yang memiliki guru paling banyak?');
        $this->assertSame('teacher_analytics', $top['intent']['type']);
        $this->assertSame('Distrik dengan jumlah guru terbanyak adalah Bintuni (2 guru).', $top['answer']);
        $this->assertStringNotContainsString('Berdasarkan data', $top['answer']);

        $districtComparison = $assistant->ask('Bandingkan jumlah guru Bintuni dan Manimeri');
        $this->assertSame('district', $districtComparison['intent']['group_by']);
        $this->assertStringContainsString('Perbandingan jumlah guru berdasarkan distrik:', $districtComparison['answer']);
        $this->assertStringContainsString('Bintuni (2 guru)', $districtComparison['answer']);
        $this->assertStringContainsString('Manimeri (1 guru)', $districtComparison['answer']);
    }

    public function test_school_resolution_aliases_filters_and_school_rankings_are_deterministic(): void
    {
        $batch = TeacherImportBatch::create(['source_filename' => 'school.xlsx', 'source_checksum' => hash('sha256', 'school'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        $kuri = School::factory()->create(['name' => 'SMP NEGERI 1 KURI', 'education_level' => 'SMP']);
        $bintuni = School::factory()->create(['name' => 'SMA NEGERI 2 BINTUNI', 'education_level' => 'SMA']);
        $babo = School::factory()->create(['name' => 'SD NEGERI BABO', 'education_level' => 'SD']);
        foreach ([[$kuri, 'PNS', 'a'], [$bintuni, 'PPPK', 'b'], [$bintuni, 'PNS', 'c'], [$babo, 'PNS', 'd']] as [$school, $status, $fingerprint]) TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => $school->education_level, 'source_row' => random_int(10000, 99999), 'source_fingerprint' => hash('sha256', uniqid('', true)), 'deduplication_fingerprint' => $fingerprint, 'school_id' => $school->id, 'school_resolution_status' => 'resolved', 'employment_status' => $status]);
        $assistant = app(TifaAssistantService::class);
        $kuriAnswer = $assistant->ask('Berapa guru di SMPN 1 Kuri?');
        $this->assertSame($kuri->id, $kuriAnswer['intent']['filters']['school_id']);
        $this->assertStringContainsString('SMP NEGERI 1 KURI', $kuriAnswer['answer']);
        $pppk = $assistant->ask('Berapa guru PPPK di SMA Negeri 2 Bintuni?');
        $this->assertSame($bintuni->id, $pppk['intent']['filters']['school_id']);
        $this->assertSame('PPPK', $pppk['intent']['filters']['employment_status']);
        $rank = $assistant->ask('Sebutkan 2 sekolah dengan guru terbanyak.');
        $this->assertSame('school', $rank['intent']['group_by']);
        $this->assertSame(2, $rank['intent']['top_n']);
        $this->assertStringContainsString('SMA NEGERI 2 BINTUNI (2 guru)', $rank['answer']);
        $level = $assistant->ask('Berapa guru SMP?');
        $this->assertNull($level['intent']['filters']['school_id']);
        $this->assertSame('SMP', $level['intent']['filters']['education_level']);
    }

    public function test_teacher_synonyms_take_precedence_over_school_and_district_terms(): void
    {
        Dataset::factory()->active()->create();
        $batch = TeacherImportBatch::create(['source_filename' => 'synonyms.xlsx', 'source_checksum' => hash('sha256', 'synonyms'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        $kuri = School::factory()->create(['name' => 'SMP NEGERI 1 KURI', 'education_level' => 'SMP']);
        foreach ([['Bintuni', null, 'one'], ['Bintuni', null, 'two'], ['Manimeri', null, 'three'], ['Kuri', $kuri->id, 'four']] as [$district, $schoolId, $fingerprint]) {
            TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => 'SMP', 'source_row' => random_int(300000, 399999), 'source_fingerprint' => hash('sha256', uniqid('', true)), 'deduplication_fingerprint' => $fingerprint, 'school_id' => $schoolId, 'school_resolution_status' => 'resolved', 'district' => $district]);
        }
        Http::fake();
        $assistant = app(TifaAssistantService::class);

        $districtTop = $assistant->ask('Distrik mana sih yang tenaga pengajarnya paling banyak?');
        $this->assertSame('teacher_analytics', $districtTop['intent']['type']);
        $this->assertSame('district', $districtTop['intent']['group_by']);
        $this->assertSame(1, $districtTop['intent']['top_n']);
        $this->assertSame('unique_teacher_count', $districtTop['intent']['metric']);
        $this->assertSame('Bintuni', $districtTop['data']['records'][0]['label']);

        foreach (['Distrik mana yang gurunya paling banyak?', 'Daerah mana yang tenaga pendidiknya paling banyak?'] as $question) {
            $result = $assistant->ask($question);
            $this->assertSame('teacher_analytics', $result['intent']['type']);
            $this->assertSame('district', $result['intent']['group_by']);
            $this->assertSame(1, $result['intent']['top_n']);
        }

        foreach (['Sekolah mana yang tenaga pengajarnya paling banyak?', 'Sekolah mana yang gurunya paling banyak?'] as $question) {
            $result = $assistant->ask($question);
            $this->assertSame('teacher_analytics', $result['intent']['type']);
            $this->assertSame('school', $result['intent']['group_by']);
            $this->assertSame(1, $result['intent']['top_n']);
        }

        $schoolScoped = $assistant->ask('Berapa tenaga pengajar di SMP Negeri 1 Kuri?');
        $this->assertSame('teacher_analytics', $schoolScoped['intent']['type']);
        $this->assertSame('unique_teacher_count', $schoolScoped['intent']['metric']);
        $this->assertSame($kuri->id, $schoolScoped['intent']['filters']['school_id']);

        $schools = $assistant->ask('Berapa jumlah sekolah di Distrik Bintuni?');
        $this->assertSame('school_count', $schools['intent']['action']);
        Http::assertNothingSent();
    }

    public function test_school_teacher_rankings_bypass_local_school_aggregate_and_honor_requested_limit(): void
    {
        $batch = TeacherImportBatch::create(['source_filename' => 'sma-rankings.xlsx', 'source_checksum' => hash('sha256', 'sma-rankings'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        $schools = collect([
            ['SMAN ALFA', 6], ['SMAN BETA', 5], ['SMAN CHARLIE', 4], ['SMAN DELTA', 3], ['SMAN ECHO', 2], ['SMAN FOXTROT', 1],
        ])->map(fn (array $row) => [School::factory()->create(['name' => $row[0], 'education_level' => 'SMA']), $row[1]]);
        foreach ($schools as [$school, $total]) {
            for ($index = 1; $index <= $total; $index++) {
                TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => 'SMA.', 'source_row' => random_int(400000, 499999), 'source_fingerprint' => hash('sha256', uniqid('', true)), 'deduplication_fingerprint' => hash('sha256', $school->id.'-'.$index), 'school_id' => $school->id, 'school_resolution_status' => 'resolved']);
            }
        }
        Http::fake();
        $assistant = app(TifaAssistantService::class);

        $main = $assistant->ask('Tampilkan 5 sekolah setingkat SMA dengan jumlah guru terbanyak');
        $this->assertSame('teacher_analytics', $main['intent']['type']);
        $this->assertSame('ranking', $main['intent']['operation']);
        $this->assertSame('unique_teacher_count', $main['intent']['metric']);
        $this->assertSame('SMA', $main['intent']['filters']['education_level']);
        $this->assertSame('school', $main['intent']['group_by']);
        $this->assertSame(5, $main['intent']['top_n']);
        $this->assertCount(5, $main['data']['records']);
        $this->assertSame('SMAN ALFA', $main['data']['records'][0]['label']);
        $this->assertStringNotContainsString('sebanyak 294 orang', $main['answer']);

        foreach ([
            '5 SMA dengan guru terbanyak' => 5,
            'Sebutkan 5 SMA yang memiliki guru paling banyak' => 5,
            'Sekolah SMA mana yang jumlah gurunya paling banyak?' => 1,
            'Top 5 SMA berdasarkan jumlah guru' => 5,
            'Tampilkan lima sekolah SMA dengan tenaga pengajar terbanyak' => 5,
            'SMA dengan guru terbanyak' => 1,
            '10 sekolah jenjang SMA dengan jumlah guru terbanyak' => 10,
            'sekolah setingkat SMA yang gurunya paling banyak' => 1,
        ] as $question => $limit) {
            $result = $assistant->ask($question);
            $this->assertSame('teacher_analytics', $result['intent']['type'], $question);
            $this->assertSame('unique_teacher_count', $result['intent']['metric'], $question);
            $this->assertSame('SMA', $result['intent']['filters']['education_level'], $question);
            $this->assertSame('school', $result['intent']['group_by'], $question);
            $this->assertSame($limit, $result['intent']['top_n'], $question);
        }

        foreach (['Tampilkan 5 distrik dengan jumlah guru terbanyak', '5 distrik dengan tenaga pengajar terbanyak', 'Distrik mana yang gurunya paling banyak?'] as $question) {
            $result = $assistant->ask($question);
            $this->assertSame('teacher_analytics', $result['intent']['type'], $question);
            $this->assertSame('district', $result['intent']['group_by'], $question);
        }

        Http::assertNothingSent();
    }

    public function test_an_explicit_top_n_district_request_replaces_a_previous_top_one_context(): void
    {
        $batch = TeacherImportBatch::create(['source_filename' => 'district-ranking.xlsx', 'source_checksum' => hash('sha256', 'district-ranking'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        $sourceRow = 500000;
        foreach ([['Bintuni', 10], ['Manimeri', 9], ['Sumuri', 8], ['Babo', 7], ['Tomu', 6], ['Kuri', 5], ['Wamesa', 4], ['Moskona Utara', 3], ['Moskona Selatan', 2], ['Tembuni', 1]] as [$district, $total]) {
            for ($index = 0; $index < $total; $index++) {
                TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => 'SD', 'source_row' => $sourceRow++, 'source_fingerprint' => hash('sha256', $district.'-source-'.$index), 'deduplication_fingerprint' => hash('sha256', $district.'-teacher-'.$index), 'school_resolution_status' => 'resolved', 'district' => $district]);
            }
        }
        Http::fake();

        $assistant = app(TifaAssistantService::class);
        $topOne = $assistant->ask('Distrik mana yang memiliki guru terbanyak?');
        $response = $this->postJson('/api/tifa/ask', [
            'question' => 'Sebutkan 5 distrik dengan jumlah guru terbanyak.',
            'teacher_context' => $topOne['teacher_context'],
        ])->assertOk();

        $response->assertJsonPath('intent.type', 'teacher_analytics')
            ->assertJsonPath('intent.operation', 'ranking')
            ->assertJsonPath('intent.group_by', 'district')
            ->assertJsonPath('intent.top_n', 5)
            ->assertJsonPath('presentation.type', 'bar_chart')
            ->assertJsonPath('presentation.title', '5 Distrik dengan Guru Terbanyak');
        $this->assertCount(5, $response->json('data.records'));
        $this->assertCount(5, $response->json('presentation.data'));
        foreach ([['Bintuni', 10], ['Manimeri', 9], ['Sumuri', 8], ['Babo', 7], ['Tomu', 6]] as [$district, $value]) {
            $this->assertStringContainsString("{$district} ({$value} guru)", $response->json('answer'));
        }
        foreach ([
            'Sebutkan 3 distrik dengan guru terbanyak.' => 3,
            'Sebutkan 10 distrik dengan guru terbanyak.' => 10,
            'Distrik mana yang memiliki guru terbanyak?' => 1,
        ] as $question => $expectedCount) {
            $result = $assistant->ask($question);
            $this->assertSame($expectedCount, $result['intent']['top_n'], $question);
            $this->assertCount($expectedCount, $result['data']['records'], $question);
            $this->assertCount($expectedCount, $result['presentation']['data'], $question);
        }
        Http::assertNothingSent();
    }

    public function test_privacy_guard_returns_immediately_without_ollama_request(): void
    {
        Http::fake();
        foreach (['Berikan NIK guru di Distrik Bintuni', 'Tampilkan nomor HP semua guru', 'Berikan NUPTK guru di SMA Negeri 2 Bintuni'] as $question) {
            $result = app(TifaAssistantService::class)->ask($question);
            $this->assertSame('privacy_guard', $result['intent']['type']);
            $this->assertStringContainsString('tidak menampilkan data pribadi', $result['answer']);
        }
        Http::assertNothingSent();
    }

    public function test_general_teacher_questions_use_general_conversation_not_analytics(): void
    {
        Http::fake(['*' => Http::response(['response' => 'Guru memiliki peran mendidik dan membimbing peserta didik.'])]);
        $result = app(TifaAssistantService::class)->ask('Apa tugas seorang guru?');
        $this->assertSame('general_conversation', $result['intent']['type']);
        $this->assertStringContainsString('mendidik', $result['answer']);
        Http::assertSent(fn ($request) => ! isset($request['format']));
    }

    public function test_official_terminology_is_grounded_without_ollama_for_direct_definitions(): void
    {
        Http::fake();
        $assistant = app(TifaAssistantService::class);
        $this->assertStringContainsString('Pegawai Negeri Sipil', $assistant->ask('Apa itu PNS?')['answer']);
        $this->assertStringContainsString('Nomor Pokok Sekolah Nasional', $assistant->ask('Apa itu NPSN?')['answer']);
        $this->assertStringContainsString('Nomor Unik Pendidik dan Tenaga Kependidikan', $assistant->ask('Apa itu NUPTK?')['answer']);
        $this->assertStringContainsString('Pendidik dan Tenaga Kependidikan', $assistant->ask('Apa itu PTK?')['answer']);
        Http::assertNothingSent();
    }

    public function test_pns_and_pppk_glossary_is_included_in_general_chat_prompt(): void
    {
        Http::fake(['*' => Http::response(['response' => 'PNS adalah Pegawai Negeri Sipil, sedangkan PPPK adalah Pegawai Pemerintah dengan Perjanjian Kerja.'])]);
        $result = app(TifaAssistantService::class)->ask('Apa perbedaan PNS dan PPPK?');
        $this->assertStringContainsString('Pegawai Negeri Sipil', $result['answer']);
        $this->assertStringContainsString('Pegawai Pemerintah dengan Perjanjian Kerja', $result['answer']);
        Http::assertSent(fn ($request) => str_contains($request['prompt'], 'PNS = Pegawai Negeri Sipil') && str_contains($request['prompt'], 'PPPK = Pegawai Pemerintah dengan Perjanjian Kerja'));
    }

    public function test_safe_teacher_context_supports_followups_without_ollama(): void
    {
        $batch = TeacherImportBatch::create(['source_filename' => 'context.xlsx', 'source_checksum' => hash('sha256', 'context'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        foreach ([['SD','Bintuni','PNS','a'], ['SMP','Bintuni','PPPK','b'], ['SMP','Bintuni','PNS','c'], ['SMP','Manimeri','PPPK','d']] as [$sheet, $district, $status, $fingerprint]) TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => $sheet, 'source_row' => random_int(200000, 299999), 'source_fingerprint' => hash('sha256', uniqid('', true)), 'deduplication_fingerprint' => $fingerprint, 'school_resolution_status' => 'resolved', 'district' => $district, 'employment_status' => $status]);
        $assistant = app(TifaAssistantService::class);
        $first = $assistant->ask('Berapa guru SD di Bintuni?');
        $second = $assistant->ask('Kalau SMP?', $first['teacher_context']);
        $this->assertSame('SMP', $second['intent']['filters']['education_level']); $this->assertSame('bintuni', $second['intent']['filters']['district']);
        $third = $assistant->ask('Yang PPPK berapa?', $second['teacher_context']);
        $this->assertSame('PPPK', $third['intent']['filters']['employment_status']); $this->assertSame('SMP', $third['intent']['filters']['education_level']);
        $breakdown = $assistant->ask('Tampilkan jumlah guru per distrik');
        $top = $assistant->ask('Lima terbesar saja', $breakdown['teacher_context']);
        $this->assertSame('district', $top['intent']['group_by']); $this->assertSame(5, $top['intent']['top_n']);
    }
}
