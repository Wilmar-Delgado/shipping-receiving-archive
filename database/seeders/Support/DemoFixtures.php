<?php

namespace Database\Seeders\Support;

final class DemoFixtures
{
    public static function pdf(string $title): string
    {
        $title = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $title);
        $stream = 'BT /F1 20 Tf 50 735 Td (FICTIONAL DEMO DOCUMENT) Tj /F1 12 Tf 0 -40 Td ('.$title.') Tj 0 -30 Td (For local Archive development and preview testing only.) Tj 0 -25 Td (Contains no real shipping, receiving, or customer data.) Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    public static function png(): string
    {
        // A generated fictional paper image: no uploaded or third-party source file.
        $width = 480;
        $height = 320;
        $pixels = '';
        for ($y = 0; $y < $height; $y++) {
            $pixels .= "\0";
            for ($x = 0; $x < $width; $x++) {
                $header = $x >= 30 && $x < 450 && $y >= 25 && $y < 65;
                $line = $x >= 30 && $x < 350 && $y >= 100 && $y < 250 && $y % 30 < 8;
                $pixels .= $header ? "\x16\x78\x73" : ($line ? "\xB8\xC9\xCC" : "\xF6\xF9\xFA");
            }
        }
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('tEXt', 'Description'."\0".'Fictional Archive development fixture; no real document data.')
            .$chunk('IDAT', gzcompress($pixels)).$chunk('IEND', '');
    }
}
