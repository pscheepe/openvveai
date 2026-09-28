# OpenVvE AI voorbereiden en installeren

OpenVvE AI is zelf te hosten software waarmee een Vereniging van Eigenaars
(VvE) haar contacten, relaties, gebouwactiva, onderhoud, vergaderingen,
projecten en documenten op één plek kan beheren en doorzoeken. Het doel is dat
bevoegde gebruikers de toepassing via hun eigen website kunnen bereiken en
dat iedere VvE haar eigen gegevens, database, bestanden en toegangsbeheer houdt.
De AI-assistent is optioneel: de basiswebsite kan zonder externe AI-provider
worden gebruikt.

OpenVvE AI is open-sourcesoftware. Dat betekent dat de broncode beschikbaar is
om te bekijken, te gebruiken, aan te passen en te delen volgens de voorwaarden
van de meegeleverde AGPL-licentie. Bij gebruik via een netwerk gelden ook de
broncodeverplichtingen uit die licentie. Open source betekent niet dat hosting,
domeinregistratie of een AI-provider kosteloos zijn; die diensten kiest en
betaalt de VvE zelf. Lees [LICENSE](LICENSE) en [DISCLAIMER](DISCLAIMER.md).

## Wat is nodig?

Het eindresultaat is een website op het internet, bijvoorbeeld
`beheer.jouwvve.nl`. Hiervoor heeft de VvE het volgende nodig:

1. **Een hostingprovider**: een bedrijf dat een server en opslagruimte verhuurt
   waarop de website en database draaien. STRATO is één voorbeeld; andere
   hostingproviders kunnen ook, als ze aan de eisen hieronder voldoen.
2. **Een domeinnaam**: het internetadres waarmee gebruikers de website openen.
   De VvE registreert een domein of maakt een subdomein aan bij de provider.
3. **Webruimte met PHP**: de plaats waar de websitebestanden staan. Het domein
   moet naar de juiste openbare map `public` verwijzen.
4. **Een database**: MySQL of MariaDB slaat gestructureerde VvE-gegevens op.
   Bestanden en uploads gebruiken daarnaast afgeschermde opslagruimte.
5. **Een e-mailadres**: minimaal één VvE-mailadres voor beheer en
   contactherstel. E-mailsynchronisatie vanuit de applicatie is optioneel en
   vraagt aanvullende mailboxinstellingen.
6. **HTTPS/SSL**: HTTPS versleutelt het verkeer tussen de browser van een
   gebruiker en de website. Dat helpt inloggegevens en uitgewisselde informatie
   onderweg te beschermen en voorkomt browserwaarschuwingen over een onveilige
   verbinding. Hiervoor moet een actief SSL/TLS-certificaat bij het domein
   horen. De provider kan een inbegrepen certificaat aanbieden; controleer dit
   vóór je iets bestelt.
7. **Een AI-account en API-verbinding (optioneel)**: alleen nodig als de VvE
   de externe AI-assistent wil gebruiken. De VvE maakt zelf een account/project
   bij een AI-provider, configureert facturering en grenzen en bewaart de
   API-sleutel veilig. Zonder deze stap blijft de lokale documentzoekfunctie
   beschikbaar.
8. **Een VvE-beheerder**: iemand die de provider, domeinnaam, database,
   beheerdersaccount en back-ups namens de VvE beheert. Leg vast hoe toegang
   wordt overgedragen bij een bestuurswissel.

### Technische eisen voor versie 8.50-beta

- PHP 8.4 met `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `zip` en `zlib`;
  `iconv` wordt ook gebruikt en GD is nodig voor afbeeldingen.
- MySQL of MariaDB, HTTPS, voldoende opslag en uploadlimieten die passen bij
  de documenten die de VvE wil bewaren.
- Een configureerbare document-root die alleen de map `public` aanbiedt;
  `private` en `install` moeten buiten de openbare website blijven.
- Bestandsbeheer/SFTP om bestanden veilig over te zetten. De huidige
  8.50-beta vraagt daarnaast PHP-CLI via SSH om de installatie en het eerste
  beheerdersaccount af te ronden.
- IMAP is optioneel en alleen nodig voor e-mailsynchronisatie.
- Een kopie van back-ups buiten de hostingruimte, bijvoorbeeld op een door de
  VvE beheerde NAS of een goed beveiligde VvE-computer. Zo blijft de VvE bij
  uitval of verlies van het hostingaccount niet alleen afhankelijk van de
  serverkopie.

**Let op:** 8.50-beta is een testversie. De instructies hieronder gaan uit van
een nieuwe VvE-installatie, een nieuw domein en een nieuwe lege database; ze
veronderstellen geen bestaande website of bestaande VvE-installatie. Begin met
fictieve gegevens. De browser-only route en een AI-installatiewizard zijn nog
geen functies van deze versie. Zie ook [de status van de drie
installatieroutes](#kies-een-van-de-drie-routes).

### Waarom een aparte `private`-map?

De `public`-map bevat webpagina’s die bezoekers mogen openen. De map `private`
bevat onder andere database-instellingen, gebruikersinformatie, sessies,
uploads en back-ups. Als die map via internet bereikbaar is, kunnen geheimen
of VvE-documenten uitlekken. Daarom moet alleen `public` als website-map
(document-root) zijn ingesteld; `private` en `install` moeten erbuiten staan.
Dit is een noodzakelijke beveiligingsgrens, geen optionele mapindeling.

### Instructievideo’s

Bij de stappen komen korte instructievideo’s. De links worden toegevoegd zodra
de video’s zijn gepubliceerd. De routegidsen markeren waar een bijbehorende
video-link kan worden geplaatst.

<!-- ***VIDEO_LINKS: voeg gepubliceerde URLs voor STRATO, mappen/upload, database en eerste login toe.*** -->

## Kies een van de drie routes

1. [Technische installatie](INSTALLATIE_TECHNISCH.md) — voor iemand die
   comfortabel is met lokale Python en serverterminal/SSH.
2. [Handmatig via STRATO-webpaneel](INSTALLATIE_STRATO_WEB.md) — laat zien
   hoe je domein, PHP, database, e-mail en SSL via het webpaneel inricht en
   bestanden/mappen met SFTP beheert. De stappen tot aan de eerste
   app-installatie zijn browsergestuurd; het afronden van versie 8.50-beta
   vraagt nog SSH. STRATO-hulp bevat screenshots bij de relevante handelingen.
3. [Met AI stap voor stap](INSTALLATIE_MET_AI.md) — een veilige werkwijze om
   een AI-chat als uitleg- en vraagbaak naast je scherm te gebruiken. Jij voert
   de handelingen zelf uit. De AI-wizard die zelfstandig de installatie
   uitvoert bestaat nog niet.

Voor hosting buiten STRATO gelden dezelfde functionele eisen; menunamen en
schermen verschillen per provider. Zie [privacy- en
veiligheidsdisclaimer](DISCLAIMER.md) en [supportgrenzen](SUPPORT.md).
