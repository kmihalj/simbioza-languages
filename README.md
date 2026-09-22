# Simbioza language packs

This repository is the public catalogue of Simbioza interface translations. The
application ships with Croatian (`hr`) and English (`en`); additional languages
can be installed from the catalogue without installing a PHP module.

English, Croatian, and German are currently published. French, Spanish, and
Italian have starter packs with multilingual names and SVG flags in `drafts/`;
their interface text is not yet translated, so they are deliberately **not**
offered for installation. A complete key count alone does not mean that a
translation is ready.

Each `packs/<locale>.json` file is a version-2 Simbioza language pack. The
`manifest.json` file lists the published packs and their SHA-256 digests. A
language is offered by the installer and Setup only when its manifest entry is
marked `released`. Work-in-progress translations remain in `drafts/` and are
never offered as complete translations.

The translations in this repository were prepared with AI assistance,
including ChatGPT/Codex. They require review by native speakers, particularly
for security messages, accessibility text, and administrative terminology.
Please report mistakes through an issue or a pull request. Do not translate
placeholder tokens such as `:name`, `%s`, `%d`, or `{{value}}`.

To create a new translation from a Simbioza checkout:

```sh
vendor/bin/hph languages template fr --source=en --output=/path/to/simbioza-languages/drafts/fr.json
vendor/bin/hph languages validate /path/to/simbioza-languages/drafts/fr.json
```

Fill the translated values, native and multilingual names, and a safe SVG flag.
After review and validation, move the file to `packs/` and publish its digest in
`manifest.json`. The repository tool can do the final verification and publish
step for a reviewed pack:

```sh
php scripts/publish.php /path/to/Simbioza fr --version=2026.09.22.2
php scripts/validate_catalog.php
```

When Simbioza or a module adds or changes strings, extract only the delta:

```sh
php scripts/sync.php /path/to/Simbioza --version=2026.09.22.2
```

This updates the built-in source packs and writes only new or changed source
keys to `pending/<locale>.json`; it never overwrites a translation. Translate
and review those keys in the corresponding pack, remove its pending file after
review, validate, then publish a newer pack revision. Installed repository
packs are refreshed during a later application update when their published
digest changes; `vendor/bin/hph languages update` can refresh them sooner.

The manifest is data, not executable code. Every published pack must have a
safe SVG flag, language names, all source keys, and intact placeholders.
User-facing dates and times follow the selected locale through ICU, while
stored timestamps and machine-readable values keep their stable formats.

Simbioza accepts UTF-8 text and BCP-47-like locale identifiers, so Cyrillic
languages such as `ru` or `sr-cyrl` can be packaged in the same way. A flag is
only a selector icon, not a claim that every speaker belongs to one country.

## Croatian

Ovaj repozitorij sadrži jezične pakete Simbioze. Zadani jezici su hrvatski i
engleski; objavljen je i njemački. Francuski, španjolski i talijanski zasad su
samo nacrti s nazivima i SVG zastavicama, ali bez prevedenog sučelja. Prijevodi
su pripremljeni uz pomoć umjetne inteligencije, uključujući ChatGPT/Codex, i
trebaju pregled izvornih govornika. Nedovršeni paketi u `drafts/` ne nude se
za instalaciju. Naredba `scripts/sync.php` izdvaja samo nove i promijenjene
ključeve; postojeće prijevode ne prepisuje. Datumi i vremena u sučelju
prilagođavaju se odabranom jeziku.
