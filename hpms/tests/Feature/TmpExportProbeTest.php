<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmpExportProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe_exports_in_bangla(): void
    {
        $user = User::factory()->create(['locale' => 'bn']);
        $this->actingAs($user);

        $renderer = app(PdfRenderer::class);
        fwrite(STDERR, 'driver='.$renderer->driver().' binary='.var_export($renderer->browserBinary(), true).PHP_EOL);

        foreach (['csv', 'xlsx', 'pdf'] as $kind) {
            $start = microtime(true);
            $response = $this->get('/reports/export.'.$kind);
            $elapsed = round(microtime(true) - $start, 2);
            $content = $response->getContent();
            fwrite(STDERR, sprintf(
                "%s status=%s time=%ss bytes=%s type=%s head=%s\n",
                $kind,
                $response->getStatusCode(),
                $elapsed,
                strlen($content),
                $response->headers->get('Content-Type'),
                substr(preg_replace('/\s+/', ' ', $content), 0, 40),
            ));
            file_put_contents('/tmp/export-'.$kind.'.bin', $content);
        }

        $this->assertTrue(true);
    }
}
