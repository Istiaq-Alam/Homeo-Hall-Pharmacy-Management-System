<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDF rendering engine
    |--------------------------------------------------------------------------
    |
    | Dompdf does not shape complex scripts, so Bengali text (conjuncts,
    | vowel signs, reordered matras) comes out mangled. WeasyPrint and headless
    | Chrome/Chromium shape through Pango/HarfBuzz instead, offline and without
    | external services. WeasyPrint is preferred because it starts in about a
    | second, while a browser start-up costs several seconds of CPU -- which is
    | the difference between a PDF and a timeout on a small instance.
    |
    | Supported values: "auto" (WeasyPrint, then Chromium, then Dompdf),
    | "weasyprint", "chromium", "dompdf". A configured engine that is not
    | installed falls back to the next available one.
    |
    */

    'driver' => env('PDF_DRIVER', 'auto'),

    /*
    | Explicit path to the WeasyPrint binary. Leave empty to search PATH.
    */

    'weasyprint' => env('PDF_WEASYPRINT_BINARY'),

    /*
    | Explicit path to a Chrome/Chromium/Edge binary. Leave empty to let the
    | renderer search PATH and the usual install locations.
    */

    'binary' => env('PDF_CHROME_BINARY'),

    /*
    | Seconds to wait for the rendering engine before giving up on a document.
    | Generous, because a fractional CPU share makes the first render slow.
    */

    'timeout' => (int) env('PDF_TIMEOUT', 60),

    /*
    | When an engine fails, try the next one so reports are still delivered
    | (Dompdf output has unshaped Bengali text) and the failure is written to
    | the application log.
    */

    'fallback' => (bool) env('PDF_FALLBACK', true),

];
