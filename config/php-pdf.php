<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Page defaults
    |--------------------------------------------------------------------------
    |
    | Applied to every document rendered through the Pdf facade. Paper is one
    | of: a3, a4, a5, a6, letter, legal, tabloid, executive. Margins are in
    | points (1 mm = 2.834646 pt); null keeps the library default (~20 mm).
    |
    */

    'paper' => env('PHP_PDF_PAPER', 'a4'),

    'orientation' => env('PHP_PDF_ORIENTATION', 'portrait'),

    'margins' => [
        'top' => null,
        'right' => null,
        'bottom' => null,
        'left' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fonts
    |--------------------------------------------------------------------------
    |
    | Without any font configuration the PDF base-14 fonts are used
    | (Latin/WinAnsi only). To embed TrueType fonts — required for Cyrillic,
    | Greek, Arabic, CJK — point `default` (and optionally the variants) at
    | .ttf files. `families` registers named families addressable from
    | RunStyle(fontFamily: ...) and CSS font-family.
    |
    | 'default'     => storage_path('fonts/DejaVuSans.ttf'),
    | 'bold'        => storage_path('fonts/DejaVuSans-Bold.ttf'),
    | 'italic'      => storage_path('fonts/DejaVuSans-Oblique.ttf'),
    | 'bold_italic' => storage_path('fonts/DejaVuSans-BoldOblique.ttf'),
    | 'families'    => [
    |     'mono' => [
    |         'regular' => storage_path('fonts/DejaVuSansMono.ttf'),
    |         'bold'    => storage_path('fonts/DejaVuSansMono-Bold.ttf'),
    |     ],
    | ],
    |
    */

    'fonts' => [
        'default' => env('PHP_PDF_FONT'),
        'bold' => env('PHP_PDF_FONT_BOLD'),
        'italic' => env('PHP_PDF_FONT_ITALIC'),
        'bold_italic' => env('PHP_PDF_FONT_BOLD_ITALIC'),
        'families' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default document metadata
    |--------------------------------------------------------------------------
    |
    | Merged into every document's /Info dictionary; per-document metadata
    | passed to Pdf::fromHtml(..., metadata: [...]) wins.
    |
    */

    'metadata' => [
        // 'Author' => 'ACME Corp.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Digital signatures
    |--------------------------------------------------------------------------
    |
    | The key Pdf::sign(), ->signed() and Pdf::signatureConfig() use: a PEM
    | certificate and key (file paths or PEM text), or a PKCS#12 container.
    | `chain` lists intermediate certificates. `format` is `pades`
    | (ETSI.CAdES.detached, the default) or `pkcs7` (adbe.pkcs7.detached).
    | With a timestamp authority URL, signatures carry an RFC 3161 timestamp
    | (PAdES B-T). GOST keys need OpenSSL's gost engine loaded into PHP.
    |
    */

    'signing' => [
        'certificate' => env('PHP_PDF_SIGN_CERT'),
        'key' => env('PHP_PDF_SIGN_KEY'),
        'passphrase' => env('PHP_PDF_SIGN_PASSPHRASE'),
        'pkcs12' => env('PHP_PDF_SIGN_P12'),
        'pkcs12_password' => env('PHP_PDF_SIGN_P12_PASSWORD'),
        'chain' => [],
        'format' => env('PHP_PDF_SIGN_FORMAT', 'pades'),
        'signer_name' => null,
        'reason' => null,
        'location' => null,
        'contact' => null,
        'timestamp' => [
            'url' => env('PHP_PDF_TSA_URL'),
            'username' => env('PHP_PDF_TSA_USERNAME'),
            'password' => env('PHP_PDF_TSA_PASSWORD'),
            'policy' => null,
            'headers' => [],
            'timeout' => 15,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature validation
    |--------------------------------------------------------------------------
    |
    | Trust anchors for Pdf::validate() — root certificates (file paths or
    | PEM text) — and whether to add OpenSSL's system roots. With
    | `fetch_revocation`, revocation data missing from a document is fetched
    | from OCSP responders and CRLs (Pdf::ltv() always fetches).
    |
    */

    'validation' => [
        'trust' => [],
        'system_trust' => false,
        'fetch_revocation' => false,
    ],

];
