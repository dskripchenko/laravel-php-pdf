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

];
