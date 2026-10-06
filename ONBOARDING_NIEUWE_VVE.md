# OpenVvE AI voorbereiden en installeren

OpenVvE AI is zelf te hosten software waarmee een Vereniging van Eigenaars
(VvE) haar contacten, relaties, gebouwactiva, onderhoud, vergaderingen,
projecten en documenten op één plek kan beheren en doorzoeken. Het doel is dat
bevoegde gebruikers de toepassing via hun eigen website kunnen bereiken en
dat iedere VvE haar eigen gegevens, database, bestanden en toegangsbeheer houdt.
De AI-assistent is optioneel: de basiswebsite kan zonder externe AI-provider
worden gebruikt.

## Doel en financiële afbakening

OpenVvE AI gaat ervan uit dat de financiële administratie van de VvE door een
extern beheerkantoor wordt verzorgd. Dat kantoor blijft verantwoordelijk voor
onder meer het innen van eigenaarsbijdragen, het betalen van leveranciers en de
volledige boekhouding. OpenVvE AI vervangt die dienstverlening of administratie
niet.

Het systeem is een aanvulling voor VvE-bestuurders: het helpt inzicht te krijgen
in de staat en het onderhoud van gebouwen en andere activa, ondersteunt bij het
voorbereiden van voorstellen voor verbetering en onderhoud en helpt de
communicatie met bewoners te verbeteren. Financiële documenten kunnen als
bronnen worden bewaard en doorzocht, maar dat maakt OpenVvE AI niet tot een
boekhoudpakket of betalings- en incassosysteem.

## Waarom deze handleiding STRATO gebruikt

De stappen in deze handleiding zijn geschreven voor STRATO. Die keuze is
gebaseerd op de relatief voordelige Europese hostingpakketten en Europese
dataverwerking die STRATO beschrijft. Pakketprijzen, introductieaanbiedingen en
verlengingstarieven veranderen; controleer daarom vóór aankoop de actuele totale
kosten en voorwaarden op de [STRATO-kostenpagina](https://www.strato.nl/hosting/hosting-kosten/).
Als persoonsgegevens bij STRATO worden verwerkt, regel dan ook de toepasselijke
verwerkersovereenkomst en beoordeel de overige AVG-verplichtingen van de VvE.
Hosting in de EU helpt bij de keuze van het juridische kader, maar maakt de
installatie op zichzelf niet automatisch AVG-conform. Zie [STRATO's informatie
over de AVG en verwerkersovereenkomst](https://www.strato.nl/faq/contract/vragen-over-de-verwerkersovereenkomst-en-de-nieuwe-eu-verordening-avg-gdpr/).

Een andere hostingprovider kan technisch ook, maar deze handleiding werkt die
installatie niet stap voor stap uit. De VvE-beheerder moet dan zelf controleren
en inrichten dat de provider aan alle technische, beveiligings- en
privacyvereisten voldoet. De installatievoorbeelden en scherminstructies hier
gaan uit van STRATO. Een eventueel ingestelde AI-API is een afzonderlijke
externe dienst; beoordeel daarvoor apart welke gegevens worden doorgestuurd en
welke voorwaarden van de AI-provider gelden.

OpenVvE AI is open-sourcesoftware. Dat betekent dat de broncode beschikbaar is
om te bekijken, te gebruiken, aan te passen en te delen volgens de voorwaarden
van de meegeleverde AGPL-licentie. Bij gebruik via een netwerk gelden ook de
broncodeverplichtingen uit die licentie. Open source betekent niet dat hosting,
domeinregistratie of een AI-provider kosteloos zijn; die diensten kiest en
betaalt de VvE zelf. Lees [LICENSE](LICENSE) en [DISCLAIMER](DISCLAIMER.md).

## Wat is nodig?

Het eindresultaat is een website op het internet, bijvoorbeeld
`beheer.jouwvve.nl`. Hiervoor heeft de VvE het volgende nodig:

1. **Een STRATO-hostingpakket**: een hostingprovider verhuurt server- en
   opslagruimte waarop de website en database draaien. Deze handleiding gebruikt
   STRATO als standaard, omdat het een relatief voordelige Europese aanbieder is.
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

De repository bevat negen instructievideo's over functies van de app. Na
installatie zijn ze beschikbaar via het menu **Instructies** voor ingelogde
gebruikers, mits de beheerder de gedeelde map `instructies/` heeft geplaatst.
Deze video's leggen de app uit; ze zijn geen STRATO-, PHP-, database- of
installatiehandleiding. Die hostingstappen staan in de routegidsen hieronder.

De map `instructies/` wordt niet automatisch naar een instantie gekopieerd.
Plaats haar, met `.htaccess` en de negen via Git LFS opgehaalde MP4-bestanden,
naast de instantiemap en buiten alle openbare document-roots. Voor meerdere
VvE-instanties op hetzelfde hostingaccount kan die map worden gedeeld.

## Kies een van de drie routes

1. [Technische installatie](INSTALLATIE_TECHNISCH.md) — voor iemand die
   comfortabel is met lokale Python en serverterminal/SSH; ook deze route
   gebruikt STRATO als hostingvoorbeeld.
2. [Handmatig via STRATO-webpaneel](INSTALLATIE_STRATO_WEB.md) — laat zien
   hoe je domein, PHP, database, e-mail en SSL via het webpaneel inricht en
   bestanden/mappen met SFTP beheert. De stappen tot aan de eerste
   app-installatie zijn browsergestuurd; het afronden van versie 8.50-beta
   vraagt nog SSH. STRATO-hulp bevat screenshots bij de relevante handelingen.
3. [Met AI stap voor stap](INSTALLATIE_MET_AI.md) — een veilige werkwijze om
   een AI-chat als uitleg- en vraagbaak naast je scherm te gebruiken. Jij voert
   de handelingen zelf uit. De AI-wizard die zelfstandig de installatie
   uitvoert bestaat nog niet.

Een andere provider blijft mogelijk, maar de VvE-beheerder richt die zelfstandig
in en controleert zelf de technische, beveiligings- en AVG-vereisten. Zie
[privacy- en veiligheidsdisclaimer](DISCLAIMER.md) en [supportgrenzen](SUPPORT.md).
