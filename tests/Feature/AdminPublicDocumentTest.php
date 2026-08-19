<?php

namespace Tests\Feature;

use App\Models\PublicDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPublicDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_guest_cannot_access_admin_documents_index(): void
    {
        $response = $this->get(route('admin.documents.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_admin_can_view_documents_index(): void
    {
        $doc = PublicDocument::factory()->create([
            'title' => 'Dokumen Pedoman BOSP 2025',
            'published_at' => '2025-01-10',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.documents.index'));

        $response->assertOk()
            ->assertSee('Dokumen Publik')
            ->assertSee('Dokumen Pedoman BOSP 2025')
            ->assertSee('Aktif');
    }

    public function test_admin_can_create_document_with_valid_pdf(): void
    {
        Storage::fake('public');

        $pdf = UploadedFile::fake()->create('renstra-2026.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('admin.documents.store'), [
            'title' => 'Renstra Dinas Pendidikan 2026',
            'published_at' => '2026-01-01',
            'pdf_file' => $pdf,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.documents.index'));
        $response->assertSessionHas('success', 'Dokumen berhasil ditambahkan.');

        $doc = PublicDocument::where('title', 'Renstra Dinas Pendidikan 2026')->first();
        $this->assertNotNull($doc);
        $this->assertTrue($doc->is_active);
        $this->assertSame('2026-01-01', $doc->published_at->format('Y-m-d'));
        Storage::disk('public')->assertExists($doc->file_path);
    }

    public function test_admin_can_create_document_with_thumbnail(): void
    {
        Storage::fake('public');

        $pdf = UploadedFile::fake()->create('dokumen-juknis.pdf', 500, 'application/pdf');
        $thumbnail = UploadedFile::fake()->image('cover.png', 400, 300);

        $response = $this->actingAs($this->admin)->post(route('admin.documents.store'), [
            'title' => 'Juknis PPDB 2026',
            'published_at' => '2026-05-01',
            'pdf_file' => $pdf,
            'thumbnail_file' => $thumbnail,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.documents.index'));

        $doc = PublicDocument::where('title', 'Juknis PPDB 2026')->first();
        $this->assertNotNull($doc);
        $this->assertNotNull($doc->thumbnail_path);
        Storage::disk('public')->assertExists($doc->file_path);
        Storage::disk('public')->assertExists($doc->thumbnail_path);
    }

    public function test_non_pdf_file_is_rejected(): void
    {
        Storage::fake('public');

        $txtFile = UploadedFile::fake()->create('dokumen.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $response = $this->actingAs($this->admin)->post(route('admin.documents.store'), [
            'title' => 'Dokumen Salah',
            'published_at' => '2026-01-01',
            'pdf_file' => $txtFile,
        ]);

        $response->assertSessionHasErrors('pdf_file');
        $this->assertDatabaseMissing('public_documents', ['title' => 'Dokumen Salah']);
    }

    public function test_invalid_thumbnail_format_is_rejected(): void
    {
        Storage::fake('public');

        $pdf = UploadedFile::fake()->create('dokumen.pdf', 500, 'application/pdf');
        $txtThumb = UploadedFile::fake()->create('cover.txt', 50, 'text/plain');

        $response = $this->actingAs($this->admin)->post(route('admin.documents.store'), [
            'title' => 'Dokumen Thumb Salah',
            'published_at' => '2026-01-01',
            'pdf_file' => $pdf,
            'thumbnail_file' => $txtThumb,
        ]);

        $response->assertSessionHasErrors('thumbnail_file');
    }

    public function test_admin_can_edit_document_without_reuploading_pdf(): void
    {
        $doc = PublicDocument::factory()->create([
            'title' => 'Judul Lama',
            'published_at' => '2025-01-01',
            'file_path' => 'documents/existing.pdf',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.documents.update', $doc), [
            'title' => 'Judul Baru yang Diperbarui',
            'published_at' => '2025-06-01',
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('admin.documents.index'));
        $response->assertSessionHas('success', 'Dokumen berhasil diperbarui.');

        $doc->refresh();
        $this->assertSame('Judul Baru yang Diperbarui', $doc->title);
        $this->assertSame('2025-06-01', $doc->published_at->format('Y-m-d'));
        $this->assertFalse($doc->is_active);
        $this->assertSame('documents/existing.pdf', $doc->file_path);
    }

    public function test_admin_can_replace_pdf_and_old_file_is_cleaned_up(): void
    {
        Storage::fake('public');

        $oldPdf = UploadedFile::fake()->create('old.pdf', 500, 'application/pdf');
        $oldPath = $oldPdf->store('documents', 'public');

        $doc = PublicDocument::factory()->create([
            'file_path' => $oldPath,
        ]);

        $newPdf = UploadedFile::fake()->create('new.pdf', 800, 'application/pdf');

        $this->actingAs($this->admin)->put(route('admin.documents.update', $doc), [
            'title' => $doc->title,
            'published_at' => $doc->published_at->format('Y-m-d'),
            'pdf_file' => $newPdf,
            'is_active' => '1',
        ]);

        $doc->refresh();
        $this->assertNotSame($oldPath, $doc->file_path);
        Storage::disk('public')->assertExists($doc->file_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_admin_can_remove_thumbnail(): void
    {
        Storage::fake('public');

        $thumb = UploadedFile::fake()->image('thumb.png', 100, 100);
        $thumbPath = $thumb->store('document-thumbnails', 'public');

        $doc = PublicDocument::factory()->create([
            'thumbnail_path' => $thumbPath,
        ]);

        $this->actingAs($this->admin)->put(route('admin.documents.update', $doc), [
            'title' => $doc->title,
            'published_at' => $doc->published_at->format('Y-m-d'),
            'remove_thumbnail' => '1',
            'is_active' => '1',
        ]);

        $doc->refresh();
        $this->assertNull($doc->thumbnail_path);
        Storage::disk('public')->assertMissing($thumbPath);
    }

    public function test_admin_can_delete_document_and_files_are_deleted(): void
    {
        Storage::fake('public');

        $pdf = UploadedFile::fake()->create('todelete.pdf', 500, 'application/pdf');
        $pdfPath = $pdf->store('documents', 'public');

        $thumb = UploadedFile::fake()->image('todelete.jpg', 100, 100);
        $thumbPath = $thumb->store('document-thumbnails', 'public');

        $doc = PublicDocument::factory()->create([
            'file_path' => $pdfPath,
            'thumbnail_path' => $thumbPath,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.documents.destroy', $doc));

        $response->assertRedirect(route('admin.documents.index'));
        $response->assertSessionHas('success', 'Dokumen berhasil dihapus.');

        $this->assertDatabaseMissing('public_documents', ['id' => $doc->id]);
        Storage::disk('public')->assertMissing($pdfPath);
        Storage::disk('public')->assertMissing($thumbPath);
    }

    public function test_database_seeder_is_idempotent_and_does_not_duplicate_renstra(): void
    {
        $seeder = new \Database\Seeders\DatabaseSeeder();

        // Run twice
        $seeder->run();
        $seeder->run();

        $renstraCount = PublicDocument::where('file_path', 'documents/renstra-dinas-pendidikan-teluk-bintuni.pdf')->count();
        $this->assertSame(1, $renstraCount);
    }
}

