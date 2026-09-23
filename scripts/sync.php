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
$modules = new App\Module\ModuleCatalog();
$manager = new App\Localization\LanguagePackManager($application, $modules);

/**
 * HR: Vraća statički tekst ili spoj statičkih tekstova iz PHP AST-a.
 * EN: Returns a static string or concatenation of static strings from the PHP AST.
 */
function staticString(mixed $node): ?string
{
    if ($node instanceof PhpParser\Node\Scalar\String_) {
        return $node->value;
    }

    if ($node instanceof PhpParser\Node\Expr\BinaryOp\Concat) {
        $left = staticString($node->left);
        $right = staticString($node->right);

        return is_string($left) && is_string($right) ? $left . $right : null;
    }

    return null;
}

/**
 * HR: Dodaje jedan statični HR/EN par bez prepisivanja ranije pronađenog
 *     konteksta; ručni katalog i dalje ima konačnu prednost.
 * EN: Adds one static HR/EN pair without replacing an earlier context; the
 *     hand-maintained catalogue still has final precedence.
 *
 * @param array<string,string> $pairs
 */
function addBilingualPair(array &$pairs, mixed $croatian, mixed $english): void
{
    $croatian = is_string($croatian) ? trim($croatian) : '';
    $english = is_string($english) ? trim($english) : '';
    if (
        $croatian === ''
        || $english === ''
        || preg_match('/\A(?:HR|utf8mb4_[a-z0-9_]+)\z/D', $croatian) === 1
        || isset($pairs[$croatian])
    ) {
        return;
    }

    $pairs[$croatian] = $english;
}

/**
 * HR: Rekurzivno skuplja dvojezične vrijednosti iz JSON konfiguracije.
 * EN: Recursively collects bilingual values from JSON configuration.
 *
 * @param array<string,string> $pairs
 */
function collectJsonBilingualPairs(mixed $value, array &$pairs): void
{
    if (!is_array($value)) {
        return;
    }

    addBilingualPair($pairs, $value['hr'] ?? null, $value['en'] ?? null);
    foreach ($value as $child) {
        collectJsonBilingualPairs($child, $pairs);
    }
}

/**
 * HR: Skuplja statične dvojezične metapodatke modula koji se prikazuju u
 *     izbornicima, API scopeovima, backupu i drugim dinamičkim sučeljima.
 * EN: Collects static bilingual module metadata displayed in menus, API scopes,
 *     backups, and other dynamic interfaces.
 *
 * @return array{hr:array<string,string>,en:array<string,string>}
 */
function bilingualMetadata(string $application, App\Module\ModuleCatalog $modules): array
{
    if (!class_exists(PhpParser\ParserFactory::class)) {
        throw new RuntimeException('nikic/php-parser is required to synchronize bilingual metadata.');
    }

    $parser = (new PhpParser\ParserFactory())->createForNewestSupportedVersion();
    $pairs = [];
    $visit = static function (mixed $node) use (&$visit, &$pairs): void {
        if ($node instanceof PhpParser\Node\Expr\Array_) {
            $values = [];
            foreach ($node->items as $item) {
                if (!$item instanceof PhpParser\Node\ArrayItem) {
                    continue;
                }

                $key = staticString($item->key);
                $value = staticString($item->value);
                if (is_string($key) && is_string($value)) {
                    $values[$key] = $value;
                }
            }

            addBilingualPair($pairs, $values['hr'] ?? null, $values['en'] ?? null);
        }

        if (!$node instanceof PhpParser\Node) {
            return;
        }

        foreach ($node->getSubNodeNames() as $name) {
            $child = $node->{$name};
            if (is_array($child)) {
                foreach ($child as $nested) {
                    $visit($nested);
                }
            } else {
                $visit($child);
            }
        }
    };

    $componentRoots = [$application => true];
    foreach ($modules->definitions() as $definition) {
        $package = $definition['package'];
        if (!Composer\InstalledVersions::isInstalled($package)) {
            continue;
        }

        $root = Composer\InstalledVersions::getInstallPath($package);
        if (!is_string($root)) {
            continue;
        }
        $componentRoots[$root] = true;

        foreach (['config', 'src', 'views'] as $directory) {
            $path = $root . '/' . $directory;
            if (!is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            );
            foreach ($files as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $nodes = $parser->parse((string)file_get_contents($file->getPathname()));
                foreach ($nodes ?? [] as $node) {
                    $visit($node);
                }
            }
        }
    }

    foreach (array_keys($componentRoots) as $root) {
        foreach (['config', 'resources'] as $directory) {
            $path = $root . '/' . $directory;
            if (!is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            );
            foreach ($files as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'json') {
                    continue;
                }

                $payload = json_decode(
                    (string)file_get_contents($file->getPathname()),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
                collectJsonBilingualPairs($payload, $pairs);
            }
        }
    }

    ksort($pairs, SORT_STRING);

    return ['hr' => array_combine(array_keys($pairs), array_keys($pairs)), 'en' => $pairs];
}

$metadataSources = bilingualMetadata($application, $modules);
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
    // HR: Ručni katalog ima prednost ako isti kanonski ključ već postoji.
    // EN: The hand-maintained catalogue wins when the same canonical key already exists.
    $newSources[$locale] = array_replace($metadataSources[$locale], $manager->catalog($locale));
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
