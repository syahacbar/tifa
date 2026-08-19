<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Services\PublicDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicInformationAndComplaintTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_ruang_informasi_with_available_documents(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('RUANG INFORMASI')
            ->assertSee('PENGADUAN LAYANAN')
            ->assertSee('Rencana Strategis (Renstra) Dinas Pendidikan Kabupaten Teluk Bintuni')
            ->assertSee('Sampaikan pengaduan atau masukan terkait layanan pendidikan');
    }

    public function test_public_document_service_only_returns_physically_existing_documents(): void
    {
        $service = new PublicDocumentService();
        $docs = $service->getHomepageDocuments();

        $this->assertNotEmpty($docs);
        foreach ($docs as $doc) {
            $this->assertFileExists(public_path(ltrim($doc['file'], '/')));
            $this->assertStringStartsWith('%PDF-', (string) file_get_contents(public_path(ltrim($doc['file'], '/')), false, null, 0, 5));
        }
    }

    public function test_complaint_endpoint_stores_valid_submission_without_attachment(): void
    {
        $payload = [
            'name' => 'Maria Simopiaref',
            'phone' => '081234567890',
            'complaint_type' => 'Pelayanan Pendidikan',
            'complaint_text' => 'Mohon informasi jadwal pencairan beasiswa daerah untuk siswa berprestasi di wilayah Bintuni.',
        ];

        $response = $this->post(route('complaints.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('complaint_success', 'Pengaduan Anda berhasil dikirim.');

        $this->assertDatabaseHas('complaints', [
            'name' => 'Maria Simopiaref',
            'phone' => '081234567890',
            'complaint_type' => 'Pelayanan Pendidikan',
            'complaint_text' => 'Mohon informasi jadwal pencairan beasiswa daerah untuk siswa berprestasi di wilayah Bintuni.',
            'attachment_path' => null,
        ]);
    }

    public function test_complaint_endpoint_stores_valid_submission_with_pdf_attachment_in_private_storage(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('surat-pengaduan.pdf', 500, 'application/pdf');

        $payload = [
            'name' => 'Dominggus Ronsumbre',
            'phone' => '+62 821-9876-5432',
            'complaint_type' => 'Sarana Prasarana',
            'complaint_text' => 'Kondisi atap ruang kelas SD Inpres Bintuni mengalami kerusakan saat musim hujan.',
            'attachment' => $file,
        ];

        $response = $this->post(route('complaints.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('complaint_success', 'Pengaduan Anda berhasil dikirim.');

        $complaint = Complaint::where('name', 'Dominggus Ronsumbre')->first();
        $this->assertNotNull($complaint);
        $this->assertNotNull($complaint->attachment_path);
        // Ensure stored in private/local disk, not public
        Storage::disk('local')->assertExists($complaint->attachment_path);
    }

    public function test_complaint_endpoint_supports_json_request(): void
    {
        $payload = [
            'name' => 'Agus Pattipi',
            'phone' => '085244112233',
            'complaint_type' => 'Sekolah',
            'complaint_text' => 'Usulan penambahan ruang perpustakaan di SMP Negeri Merdey untuk menunjang literasi siswa.',
        ];

        $response = $this->postJson(route('complaints.store'), $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Pengaduan Anda berhasil dikirim.',
            ]);

        $this->assertDatabaseHas('complaints', [
            'name' => 'Agus Pattipi',
            'phone' => '085244112233',
        ]);
    }

    public function test_complaint_validation_rejects_missing_required_fields(): void
    {
        $response = $this->post(route('complaints.store'), []);

        $response->assertSessionHasErrors(['name', 'phone', 'complaint_type', 'complaint_text']);
        $this->assertSame(0, Complaint::count());
    }

    public function test_complaint_validation_rejects_invalid_phone_number_or_short_text(): void
    {
        $response = $this->post(route('complaints.store'), [
            'name' => 'Test User',
            'phone' => 'abcde123',
            'complaint_type' => 'Pelayanan Pendidikan',
            'complaint_text' => 'Pendek',
        ]);

        $response->assertSessionHasErrors(['phone', 'complaint_text']);
        $this->assertSame(0, Complaint::count());
    }

    public function test_complaint_validation_rejects_disallowed_attachment_types(): void
    {
        Storage::fake('local');

        $disallowedFile = UploadedFile::fake()->create('script.php', 10, 'application/x-php');

        $response = $this->post(route('complaints.store'), [
            'name' => 'Hacker Test',
            'phone' => '081234567890',
            'complaint_type' => 'Lainnya',
            'complaint_text' => 'Mencoba upload file executable atau script PHP terlarang.',
            'attachment' => $disallowedFile,
        ]);

        $response->assertSessionHasErrors(['attachment']);
        $this->assertSame(0, Complaint::count());
    }

    public function test_homepage_only_renders_active_documents_up_to_six(): void
    {
        // Create 8 active documents and 2 inactive documents
        for ($i = 1; $i <= 8; $i++) {
            \App\Models\PublicDocument::factory()->create([
                'title' => "Dokumen Aktif {$i}",
                'is_active' => true,
                'published_at' => "2026-01-0{$i}",
            ]);
        }

        \App\Models\PublicDocument::factory()->inactive()->create([
            'title' => 'Dokumen Rahasia Nonaktif',
            'published_at' => '2026-02-01',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Dokumen Aktif 8')
            ->assertSee('Dokumen Aktif 7')
            ->assertSee('Dokumen Aktif 3')
            ->assertDontSee('Dokumen Rahasia Nonaktif');
    }

    public function test_public_document_api_returns_paginated_active_documents(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            \App\Models\PublicDocument::factory()->create([
                'title' => sprintf('Dokumen Ke-%02d', $i),
                'is_active' => true,
                'published_at' => sprintf('2026-01-%02d', $i),
            ]);
        }

        \App\Models\PublicDocument::factory()->inactive()->create([
            'title' => 'Dokumen Nonaktif Tersembunyi',
            'published_at' => '2026-02-01',
        ]);

        // Page 1
        $res1 = $this->getJson('/api/ruang-informasi?page=1');
        $res1->assertOk()
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('total', 10)
            ->assertJsonPath('has_previous', false)
            ->assertJsonPath('has_next', true)
            ->assertJsonCount(6, 'data');

        $this->assertSame('Dokumen Ke-10', $res1->json('data.0.title'));

        // Page 2
        $res2 = $this->getJson('/api/ruang-informasi?page=2');
        $res2->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('has_previous', true)
            ->assertJsonPath('has_next', false)
            ->assertJsonCount(4, 'data');

        $this->assertSame('Dokumen Ke-04', $res2->json('data.0.title'));
    }

    public function test_public_document_api_response_does_not_expose_absolute_filesystem_path(): void
    {
        \App\Models\PublicDocument::factory()->create([
            'title' => 'Dokumen Keamanan Path',
            'file_path' => 'documents/sample.pdf',
            'thumbnail_path' => 'document-thumbnails/thumb.png',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/ruang-informasi');
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('d:\\laragon\\www\\tifa', strtolower($content));
        $this->assertStringNotContainsString('c:\\', strtolower($content));
    }

    public function test_public_complaint_submission_ignores_user_supplied_status_and_defaults_to_baru(): void
    {
        $payload = [
            'name' => 'Korneles Boki',
            'phone' => '081233445566',
            'complaint_type' => 'Lainnya',
            'complaint_text' => 'Mencoba injeksi status selesai pada formulir publik.',
            'status' => 'selesai',
        ];

        $response = $this->post(route('complaints.store'), $payload);
        $response->assertRedirect();

        $complaint = Complaint::where('name', 'Korneles Boki')->first();
        $this->assertNotNull($complaint);
        $this->assertSame(Complaint::STATUS_BARU, $complaint->status);
    }

    public function test_renstra_inactive_does_not_fall_back_when_records_exist_in_database(): void
    {
        // When there is a record in the database, but it is inactive
        \App\Models\PublicDocument::factory()->inactive()->create([
            'title' => 'Renstra Nonaktif',
            'file_path' => 'documents/renstra-dinas-pendidikan-teluk-bintuni.pdf',
        ]);

        $service = new PublicDocumentService();
        $docs = $service->getHomepageDocuments();

        // Must return empty array, NOT falling back to the static array
        $this->assertEmpty($docs);
    }
}


