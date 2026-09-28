# Route 1 — technische installatie

Deze route is bedoeld voor een technisch beheerder die Python lokaal kan
uitvoeren en SSH op de hostingserver kan gebruiken. Voor de doelstelling,
definities en algemene benodigdheden, begin bij
[OpenVvE AI voorbereiden en installeren](ONBOARDING_NIEUWE_VVE.md).

De stappen hieronder zijn de huidige, door de broncode ondersteunde route voor
versie 8.50-beta en gebruiken STRATO als uitgangspunt. De handleiding is geen
configuratieplan voor andere providers; de VvE-beheerder moet daar zelf de
gelijkwaardige eisen controleren en inrichten. OpenVvE AI vult het externe
beheerkantoor aan en vervangt geen bijdrage-incasso, leveranciersbetalingen of
volledige boekhouding. Maak altijd een aparte installatie en lege database per
VvE.

## 1. Maak lokaal een schone instantie

<!-- ***INSTRUCTIEVIDEO: link toevoegen zodra de video over een nieuwe instantie beschikbaar is.*** -->

Haal de vertrouwde broncode op. Voer vanuit de projectmap uit en vervang naam
en host door die van de VvE:

```sh
python3 tools/create_instance.py \
  --destination instances/mijn-vve \
  --name 'VvE Voorbeeld' \
  --host beheer.vve-voorbeeld.nl
```

Het script weigert bestaande bestemmingen te overschrijven. Het maakt onder
meer `public`, `private` en `install`. Deel de `private`-map nooit tussen VvE's.

## 2. Maak hostingvoorzieningen aan

Maak in STRATO een domein/subdomein, PHP-webruimte, een nieuwe lege
MySQL/MariaDB-database en een eigen databasegebruiker aan. Activeer HTTPS.
Bewaar databasehost, database, gebruikersnaam en wachtwoord in een VvE-
wachtwoordkluis, nooit in Git of een chat. Zie de STRATO-stappen in
[route 2](INSTALLATIE_STRATO_WEB.md) voor schermnamen en voorbeelden.

## 3. Upload en configureer

<!-- ***INSTRUCTIEVIDEO: link toevoegen zodra de video over uploaden en configureren beschikbaar is.*** -->

Upload met SFTP de volledige instantie naar een nieuwe projectmap, bijvoorbeeld
`/vve-platform/mijn-vve`. Stel de document-root van het domein uitsluitend in
op `/vve-platform/mijn-vve/public`. Bewaar de meegeleverde `.htaccess`-bestanden.

Maak op de server `private/config.php` als kopie van
`private/config.example.php` en vul de eigen nieuwe databasegegevens in.
Controleer dat `private` en `install` buiten de openbare document-root staan.

## 4. Maak de beheerder en initialiseer de database

<!-- ***INSTRUCTIEVIDEO: link toevoegen zodra de video over de eerste database-installatie beschikbaar is.*** -->

Log via SSH in, ga naar de instantie en voer uit:

```sh
php install/create_admin.php
php install/install.php
php install/check.php
```

Het eerste script vraagt interactief om het nieuwe wachtwoord en slaat een hash
op. Het installatiescript weigert databases die al tabellen bevatten. Een
gedeeltelijke import wordt niet automatisch teruggedraaid; onderzoek de fout
en gebruik zo nodig een nieuwe lege database.

## 5. Test en richt optionele diensten in

Controleer HTTPS, aanmelden/afmelden, een fictief record, een fictieve upload,
download, zoeken en de instellingen. Controleer via een browser dat
`private/config.php` en inhoud uit `private/uploads` niet openbaar zijn.
Configureer e-mail, AI en automatische back-ups alleen als de VvE dit wil;
ze staan aanvankelijk uit. Gebruik uitsluitend fictieve informatie tijdens de
test.
