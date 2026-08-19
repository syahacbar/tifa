<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_admin_login_page(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk()
            ->assertSee('Administrasi TIFAA')
            ->assertSee('Alamat Email')
            ->assertSee('Kata Sandi')
            ->assertSee('Masuk ke Panel Admin');
    }

    public function test_guest_cannot_access_admin_dashboard_and_is_redirected_to_admin_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@telukbintunikab.go.id',
            'password' => Hash::make('rahasia123'),
        ]);

        $response = $this->post(route('admin.login.store'), [
            'email' => 'admin@telukbintunikab.go.id',
            'password' => 'rahasia123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'admin@telukbintunikab.go.id',
            'password' => Hash::make('rahasia123'),
        ]);

        $response = $this->post(route('admin.login.store'), [
            'email' => 'admin@telukbintunikab.go.id',
            'password' => 'passwordsalah',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_fails_with_unregistered_email_and_shows_generic_error(): void
    {
        $response = $this->post(route('admin.login.store'), [
            'email' => 'tidakada@telukbintunikab.go.id',
            'password' => 'rahasia123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Email atau kata sandi tidak sesuai.',
        ]);
        $this->assertGuest();
    }

    public function test_login_validation_rejects_empty_inputs(): void
    {
        $response = $this->post(route('admin.login.store'), [
            'email' => '',
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_authenticated_admin_can_access_dashboard(): void
    {
        $user = User::factory()->create([
            'name' => 'Operator TIFAA',
            'email' => 'operator@telukbintunikab.go.id',
        ]);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Administrasi TIFAA')
            ->assertSee('Kelola layanan dan informasi publik TIFAA.')
            ->assertSee('Operator TIFAA')
            ->assertSee('Ruang Informasi')
            ->assertSee('Pengaduan Layanan');
    }

    public function test_authenticated_admin_visiting_login_page_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.login'));

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_can_logout_via_post_request(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.logout'));

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest();

        // Dashboard is protected again
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_create_admin_command_creates_user_with_hashed_password(): void
    {
        $this->artisan('tifa:admin-create', [
            '--name' => 'Admin Baru',
            '--email' => 'adminbaru@telukbintunikab.go.id',
            '--password' => 'password12345',
        ])
            ->assertSuccessful();

        $user = User::where('email', 'adminbaru@telukbintunikab.go.id')->first();
        $this->assertNotNull($user);
        $this->assertSame('Admin Baru', $user->name);
        $this->assertTrue(Hash::check('password12345', $user->password));
    }

    public function test_create_admin_command_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'duplicate@telukbintunikab.go.id',
        ]);

        $this->artisan('tifa:admin-create', [
            '--name' => 'Admin Dua',
            '--email' => 'duplicate@telukbintunikab.go.id',
            '--password' => 'password12345',
        ])
            ->assertFailed();

        $this->assertSame(1, User::where('email', 'duplicate@telukbintunikab.go.id')->count());
    }
}
