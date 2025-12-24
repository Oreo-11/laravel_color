import { useState } from 'react';
import { Head } from '@inertiajs/react';

interface ColorItem {
    hex: string;
    meaning: string;
}

interface HarmonyResult {
    complementary: ColorItem;
    analogous: ColorItem[];
    triadic: ColorItem[];
}

interface ContrastResult {
    contrast_ratio: number;
    wcag_aa_large: boolean;
    wcag_aa: boolean;
    wcag_aaa: boolean;
}

export default function ColorTool() {
    const [image, setImage] = useState<File | null>(null);
    const [dominantColors, setDominantColors] = useState<ColorItem[]>([]);
    const [baseColor, setBaseColor] = useState<string>('#48D1CC');
    const [harmony, setHarmony] = useState<HarmonyResult | null>(null);
    const [selectedHarmonyType, setSelectedHarmonyType] = useState<'complementary' | 'analogous' | 'triadic'>('complementary');
    const [fgColor, setFgColor] = useState<string>('#000000');
    const [bgColor, setBgColor] = useState<string>('#ffffff');
    const [contrast, setContrast] = useState<ContrastResult | null>(null);
    const [exportFormat, setExportFormat] = useState<'css' | 'scss' | 'png' | 'svg'>('css');
    const [exportUrl, setExportUrl] = useState<string>('');

    // 1. Извлечение цветов из изображения
    const handleImageUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setImage(file);
        const formData = new FormData();
        formData.append('image', file);

        try {
            const res = await fetch('/api/v1/extract', {
                method: 'POST',
                body: formData,
            });
            const data = await res.json();
            setDominantColors(data.dominant_colors || []);
        } catch (err) {
            alert('Ошибка загрузки изображения');
        }
    };

    // 2. Генерация гармоний
    const generateHarmony = async () => {
        try {
            const res = await fetch('/api/v1/harmony', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ base_color: baseColor }),
            });
            const data = await res.json();
            setHarmony(data);
        } catch (err) {
            alert('Ошибка генерации гармоний');
        }
    };

    // 3. Проверка контраста
    const checkContrast = async () => {
        try {
            const res = await fetch('/api/v1/contrast', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ foreground: fgColor, background: bgColor }),
            });
            const data = await res.json();
            setContrast(data);
        } catch (err) {
            alert('Ошибка проверки контраста');
        }
    };

    // 4. Экспорт палитры
    const exportPalette = async () => {
        const colors = dominantColors.map(c => c.hex);
        if (colors.length === 0) {
            alert('Сначала загрузите изображение');
            return;
        }

        try {
            const res = await fetch('/api/v1/export', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ colors, format: exportFormat }),
            });

            if (exportFormat === 'css' || exportFormat === 'scss') {
                const text = await res.text();
                const blob = new Blob([text], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                setExportUrl(url);
            } else {
                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `palette.${exportFormat}`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
        } catch (err) {
            alert('Ошибка экспорта');
        }
    };

    // Вспомогательная функция для отображения выбранной гармонии
    const renderHarmony = () => {
        if (!harmony) return null;

        switch (selectedHarmonyType) {
            case 'complementary':
                return (
                    <div className="mt-2">
                        <h3 className="font-medium">Комплементарный цвет:</h3>
                        <div
                            className="w-16 h-16 border rounded mb-1"
                            style={{ backgroundColor: harmony.complementary.hex }}
                        ></div>
                        <p className="text-sm">{harmony.complementary.meaning}</p>
                    </div>
                );
            case 'analogous':
                return (
                    <div className="mt-2">
                        <h3 className="font-medium">Аналоговые цвета:</h3>
                        <div className="flex gap-2">
                            {harmony.analogous.map((color, i) => (
                                <div key={i} className="flex flex-col items-center">
                                    <div
                                        className="w-12 h-12 border rounded"
                                        style={{ backgroundColor: color.hex }}
                                    ></div>
                                    <p className="text-xs mt-1">{color.meaning}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                );
            case 'triadic':
                return (
                    <div className="mt-2">
                        <h3 className="font-medium">Триадные цвета:</h3>
                        <div className="flex gap-2">
                            {harmony.triadic.map((color, i) => (
                                <div key={i} className="flex flex-col items-center">
                                    <div
                                        className="w-12 h-12 border rounded"
                                        style={{ backgroundColor: color.hex }}
                                    ></div>
                                    <p className="text-xs mt-1">{color.meaning}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                );
            default:
                return null;
        }
    };

    return (
        <div className="max-w-4xl mx-auto p-6">
            <Head title="Color Palette Tool" />
            <h1 className="text-2xl font-bold mb-6">🎨 Color Palette Tool</h1>

            {/* 1. Загрузка изображения */}
            <section className="mb-8">
                <h2 className="text-xl font-semibold mb-2">1. Загрузите изображение</h2>
                <input type="file" accept="image/png" onChange={handleImageUpload} className="mb-4" />
                {dominantColors.length > 0 && (
                    <div>
                        <h3 className="font-medium mb-2">Доминирующие цвета:</h3>
                        <div className="flex flex-wrap gap-2">
                            {dominantColors.map((item, i) => (
                                <div key={i} className="flex flex-col items-center">
                                    <div
                                        className="w-16 h-16 border rounded"
                                        style={{ backgroundColor: item.hex }}
                                    ></div>
                                    <span className="text-xs mt-1">{item.hex}</span>
                                    <p className="text-xs text-gray-600 mt-1">{item.meaning}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </section>

            {/* 2. Гармонии */}
            <section className="mb-8">
                <h2 className="text-xl font-semibold mb-2">2. Генерация гармоний</h2>
                <div className="flex flex-wrap gap-2 items-end mb-2">
                    <div>
                        <label className="block text-sm font-medium mb-1">Базовый цвет</label>
                        <div className="flex gap-1">
                            <input
                                type="color"
                                value={baseColor}
                                onChange={(e) => setBaseColor(e.target.value)}
                                className="w-10 h-10"
                            />
                            <input
                                type="text"
                                value={baseColor}
                                onChange={(e) => setBaseColor(e.target.value)}
                                className="border px-2 py-1 w-24"
                            />
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Тип гармонии</label>
                        <select
                            value={selectedHarmonyType}
                            onChange={(e) => setSelectedHarmonyType(e.target.value as any)}
                            className="border px-2 py-1 rounded bg-white"
                        >
                            <option value="complementary">Комплементарная</option>
                            <option value="analogous">Аналоговая</option>
                            <option value="triadic">Триадная</option>
                        </select>
                    </div>
                    <button
                        onClick={generateHarmony}
                        className="bg-blue-500 text-white px-3 py-2 rounded hover:bg-blue-600"
                    >
                        Сгенерировать
                    </button>
                </div>
                {harmony && renderHarmony()}
            </section>

            {/* 3. Контраст */}
            <section className="mb-8">
                <h2 className="text-xl font-semibold mb-2">3. Проверка контраста</h2>
                <div className="flex gap-2 mb-2 flex-wrap">
                    <div>
                        <label className="block text-sm mb-1">Текст</label>
                        <input
                            type="color"
                            value={fgColor}
                            onChange={(e) => setFgColor(e.target.value)}
                            className="w-12 h-12"
                        />
                    </div>
                    <div>
                        <label className="block text-sm mb-1">Фон</label>
                        <input
                            type="color"
                            value={bgColor}
                            onChange={(e) => setBgColor(e.target.value)}
                            className="w-12 h-12"
                        />
                    </div>
                    <button
                        onClick={checkContrast}
                        className="bg-green-500 text-white px-3 py-2 rounded self-end hover:bg-green-600"
                    >
                        Проверить
                    </button>
                </div>
                {contrast && (
                    <div className="mt-2">
                        <p><strong>Контраст:</strong> {contrast.contrast_ratio}</p>
                        <p><strong>WCAG AA (обычный текст):</strong> {contrast.wcag_aa ? '✅' : '❌'}</p>
                        <p><strong>WCAG AA (крупный текст):</strong> {contrast.wcag_aa_large ? '✅' : '❌'}</p>
                    </div>
                )}
            </section>

            {/* 4. Экспорт */}
            <section>
                <h2 className="text-xl font-semibold mb-2">4. Экспорт палитры</h2>
                <div className="flex gap-2 flex-wrap items-end">
                    <div>
                        <label className="block text-sm font-medium mb-1">Формат</label>
                        <select
                            value={exportFormat}
                            onChange={(e) => setExportFormat(e.target.value as any)}
                            className="border px-3 py-2 rounded bg-white appearance-none min-w-[120px]"
                        >
                            <option value="css">CSS</option>
                            <option value="scss">SCSS</option>
                            <option value="png">PNG</option>
                            <option value="svg">SVG</option>
                        </select>
                    </div>
                    <button
                        onClick={exportPalette}
                        className="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600"
                    >
                        Экспортировать
                    </button>
                </div>
                {exportUrl && (exportFormat === 'css' || exportFormat === 'scss') && (
                    <div className="mt-3">
                        <a
                            href={exportUrl}
                            download={`palette.${exportFormat}`}
                            className="inline-block bg-blue-100 text-blue-700 px-3 py-1 rounded"
                        >
                            Скачать {exportFormat.toUpperCase()}
                        </a>
                    </div>
                )}
            </section>
        </div>
    );
}
