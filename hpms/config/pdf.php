<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDF rendering engine
    |--------------------------------------------------------------------------
    |
    | Dompdf does not shape complex scripts, so Bengali text (conjuncts,
    | vowel signs, reordered matras) comes out mangled. A local headless
    | Chrome/Chromium renders the same HTML with HarfBuzz shaping instead,
    | offline and without external services.
    |
    | Supported values: "auto" (Chromium when available, otherwise Dompdf),
    | "chromium", "dompdf".
    |
    */

    'driver' => env('PDF_DRIVER', 'auto'),

    /*
    | Explicit path to a Chrome/Chromium/Edge binary. Leave empty to let the
    | renderer search PATH and the usual install locations.
    */

    'binary' => env('PDF_CHROME_BINARY'),

    /*
    | Seconds to wait for the browser before giving up on a document.
    */

    'timeout' => (int) env('PDF_TIMEOUT', 30),

    /*
    | When Chromium is configured but fails, fall back to Dompdf so reports
    | are still delivered (with unshaped Bengali text) and the failure is
    | written to the application log.
    */

    'fallback' => (bool) env('PDF_FALLBACK', true),

];
