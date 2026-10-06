# Release-instructies

Gebruik alleen deze opgeschoonde repository als bron. Publiceer geen lokale
configuratie, gebruikersbestanden, uploads, databases, back-ups, sessies,
`.env`-bestanden of historische patchmappen.

## Voor elke release

1. Werk de versie in `VERSION` en de releaseverwijzingen in de documentatie
   bij. Controleer de code, tests, installatiestappen, licenties en
   `docs/SECURITY_REVIEW_GITHUB.md`.
2. Controleer dat alleen bedoelde bestanden zijn opgenomen:

   ```sh
   git status --short
   git diff --check
   git diff --cached --check
   ```

3. Scan alle refs en de werkmap op geheimen. Gebruik een actuele Gitleaks-
   installatie en beoordeel iedere treffer; voer gevonden echte geheimen ook
   uit de Git-geschiedenis af en roteer ze.
4. Zorg dat Git LFS beschikbaar is en dat alle negen instructievideo's als
   echte bestanden aanwezig zijn, niet als kleine LFS-pointertekstbestanden:

   ```sh
   git lfs install
   git lfs pull
   git lfs ls-files
   ```

   Open een clone opnieuw met `git clone` en `git lfs pull` om te controleren
   dat een nieuwe gebruiker de volledige video's kan ophalen. Controleer dat
   iedere MP4 een geldige videobestandsgrootte heeft en speel de instructies
   steekproefsgewijs af.
5. Bouw de deployment vanuit `release/public/` volgens de technische
   installatiehandleiding. Test op een aparte installatie met fictieve data en
   de gekozen hostingprovider. De GitHub-code-review is geen provider-audit of
   penetratietest.
6. Controleer vóór tag/push de exacte commit, licentie-inventaris en
   third-party notices. Behoud LFS-objecten; vervang of verwijder bestaande
   LFS-objecten alleen wanneer daar een bewuste migratie voor is.

## Nieuwe clone controleren

```sh
git clone <repository-url> openvveai
cd openvveai
git lfs install
git lfs pull
git lfs ls-files
```

Controleer dat `instructies/` negen MP4-bestanden bevat en dat geen bestand
begint met `version https://git-lfs.github.com/spec/v1`. Zonder Git LFS kan Git
wel een clone opleveren, maar de video's zijn dan alleen pointerbestanden.

## Openbaarmaking op GitHub

Maak de repository pas openbaar wanneer de code- en rechtencontroles zijn
afgerond, de releasebranch en LFS-bestanden zijn gecontroleerd, er geen echte
geheimen in de geschiedenis staan en de maintainer de uiteindelijke wijziging
van repositoryzichtbaarheid expliciet bevestigt. Een commit of push verandert
de repositoryzichtbaarheid niet. Na openbaarmaking: controleer de publieke
README, licentie, instructievideo-downloads, securitymeldingen en
GitHub Actions-instellingen.
