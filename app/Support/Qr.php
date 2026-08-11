<?php

namespace App\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

/**
 * QR codes as inline data URIs — usable in Blade and in DomPDF alike.
 */
class Qr
{
    public static function dataUri(string $data, int $size = 200): string
    {
        return (new Builder(
            writer: new PngWriter,
            data: $data,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 8,
        ))->build()->getDataUri();
    }
}
