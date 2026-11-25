<?php

return [

    /*
    | Logical canvas resolution. A print area is drawn at this many canvas pixels per
    | centimetre, and exported at `export_dpi` by scaling up (never by re-drawing).
    */

    'px_per_cm' => 20,
    'export_dpi' => 300,
    'min_export_dpi' => 240,

    // Browsers (notably iOS Safari) cap canvas size, so larger exports are scaled to fit.
    'max_export_pixels' => 16000000,

    // Warn when an image would print below this resolution at its placed size.
    'min_image_dpi' => 150,

    'max_upload_kb' => 20480,

    /*
    | Printable colours, to be confirmed with the print shop.
    */

    'palette' => [
        ['name' => 'White', 'hex' => '#FFFFFF'],
        ['name' => 'Black', 'hex' => '#111111'],
        ['name' => 'Charcoal', 'hex' => '#4B5563'],
        ['name' => 'Silver', 'hex' => '#C0C4CC'],
        ['name' => 'Red', 'hex' => '#D32F2F'],
        ['name' => 'Orange', 'hex' => '#F57C00'],
        ['name' => 'Yellow', 'hex' => '#FBC02D'],
        ['name' => 'Gold', 'hex' => '#D4AF37'],
        ['name' => 'Green', 'hex' => '#2E7D32'],
        ['name' => 'Lime', 'hex' => '#8BC34A'],
        ['name' => 'Sky blue', 'hex' => '#29B6F6'],
        ['name' => 'Royal blue', 'hex' => '#1D4ED8'],
        ['name' => 'Navy', 'hex' => '#1F2A44'],
        ['name' => 'Purple', 'hex' => '#7B1FA2'],
        ['name' => 'Pink', 'hex' => '#E91E63'],
        ['name' => 'Brown', 'hex' => '#6D4C41'],
    ],

    // Curated fonts, to be confirmed with the print shop. Loaded from fonts.bunny.net.
    'fonts' => ['Anton', 'Bebas Neue', 'Oswald', 'Montserrat', 'Playfair Display', 'Roboto Slab', 'Pacifico', 'Permanent Marker'],

];
