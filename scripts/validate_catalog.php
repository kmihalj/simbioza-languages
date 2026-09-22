<?php

declare(strict_types=1);

// HR: Neovisno provjerava manifest, cjelovitost paketa i sigurne SVG zastavice.
// EN: Independently checks the manifest, pack completeness, and safe SVG flags.
$root = dirname(__DIR__);
$manifest = json_decode((string)file_get_contents($root . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
if (($manifest['format'] ?? null) !== 'simbioza-language-catalog'
    || ($manifest['version'] ?? null) !== 1 || !is_array($manifest['languages'] ?? null)) {
    throw new RuntimeException('Invalid catalogue manifest.');
}
$source = json_decode((string)file_get_contents($root . '/packs/hr.json'), true, 512, JSON_THROW_ON_ERROR);
$sourceStrings = $source['translations'] ?? null;
if (!is_array($sourceStrings) || $sourceStrings === []) {
    throw new RuntimeException('The Croatian source pack is empty.');
}
foreach ($sourceStrings as $key => $value) {
    if (preg_match('/\A[a-z][a-z0-9_.-]*\z/D', $key) !== 1 && $key !== $value) {
        throw new RuntimeException('Croatian source text must be the canonical key: ' . $key);
    }
}
$seen = [];
foreach ($manifest['languages'] as $entry) {
    $locale = $entry['locale'] ?? null;
    if (!is_string($locale) || preg_match('/\A[a-z0-9]+(?:[-_][a-z0-9]+)*\z/D', $locale) !== 1
        || isset($seen[$locale]) || ($entry['status'] ?? null) !== 'released'
        || ($entry['file'] ?? null) !== 'packs/' . $locale . '.json') {
        throw new RuntimeException('Invalid or duplicate released language entry.');
    }
    $seen[$locale] = true;
    $path = $root . '/packs/' . $locale . '.json';
    if (!is_file($path) || !hash_equals((string)($entry['sha256'] ?? ''), hash_file('sha256', $path))) {
        throw new RuntimeException('Invalid checksum for ' . $locale);
    }
    $pack = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (($pack['format'] ?? null) !== 'simbioza-language-pack' || ($pack['version'] ?? null) !== 2
        || ($pack['locale'] ?? null) !== $locale || ($pack['native_name'] ?? null) !== ($entry['native_name'] ?? null)
        || ($pack['names'][$locale] ?? null) !== ($pack['native_name'] ?? null)
        || !is_string($pack['names']['en'] ?? null) || !is_string($pack['names']['hr'] ?? null)
        || !is_array($pack['translations'] ?? null)) {
        throw new RuntimeException('Invalid pack metadata for ' . $locale);
    }
    $strings = $pack['translations'];
    if (array_diff_key($sourceStrings, $strings) !== [] || array_diff_key($strings, $sourceStrings) !== []) {
        throw new RuntimeException('Source keys differ in ' . $locale);
    }
    $sourceLocale = $pack['source_locale'] ?? null;
    if (!in_array($sourceLocale, ['hr', 'en'], true)) {
        throw new RuntimeException('Unsupported translation source for ' . $locale);
    }
    $placeholderSource = $sourceLocale === 'hr'
        ? $sourceStrings
        : json_decode((string)file_get_contents($root . '/packs/en.json'), true, 512, JSON_THROW_ON_ERROR)['translations'];
    foreach ($sourceStrings as $key => $value) {
        if (!is_string($value) || !is_string($strings[$key] ?? null)) {
            throw new RuntimeException('Invalid translation value in ' . $locale . ': ' . $key);
        }
        preg_match_all('/(?<!:):[A-Za-z_][A-Za-z0-9_]*|%[sd]|\{\{[^{}]+\}\}/', $placeholderSource[$key], $original);
        preg_match_all('/(?<!:):[A-Za-z_][A-Za-z0-9_]*|%[sd]|\{\{[^{}]+\}\}/', $strings[$key], $translated);
        sort($original[0]);
        sort($translated[0]);
        if ($original[0] !== $translated[0]) {
            throw new RuntimeException('Placeholder mismatch in ' . $locale . ': ' . $key);
        }
    }
    $svg = $pack['flag_svg'] ?? null;
    if (!is_string($svg) || $svg === '' || strlen($svg) > 65536 || str_contains($svg, '<!')) {
        throw new RuntimeException('Missing or unsafe flag for ' . $locale);
    }
    $document = new DOMDocument();
    if (!@$document->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS)
        || strtolower($document->documentElement?->localName ?? '') !== 'svg') {
        throw new RuntimeException('Invalid SVG flag for ' . $locale);
    }
    $allowedElements = array_fill_keys(['svg', 'g', 'defs', 'clippath', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon'], true);
    $allowedAttributes = array_fill_keys(['xmlns', 'width', 'height', 'viewbox', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'd', 'points', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'fill-rule', 'clip-rule', 'clip-path', 'transform', 'opacity', 'id'], true);
    foreach ($document->getElementsByTagName('*') as $element) {
        if (!isset($allowedElements[strtolower($element->localName ?? '')])) {
            throw new RuntimeException('Unsupported SVG element in ' . $locale);
        }
        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = strtolower(trim($attribute->nodeValue ?? ''));
            if (!isset($allowedAttributes[$name]) || str_starts_with($name, 'on') || str_contains($value, 'javascript:')
                || (str_contains($value, 'url(') && preg_match('/\Aurl\(#[a-z0-9_.:-]+\)\z/D', $value) !== 1)) {
                throw new RuntimeException('Unsafe SVG attribute in ' . $locale);
            }
        }
    }
    fwrite(STDOUT, $locale . ': ' . count($strings) . " keys, SHA-256 and SVG verified\n");
}
