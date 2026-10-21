<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\ByteMatrix;
use BaconQrCode\Encoder\Encoder;

/**
 * QR код үүсгэгч: SVG (дэлгэц, хэвлэхэд) болон PNG (татахад).
 * Хоёулаа нэмэлт өргөтгөл (GD, Imagick) шаардахгүй: PNG-г цэвэр PHP-ээр (zlib) бичнэ.
 */
class QrPng
{
    /**
     * QR кодыг SVG болгоно (хэвлэхэд ямар ч хэмжээнд тод).
     *
     * @param  string  $content    QR дотор кодлох текст (жишээ нь компьютерийн хуудасны URL)
     * @param  int     $quietZone  Эргэн тойрны цагаан зай (модулиар), уншигчид хамгийн багадаа 4 хэрэгтэй
     */
    public static function svg(string $content, int $quietZone = 4): string
    {
        $matrix = self::matrix($content);
        $modules = $matrix->getWidth();
        $total = $modules + 2 * $quietZone;

        // Мөр бүрийн дараалсан хар модулиудыг нэг тэгш өнцөгт болгож нийлүүлнэ
        $path = '';
        for ($y = 0; $y < $modules; $y++) {
            $x = 0;
            while ($x < $modules) {
                if ($matrix->get($x, $y) !== 1) {
                    $x++;

                    continue;
                }
                $start = $x;
                while ($x < $modules && $matrix->get($x, $y) === 1) {
                    $x++;
                }
                $len = $x - $start;
                $path .= 'M'.($start + $quietZone).' '.($y + $quietZone).'h'.$len.'v1h-'.$len.'z';
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$total.' '.$total.'"'
            .' shape-rendering="crispEdges" role="img" aria-label="QR код">'
            .'<rect width="'.$total.'" height="'.$total.'" fill="#fff"/>'
            .'<path d="'.$path.'" fill="#000"/></svg>';
    }

    /**
     * QR кодыг PNG зураг болгоно.
     *
     * @param  int  $targetSize  Зургийн ойролцоо хэмжээ (пиксел). Модуль бүр бүхэл пикселтэй байхаар тохируулна.
     * @return string  PNG-ийн бинар агуулга
     */
    public static function render(string $content, int $targetSize = 640, int $quietZone = 4): string
    {
        $matrix = self::matrix($content);

        $modules = $matrix->getWidth();
        $total = $modules + 2 * $quietZone;
        $scale = max(8, intdiv($targetSize, $total));
        $size = $total * $scale;

        // 1 битийн саарал өнгөт PNG: бит 1 = цагаан, 0 = хар. Мөр бүр: шүүлтүүрийн байт (0) + пикселүүд
        $raw = '';
        for ($my = 0; $my < $total; $my++) {
            $bits = '';
            for ($mx = 0; $mx < $total; $mx++) {
                $x = $mx - $quietZone;
                $y = $my - $quietZone;
                $dark = $x >= 0 && $y >= 0 && $x < $modules && $y < $modules && $matrix->get($x, $y) === 1;
                $bits .= str_repeat($dark ? '0' : '1', $scale);
            }
            $bits = str_pad($bits, (int) ceil($size / 8) * 8, '1'); // байтын төгсгөлийг цагаанаар дүүргэнэ
            $row = "\0";
            foreach (str_split($bits, 8) as $byte) {
                $row .= chr(bindec($byte));
            }
            $raw .= str_repeat($row, $scale); // модулийн өндөр
        }

        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $size, $size, 1, 0, 0, 0, 0)) // өргөн, өндөр, 1 бит, саарал
            .$chunk('IDAT', gzcompress($raw, 9))
            .$chunk('IEND', '');
    }

    private static function matrix(string $content): ByteMatrix
    {
        return Encoder::encode($content, ErrorCorrectionLevel::M())->getMatrix();
    }
}