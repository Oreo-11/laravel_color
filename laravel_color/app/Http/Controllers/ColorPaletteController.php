<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use League\ColorExtractor\ColorExtractor;
use League\ColorExtractor\Palette;
use Illuminate\Support\Arr;

class ColorPaletteController extends Controller
{
    // === 1. Извлечение доминирующих цветов + мгновенная интерпретация ===
    public function extractFromImage(Request $request)
    {
        $request->validate(['image' => 'required|image|mimes:jpeg,png,jpg|max:32768']);

        $tempPath = $request->file('image')->getPathname();

        try {
            $palette = Palette::fromFilename($tempPath);
            $colorExtractor = new ColorExtractor($palette);
            $colors = $colorExtractor->extract(5);

            $hexColors = [];
            foreach ($colors as $rgbInt) {
                $r = ($rgbInt >> 16) & 0xFF;
                $g = ($rgbInt >> 8) & 0xFF;
                $b = $rgbInt & 0xFF;
                $hexColors[] = sprintf("#%02x%02x%02x", $r, $g, $b);
            }

            $annotatedColors = [];
            foreach ($hexColors as $hex) {
                $annotatedColors[] = [
                    'hex' => $hex,
                    'meaning' => $this->interpretColor($hex)
                ];
            }

            return response()->json(['dominant_colors' => $annotatedColors]);
        } catch (\Exception $e) {
            \Log::error('Color extraction failed: ' . $e->getMessage());
            return response()->json(['error' => 'Не удалось обработать изображение'], 400);
        }
    }

    // === 2. Генерация гармоничных палитр + интерпретация каждого цвета ===
    public function generateHarmony(Request $request)
    {
        $request->validate(['base_color' => 'required|string']);
        $hex = ltrim($request->base_color, '#');

        if (!ctype_xdigit($hex) || strlen($hex) !== 6) {
            return response()->json(['error' => 'Некорректный HEX-цвет'], 400);
        }

        [$h, $s, $l] = $this->hexToHsl($hex);

        $harmonies = [
            'complementary' => $this->hslToHex(($h + 180) % 360, $s, $l),
            'analogous' => [
                $this->hslToHex(($h + 30) % 360, $s, $l),
                $this->hslToHex(($h - 30 + 360) % 360, $s, $l),
            ],
            'triadic' => [
                $this->hslToHex(($h + 120) % 360, $s, $l),
                $this->hslToHex(($h + 240) % 360, $s, $l),
            ]
        ];

        $annotate = function ($hex) {
            return [
                'hex' => $hex,
                'meaning' => $this->interpretColor($hex)
            ];
        };

        return response()->json([
            'complementary' => $annotate($harmonies['complementary']),
            'analogous' => array_map($annotate, $harmonies['analogous']),
            'triadic' => array_map($annotate, $harmonies['triadic']),
        ]);
    }

// === 3. Улучшенный метод с fallback-интерпретацией ===
    public function getColorMeaning(Request $request)
    {
        $request->validate(['color' => 'required|string']);
        $hex = ltrim($request->color, '#');

        if (!ctype_xdigit($hex) || strlen($hex) !== 6) {
            return response()->json(['error' => 'Некорректный HEX-цвет'], 400);
        }

        return response()->json([
            'color' => "#$hex",
            'meaning' => $this->interpretColor($hex)
        ]);
    }

    /**
     * Возвращает маркетинговую интерпретацию цвета.
     * Сначала ищет в ручном справочнике, затем генерирует динамически.
     */
    private function interpretColor(string $hex): string
    {
        $cleanHex = strtoupper(ltrim($hex, '#'));

        // Валидация
        if (!ctype_xdigit($cleanHex) || strlen($cleanHex) !== 6) {
            return 'Некорректный HEX-цвет.';
        }

        // 1. Ручной справочник
        $meanings = config('color_meanings', []);
        if (isset($meanings[$cleanHex])) {
            return $meanings[$cleanHex];
        }

        // 2. Динамическая генерация
        try {
            [$h, $s, $l] = $this->hexToHsl($cleanHex);

            return match (true) {
                $l < 20 => 'Тёмный оттенок. Передаёт глубину, серьёзность, роскошь.',
                $l > 90 => 'Светлый оттенок. Создаёт ощущение чистоты, лёгкости, минимализма.',
                $s < 20 => 'Пастельный/приглушённый цвет. Универсален, не агрессивен, подходит для фона.',
                $s > 70 => 'Насыщенный цвет. Привлекает внимание, подходит для акцентов и CTA.',
                $h >= 0 && $h < 30 => 'Тёплый оттенок (красный/оранжевый). Энергия, страсть, тепло.',
                $h >= 30 && $h < 90 => 'Земляные/жёлтые тона. Натуральность, уют, стабильность.',
                $h >= 90 && $h < 180 => 'Холодные зелёные/бирюзовые тона. Свежесть, рост, доверие.',
                $h >= 180 && $h < 270 => 'Сине-фиолетовые тона. Спокойствие, глубина, интуиция.',
                default => 'Нейтральный или сложный оттенок. Рекомендуется тестировать в контексте бренда.'
            };
        } catch (\Exception $e) {
            return 'Не удалось определить характеристики цвета.';
        }
    }

    // === 4. Проверка контрастности (WCAG) ===
    public function checkContrast(Request $request)
    {
        $request->validate([
            'foreground' => 'required|string',
            'background' => 'required|string'
        ]);

        $fg = ltrim($request->foreground, '#');
        $bg = ltrim($request->background, '#');

        if (!ctype_xdigit($fg) || strlen($fg) !== 6 || !ctype_xdigit($bg) || strlen($bg) !== 6) {
            return response()->json(['error' => 'Некорректные цвета'], 400);
        }

        $l1 = $this->relativeLuminance($fg);
        $l2 = $this->relativeLuminance($bg);

        $contrast = ($l1 > $l2 ? ($l1 + 0.05) / ($l2 + 0.05) : ($l2 + 0.05) / ($l1 + 0.05));

        return response()->json([
            'contrast_ratio' => round($contrast, 2),
            'wcag_aa_large' => $contrast >= 3.0,
            'wcag_aa'       => $contrast >= 4.5,
            'wcag_aaa'      => $contrast >= 7.0,
        ]);
    }

    // === 5. Экспорт палитры (CSS/SCSS/PNG) ===
    public function exportPalette(Request $request)
    {
        $request->validate([
            'colors' => 'required|array',
            'format' => 'required|in:css,scss,png,svg' // ← добавили svg
        ]);

        $colors = $request->colors;
        $format = $request->format;

        if ($format === 'css') {
            $content = ":root {\n";
            foreach ($colors as $i => $color) {
                $content .= "  --color-" . ($i + 1) . ": $color;\n";
            }
            $content .= "}";
            return response($content)
                ->header('Content-Type', 'text/css')
                ->header('Content-Disposition', 'attachment; filename="palette.css"');
        }

        if ($format === 'scss') {
            $content = "";
            foreach ($colors as $i => $color) {
                $content .= "\$color-" . ($i + 1) . ": $color;\n";
            }
            return response($content)
                ->header('Content-Type', 'text/scss')
                ->header('Content-Disposition', 'attachment; filename="palette.scss"');
        }

        if ($format === 'png') {
            $width = 600;
            $height = 100;
            $img = imagecreate($width, $height);
            imagecolorallocate($img, 255, 255, 255); // белый фон

            $step = $width / count($colors);
            foreach ($colors as $i => $hex) {
                $rgb = $this->hexToRgb($hex);
                $color = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
                imagefilledrectangle($img, (int)($i * $step), 0, (int)(($i + 1) * $step), $height, $color);
            }

            ob_start();
            imagepng($img);
            $png = ob_get_clean();
            imagedestroy($img);

            return response($png)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="palette.png"');
        }

        // === SVG ЭКСПОРТ ===
        if ($format === 'svg') {
            $width = 600;
            $height = 100;
            $step = $width / count($colors);

            $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $svg .= '<svg width="' . $width . '" height="' . $height . '" xmlns="http://www.w3.org/2000/svg">' . "\n";

            foreach ($colors as $i => $hex) {
                $x = (int)($i * $step);
                $svg .= '<rect x="' . $x . '" y="0" width="' . $step . '" height="' . $height . '" fill="' . $hex . '" />' . "\n";
            }

            $svg .= '</svg>';

            return response($svg)
                ->header('Content-Type', 'image/svg+xml')
                ->header('Content-Disposition', 'attachment; filename="palette.svg"');
        }
    }

    // === ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ===

    private function hexToRgb($hex)
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        ];
    }

    private function hexToHsl($hex)
    {
        $rgb = $this->hexToRgb($hex);
        $r = $rgb[0] / 255;
        $g = $rgb[1] / 255;
        $b = $rgb[2] / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;

        if ($d == 0) {
            $h = $s = 0;
        } else {
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            switch ($max) {
                case $r: $h = ($g - $b) / $d + ($g < $b ? 6 : 0); break;
                case $g: $h = ($b - $r) / $d + 2; break;
                case $b: $h = ($r - $g) / $d + 4; break;
            }
            $h /= 6;
        }

        return [(int)($h * 360), (int)($s * 100), (int)($l * 100)];
    }

    private function hslToHex($h, $s, $l)
    {
        $h /= 360;
        $s /= 100;
        $l /= 100;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h * 6, 2) - 1));
        $m = $l - $c / 2;

        if ($h < 1/6) [$r, $g, $b] = [$c, $x, 0];
        elseif ($h < 2/6) [$r, $g, $b] = [$x, $c, 0];
        elseif ($h < 3/6) [$r, $g, $b] = [0, $c, $x];
        elseif ($h < 4/6) [$r, $g, $b] = [0, $x, $c];
        elseif ($h < 5/6) [$r, $g, $b] = [$x, 0, $c];
        else [$r, $g, $b] = [$c, 0, $x];

        $r = dechex((int)(($r + $m) * 255));
        $g = dechex((int)(($g + $m) * 255));
        $b = dechex((int)(($b + $m) * 255));

        return '#' . str_pad($r, 2, '0', STR_PAD_LEFT) .
            str_pad($g, 2, '0', STR_PAD_LEFT) .
            str_pad($b, 2, '0', STR_PAD_LEFT);
    }

    private function relativeLuminance($hex)
    {
        $rgb = $this->hexToRgb($hex);
        $a = [];
        foreach ($rgb as $v) {
            $v /= 255;
            $a[] = $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * $a[0] + 0.7152 * $a[1] + 0.0722 * $a[2];
    }
}
