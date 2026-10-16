<?php

namespace App\Services\Media;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QrCodeGenerator
{
    private const DISK = 'public';

    /** @return string nama file PNG di dalam $directory */
    public function store(string $content, string $directory, string $prefix = 'qrcode'): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'scale' => 12,
            'quietzoneSize' => 2,
        ]);

        $png = (new QRCode($options))->render($content);

        if (str_starts_with($png, 'data:image')) {
            $png = base64_decode(Str::after($png, ','));
        }

        $filename = $prefix.'-'.Str::lower(Str::random(12)).'.png';
        Storage::disk(self::DISK)->put(trim($directory, '/').'/'.$filename, $png);

        return $filename;
    }

    /** QR langsung sebagai data URI PNG (tidak disimpan), mis. untuk e-ticket & PDF. */
    public function dataUri(string $content, int $scale = 8): string
    {
        $png = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'scale' => $scale,
            'quietzoneSize' => 2,
        ])))->render($content);

        return str_starts_with($png, 'data:image') ? $png : 'data:image/png;base64,'.base64_encode($png);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
