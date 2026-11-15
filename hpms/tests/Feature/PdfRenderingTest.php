<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class PdfRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_export_falls_back_to_dompdf_when_no_shaping_engine_is_available(): void
    {
        config([
            'pdf.driver' => 'auto',
            'pdf.binary' => '/nonexistent/browser',
            'pdf.weasyprint' => '/nonexistent/weasyprint',
        ]);

        $owner = User::factory()->create();

        $this->assertSame('dompdf', app(PdfRenderer::class)->driver());

        $response = $this->actingAs($owner)->get('/reports/export.pdf?from=2026-11-01&to=2026-11-30');

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function test_a_configured_browser_binary_is_used_to_render_the_pdf(): void
    {
        $binary = storage_path('app/fake-chrome-'.Str::random(8));
        File::put($binary, implode("\n", [
            '#!/usr/bin/env bash',
            'for arg in "$@"; do',
            '  case "$arg" in',
            '    --print-to-pdf=*) echo -n "%PDF-1.4 rendered by fake browser" > "${arg#--print-to-pdf=}" ;;',
            '  esac',
            'done',
            '',
        ]));
        chmod($binary, 0755);

        try {
            config([
                'pdf.driver' => 'chromium',
                'pdf.binary' => $binary,
                'pdf.weasyprint' => '/nonexistent/weasyprint',
            ]);

            $owner = User::factory()->create();

            $this->assertSame('chromium', app(PdfRenderer::class)->driver());

            $response = $this->actingAs($owner)->get('/reports/export.pdf?from=2026-11-01&to=2026-11-30');

            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertSame('%PDF-1.4 rendered by fake browser', $response->getContent());
        } finally {
            File::delete($binary);
        }
    }

    public function test_weasyprint_is_preferred_and_used_when_configured(): void
    {
        $binary = storage_path('app/fake-weasyprint-'.Str::random(8));
        File::put($binary, implode("\n", [
            '#!/usr/bin/env bash',
            '# The PDF path is the last argument on the WeasyPrint command line.',
            'for arg in "$@"; do out="$arg"; done',
            'echo -n "%PDF-1.4 rendered by fake weasyprint" > "$out"',
            '',
        ]));
        chmod($binary, 0755);

        try {
            config([
                'pdf.driver' => 'auto',
                'pdf.binary' => '/nonexistent/browser',
                'pdf.weasyprint' => $binary,
            ]);

            $owner = User::factory()->create();

            $this->assertSame('weasyprint', app(PdfRenderer::class)->driver());

            $response = $this->actingAs($owner)->get('/reports/export.pdf?from=2026-11-01&to=2026-11-30');

            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertSame('%PDF-1.4 rendered by fake weasyprint', $response->getContent());
        } finally {
            File::delete($binary);
        }
    }

    public function test_the_export_falls_through_to_the_next_engine_when_one_fails(): void
    {
        $failing = storage_path('app/failing-weasyprint-'.Str::random(8));
        File::put($failing, "#!/usr/bin/env bash\necho 'boom' >&2\nexit 3\n");
        chmod($failing, 0755);

        $browser = storage_path('app/fake-chrome-'.Str::random(8));
        File::put($browser, implode("\n", [
            '#!/usr/bin/env bash',
            'for arg in "$@"; do',
            '  case "$arg" in',
            '    --print-to-pdf=*) echo -n "%PDF-1.4 rendered by fake browser" > "${arg#--print-to-pdf=}" ;;',
            '  esac',
            'done',
            '',
        ]));
        chmod($browser, 0755);

        try {
            config([
                'pdf.driver' => 'auto',
                'pdf.weasyprint' => $failing,
                'pdf.binary' => $browser,
                'pdf.fallback' => true,
            ]);

            $this->assertSame('%PDF-1.4 rendered by fake browser', app(PdfRenderer::class)->render('<html><body>ok</body></html>'));
        } finally {
            File::delete([$failing, $browser]);
        }
    }
}
