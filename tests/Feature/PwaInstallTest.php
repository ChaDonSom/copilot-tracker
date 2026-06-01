<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaInstallTest extends TestCase
{
    public function test_welcome_page_includes_manifest_link(): void
    {
        $html = view('welcome')->render();

        $this->assertStringContainsString(
            '<link rel="manifest" href="' . asset('manifest.json') . '">',
            $html
        );
    }

    public function test_manifest_and_service_worker_files_exist(): void
    {
        $this->assertFileExists(public_path('manifest.json'));
        $this->assertFileExists(public_path('service-worker.js'));
    }
}
