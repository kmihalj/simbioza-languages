<?php

declare(strict_types=1);

// HR: Izvoz izvora i nacrta iz lokalne Simbioze; nikada ne objavljuje nacrt kao gotov prijevod.
// EN: Exports sources and drafts from a local Simbioza; never publishes a draft as complete.
$application = realpath($argv[1] ?? '');
$repository = dirname(__DIR__);
if ($application === false || !is_file($application . '/vendor/autoload.php')) {
    fwrite(STDERR, "Usage: php scripts/build_catalog.php /path/to/Simbioza\n");
    exit(2);
}
if (is_file($repository . '/manifest.json') && !in_array('--force', $argv, true)) {
    fwrite(STDERR, "Catalogue already exists. Use sync.php to extract only changed keys.\n");
    exit(2);
}

require $application . '/vendor/autoload.php';
$manager = new App\Localization\LanguagePackManager($application, new App\Module\ModuleCatalog());
$names = [
    'en' => ['en' => 'English', 'hr' => 'Engleski', 'de' => 'Englisch', 'fr' => 'anglais', 'es' => 'inglés', 'it' => 'inglese'],
    'hr' => ['en' => 'Croatian', 'hr' => 'Hrvatski', 'de' => 'Kroatisch', 'fr' => 'croate', 'es' => 'croata', 'it' => 'croato'],
    'de' => ['en' => 'German', 'hr' => 'Njemački', 'de' => 'Deutsch', 'fr' => 'allemand', 'es' => 'alemán', 'it' => 'tedesco'],
    'fr' => ['en' => 'French', 'hr' => 'Francuski', 'de' => 'Französisch', 'fr' => 'français', 'es' => 'francés', 'it' => 'francese'],
    'es' => ['en' => 'Spanish', 'hr' => 'Španjolski', 'de' => 'Spanisch', 'fr' => 'espagnol', 'es' => 'español', 'it' => 'spagnolo'],
    'it' => ['en' => 'Italian', 'hr' => 'Talijanski', 'de' => 'Italienisch', 'fr' => 'italien', 'es' => 'italiano', 'it' => 'italiano'],
];

/** HR: Čita zastavicu iz paketa ili ugrađene datoteke. EN: Reads a flag from a pack or bundled file. */
function flagSvg(string $application, string $locale): string
{
    if (in_array($locale, ['en', 'hr'], true)) {
        return trim((string)file_get_contents($application . '/vendor/aaieduhr/heartphrame-module-menu/resources/assets/flags/' . $locale . '.svg'));
    }

    if ($locale === 'de') {
        $example = json_decode((string)file_get_contents($application . '/resources/language-packs/de.example.json'), true, 512, JSON_THROW_ON_ERROR);
        return (string)$example['flag_svg'];
    }

    $bands = match ($locale) {
        'fr' => ['#0055a4', '#ffffff', '#ef4135'],
        'it' => ['#009246', '#ffffff', '#ce2b37'],
        'es' => ['#aa151b', '#f1bf00', '#aa151b'],
    };
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 24">';
    foreach ($bands as $index => $color) {
        $svg .= $locale === 'es'
            ? '<rect x="0" y="' . ($index === 0 ? 0 : ($index === 1 ? 6 : 18))
                . '" width="32" height="' . ($index === 1 ? 12 : 6) . '" fill="' . $color . '"/>'
            : '<rect x="' . ($index * 11) . '" y="0" width="11" height="24" fill="' . $color . '"/>';
    }
    return $svg . '</svg>';
}

/** HR: Zapisuje ponovljiv UTF-8 JSON paket. EN: Writes a reproducible UTF-8 JSON pack. */
function writePack(string $path, array $payload): void
{
    ksort($payload['translations'], SORT_STRING);
    $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $encoded . "\n") === false) {
        throw new RuntimeException('Unable to write ' . $path);
    }
}

foreach (['packs', 'drafts'] as $directory) {
    if (!is_dir($repository . '/' . $directory) && !mkdir($repository . '/' . $directory, 0775, true)) {
        throw new RuntimeException('Unable to create ' . $directory);
    }
}

$released = [];
foreach (['en', 'hr'] as $locale) {
    $payload = [
        'format' => 'simbioza-language-pack',
        'version' => 2,
        'locale' => $locale,
        'source_locale' => $locale,
        'names' => $names[$locale],
        'native_name' => $names[$locale][$locale],
        'flag_svg' => flagSvg($application, $locale),
        'translations' => $manager->catalog($locale),
    ];
    $path = $repository . '/packs/' . $locale . '.json';
    writePack($path, $payload);
    $released[] = [
        'locale' => $locale,
        'native_name' => $payload['native_name'],
        'version' => '2026.09.22.1',
        'file' => 'packs/' . $locale . '.json',
        'sha256' => hash_file('sha256', $path),
        'status' => 'released',
    ];
}

$german = json_decode((string)file_get_contents($application . '/resources/language-packs/de.example.json'), true, 512, JSON_THROW_ON_ERROR);
$german['names'] = $names['de'];
$german['native_name'] = 'Deutsch';
writePack($repository . '/drafts/de.json', $german);

foreach (['fr', 'es', 'it'] as $locale) {
    writePack($repository . '/drafts/' . $locale . '.json', [
        'format' => 'simbioza-language-pack',
        'version' => 2,
        'locale' => $locale,
        'source_locale' => 'en',
        'names' => $names[$locale],
        'native_name' => $names[$locale][$locale],
        'flag_svg' => flagSvg($application, $locale),
        'translations' => $manager->catalog('en'),
    ]);
}

file_put_contents($repository . '/manifest.json', json_encode([
    'format' => 'simbioza-language-catalog',
    'version' => 1,
    'languages' => $released,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
