# OpenVvE AI — ontwikkelbasis voor meerdere VvE's

OpenVvE AI is bedoeld als gratis open-source software voor VvE-beheer die
gebruikers zelf kunnen installeren, beheren en aanpassen. Deze repository is
nog een **privé-ontwikkelbasis** en is nog niet openbaar uitgebracht. De eigen
projectcode krijgt de AGPL-3.0-licentie; de meegeleverde PDF-parser behoudt
zijn eigen LGPL-3.0-licentie. De beoogde projectwebsite is
`openvveai.nl`. Zie [PROJECTVISIE.md](PROJECTVISIE.md) voor de positionering
en de voorwaarden voor een latere openbare uitgave.

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

## Wat is bewust leeg?

Geen bestaande contacten, adressen, documenten, e-mails, documentteksten,
zoekindexen, activiteiten, gebruikers, wachtwoorden, API-sleutels of back-ups
zijn overgenomen. Geen Git-historie, oude ZIP's, SQL-dumps of bronanalyses worden
meegeleverd. De naam, het adres, het logo en specifieke MJOP-documentkoppelingen
van de oorspronkelijke VvE zijn verwijderd. Oude instructieopnames zijn niet
overgenomen: die kunnen VvE-gegevens op het scherm of in de gesproken tekst bevatten.
De videofunctie blijft beschikbaar voor nieuwe, neutrale instructievideo's.

De lege database bevat alleen de structuur. De installer voegt één installatie-ID
en algemene namen van gebouwelementen toe, zonder oude documenten of paginanummers.
Algemene nieuwe keuzelijsten staan in `templates/lookups.php`; categorieën en
STABU-codes kunnen via Instellingen per VvE worden ingevuld.

Externe AI staat standaard uit; de lokale bronnenzoeker blijft aanwezig.
E-mail heeft nog geen accounts of wachtwoorden. Automatische back-ups staan uit.

## Nieuwe VvE voorbereiden

Gebruik Python 3 op de ontwikkelcomputer. Werk vanuit deze map:

```sh
python3 tools/create_instance.py \
  --destination instances/demo-a \
  --name 'Demo VvE A' \
  --host demo-a.example.org
```

Voer hetzelfde commando met een andere bestemming, naam en host uit voor VvE B.
Het script weigert bestaande installaties te overschrijven. Kopieer nooit de
`private`-map van A naar B: de installatie-ID en gebruikers horen bij één VvE.

Structuur per installatie:

```text
demo-a/
  .htaccess               standaard geen webtoegang
  public/                 enige document-root van het subdomein
  private/                configuratie, gebruikers, bestanden en sessies
  install/                CLI-installatie en gegevensvrij schema
```

## Installatie op Strato

1. Maak voor iedere VvE een eigen lege database met eigen toegangsgegevens.
2. Upload de bijbehorende installatie, bijvoorbeeld naar `/vve-platform/demo-a`.
3. Koppel het HTTPS-subdomein uitsluitend aan `/vve-platform/demo-a/public`.
   Laat `private` en `install` daarbuiten. De extra `.htaccess`-bestanden blokkeren
   toegang via alternatieve domeinverwijzingen. Controleer ook het Strato-masteradres.
4. Kopieer `private/config.example.php` naar `private/config.php` en vul uitsluitend
   de nieuwe databasegegevens in. Houd private-bestanden op 0600 en mappen op 0700
   waar de Strato-uitvoergebruiker dit ondersteunt. Gebruik nooit 0777.
5. Voer via SSH, vanuit de installatie, uit:

   ```sh
   php install/create_admin.php
   php install/install.php
   php install/check.php
   ```

   Gebruik het PHP 8.4-commando van de server. Het eerste script vraagt interactief
   om een nieuw wachtwoord en slaat alleen de hash op. Er is geen standaardlogin.
   De installer weigert iedere database die al tabellen of views bevat.
   DDL is niet transactioneel: bij een mislukte import stopt de installer. Controleer
   de oorzaak en gebruik vervolgens een nieuwe lege database; importeer niet over
   de bestaande VvE-database heen.

6. Controleer HTTPS, inloggen, een fictieve upload/download, zoeken, CRUD per module,
   afmelden en de scheiding met een tweede VvE. Controleer dat `private/config.php`,
   `install/schema.json` en bestanden onder `private/uploads` via HTTP niet bereikbaar zijn,
   ook niet via het hoofddomein of Strato-masteradres.
7. Maak daarna pas echte gebruikers of koppel eigen e-mail/AI-diensten.

Benodigde PHP-extensies: PDO MySQL, mbstring, fileinfo, OpenSSL, ZIP en zlib.
GD is nodig voor beeldverwerking. E-mailsynchronisatie vereist de PHP IMAP-extensie;
de aanwezigheid daarvan op Strato moet afzonderlijk worden gecontroleerd.
Uploadlimieten hangen ook af van `upload_max_filesize` en `post_max_size`.

De applicatie voert de nieuwe schema-installatie buiten gewone webverzoeken uit.
Gebruik indien de hosting dat toestaat een runtime-databasegebruiker met alleen
SELECT, INSERT, UPDATE, DELETE en SHOW VIEW. Het lokaal geteste runtimepad werkt
zonder CREATE, ALTER en DROP. Latere schemawijzigingen horen in aparte migraties.

## Back-ups en herstel

De bestaande back-upfunctie is per VvE gescheiden. De database-export bevat alleen
de eigen database. De applicatie-ZIP omvat de eigen code, private-configuratie,
gebruikers en uploads; bestaande back-ups, sessies en tijdelijke bestanden worden
uitgesloten om recursieve archieven te voorkomen. Deze ZIP bevat dus geheimen en
moet privé blijven. Een back-up op dezelfde hosting is geen off-site back-up.

Herstel een volledige back-up uitsluitend als dezelfde VvE, inclusief de bijbehorende
installatie-ID. Gebruik voor een nieuwe VvE altijd een nieuwe lege installatie.

## Verder ontwikkelen

Bewaar deze schone basis in een nieuwe repository. Ontwikkel de gemeenschappelijke
PHP-code in `release/public`. Rol dezelfde geteste code uit naar de `public`-map van
iedere VvE; behoud diens `private`-map. Neem wijzigingen aan templates en schema mee
in versiebeheer en maak expliciete migraties voor bestaande installaties.

De eenmalige omzetting uit de oorspronkelijke VvE-toepassing en databaseback-up
maakt geen deel uit van deze repository. Ontwikkel rechtstreeks in deze schone
broncode; neem geen gegevens of Git-geschiedenis uit de oorspronkelijke VvE over.

## Controles

De integratietest gebruikt twee nieuwe databases en fictieve records. Een
test-router simuleert HTTPS-metadata;
dit vervangt de controle van Apache, SSL en `.htaccess` bij Strato niet.
De test-router en testaccounts horen nooit in `public` of in een live-installatie.

De integratietest vereist een expliciet opgegeven tijdelijke MariaDB-socket.
Gebruik daarvoor geen bestaande productiedatabase. Op macOS moet het testvolume
hoofdlettergevoelig zijn: het bestaande schema heeft twee verschillende tabelnamen
die alleen in hoofdlettergebruik verschillen.
