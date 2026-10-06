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
een ingesloten afbeelding. De maker heeft verklaard het logo samen met AI te
hebben gemaakt en de rechten te bezitten. De bestanden hebben geen bruikbare
auteursrechtmetadata; de Git-geschiedenis toont alleen de initiële
repository-import. De verklaring van de maker is de beschikbare
rechtenonderbouwing. Leg daarnaast vast welke AI-dienst is gebruikt en controleer
de voorwaarden daarvan en of de afbeelding niet sterk lijkt op een bestaand
logo. Als ChatGPT/OpenAI is gebruikt, wijzen de huidige voorwaarden de output
tussen OpenAI en de gebruiker aan de gebruiker toe voor zover de wet dat
toestaat; ze waarschuwen ook dat output niet uniek hoeft te zijn.

De grote variant `OpenVvE AI logo groot.png` staat momenteel buiten versiebeheer
en is niet klaargezet voor upload. Een controle in het Benelux Merkenregister is
niet uitgevoerd; auteursrecht en merkregistratie zijn afzonderlijke vragen.
Leg vast of het logo onder AGPL-3.0-only mag worden hergebruikt of een aparte
merk-/beeldrichtlijn krijgt. Het softwarelicentiebestand noemt specifiek de
programmatuur; daardoor is de bedoeling voor beeldmateriaal nu niet expliciet.

### Instructievideo's

De app verwijst naar negen bestanden met vaste namen in `instructies/`. De
bestanden zijn H.264/AVC-video's en samen ongeveer 500 MB. Daarom worden ze met
Git LFS bijgehouden; `.htaccess` blokkeert rechtstreekse webtoegang tot de map.
GitHub begrenst gewone Git-bestanden op 100 MiB en raadt Git LFS aan voor zulke
binaire bestanden. GitHub Free bevat momenteel 10 GiB LFS-opslag en 10 GiB
bandbreedte per factureringsperiode. Veel downloads kunnen die bandbreedte
verbruiken.

De maker heeft bevestigd dat hij de video's zelf heeft gemaakt en uitsluitend
fictieve demo-gegevens heeft gebruikt. De volledige beeld- en geluidsinhoud kon
in deze werkomgeving niet worden afgespeeld of als frames worden uitgelezen; de
inhoudelijke controle is dus gebaseerd op die verklaring en niet onafhankelijk
uitgevoerd. Controleer zo nodig op herkenbare personen, muziek, lettertypes of
andere onderdelen van derden en vraag daarvoor toestemming als dat van toepassing
is.

De maker heeft verklaard de rechten op de video's te bezitten. Nog te kiezen is
of hergebruik van de video's onder AGPL-3.0-only valt, of dat een aparte
Creative-Commons-licentie geldt. De bestaande README beperkt de AGPL-aanduiding
tot de projectcode; maak de reikwijdte expliciet voordat de repository openbaar
wordt.

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
2. Leg de verklaring vast dat de maker het logo met AI heeft gemaakt. Noteer
   welke AI-dienst is gebruikt, controleer de toepasselijke voorwaarden en
   onderzoek eventueel het Benelux Merkenregister op verwarring met bestaande
   merken.
3. Leg de keuze vast of logo en video's onder AGPL-3.0-only of onder een aparte
   licentie worden verspreid. Controleer eventuele onderdelen van derden in de
   video's en regel daarvoor zo nodig toestemming.
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
- [OpenAI: gebruiksvoorwaarden](https://openai.com/policies/terms-of-use/)
