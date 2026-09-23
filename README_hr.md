# Jezični paketi Simbioze

[Engleska verzija](README.md)

Ovaj je repozitorij javni katalog prijevoda sučelja Simbioze. Aplikacija dolazi
s hrvatskim (`hr`) i engleskim (`en`), a dodatni se jezici mogu instalirati iz
kataloga bez instaliranja PHP modula.
Hrvatski tekst sučelja u kodu Simbioze kanonski je ključ u svakom paketu.
`source_locale` bilježi jezik predloška iz kojeg se prevode vrijednosti:
njemački, francuski, španjolski i talijanski mogu se pripremiti s engleskog.
Pri prikazu se prijevod uvijek dohvaća izravno po hrvatskom ključu, bez
posrednog prevođenja preko engleskog.
Tehnički identifikatori poput oznaka prava (`can_view`) i postavki formata
datuma ostaju stabilni kodovi; nisu tekstovi prikazani korisniku.

Objavljeni su hrvatski, engleski, njemački, francuski, španjolski i talijanski.
Svaki paket sadrži višejezične nazive i SVG zastavicu. Francuski, španjolski i
talijanski tekst izrađen je pomoću AI alata i može sadržavati jezične pogreške;
molimo prijavite ispravke. Status objave potvrđuje strukturnu potpunost, a ne
lektoriranje izvornih govornika.

Svaka datoteka `packs/<jezik>.json` je jezični paket Simbioze formata 2.
`manifest.json` sadrži objavljene pakete i njihove SHA-256 sažetke. Installer i
Setup nude jezik samo kada njegov zapis u manifestu ima status `released`.
Prijevodi u izradi ostaju u `drafts/`.

Prijevodi u ovom repozitoriju pripremljeni su uz pomoć umjetne inteligencije,
uključujući postupak vođen ChatGPT/Codex agentom i Argos Translate za početne
francuske, španjolske i talijanske nacrte. Nisu potvrđeni lekturom izvornih
govornika, osobito za sigurnosne poruke, pristupačnost i administrativno
nazivlje. Pogreške prijavite
u issueu ili pull requestu. Ne prevodite zamjenske oznake poput `:name`, `%s`,
`%d` i `{{value}}`.

Za izradu novog prijevoda iz lokalne Simbioze:

```sh
vendor/bin/hph languages template fr --source=en --output=/putanja/do/simbioza-languages/drafts/fr.json
vendor/bin/hph languages validate /putanja/do/simbioza-languages/drafts/fr.json
```

Prevedite vrijednosti, upišite naziv jezika na više jezika i dodajte sigurnu
SVG zastavicu. Nakon pregleda i provjere objavite paket:

```sh
php scripts/publish.php /putanja/do/Simbioze fr --version=2026.09.23.4
php scripts/validate_catalog.php
```

Kada se u Simbiozi ili modulu dodaju ili promijene stringovi, izdvojite samo
razliku:

```sh
php scripts/sync.php /putanja/do/Simbioze --version=2026.09.23.4
```

Alat osvježava hrvatski i engleski referentni paket, a u `pending/<jezik>.json`
zapisuje samo nove ili promijenjene ključeve odnosno vrijednosti predloška
prema `source_locale` pojedinog paketa. Postojeće prijevode ne
prepisuje. Prevedite i pregledajte te ključeve, uklonite pending datoteku nakon
pregleda, provjerite paket i objavite novu reviziju. Instalirani paket
osvježava se pri sljedećoj nadogradnji aplikacije ako je objavljen novi sažetak;
`vendor/bin/hph languages update` provjerava odmah.

Manifest je podatkovna, a ne izvršna datoteka. Svaki objavljeni paket mora
sadržavati sigurnu SVG zastavicu, nazive jezika, sve izvorne ključeve i
nepromijenjene zamjenske oznake.
`translation_exceptions.json` je pregledani popis tehničkih izraza, naziva
proizvoda, naredbi i riječi koje se doista jednako pišu u izvornom i ciljnom
jeziku. Provjera odbija svaku drugu nepromijenjenu izvornu vrijednost, kao i
zastarjele iznimke, pa postotak koji broji ključeve više ne može skrivati
neprevedeni tekst sučelja.
Datumi i vremena vidljivi korisniku slijede
odabrani jezik prema ICU pravilima; pohranjeni i strojno čitljivi zapisi ostaju
stabilni.

Simbioza prihvaća UTF-8 tekst i jezične oznake nalik BCP 47, pa se mogu
pakirati i jezici na ćirilici poput `ru` ili `sr-cyrl`. Zastavica je samo ikona
u izborniku, ne tvrdnja da svi govornici pripadaju jednoj zemlji.
