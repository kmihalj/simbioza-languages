<?php

declare(strict_types=1);

// HR: Uspoređuje aktualni katalog sa zadnjim izvorom i izdvaja samo nove/promijenjene ključeve.
// EN: Compares the current catalogue with the last source and extracts only new/changed keys.
$application = realpath($argv[1] ?? '');
$repository = dirname(__DIR__);
$version = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--version=')) {
        $version = substr($argument, 10);
    }
}
if ($application === false || !is_file($application . '/vendor/autoload.php')
    || preg_match('/\A[0-9]{4}\.[0-9]{2}\.[0-9]{2}\.[0-9]+\z/D', $version) !== 1) {
    fwrite(STDERR, "Usage: php scripts/sync.php /path/to/Simbioza --version=YYYY.MM.DD.N\n");
    exit(2);
}
require $application . '/vendor/autoload.php';
$manager = new App\Localization\LanguagePackManager($application, new App\Module\ModuleCatalog());
$manifestPath = $repository . '/manifest.json';
$manifest = json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$oldEnglish = json_decode((string)file_get_contents($repository . '/packs/en.json'), true, 512, JSON_THROW_ON_ERROR);
$newEnglish = $manager->catalog('en');
$oldStrings = $oldEnglish['translations'];

if (count($newEnglish) < count($oldStrings)) {
    throw new RuntimeException('Source key count decreased. Check that every release module is installed before syncing.');
}

$delta = [];
foreach ($newEnglish as $key => $value) {
    if (!array_key_exists($key, $oldStrings)) {
        $delta[$key] = ['reason' => 'new', 'source' => $value];
    } elseif ($oldStrings[$key] !== $value) {
        $delta[$key] = ['reason' => 'changed', 'previous_source' => $oldStrings[$key], 'source' => $value];
    }
}
ksort($delta, SORT_STRING);

// HR: Ažuriraj samo izvorne pakete; ručne prijevode nikada ne prepisuj.
// EN: Update only source packs; never overwrite human-reviewed translations.
foreach ($manifest['languages'] as &$entry) {
    if (!in_array($entry['locale'] ?? null, ['en', 'hr'], true)) {
        continue;
    }
    $locale = $entry['locale'];
    $path = $repository . '/packs/' . $locale . '.json';
    $pack = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $strings = $manager->catalog($locale);
    ksort($strings, SORT_STRING);
    $pack['translations'] = $strings;
    $encoded = json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    if (hash('sha256', $encoded) !== $entry['sha256']) {
        file_put_contents($path, $encoded);
        $entry['sha256'] = hash('sha256', $encoded);
        $entry['version'] = $version;
    }
}
unset($entry);

if ($delta !== []) {
    $pendingDirectory = $repository . '/pending';
    if (!is_dir($pendingDirectory) && !mkdir($pendingDirectory, 0775, true)) {
        throw new RuntimeException('Unable to create pending directory.');
    }
    foreach (glob($repository . '/{packs,drafts}/*.json', GLOB_BRACE) ?: [] as $path) {
        $locale = basename($path, '.json');
        if (in_array($locale, ['en', 'hr'], true)) {
            continue;
        }
        $pendingPath = $pendingDirectory . '/' . $locale . '.json';
        $previous = is_file($pendingPath)
            ? json_decode((string)file_get_contents($pendingPath), true, 512, JSON_THROW_ON_ERROR)
            : ['locale' => $locale, 'source_locale' => 'en', 'pending' => []];
        $previous['pending'] = array_replace($previous['pending'] ?? [], $delta);
        ksort($previous['pending'], SORT_STRING);
        file_put_contents($pendingPath, json_encode(
            $previous,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ) . "\n");
        fwrite(STDOUT, $locale . ': ' . count($previous['pending']) . " pending keys\n");
    }
}
file_put_contents($manifestPath, json_encode(
    $manifest,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
) . "\n");
fwrite(STDOUT, count($delta) . " new or changed source keys\n");
