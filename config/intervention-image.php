<?php

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

return [

    /*
    | Image driver: prefer Imagick when the extension is loaded, otherwise GD.
    | Override with IMAGE_DRIVER (a fully-qualified driver class name).
    */

    'driver' => env('IMAGE_DRIVER') ?: (extension_loaded('imagick') ? ImagickDriver::class : GdDriver::class),

    'options' => [
        'autoOrientation' => true,
        'decodeAnimation' => true,
        'backgroundColor' => 'ffffff',
        'strip' => false,
    ],
];
