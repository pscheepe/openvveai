# Voorbereiding op openbare publicatie

Beoordeeld op 6 oktober 2026. Dit document legt vast wat is nagekeken en welke
beperkingen overblijven. Het is geen juridisch advies, penetratietest of
garantie dat alle rechten onafhankelijk zijn vastgesteld.

## Rechten en licenties

De maker heeft bevestigd dat de eigen projectcode, documentatie, het met AI
gemaakte logo en de zelfgemaakte instructievideo's mogen worden gepubliceerd.
Voor het logo en de video's is uitsluitend fictieve demo-inhoud gebruikt. De
maker heeft gekozen voor AGPL-3.0-only voor de oorspronkelijke projectcode,
documentatie, logo's en video's. Dit is vastgelegd in `README.md` en
`THIRD_PARTY_NOTICES.md`.

De meegeleverde `smalot/pdfparser` blijft onder LGPL-3.0; de licentietekst en
kennisgeving zijn aanwezig in `COPYING.LESSER`, `THIRD_PARTY_NOTICES.md` en de
bibliotheekmap. De maker heeft aangegeven dat het logo is gemaakt met ChatGPT
5.6 Sol. Die modelvermelding documenteert de herkomst; de gekozen AGPL-3.0-only
licentie bepaalt de voorwaarden waaronder het logo met de projectbestanden
wordt hergebruikt. De licentie van de software wordt niet bepaald door de
gebruikte AI-dienst.

## Naam en logo

De naam is opgezocht in TMview; voor “OpenVvE AI” en “OpenVvE” werden geen
treffers gevonden. De officiële BOIP-controle kon niet worden voltooid doordat
de dienst niet beschikbaar was en het register een beveiligingsbarrière toonde.
Een zoekactie is geen garantie dat een naam vrij is. Auteursrecht, handelsnaam
en merkregistratie zijn afzonderlijke vragen. Een onafhankelijke visuele
vergelijking van het logo met andere merken is niet afgerond. De logo-bestanden
hebben geen bruikbare auteursrechtmetadata; de rechtenverklaring van de maker is
de beschikbare onderbouwing.

De losse grote variant `OpenVvE AI logo groot.png` is niet opgenomen in deze
release. De versiebeheerde logo's staan bij de applicatie.

## Instructievideo's

De app verwijst naar negen bestanden met vaste namen in `instructies/`. De
bestanden zijn samen ongeveer 500 MB en staan onder Git LFS; `.htaccess` blokkeert
rechtstreekse webtoegang tot die map. Nieuwe clones moeten Git LFS installeren
en de LFS-objecten ophalen. Houd rekening met GitHub-opslag- en
bandbreedtelimieten. In de app verschijnen de video's voor ingelogde gebruikers
via een geauthenticeerde stream. `tools/create_instance.py` kopieert de map niet:
voor weergave op een server moet `instructies/` apart in de bovenliggende map
van de instanties worden geplaatst, buiten de document-roots. De
installatiehandleidingen beschrijven dit nu.

De maker heeft bevestigd de video's zelf te hebben gemaakt, uitsluitend
fictieve demo-gegevens te gebruiken en ze onder AGPL-3.0-only beschikbaar te
stellen. De volledige beeld- en geluidsinhoud is hier niet onafhankelijk
gecontroleerd. Herkenbare personen, muziek, lettertypes of andere werken van
derden moeten afzonderlijk worden gecontroleerd en zo nodig vrijgegeven.

## Bestanden buiten de release

Oudere patchmappen, `package-dropdown-fix/`, `OLLAMA_DEMO_B.md`, installatie-
PDF-kopieën, een losse grote logo-variant, `index.php` en losse eenmalige
hulpscripts blijven buiten de release; ze zijn niet volledig beoordeeld op
actualiteit, persoonlijke gegevens, serveradressen of rechten. De
niet-getrackte `AI_INSTALLATIEBEGELEIDER.md` en `INSTALLATIESTRATEGIE.md` zijn
wel ingezien: het zijn voorstellen voor toekomstig werk, geen beschrijving van
geïmplementeerde installatieroutes. Ze zijn niet klaargezet voor publicatie.
De Markdown-handleidingen in versiebeheer zijn de actuele bronbestanden. De
persoonlijke Ollama-notities bevatten details van de thuisomgeving en worden
niet gepubliceerd.

## Secretscan en beveiligingsreview

De volledige aanwezige Git-geschiedenis en de huidige werkmap zijn met Gitleaks
gescand. De enige treffer was een vaste, openbare RFC 6238-TOTP-testvector in
`tests/test_mfa.php`, geen accountgeheim. Die is in de bron als testfixture
gemarkeerd. `.gitleaksignore` onderdrukt uitsluitend deze historische treffer
op basis van zijn exacte fingerprint; andere meldingen blijven zichtbaar.

`docs/SECURITY_REVIEW_GITHUB.md` is een statische code-review, geen onafhankelijke
audit of penetratietest. Daarin staan onder meer risico's rond back-uptokens,
gevoelige volledige back-ups en de hostingaccountgrens. Een live deployment,
providerconfiguratie en geïsoleerde database-integratietest zijn niet door deze
publicatievoorbereiding gevalideerd.

## Controlelijst

1. **Rechtenverklaring:** ontvangen van de maker voor eigen code, logo en
   video's; dit is geen onafhankelijk juridisch oordeel.
2. **Naam/logo:** eerste TMview-zoekactie zonder treffers; officiële BOIP- en
   onafhankelijke beeldvergelijking bleven onvoltooid.
3. **Licentie:** AGPL-3.0-only voor eigen projectcode, documentatie, logo's en
   video's; LGPL-3.0 voor de meegeleverde PDF-parser.
4. **Secretscan:** uitgevoerd over alle aanwezige Git-commits/refs en de
   werkmap; de ene historische publieke testvector is per exacte fingerprint
   gedocumenteerd en uitgesloten.
5. **Documentatie:** README, installatiehandleidingen, derde-partijkennisgeving,
   projectvisie en release-instructies bijgewerkt om implementatie en de
   app-video's te beschrijven. Zie [`RELEASING.md`](../RELEASING.md) voor de
   clone- en LFS-checklist.
6. **Zichtbaarheid:** repository blijft privé tot de releasechecks zijn
   afgerond. Openbaar maken is een afzonderlijke laatste handeling.

## Bronnen

- [GitHub: repositorylimieten](https://docs.github.com/en/repositories/creating-and-managing-repositories/repository-limits)
- [GitHub: Git LFS-opslag en bandbreedte](https://docs.github.com/en/billing/concepts/product-billing/git-lfs)
- [Nederlandse overheid/RVO: auteursrecht](https://business.gov.nl/regulations/copyright/)
- [BOIP: intellectuele-eigendomsrechten controleren](https://business.gov.nl/products-services-and-innovation/protecting-your-product-or-idea/check-if-your-idea-is-already-protected-by-intellectual-property-rights/)
- [OpenAI: gebruiksvoorwaarden](https://openai.com/policies/terms-of-use/)
