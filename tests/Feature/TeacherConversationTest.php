<?php

namespace Tests\Feature;

use App\Models\TeacherAssignment;
use App\Models\TeacherImportBatch;
use App\Models\School;
use App\Services\TifaAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeacherConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deterministic_teacher_question_uses_analytics_without_ollama(): void
    {
        $batch = TeacherImportBatch::create(['source_filename' => 'x.xlsx', 'source_checksum' => hash('sha256', 'x'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        foreach (['a', 'b'] as $fingerprint) TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => 'SD', 'source_row' => random_int(1, 999), 'source_fingerprint' => hash('sha256', uniqid('', true)), 'deduplication_fingerprint' => $fingerprint, 'school_resolution_status' => 'resolved', 'employment_status' => 'PPPK']);
        $result = app(TifaAssistantService::class)->ask('Berapa guru PPPK di SD?');
        $this->assertSame('teacher_analytics', $result['intent']['type']);
        $this->assertSame(2, $result['data']['value']);
        $this->assertStringContainsString('data pendidikan terintegrasi Kabupaten Teluk Bintuni', $result['answer']);
    }

    public function test_privacy_request_is_not_a_teacher_analytics_result(): void
    {
        $this->postJson('/api/tifa/ask', ['question' => 'Berapa NIK guru?'])->assertOk()->assertJsonPath('intent.type', 'privacy_guard');
    }

    public function test_grouping_ranking_comparison_and_subject_labels_render_database_rows(): void
    {
        $batch = TeacherImportBatch::create(['source_filename' => 'group.xlsx', 'source_checksum' => hash('sha256', 'group'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        foreach ([['Bintuni','PNS','a'], ['Bintuni','PPPK','b'], ['Manimeri','PPPK','c'], ['Sumuri','PNS','d']] as [$district, $status, $fingerprint]) TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => 'SD', 'source_row' => random_int(1000, 9999), 'source_fingerprint' => hash('sha256', uniqid('', true)), 'deduplication_fingerprint' => $fingerprint, 'school_resolution_status' => 'resolved', 'district' => $district, 'employment_status' => $status]);
        $assistant = app(TifaAssistantService::class);
        $grouping = $assistant->ask('Tampilkan jumlah guru berdasarkan distrik.');
        $this->assertStringContainsString('Bintuni 2 guru', $grouping['answer']);
        $this->assertStringContainsString('Manimeri 1 guru', $grouping['answer']);
        $ranking = $assistant->ask('Sebutkan 3 distrik dengan guru terbanyak.');
        $this->assertStringContainsString('Bintuni 2 guru', $ranking['answer']);
        $this->assertStringContainsString('Manimeri 1 guru', $ranking['answer']);
        $comparison = $assistant->ask('Bandingkan jumlah guru PNS dan PPPK.');
        $this->assertStringContainsString('PNS 2 guru', $comparison['answer']);
        $this->assertStringContainsString('PPPK 2 guru', $comparison['answer']);
        $top = $assistant->ask('Distrik mana yang memiliki guru paling banyak?');
        $this->assertSame('teacher_analytics', $top['intent']['type']);
        $this->assertStringContainsString('Bintuni sebanyak 2', $top['answer']);
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
        $this->assertStringContainsString('SMA NEGERI 2 BINTUNI 2 guru', $rank['answer']);
        $level = $assistant->ask('Berapa guru SMP?');
        $this->assertNull($level['intent']['filters']['school_id']);
        $this->assertSame('SMP', $level['intent']['filters']['education_level']);
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
