# Route 2 — STRATO instellen via het webpaneel

Deze route beschrijft het handmatige werk in de STRATO-klantenlogin en het
plaatsen van bestanden met een SFTP-programma. De STRATO-help links hieronder
tonen screenshots van hun schermen. De vormgeving en menunamen kunnen wijzigen;
volg bij verschillen de actuele STRATO-pagina. Dit is geen kopie van een
specifiek klantaccount.

> **Status bèta 8.50:** je kunt hostinginstellingen en mappen via het web
> inrichten, zoals in de eerdere VvE-installatie. De huidige schone bèta heeft
> echter geen volledige browserinstaller: zij gebruikt nu een lokaal
> voorbereide instantie en PHP-opdrachten via SSH voor database-initialisatie
> en de eerste beheerder. Sla die laatste stappen niet over en probeer geen
> productiegegevens in een testdatabase te importeren.

## Eerst begrijpen wat je gaat inrichten

OpenVvE AI wordt uiteindelijk een website die VvE-gebruikers via internet
openen, bijvoorbeeld `demo.jouwvve.nl`. STRATO levert hiervoor webhosting:
serverruimte waarop de PHP-website, de databaseverbinding en de afgeschermde
bestanden draaien. Het domein is het adres. De MySQL/MariaDB-database bewaart
contacten, activa en andere records. De map `private` bewaart bestanden en
configuratie buiten het publieke deel van de website. HTTPS beschermt de
verbinding. Een e-mailadres is nodig voor VvE-beheer; mailboxkoppeling en AI
zijn optioneel. Als AI wordt aangezet, is daarnaast een eigen account/project
bij een AI-provider en een API-sleutel nodig.

## Benodigd vóór je begint

- STRATO-hostingpakket met PHP 8.4 en de vereiste extensies uit het
  [installatieoverzicht](ONBOARDING_NIEUWE_VVE.md#technische-eisen-voor-versie-850-beta).
- Een domein of subdomein dat bij de VvE hoort, toegang tot de STRATO-klantenlogin
  en toestemming om het domein en pakket namens de VvE te beheren.
- Een lege, nieuwe MySQL/MariaDB-database; maak geen verbinding met de
  bestaande productie-VvE-database.
- Een VvE-e-mailadres, SFTP-programma zoals FileZilla, de bestanden van een
  schone OpenVvE-instantie en een veilige wachtwoordkluis.
- Voor het afmaken van de huidige bèta: een technisch beheerder met SSH.

Maak vóór wijzigingen een herstelbare back-up van wat al bestaat. Bestel geen
hosting, SSL of andere betaalde dienst zonder besluit van het VvE-bestuur.

## Stap 1 — open het juiste hostingpakket

1. Meld je aan bij de STRATO-klantenlogin.
2. Kies het hostingpakket dat voor deze VvE is bedoeld.
3. Controleer de pakketnaam en het domein bovenin het scherm. Gebruik geen
   account of pakket van een andere VvE.

STRATO legt de navigatie van de klantenlogin uit in
[deze schermafbeeldingrijke startgids](https://www.strato.nl/faq/hosting/eerste-stappen-in-de-klantenlogin/).

## Stap 2 — controleer PHP

1. Open **Databases en webspace → PHP-versie instellen**.
2. Kies PHP 8.4 als deze versie in het pakket beschikbaar is.
3. Sla op en wacht tot STRATO de wijziging heeft toegepast.
4. Als PHP 8.4 of de vereiste extensies ontbreken, pauzeer dan en vraag STRATO
   of kies een geschikt pakket/provider.

Bekijk STRATO’s actuele schermbeelden en uitleg bij
[PHP-instellingen wijzigen](https://www.strato.nl/faq/hosting/hoe-wijzig-ik-de-php-instellingen-in-mijn-pakket/).

## Stap 3 — maak een database voor deze VvE

1. Open **Databases en webspace → Databasebeheer**.
2. Klik **Database aanmaken** en kies MySQL of MariaDB, als de keuze wordt
   aangeboden.
3. Stel een nieuw sterk wachtwoord in.
4. Noteer de getoonde databasenaam, databasegebruiker en databasehost in de
   VvE-wachtwoordkluis.
5. Laat de database leeg. Importeer geen SQL uit een andere VvE.

STRATO toont schermafbeeldingen van dit scherm onder
[database maken en beheren](https://www.strato.nl/faq/hosting/het-inrichten-van-databases/).

## Stap 4 — maak minstens één VvE-e-mailadres

1. Open in de klantenlogin het onderdeel **E-mail** of **E-mailbeheer**.
2. Maak bijvoorbeeld `bestuur@jouwvve.nl` aan.
3. Bewaar het wachtwoord in de VvE-wachtwoordkluis en controleer dat je kunt
   aanmelden bij de mailbox.
4. E-mail synchroniseren met OpenVvE is optioneel; mailbox-aanmeldgegevens
   hoef je niet tijdens de basisinstallatie in te vullen.

De exacte mailstappen verschillen per STRATO-pakket. Gebruik de actuele
E-mailbeheer-hulp in je klantenlogin en controleer dat het adres van de VvE is.

## Stap 5 — wijs het domein toe en activeer HTTPS

1. Open **Domeinen** of **Domeinbeheer** en kies het VvE-domein/subdomein.
2. Stel de interne bestemming in op de webmap die voor deze instantie is
   afgesproken. In stap 7 moet deze map uiteindelijk precies naar de installatie
   `public`-map wijzen.
3. Open **SSL beheren** en wijs een actief certificaat aan het gekozen domein
   toe. Wacht tot de status actief is en test `https://` in een browser.
4. Gebruik geen SSL-bestelling die extra kosten veroorzaakt zonder voorafgaand
   bestuursbesluit.

STRATO legt de domeindoelmap uit in
[domeinomleiding](https://www.strato.nl/faq/domeinnaam/belangrijke-informatie-over-domeinomleiding/)
en certificaattoewijzing in
[SSL gebruiken](https://www.strato.nl/faq/domeinnaam/hoe-kan-ik-mijn-gratis-strato-ssl-certificaat-gebruiken/).

## Stap 6 — maak de webmappen

Maak via STRATO Webspace/File Manager als die in jouw pakket beschikbaar is,
of via FileZilla met SFTP, deze structuur:

```text
vve-platform/
└── jouw-vve/
    ├── public/     ← enige map die het domein publiek aanbiedt
    ├── private/    ← buiten de document-root; nooit via het web bereikbaar
    └── install/    ← buiten de document-root; alleen voor installatie
```

De exacte namen zijn vrij, maar de relatie is verplicht: `public` is de
document-root; `private` en `install` zijn dat niet. STRATO legt SFTP-toegang,
startmappen en het tonen van verborgen `.htaccess`-bestanden uit in
[Zo gebruik je SSH/SFTP](https://www.strato.nl/faq/hosting/zo-gebruik-je-ssh-sftp/).

## Stap 7 — plaats de bestanden

In versie 8.50-beta wordt een lege instantie eerst met het projectscript
voorbereid. Een beheerder met de technische route kan je de gegevensvrije
instantiebestanden geven. Upload vervolgens de inhoud:

- de inhoud van instantie-map `public` naar de servermap `public`;
- de inhoud van instantie-map `private` naar de servermap `private`;
- de inhoud van instantie-map `install` naar de servermap `install`;
- de meegeleverde `.htaccess`-bestanden op hun bijbehorende plek.

Upload geen `private`- of `install`-bestanden ín de openbare `public`-map.
Controleer in Domeinbeheer dat het domein rechtstreeks op
`.../jouw-vve/public` uitkomt. In STRATO-documentatie staan voorbeelden voor
SFTP via FileZilla; gebruik **SFTP** en de server/poort/gebruikersnaam uit je
eigen pakket.

## Stap 8 — rond de huidige bèta-installatie af

De actuele release maakt dit deel niet via phpMyAdmin of een webformulier af.
Een technisch beheerder moet via SSH:

1. `private/config.example.php` kopiëren naar `private/config.php` en de nieuwe
   databasegegevens invullen;
2. vanuit de instantieroot `php install/create_admin.php` uitvoeren en een
   sterk uniek wachtwoord instellen;
3. `php install/install.php` uitvoeren om de schema-structuur in de lege
   database te maken;
4. `php install/check.php` uitvoeren om de installatie te controleren.

Als je dit uitsluitend via browser/STRATO-bestandsbeheer wilt doen, stop dan
vóór je echte gegevens invoert: die volledige browserroute wordt in 8.50-beta
niet ondersteund. Een veilige eenmalige webinstaller moet eerst worden gebouwd
en getest; `INSTALLATIE_MET_AI.md` beschrijft wat AI daarbij wel en niet kan.

## Stap 9 — test met fictieve gegevens

Open de site met HTTPS. Test aanmelden/afmelden, een fictief record, een
onschadelijk document uploaden/downloaden, zoeken en uitloggen. Controleer ook
dat `private/config.php` en bestanden uit `private/uploads` geen webpagina
opleveren. Vul pas daarna echte gegevens in, na bestuursbesluit en controle
van back-ups en toegang.

### Screenshotbronnen

Deze STRATO-artikelen bevatten eigen screenshots van de schermen die in de
stappen hierboven genoemd worden:

- [klantenlogin en pakket kiezen](https://www.strato.nl/faq/hosting/eerste-stappen-in-de-klantenlogin/)
- [PHP-versie instellen](https://www.strato.nl/faq/hosting/hoe-wijzig-ik-de-php-instellingen-in-mijn-pakket/)
- [database aanmaken en phpMyAdmin openen](https://www.strato.nl/faq/hosting/het-inrichten-van-databases/)
- [SFTP en FileZilla gebruiken](https://www.strato.nl/faq/hosting/zo-gebruik-je-ssh-sftp/)
- [domein naar een webmap verwijzen](https://www.strato.nl/faq/domeinnaam/belangrijke-informatie-over-domeinomleiding/)
- [SSL-certificaat toewijzen](https://www.strato.nl/faq/domeinnaam/hoe-kan-ik-mijn-gratis-strato-ssl-certificaat-gebruiken/)

De afbeeldingen tonen STRATO-voorbeelden; je eigen paneel kan verschillen. Deel
geen screenshots waarop klantnummer, wachtwoorden, API-sleutels, persoonlijke
gegevens of actieve sessies zichtbaar zijn.
