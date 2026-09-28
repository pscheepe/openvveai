# Nieuwe VvE voorbereiden en installeren

Deze handleiding beschrijft de huidige handmatige installatie. Er is nog geen
webinstaller of kant-en-klaar installatie-ZIP. Je hebt tijdelijk een technisch
beheerder nodig met toegang tot Git/Python op een computer en SFTP/SSH op de
hosting. Na oplevering beheert de VvE haar eigen account, sleutels en back-ups.
Lees vooraf ook de [disclaimer](DISCLAIMER.md).

## 1. Besluit en gegevens van de VvE

Laat het bestuur schriftelijk besluiten wie eigenaar en beheerder is van domein,
hosting, database, API-sleutels en back-ups. Wijs een vervanger aan en gebruik
geen privéaccount van een bestuurder als enige toegang. Leg vast wie toegang
heeft en hoe die bij bestuurswissel wordt overgedragen.

Kies een eigen domein of subdomein, bijvoorbeeld `beheer.vve-voorbeeld.nl`.
Gebruik voor elke VvE een afzonderlijke installatie, lege database, databasegebruiker,
privémap, beheerdersaccount en back-up. Deel geen database, `private`-map,
gebruikersbestand of sessies tussen VvE's.

## 2. Controleer de hosting vóór aanschaf

Vraag de hoster of controleer in het pakket dat het beschikt over:

- PHP 8.4 (de versie waarop deze broncode is getest) en de extensies PDO MySQL,
  mbstring, fileinfo, OpenSSL, ZIP, zlib en iconv;
- GD voor afbeeldingen; IMAP is alleen nodig voor e-mailsynchronisatie;
- MySQL of MariaDB, HTTPS, voldoende opslag en configureerbare uploadlimieten;
- SFTP om bestanden te plaatsen, PHP-CLI via SSH voor de installatie en de
  mogelijkheid om het subdomein rechtstreeks naar de map `public` te laten wijzen;
- privéopslag buiten de document-root en bruikbare bestandsrechten.

STRATO is één voorbeeld, geen vereiste. De pakketnamen en bediening kunnen
wijzigen. Controleer de eisen voor je bestelt; gebruik bij een andere provider
dezelfde technische checklist. Als SSH of de document-root ontbreekt, stop dan:
de huidige versie heeft geen veilige webinstaller om dat op te vangen.

### STRATO-schermbeelden bij deze stappen

De schermafbeeldingen hieronder staan in STRATO's eigen helpartikelen. Open ze
naast deze handleiding; de tekst hier vertelt welke instelling je kiest. De
klantlogin kan per pakket anders ogen.

- PHP-keuze, stap 4: [PHP-versie instellen — screenshots in de STRATO-handleiding](https://www.strato.nl/faq/hosting/hoe-wijzig-ik-de-php-instellingen-in-mijn-pakket/).
- Database aanmaken, stap 4: [Databasebeheer — screenshots bij “Database aanleggen en beheren”](https://www.strato.nl/faq/hosting/het-inrichten-van-databases/).
- SFTP/SSH aanmaken en verbinden, stap 4: [SFTP & SSH — screenshots bij “Toegang aanmaken” en “Verbinden met SFTP”](https://www.strato.nl/faq/hosting/zo-gebruik-je-ssh-sftp/).
- Subdomein naar `public` koppelen, stap 5: [Interne domeinomleiding naar een webspacemap](https://www.strato.nl/faq/domeinnaam/belangrijke-informatie-over-domeinomleiding/).
- HTTPS activeren, stap 4: [STRATO SSL aan een domein toewijzen](https://www.strato.nl/faq/domeinnaam/hoe-kan-ik-mijn-gratis-strato-ssl-certificaat-gebruiken/).

Dit zijn screenshots van STRATO zelf, niet van een specifiek VvE-account. Maak
geen schermafbeelding openbaar waarop klantnummer, domeinbeheer, database- of
SFTP-wachtwoorden, persoonlijke gegevens of sessiecodes zichtbaar zijn.

## 3. Maak lokaal een schone installatie

De technisch beheerder haalt de broncode van de repository die de VvE
vertrouwt en voert in de projectmap uit:

```sh
python3 tools/create_instance.py \
  --destination instances/mijn-vve \
  --name 'VvE Voorbeeld' \
  --host beheer.vve-voorbeeld.nl
```

Gebruik de eigen naam en exacte hostnaam van de VvE. Het script weigert een
bestaande doelmap te overschrijven. Het maakt een unieke installatie-ID en
`public`, `private` en `install` aan. Voeg `instances/` nooit aan Git toe:
die map staat daarom in `.gitignore`.

## 4. Richt STRATO in

Meld je aan bij de STRATO-klantenlogin en selecteer het hostingpakket van de
VvE. Doe geen betaalde upgrade of certificaatbestelling zonder besluit van het
bestuur. De menunamen kunnen enigszins afwijken:

1. **Kies PHP.** Open **Databases en webspace → PHP-versie instellen** en kies
   PHP 8.4. Sla op en wacht totdat de wijziging actief is.
2. **Maak een database.** Open **Databases en webspace → Databasebeheer →
   Database aanmaken**. Kies MySQL/MariaDB, stel een uniek sterk wachtwoord in
   en noteer de getoonde databasehost, databasenaam en gebruikersnaam in de
   beveiligde VvE-wachtwoordkluis. Maak een nieuwe, lege database voor deze
   installatie; importeer hier geen bestaande VvE-data.
3. **Maak toegang aan.** Open **Databases en webspace → SFTP & SSH → Nieuwe
   aanmaken**. Kies **SFTP + SSH**, een sterk uniek wachtwoord en een
   startmap voor uitsluitend dit project. Noteer servernaam, automatisch
   gegenereerde gebruikersnaam en poort 22 in de VvE-wachtwoordkluis.
4. **Activeer HTTPS.** Open **SSL beheren / STRATO SSL**, selecteer het eigen
   domein of subdomein en kies **Toewijzen**. Een gratis of al inbegrepen
   certificaat is alleen bruikbaar als STRATO het voor dit domein beschikbaar
   toont. Koop niets extra's zonder bestuursbesluit. Wacht tot de status actief is.

Voor een andere hoster maak je op dezelfde manier een database, SFTP/SSH-account,
PHP-runtime en TLS-certificaat aan. Gebruik de gegevens die die hoster opgeeft.

## 5. Upload en koppel het domein

Open FileZilla of een andere SFTP-app. Vul **Protocol: SFTP**, de STRATO-
servernaam, **poort 22**, gebruikersnaam en projectwachtwoord in. Klik op
**Verbinden** en bevestig bij de eerste verbinding de servervingerafdruk.
Toon verborgen bestanden zodat ook `.htaccess` wordt overgezet. Upload de
volledige inhoud van `instances/mijn-vve` naar een nieuwe map, bijvoorbeeld
`/vve-platform/mijn-vve`. Bewaar de mappenstructuur.

In de domeininstellingen maak je het eigen subdomein aan en kies je
**interne omleiding naar webspace/map**. Selecteer uitsluitend
`/vve-platform/mijn-vve/public` als doelmap. Laat `private` en `install` buiten
de openbare document-root. Bewaar alle `.htaccess`-bestanden. Zet HTTPS pas
aan nadat het certificaat actief is.

Controleer dat de hostnaam in `private/tenant.php` exact gelijk is aan het
subdomein. Controleer bij Strato ook het masteradres of andere domeinen die naar
dezelfde webruimte wijzen. Als het masteradres de app kan openen met een andere
hostnaam, laat de hoster de document-root of domeinkoppeling corrigeren.

## 6. Configureer en initialiseer

Maak op de server een kopie van `private/config.example.php` met de naam
`private/config.php`. Vul daarin de nieuwe databasehost, databasenaam,
databasegebruiker en het wachtwoord in. Stuur deze gegevens niet naar
OpenVvE, GitHub of een AI-chat. Beperk de bestandsrechten tot de servergebruiker
(0600 voor bestanden, 0700 voor mappen indien ondersteund); gebruik nooit 0777.

Log via SSH in en voer vanuit de installatiemap uit:

```sh
pwd
ls -la
php install/create_admin.php
php install/install.php
php install/check.php
```

Voer het eerste commando interactief uit: het vraagt tweemaal om een nieuw
sterk wachtwoord en slaat alleen de hash op. De installatie controleert
extensies en databaseleegte; ze weigert bestaande tabellen. Gebruik dus een
nieuwe lege database. Als de schema-import halverwege stopt, herstel dan niet
op goed geluk: maak een nieuwe lege database en onderzoek eerst de fout.

Controleer met `pwd` en `ls -la` dat je `install`, `private` en `public` ziet.
Zie je die mappen niet, ga dan naar de installatiemap met `cd` en het volledige
pad dat FileZilla bovenaan toont. Als het PHP-commando niet wordt gevonden,
vraag de hoster welk CLI-commando
voor PHP 8.4 gebruikt moet worden. Plak wachtwoorden nooit als argument van
een terminalcommando.

## 7. Controleer vóór echte gegevens

Test met een fictieve contactpersoon en een onschadelijk document:

1. aanmelden, afmelden en een nieuw wachtwoord instellen;
2. record aanmaken, wijzigen, zoeken en verwijderen;
3. document uploaden, downloaden/openen en indexeren;
4. zo nodig e-mail, AI, back-up en herstel afzonderlijk configureren en testen;
5. controleren dat `private/config.php`, `private/uploads`, `private/users.php`
   en `install/schema.json` via HTTPS niet opvraagbaar zijn, ook niet via het
   Strato-masteradres;
6. controleren dat een aparte testinstallatie met eigen database geen gegevens
   uit de eerste VvE kan zien.

Pas nadat tests en herstel goed werken, mogen echte VvE-gegevens worden
ingevoerd. Externe AI en mailboxkoppeling zijn optioneel. Schakel het delen van
gegevens pas bewust in en lees de privacywaarschuwingen in de app.

## 8. Beheer en herstel

Wijs een beheerder en vervanger aan. Werk updates eerst in een testinstallatie
met fictieve data bij. Maak vóór iedere update een herstelbare kopie van zowel
database als volledige `private`-map en bewaar minstens één kopie buiten het
hostingaccount. Test periodiek dat een herstel echt werkt. Een back-up op
dezelfde webruimte is geen bescherming tegen verlies van het hostingaccount.

Voor een nieuwe VvE maak je altijd opnieuw een installatie met het script uit
stap 3. Kopieer nooit `private/` van een andere VvE; die bevat installatie-ID,
gebruikers, sessies en mogelijk vertrouwelijke documenten of geheimen.

Deze route vraagt serverkennis en is geen garantie dat ieder hostingpakket
geschikt is. De software wordt zonder support geleverd; zie [SUPPORT.md](SUPPORT.md).
