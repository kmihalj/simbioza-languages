<?php

declare(strict_types=1);

// HR: Objavljuje samo ručno pregledan i potpun paket s novom revizijom.
// EN: Publishes only a manually reviewed, complete pack with a new revision.
$application = realpath($argv[1] ?? '');
$locale = $argv[2] ?? '';
$version = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--version=')) {
        $version = substr($argument, 10);
    }
}
if ($application === false || !is_file($application . '/vendor/autoload.php')
    || preg_match('/\A[a-z0-9]+(?:[-_][a-z0-9]+)*\z/D', $locale) !== 1
    || preg_match('/\A[0-9]{4}\.[0-9]{2}\.[0-9]{2}\.[0-9]+\z/D', $version) !== 1) {
    fwrite(STDERR, "Usage: php scripts/publish.php /path/to/Simbioza LOCALE --version=YYYY.MM.DD.N\n");
    exit(2);
}
$root = dirname(__DIR__);
if (is_file($root . '/pending/' . $locale . '.json')) {
    throw new RuntimeException('Review and clear the pending source changes before publishing ' . $locale);
}
$draft = $root . '/drafts/' . $locale . '.json';
$target = $root . '/packs/' . $locale . '.json';
$source = is_file($draft) ? $draft : $target;
if (!is_file($source)) {
    throw new RuntimeException('The language pack does not exist: ' . $locale);
}
require $application . '/vendor/autoload.php';
$manager = new App\Localization\LanguagePackManager($application, new App\Module\ModuleCatalog());
$check = $manager->validate($source);
if ($check['locale'] !== $locale || $check['missing'] !== [] || $check['placeholder_errors'] !== []) {
    throw new RuntimeException('The language pack is incomplete or changes placeholders.');
}
$manifestPath = $root . '/manifest.json';
$manifest = json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$previous = null;
foreach ($manifest['languages'] as $entry) {
    if (($entry['locale'] ?? null) === $locale) {
        $previous = $entry;
    }
}
if ($previous !== null && version_compare($version, (string)$previous['version'], '<=')) {
    throw new RuntimeException('A new publication needs a later version.');
}
if ($source !== $target && !rename($source, $target)) {
    throw new RuntimeException('Unable to move the reviewed pack into packs/.');
}
$entry = [
    'locale' => $locale,
    'native_name' => $check['native_name'],
    'version' => $version,
    'file' => 'packs/' . $locale . '.json',
    'sha256' => hash_file('sha256', $target),
    'status' => 'released',
];
$manifest['languages'] = array_values(array_filter(
    $manifest['languages'],
    static fn(array $existing): bool => ($existing['locale'] ?? null) !== $locale,
));
$manifest['languages'][] = $entry;
usort($manifest['languages'], static fn(array $left, array $right): int => strcmp($left['locale'], $right['locale']));
file_put_contents($manifestPath, json_encode(
    $manifest,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
) . "\n");
fwrite(STDOUT, $locale . ': published ' . $version . ' (' . $check['translated'] . " keys)\n");
