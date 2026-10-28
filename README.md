# Simbioza language packs

[Croatian version](README_hr.md)

The **2026.10.28.2** pack sources include **4640 keys**, covering the modular
installation profiles, standalone HTML guides, Survey permissions and backup descriptions.
Existing translations, legacy keys, language names and flags are preserved.
CI also validates compatibility with the published 0.3.17 application, not just
the catalogue hashes. Repository tags do not replace the independent pack revisions.

This repository is the public catalogue of Simbioza interface translations. The
application ships with Croatian (`hr`) and English (`en`); additional languages
can be installed from the catalogue without installing a PHP module.
Croatian UI text in Simbioza's code is the canonical key in every pack.
`source_locale` records the reference language used while translating values:
English can be used for German, French, Spanish, or Italian. Runtime lookup is
always direct from the Croatian key to the selected locale, not a translation
chain through English.
Technical identifiers such as permission codes (`can_view`) and locale-format
settings remain stable IDs; they are not user-facing source sentences.

Croatian, English, German, French, Spanish, and Italian are published. Every
pack includes multilingual names and an SVG flag. The French, Spanish, and
Italian text is AI-generated and may contain wording errors; please report
corrections. Published status confirms structural completeness, not native-
speaker review.

Each `packs/<locale>.json` file is a version-2 Simbioza language pack. The
`manifest.json` file lists the published packs and their SHA-256 digests. A
language is offered by the installer and Setup only when its manifest entry is
marked `released`. Work-in-progress translations remain in `drafts/` and are
never offered as complete translations.

The translations in this repository were prepared with AI assistance,
including a ChatGPT/Codex-guided workflow and Argos Translate for the initial
French, Spanish, and Italian drafts. They have not been certified by native
speakers, particularly for security messages, accessibility text, and
administrative terminology.
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
step for a structurally complete pack:

```sh
php scripts/publish.php /path/to/Simbioza fr --version=2026.10.22.2
php scripts/validate_catalog.php
```

When Simbioza or a module adds or changes strings, extract only the delta:

```sh
php scripts/sync.php /path/to/Simbioza --version=2026.10.22.2
```

Choose a revision newer than the published one; the date above is an example.
This updates the built-in Croatian and English reference packs and writes only
new or changed keys or reference values to `pending/<locale>.json` according to
each pack's `source_locale`; it never overwrites a translation. Translate
and review those keys in the corresponding pack, remove its pending file after
review, validate, then publish a newer pack revision. Installed repository
packs are refreshed during a later application update when their published
digest changes; `vendor/bin/hph languages update` can refresh them sooner.

If source keys have been removed, review them before synchronizing. Use
`--retain-removed` only to preserve reviewed legacy translations needed by older
supported installations; the tool lists every retained key.

The manifest is data, not executable code. Every published pack must have a
safe SVG flag, language names, all source keys, and intact placeholders.
`translation_exceptions.json` is the reviewed allowlist for technical terms,
product names, commands, and words that are genuinely written the same way in
the source and target languages. Validation rejects every other unchanged
source value and also rejects stale allowlist entries, so key-count coverage
can no longer hide untranslated interface text.
User-facing dates and times follow the selected locale through ICU, while
stored timestamps and machine-readable values keep their stable formats.

## Updating an installed language

Translation-only updates do **not** require a new Simbioza or PHP-module release.
In Setup's Languages section, check for language updates and update the installed
packs. Where GUI language installation is unavailable, run these commands from
the application root with its configured PHP CLI and an account permitted to
update its runtime language data:

```sh
vendor/bin/hph languages available --refresh
vendor/bin/hph languages update
vendor/bin/hph languages list
```

The update refreshes previously installed repository/bundled packs and preserves
their enabled/disabled state; it does not install every language in the catalogue.
Keep the same `HPH_CONFIG_PATH` and override layers as the web application. For
secured FPM, use the configured application runtime identity, not a shared Apache
account or an unrelated deployment. Review manual translations separately before
using `languages install <locale> --replace`, because replacement overwrites them.

Simbioza accepts UTF-8 text and BCP-47-like locale identifiers, so Cyrillic
languages such as `ru` or `sr-cyrl` can be packaged in the same way. A flag is
only a selector icon, not a claim that every speaker belongs to one country.
