<?php

declare(strict_types=1);

// EN: Verifies every released pack against the application and installed module keys.
// Checksums alone cannot detect a required translation missing from every pack.
//
// HR: Provjerava svaki objavljeni paket prema ključevima aplikacije i instaliranih modula.
// Sami sažeci ne mogu otkriti obavezni prijevod koji nedostaje u svim paketima.
$application = realpath($argv[1] ?? '');
if ($application === false || !is_file($application . '/vendor/autoload.php')) {
    fwrite(STDERR, "Usage: php scripts/validate_source.php /path/to/Simbioza\n");
    exit(2);
}
require $application . '/vendor/autoload.php';
$manager = new App\Localization\LanguagePackManager($application, new App\Module\ModuleCatalog());
foreach (glob(dirname(__DIR__) . '/packs/*.json') ?: [] as $path) {
    $result = $manager->validate($path);
    if ($result['missing'] !== [] || $result['placeholder_errors'] !== []) {
        throw new RuntimeException('Pack is incompatible with the release: ' . basename($path));
    }
    echo $result['locale'] . ': complete release source and placeholders verified' . PHP_EOL;
}
