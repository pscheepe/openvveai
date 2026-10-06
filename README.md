# OpenVvE AI — zelf te hosten VvE-software

OpenVvE AI is bedoeld als gratis open-source software voor VvE-beheer die
gebruikers zelf kunnen installeren, beheren en aanpassen. De oorspronkelijke
projectcode, documentatie, instructievideo's en logo's vallen onder de GNU
Affero General Public License versie 3 (AGPL-3.0-only). De meegeleverde
PDF-parser behoudt zijn eigen LGPL-3.0-licentie; zie
[`THIRD_PARTY_NOTICES.md`](THIRD_PARTY_NOTICES.md).
**Huidige versie: 8.50-beta.** Dit is een testversie; gebruik haar eerst in
een afgeschermde testinstallatie met fictieve gegevens. Lees ook de
[disclaimer](DISCLAIMER.md), [supportgrenzen](SUPPORT.md)
en [derdenkennisgevingen](THIRD_PARTY_NOTICES.md). De GitHub-repository is nog
privé; een push maakt haar niet automatisch openbaar.

Als je een aangepaste versie op een server aanbiedt, lees dan ook AGPL-sectie 13:
gebruikers die ermee via een netwerk communiceren moeten toegang krijgen tot de
Corresponding Source van die draaiende versie. De rechtenverklaring en
resterende publicatiebeperkingen staan in
[`docs/PUBLICATION_READINESS.md`](docs/PUBLICATION_READINESS.md).

Deze versie bevat de volledige PHP-toepassing: contacten, adressen, relaties,
activa, sleutels, onderhoud, MJOP-verwijzingen, projecten, vergaderingen,
actiepunten, documenten en versies, zoeken, assistent, e-mailbeheer,
gebruikers, instellingen, activiteitenlog, versielog en back-ups.

## Architectuur

Eén onderhouden broncodeversie in `release/public`, uitgerold als een afzonderlijke
installatie per VvE. Iedere installatie krijgt een eigen HTTPS-subdomein,
database en databasegebruiker, gebruikersbestand, sessies, configuratie,
documentopslag en back-ups. Er is geen gedeelde VvE-keuzelijst of centraal
gebruikersaccount. Een beheerder die voor meerdere VvE's werkt, logt per VvE in.

De server kiest de VvE uitsluitend uit de vaste configuratie van de installatie.
Een URL-parameter kan geen andere VvE selecteren. De database bevat een
installatie-ID: een verkeerde databaseconfiguratie wordt geweigerd, ook als
de opgegeven database-inloggegevens geldig zijn.

Dit is applicatiescheiding binnen één hostingaccount. De eigenaar van het
Strato-account heeft toegang tot alle installaties. Het is geen afzonderlijk
besturingssysteem of afzonderlijk hostingaccount per VvE. Voor onbetrouwbare
beheerders met SFTP/PHP-toegang zijn afzonderlijke hostingaccounts nodig.

OpenVvE AI ondersteunt VvE-bestuurders bij inzicht in gebouw en activa,
onderhoud en bewonerscommunicatie. Het vervangt geen extern beheerkantoor voor
bijdrage-incasso, leveranciersbetalingen of volledige boekhouding. De doelstelling,
technische benodigdheden en drie STRATO-gerichte installatieroutes staan in
[ONBOARDING_NIEUWE_VVE.md](ONBOARDING_NIEUWE_VVE.md). Er is een technische
SSH-route, een handmatige STRATO-webpaneelgids en een gids voor veilig gebruik
van AI als stap-voor-stap vraagbaak. STRATO is de standaardroute vanwege de
relatief voordelige Europese hostingmogelijkheden; andere providers vergen
zelfstandige inrichting door de VvE-beheerder. De laatste twee zijn geen zelfstandige
webinstaller: versie 8.50-beta heeft voor de laatste installatiestappen nog
SSH nodig. Een AI-wizard die hostinginstellingen zelf uitvoert bestaat nog niet.

## Wat is bewust leeg?

Geen bestaande contacten, adressen, documenten, e-mails, documentteksten,
zoekindexen, activiteiten, gebruikers, wachtwoorden, API-sleutels of back-ups
zijn overgenomen. De oude Git-geschiedenis van de oorspronkelijke VvE-app,
oude ZIP's, SQL-dumps en bronanalyses zijn niet opgenomen. De naam, het adres,
het logo en specifieke MJOP-documentkoppelingen van de oorspronkelijke VvE zijn
verwijderd. De negen huidige productinstructies
staan in `instructies/` en worden met Git LFS opgeslagen; Git LFS moet zijn
geïnstalleerd om de videobestanden bij een nieuwe clone op te halen. Voor
weergave in een serverinstallatie moet deze gedeelde map in de bovenliggende
map van de VvE-instanties en buiten de document-roots worden geplaatst; zie
[het installatieoverzicht](ONBOARDING_NIEUWE_VVE.md#instructievideos). De oude
opnames uit de oorspronkelijke VvE-omgeving zijn niet opgenomen. Volgens de
maker zijn de huidige video's zelfgemaakt met fictieve demo-gegevens.
De maker heeft bevestigd het logo met AI te hebben gemaakt en heeft gekozen
voor AGPL-3.0-only voor eigen logo's en video's. Zie
[`docs/PUBLICATION_READINESS.md`](docs/PUBLICATION_READINESS.md) voor de
vastgelegde verklaring en resterende controles.

De lege database bevat alleen de structuur. De installer voegt één installatie-ID
en algemene namen van gebouwelementen toe, zonder oude documenten of paginanummers.
Algemene nieuwe keuzelijsten staan in `templates/lookups.php`. De keuzelijsten
voor activa- en onderhoudscategorieën lezen bestaande omschrijvingen uit de
gelijknamige databasetabellen. Een aanwezige `StabuCodes`-tabel wordt ook
gelezen. In Instellingen kan de beheerder de getoonde tekst per code aanpassen
of nieuwe codes toevoegen; die wijzigingen blijven in de privéconfiguratie
van de eigen VvE en krijgen voorrang op de databasetekst.

Externe AI staat standaard uit; de lokale bronnenzoeker blijft aanwezig.
E-mailaccounts en mailboxinstellingen zijn optioneel configureerbaar; het
mailboxwachtwoord staat server-lokaal buiten de database en de installatie-ZIP.
Automatische back-ups staan standaard uit.
Tweestapsverificatie via een authenticator-app is beschikbaar als
installatieoptie. Deze staat standaard uit voor de demo; een beheerder kan
haar per echte installatie optioneel of verplicht maken. Herstelcodes zijn
eenmalig. Bewaar de private `mfa.key` samen met de gebruikersconfiguratie in
een beveiligde applicatieback-up.

## Nieuwe VvE voorbereiden

Begin bij [OpenVvE AI voorbereiden en installeren](ONBOARDING_NIEUWE_VVE.md).
De volledige technische, STRATO en AI-begeleide routes zijn afzonderlijk
beschreven:

- [Technische installatie](INSTALLATIE_TECHNISCH.md)
- [Handmatig via STRATO-webpaneel](INSTALLATIE_STRATO_WEB.md)
- [Met AI stap voor stap](INSTALLATIE_MET_AI.md)

De STRATO-route linkt per scherm naar STRATO-help met actuele voorbeelden en
screenshots. Alle drie beschrijven de huidige bèta en maken duidelijk welke
stappen nog SSH vereisen. Een andere provider is technisch mogelijk, maar de
VvE-beheerder richt die zelfstandig in en toetst die aan de vereisten; deze
handleiding geeft daarvoor geen aparte scherm-voor-schermroute. Een veilige
browserinstaller is toekomstig werk.

## Back-ups en herstel

De bestaande back-upfunctie is per VvE gescheiden. De database-export bevat alleen
de eigen database. De applicatie-ZIP omvat de eigen code, private-configuratie,
gebruikers en uploads; bestaande back-ups, sessies en tijdelijke bestanden worden
uitgesloten om recursieve archieven te voorkomen. Deze ZIP bevat dus geheimen en
moet privé blijven. De MFA-sleutel staat in de private-configuratie en is nodig
voor authenticatorcodes; herstelcodes werken ook als deze sleutel verloren gaat.
Een back-up op dezelfde hosting is geen off-site back-up.

Herstel een volledige back-up uitsluitend als dezelfde VvE, inclusief de bijbehorende
installatie-ID. Gebruik voor een nieuwe VvE altijd een nieuwe lege installatie.

## Verder ontwikkelen

Deze repository is de schone basis. Ontwikkel de gemeenschappelijke PHP-code in
`release/public`. Rol dezelfde geteste code uit naar de `public`-map van
iedere VvE; behoud diens `private`-map. Neem wijzigingen aan templates en schema
mee in versiebeheer en maak expliciete migraties voor bestaande installaties.

De eenmalige omzetting uit de oorspronkelijke VvE-toepassing en databaseback-up
maakt geen deel uit van deze repository. Ontwikkel rechtstreeks in deze broncode;
neem geen gegevens of geschiedenis van die oorspronkelijke toepassing over.

## Licentie, geen support en geen garantie

Zie [LICENSE](LICENSE) voor de AGPL-3.0-licentie en
[COPYING.LESSER](COPYING.LESSER) voor de licentie van de meegeleverde PDF-parser.
De software is “zoals ze is”; er wordt geen garantie, aansprakelijkheid of
supportdienst toegezegd, voor zover wettelijk toegestaan. De precieze grenzen en
verantwoordelijkheden staan in [DISCLAIMER.md](DISCLAIMER.md) en [SUPPORT.md](SUPPORT.md).
Deze projectteksten zijn geen juridisch advies. De maker heeft bevestigd dat de
eigen code en media mogen worden gepubliceerd en heeft AGPL-3.0-only voor die
eigen materialen gekozen. De naam- en logocontroles hebben beperkingen; zie
[`docs/PUBLICATION_READINESS.md`](docs/PUBLICATION_READINESS.md) voor de
resterende controles vóór openbaarmaking.

## Controles

De integratietest gebruikt twee nieuwe databases en fictieve records. Een
test-router simuleert HTTPS-metadata;
dit vervangt de controle van Apache, SSL en `.htaccess` bij Strato niet.
De test-router en testaccounts horen nooit in `public` of in een live-installatie.

De integratietest vereist een expliciet opgegeven tijdelijke MariaDB-socket.
Gebruik daarvoor geen bestaande productiedatabase. Op macOS moet het testvolume
hoofdlettergevoelig zijn: het bestaande schema heeft twee verschillende tabelnamen
die alleen in hoofdlettergebruik verschillen.
