<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminComplaintTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_complaint_defaults_to_status_baru(): void
    {
        $complaint = Complaint::factory()->create([
            'status' => Complaint::STATUS_BARU,
        ]);

        $this->assertSame('baru', $complaint->status);
        $this->assertSame('Baru', $complaint->status_label);
    }

    public function test_guest_cannot_access_admin_complaints_index(): void
    {
        $response = $this->get(route('admin.complaints.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_admin_can_view_complaints_index(): void
    {
        Complaint::factory()->create([
            'name' => 'Yakob Pattinama',
            'phone' => '081234567890',
            'complaint_type' => 'Sarana Prasarana',
            'status' => Complaint::STATUS_BARU,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.complaints.index'));

        $response->assertOk()
            ->assertSee('Pengaduan Masuk')
            ->assertSee('Yakob Pattinama')
            ->assertSee('081234567890')
            ->assertSee('Sarana Prasarana')
            ->assertSee('Baru');
    }

    public function test_complaints_are_ordered_by_created_at_desc(): void
    {
        $c1 = Complaint::factory()->create([
            'name' => 'Pengaduan Pertama',
            'created_at' => now()->subDays(2),
        ]);

        $c2 = Complaint::factory()->create([
            'name' => 'Pengaduan Terbaru',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.complaints.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['Pengaduan Terbaru', 'Pengaduan Pertama']);
    }

    public function test_status_filtering_works(): void
    {
        Complaint::factory()->create(['name' => 'Pengaduan Baru', 'status' => 'baru']);
        Complaint::factory()->create(['name' => 'Pengaduan Diproses', 'status' => 'diproses']);
        Complaint::factory()->create(['name' => 'Pengaduan Selesai', 'status' => 'selesai']);

        // Filter Baru
        $resBaru = $this->actingAs($this->admin)->get(route('admin.complaints.index', ['status' => 'baru']));
        $resBaru->assertOk()->assertSee('Pengaduan Baru')->assertDontSee('Pengaduan Selesai');

        // Filter Diproses
        $resDiproses = $this->actingAs($this->admin)->get(route('admin.complaints.index', ['status' => 'diproses']));
        $resDiproses->assertOk()->assertSee('Pengaduan Diproses')->assertDontSee('Pengaduan Baru');

        // Filter Selesai
        $resSelesai = $this->actingAs($this->admin)->get(route('admin.complaints.index', ['status' => 'selesai']));
        $resSelesai->assertOk()->assertSee('Pengaduan Selesai')->assertDontSee('Pengaduan Baru');
    }

    public function test_admin_can_view_complaint_detail(): void
    {
        $complaint = Complaint::factory()->create([
            'name' => 'Petrus Salossa',
            'phone' => '082199887766',
            'complaint_type' => 'Pelayanan Pendidikan',
            'complaint_text' => 'Ini adalah isi pengaduan lengkap dari masyarakat Teluk Bintuni.',
            'status' => 'baru',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.complaints.show', $complaint));

        $response->assertOk()
            ->assertSee('Pengaduan #' . $complaint->id)
            ->assertSee('Petrus Salossa')
            ->assertSee('082199887766')
            ->assertSee('Ini adalah isi pengaduan lengkap dari masyarakat Teluk Bintuni.');
    }

    public function test_admin_can_update_complaint_status(): void
    {
        $complaint = Complaint::factory()->create([
            'status' => 'baru',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.complaints.status', $complaint), [
            'status' => 'diproses',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Status pengaduan berhasil diperbarui.');

        $complaint->refresh();
        $this->assertSame('diproses', $complaint->status);

        // Update to selesai
        $this->actingAs($this->admin)->patch(route('admin.complaints.status', $complaint), [
            'status' => 'selesai',
        ]);

        $complaint->refresh();
        $this->assertSame('selesai', $complaint->status);
    }

    public function test_invalid_status_update_is_rejected(): void
    {
        $complaint = Complaint::factory()->create([
            'status' => 'baru',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.complaints.status', $complaint), [
            'status' => 'invalid_status',
        ]);

        $response->assertSessionHasErrors('status');
        $complaint->refresh();
        $this->assertSame('baru', $complaint->status);
    }

    public function test_guest_cannot_update_status(): void
    {
        $complaint = Complaint::factory()->create(['status' => 'baru']);

        $response = $this->patch(route('admin.complaints.status', $complaint), [
            'status' => 'selesai',
        ]);

        $response->assertRedirect(route('admin.login'));
        $complaint->refresh();
        $this->assertSame('baru', $complaint->status);
    }

    public function test_admin_can_download_private_attachment(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('berkas-lampiran.pdf', 300, 'application/pdf');
        $path = $file->store('complaints', 'local');

        $complaint = Complaint::factory()->create([
            'attachment_path' => $path,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.complaints.attachment', $complaint));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=lampiran-pengaduan-' . $complaint->id . '.pdf');
    }

    public function test_guest_cannot_download_attachment(): void
    {
        $complaint = Complaint::factory()->withAttachment()->create();

        $response = $this->get(route('admin.complaints.attachment', $complaint));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_downloading_non_existent_attachment_returns_404(): void
    {
        $complaint = Complaint::factory()->create([
            'attachment_path' => null,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.complaints.attachment', $complaint));

        $response->assertNotFound();
    }

    public function test_path_traversal_attempt_in_attachment_download_is_blocked(): void
    {
        Storage::fake('local');

        $complaint = Complaint::factory()->create([
            'attachment_path' => '../secret.env',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.complaints.attachment', $complaint));

        $response->assertNotFound();
    }
}
