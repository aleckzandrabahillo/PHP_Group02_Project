<?php

namespace App\Core;

final class Captcha
{
    private const CHARS = 'ACEHKMNPRST23456789';

    private const GLYPHS = [
        'A'=>['01110','10001','10001','11111','10001','10001','10001'],
        'C'=>['01111','10000','10000','10000','10000','10000','01111'],
        'E'=>['11111','10000','10000','11110','10000','10000','11111'],
        'H'=>['10001','10001','10001','11111','10001','10001','10001'],
        'K'=>['10001','10010','10100','11000','10100','10010','10001'],
        'M'=>['10001','11011','10101','10101','10001','10001','10001'],
        'N'=>['10001','11001','10101','10011','10001','10001','10001'],
        'P'=>['11110','10001','10001','11110','10000','10000','10000'],
        'R'=>['11110','10001','10001','11110','10100','10010','10001'],
        'S'=>['01111','10000','10000','01110','00001','00001','11110'],
        'T'=>['11111','00100','00100','00100','00100','00100','00100'],
        '2'=>['01110','10001','00001','00010','00100','01000','11111'],
        '3'=>['11110','00001','00001','01110','00001','00001','11110'],
        '4'=>['00010','00110','01010','10010','11111','00010','00010'],
        '5'=>['11111','10000','10000','11110','00001','00001','11110'],
        '6'=>['01110','10000','10000','11110','10001','10001','01110'],
        '7'=>['11111','00001','00010','00100','01000','01000','01000'],
        '8'=>['01110','10001','10001','01110','10001','10001','01110'],
        '9'=>['01110','10001','10001','01111','00001','00001','01110'],
    ];

    public static function refresh(): void
    {
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code .= self::CHARS[random_int(0, strlen(self::CHARS) - 1)];
        }
        Session::put('_captcha', ['code' => $code, 'created_at' => time()]);
    }

    public static function ensure(): void
    {
        $data = Session::get('_captcha');
        if (!is_array($data) || empty($data['code']) || (time() - (int) ($data['created_at'] ?? 0)) > 600) {
            self::refresh();
        }
    }

    public static function verify(string $answer): bool
    {
        $data = Session::get('_captcha');
        if (!is_array($data) || empty($data['code'])) return false;
        $fresh = (time() - (int) ($data['created_at'] ?? 0)) <= 600;
        $ok = $fresh && hash_equals((string) $data['code'], strtoupper(trim($answer)));
        self::refresh();
        return $ok;
    }

    public static function svg(): string
    {
        self::ensure();
        $data = Session::get('_captcha');
        $code = (string) $data['code'];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="70" viewBox="0 0 220 70" role="img" aria-label="CAPTCHA image">';
        $svg .= '<rect width="220" height="70" rx="12" fill="#FFE5EE"/>';
        for ($i=0; $i<9; $i++) {
            $x1=random_int(0,220); $y1=random_int(0,70); $x2=random_int(0,220); $y2=random_int(0,70);
            $svg .= '<line x1="'.$x1.'" y1="'.$y1.'" x2="'.$x2.'" y2="'.$y2.'" stroke="#E4B3C3" stroke-width="'.random_int(1,2).'" opacity="0.72"/>';
        }
        foreach (str_split($code) as $i => $char) {
            $pattern = self::GLYPHS[$char];
            $cell = 4.2;
            $startX = 17 + ($i * 40);
            $startY = 18 + random_int(-3,3);
            $cx = $startX + 10.5; $cy = $startY + 14.7;
            $rot = random_int(-9,9);
            $svg .= '<g transform="rotate('.$rot.' '.$cx.' '.$cy.')">';
            foreach ($pattern as $row => $bits) {
                for ($col=0; $col<5; $col++) {
                    if ($bits[$col] === '1') {
                        $x = $startX + ($col*$cell); $y = $startY + ($row*$cell);
                        $svg .= '<rect x="'.$x.'" y="'.$y.'" width="'.($cell-0.5).'" height="'.($cell-0.5).'" rx="0.7" fill="#944D63"/>';
                    }
                }
            }
            $svg .= '</g>';
        }
        for ($i=0; $i<18; $i++) {
            $cx=random_int(4,216); $cy=random_int(4,66); $r=random_int(1,2);
            $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="#944D63" opacity="0.28"/>';
        }
        return $svg . '</svg>';
    }
}
