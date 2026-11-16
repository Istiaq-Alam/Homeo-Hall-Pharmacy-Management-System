<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualController extends Controller
{
    private const FILE_NAME = 'USER_MANUAL_BN.pdf';

    public function index(): View
    {
        $path = $this->pdfPath();
        abort_if($path === null, 404, __('manual.missing'));

        return view('manual.index', [
            'fileName' => self::FILE_NAME,
            'fileSize' => round(filesize($path) / 1048576, 1),
            'updatedAt' => CarbonImmutable::createFromTimestamp(filemtime($path)),
        ]);
    }

    public function pdf(): Response
    {
        $path = $this->pdfPath();
        abort_if($path === null, 404, __('manual.missing'));

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function download(): BinaryFileResponse
    {
        $path = $this->pdfPath();
        abort_if($path === null, 404, __('manual.missing'));

        return response()->download($path, self::FILE_NAME, ['Content-Type' => 'application/pdf']);
    }

    private function pdfPath(): ?string
    {
        foreach ([base_path('docs/'.self::FILE_NAME), base_path('../docs/'.self::FILE_NAME)] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
