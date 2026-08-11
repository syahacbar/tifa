<?php

namespace Tests\Feature;

use Tests\TestCase;

class InspectDapodikCommandTest extends TestCase
{
    public function test_command_inspects_the_workbook_without_writing_to_the_database(): void
    {
        $path = storage_path('app/imports/rekap-dapodik-juni-2026.xlsx');
        if (! is_file($path)) {
            $this->markTestSkipped('Workbook Dapodik privat tidak tersedia.');
        }

        $modifiedAt = filemtime($path);

        $this->artisan('tifa:inspect-dapodik')
            ->expectsOutputToContain('Inspeksi workbook Dapodik (read-only)')
            ->expectsOutputToContain('Sheet data sekolah')
            ->expectsOutputToContain('Nilai unik yang dikenali')
            ->assertSuccessful();

        clearstatcache(true, $path);
        $this->assertSame($modifiedAt, filemtime($path));
    }
}
