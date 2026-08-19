<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RenstraDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_renstra_document_is_publicly_accessible_and_the_homepage_exposes_a_preview_modal(): void
    {
        $path = '/documents/renstra-dinas-pendidikan-teluk-bintuni.pdf';

        $this->assertFileExists(public_path(ltrim($path, '/')));
        $this->assertStringStartsWith('%PDF-', (string) file_get_contents(public_path(ltrim($path, '/')), false, null, 0, 5));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('role="dialog"', false)
            ->assertSee('z-[2100]', false)
            ->assertSee('bg-slate-950/65', false)
            ->assertSee('border-slate-200 bg-white shadow-2xl', false)
            ->assertSee('Rencana Strategis Dinas Pendidikan Kabupaten Teluk Bintuni')
            ->assertSee('title="Preview Rencana Strategis Dinas Pendidikan Kabupaten Teluk Bintuni"', false)
            ->assertSee('href="'.$path.'"', false)
            ->assertSee('download="renstra-dinas-pendidikan-teluk-bintuni.pdf"', false)
            ->assertSee('Download PDF')
            ->assertSee('closeRenstra()', false)
            ->assertSee('@keydown.escape.window', false)
            ->assertSee('overflow-hidden', false);
    }
}
