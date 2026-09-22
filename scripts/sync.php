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
$oldSources = [];
$newSources = [];
$deltas = [];
foreach (['hr', 'en'] as $locale) {
    $oldSources[$locale] = json_decode(
        (string)file_get_contents($repository . '/packs/' . $locale . '.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    )['translations'];
    $newSources[$locale] = $manager->catalog($locale);
    $removed = array_diff_key($oldSources[$locale], $newSources[$locale]);
    if ($removed !== []) {
        throw new RuntimeException('Source keys were removed or renamed; review before syncing: '
            . implode(', ', array_slice(array_keys($removed), 0, 10)));
    }
    foreach ($newSources[$locale] as $key => $value) {
        if (!array_key_exists($key, $oldSources[$locale])) {
            $deltas[$locale][$key] = ['reason' => 'new', 'source' => $value];
        } elseif ($oldSources[$locale][$key] !== $value) {
            $deltas[$locale][$key] = [
                'reason' => 'changed',
                'previous_source' => $oldSources[$locale][$key],
                'source' => $value,
            ];
        }
    }
}
if (array_diff_key($newSources['hr'], $newSources['en']) !== []
    || array_diff_key($newSources['en'], $newSources['hr']) !== []) {
    throw new RuntimeException('Croatian and English keys must match before syncing.');
}

// HR: Ažuriraj samo izvorne pakete; ručne prijevode nikada ne prepisuj.
// EN: Update only source packs; never overwrite human-reviewed translations.
foreach ($manifest['languages'] as &$entry) {
    if (!in_array($entry['locale'] ?? null, ['en', 'hr'], true)) {
        continue;
    }
    $locale = $entry['locale'];
    $path = $repository . '/packs/' . $locale . '.json';
    $pack = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $strings = $newSources[$locale];
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

$pendingDirectory = $repository . '/pending';
if (!is_dir($pendingDirectory) && !mkdir($pendingDirectory, 0775, true)) {
    throw new RuntimeException('Unable to create pending directory.');
}
foreach (glob($repository . '/{packs,drafts}/*.json', GLOB_BRACE) ?: [] as $path) {
    $locale = basename($path, '.json');
    if (in_array($locale, ['en', 'hr'], true)) {
        continue;
    }
    $pack = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $source = $pack['source_locale'];
    if (!in_array($source, ['en', 'hr'], true)) {
        throw new RuntimeException('Unsupported source locale in ' . $path);
    }
    $pendingPath = $pendingDirectory . '/' . $locale . '.json';
    $previous = is_file($pendingPath)
        ? json_decode((string)file_get_contents($pendingPath), true, 512, JSON_THROW_ON_ERROR)
        : ['locale' => $locale, 'source_locale' => $source, 'pending' => []];
    $delta = $deltas[$source] ?? [];
    foreach (array_diff_key($newSources[$source], $pack['translations']) as $key => $value) {
        $delta[$key] = ['reason' => 'missing', 'source' => $value];
    }
    $previous['source_locale'] = $source;
    $previous['pending'] = array_replace($previous['pending'] ?? [], $delta);
    ksort($previous['pending'], SORT_STRING);
    if ($previous['pending'] === []) {
        continue;
    }
    file_put_contents($pendingPath, json_encode(
        $previous,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    ) . "\n");
    fwrite(STDOUT, $locale . ': ' . count($previous['pending']) . " pending keys\n");
}
file_put_contents($manifestPath, json_encode(
    $manifest,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
) . "\n");
fwrite(STDOUT, count($deltas['hr'] ?? []) . " new or changed Croatian keys\n");
