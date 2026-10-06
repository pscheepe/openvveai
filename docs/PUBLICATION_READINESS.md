# Voorbereiding op openbare publicatie

Beoordeeld op 6 oktober 2026. Dit document legt vast wat in de repository is
nagekeken en welke punten nog openstaan. Het is geen juridisch advies,
penetratietest of garantie dat alle auteursrechten zijn vastgesteld.

## Bevindingen

### Software en licenties

- De eigen applicatiecode wordt in `README.md` aangeduid als AGPL-3.0-only.
- De meegeleverde `smalot/pdfparser` heeft een eigen LGPL-3.0-licentie. De
  licentietekst en kennisgeving zijn aanwezig in `COPYING.LESSER`,
  `THIRD_PARTY_NOTICES.md` en de bibliotheekmap.
- Een licentie op de programmatuur geeft niet automatisch rechten op logo's,
  video's, portretten, muziek of andere werken. Voor publicatie moet ook voor
  die onderdelen de maker, bron en toegestane licentie zijn vastgelegd.

### Logo

De applicatie bevat `templates/logo.svg`, `templates/logo.png`,
`release/public/logo.svg` en `release/public/logo.png`. De SVG-versies bevatten
een ingesloten afbeelding. In de beschikbare bestanden en de Git-geschiedenis
staat geen bron, maker, overdrachtsbewijs of licentie. De bestanden hebben geen
bruikbare auteursrechtmetadata. De geschiedenis toont alleen dat de logo's in
de initiële repository-import zijn opgenomen; dat bewijst geen eigendom of
publicatierecht.

De grote variant `OpenVvE AI logo groot.png` staat momenteel buiten versiebeheer
en is niet klaargezet voor upload. Publiceer de logo's pas nadat de maker en de
rechten op alle beeld- en lettertypeonderdelen zijn bevestigd, en nadat is
vastgelegd onder welke voorwaarden anderen het logo mogen gebruiken. Een
controle in het Benelux Merkenregister is ook niet uitgevoerd; auteursrecht en
merkregistratie zijn afzonderlijke vragen.

### Instructievideo's

De app verwijst naar negen bestanden met vaste namen in `instructies/`. De
bestanden zijn H.264/AVC-video's en samen ongeveer 500 MB. Daarom worden ze met
Git LFS bijgehouden; `.htaccess` blokkeert rechtstreekse webtoegang tot de map.
GitHub begrenst gewone Git-bestanden op 100 MiB en raadt Git LFS aan voor zulke
binaire bestanden. GitHub Free bevat momenteel 10 GiB LFS-opslag en 10 GiB
bandbreedte per factureringsperiode. Veel downloads kunnen die bandbreedte
verbruiken.

De volledige beeld- en geluidsinhoud kon in deze werkomgeving niet worden
afgespeeld of als frames worden uitgelezen. Er is daarom geen inhoudelijke
controle uitgevoerd op namen, adressen, e-mail, VvE-documenten, accounts,
sleutels, notificaties, gesproken persoonsgegevens, portretrechten, muziek of
andere rechten van derden. Behandel de video's als niet vrijgegeven materiaal
totdat iemand ze van begin tot eind heeft bekeken en beluisterd. Verwijder of
maskeer gevoelige informatie onomkeerbaar en vraag zo nodig toestemming van
herkenbare personen en rechthebbenden.

Ook is nog niet vastgelegd of de video's onder AGPL-3.0, een aparte
Creative-Commons-licentie of alleen met een beperktere toestemming worden
verspreid. Leg de keuze en de maker/rechthebbende vast voordat de repository
openbaar wordt.

### Overige bestanden

De huidige commit bevat de onderhouden applicatie, documentatie, security-
reviews, licentiebestanden, tests en (na de video-upload) de negen instructie-
video's. Niet klaargezet voor publicatie zijn onder meer oudere patchmappen,
`package-dropdown-fix/`, de grote logo-variant, `OLLAMA_DEMO_B.md`, lokale
installatie-PDF's en `index.php`. Deze bestanden zijn niet inhoudelijk
gecontroleerd op verouderde code, privégegevens, serveradressen of rechten en
horen pas in een aparte review thuis. De Markdown-installatiehandleidingen die
al versiebeheerd zijn, blijven de bronversies; de niet-getrackte PDF's kunnen
verouderde kopieën zijn.

### Beveiliging en repositoryhygiëne

De bestaande `docs/SECURITY_REVIEW_GITHUB.md` is een statische review. Die noemt
onder andere aandachtspunten rond back-uptokens, gevoelige volledige back-ups
en de grens van één hostingaccount. Het is geen onafhankelijke audit of
penetratietest. Controleer voor publicatie of die bevindingen duidelijk als
bekende beperkingen zijn beschreven en voer een secretscan uit over de volledige
Git-geschiedenis. Verwijder gevonden geheimen ook uit de geschiedenis en roteer
ze; alleen verwijderen in de huidige werkmap is onvoldoende.

## Controlelijst vóór openbaarmaking

1. Bevestig dat alle codebijdragen mogen worden uitgegeven onder de genoemde
   licentie en dat de AGPL-versie exact overeenkomt met de projectkeuze.
2. Leg bron, maker, rechten en gebruiksvoorwaarden van de logo's schriftelijk
   vast. Vervang de logo's als de rechten niet duidelijk zijn. Controleer
   eventueel het Benelux Merkenregister op verwarring met bestaande merken.
3. Bekijk en beluister alle instructievideo's volledig. Bevestig rechten op
   scherminhoud, stemmen, personen, muziek, lettertypes en overige media; leg
   daarna de videolicentie en eventuele toestemming vast.
4. Voer een secretscan uit op alle branches en commits die openbaar worden.
   Beoordeel ook de niet-getrackte patch-, demo- en installatiebestanden apart.
5. Werk daarna README, licentiekennisgevingen en release-instructies bij en
   controleer een schone clone mét Git LFS. Test de gepubliceerde deployment
   onder de bedoelde hostingprovider.
6. Wijzig pas als laatste de repositoryzichtbaarheid. Dat is een aparte
   publicatiestap; dit document of een gewone `git push` maakt de repository
   niet openbaar.

## Bronnen

- [GitHub: repositorylimieten](https://docs.github.com/en/repositories/creating-and-managing-repositories/repository-limits)
- [GitHub: Git LFS-opslag en bandbreedte](https://docs.github.com/en/billing/concepts/product-billing/git-lfs)
- [Nederlandse overheid/RVO: auteursrecht](https://business.gov.nl/regulations/copyright/)
- [BOIP: intellectuele-eigendomsrechten controleren](https://business.gov.nl/products-services-and-innovation/protecting-your-product-or-idea/check-if-your-idea-is-already-protected-by-intellectual-property-rights/)
