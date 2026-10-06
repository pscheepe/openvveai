# Beveiligingsreview: tweestapsverificatie

**Datum:** 6 oktober 2026

**Reikwijdte:** optionele TOTP-MFA in `release/public`, aanmeldstroom, gebruikersinstellingen en beheerder-reset.

**Uitkomst:** de gecontroleerde MFA-wijzigingen zijn geschikt voor verdere acceptatie- en implementatietests. Deze review is een statische code-inspectie met gerichte helperchecks; dit is geen onafhankelijke penetratietest of volledige audit van OpenVvEai.

## In gewone taal

Een wachtwoord alleen is niet genoeg voor een account waarvoor MFA is ingesteld. De gebruiker moet daarnaast een tijdelijke code uit een authenticator-app invullen. Een gestolen wachtwoord geeft daardoor op zichzelf geen toegang. De demo blijft eenvoudig: de MFA-stand staat standaard op **uit**. Per installatie kan een beheerder MFA beschikbaar maken of verplicht stellen.

MFA-geheimen worden versleuteld opgeslagen in de privémap van de installatie. Herstelcodes worden alleen als hashes bewaard en zijn eenmalig. Verkeerde codes tellen mee voor dezelfde tijdelijke blokkering als verkeerde wachtwoorden. Een geldige TOTP-code kan niet nogmaals in hetzelfde tijdvak worden gebruikt.

## Bevindingen

| Onderdeel | Resultaat | Uitleg |
|---|---|---|
| Standaardinstelling | Akkoord | Ontbrekende configuratie betekent `uit`; bestaande demoaccounts krijgen geen nieuwe login-stap. |
| Beleid per installatie | Akkoord | Admin kan kiezen uit uit, optioneel of verplicht. Verplichte MFA stuurt niet-ingeschreven gebruikers naar de instelpagina voordat zij appfuncties gebruiken. |
| Aanmeldvolgorde | Akkoord | MFA wordt gevraagd voordat de sessie als ingelogd wordt aangemaakt. De MFA-stap verloopt na vijf minuten. |
| Opslag van geheimen | Akkoord | TOTP-seed wordt met AES-256-GCM versleuteld; encryptiesleutel staat afzonderlijk in de privémap met bestandsrechten 0600. |
| Herstelcodes | Akkoord | Tien willekeurige eenmalige codes; alleen wachtwoordhashes worden bewaard. De ruwe codes worden eenmaal getoond. |
| Pogingbeperking | Akkoord | Mislukte wachtwoord- en MFA-pogingen tellen samen mee voor de bestaande grens van vijf pogingen in vijftien minuten. |
| Hergebruik TOTP | Akkoord | Het laatst gebruikte tijdvak wordt bewaard en een code uit hetzelfde of eerder tijdvak wordt afgewezen. |
| MFA opnieuw instellen | Akkoord met begrenzing | Een admin kan MFA voor een andere gebruiker resetten; de actie wordt geprobeerd in het activiteitenlog vast te leggen. Een gebruiker kan de eigen MFA uitschakelen met wachtwoordbevestiging. |

## Uitgevoerde controles

- PHP-syntaxis gecontroleerd voor de gewijzigde authenticatie-, login-, beheer- en instelbestanden.
- TOTP-helper gecontroleerd met een bekende RFC 6238 SHA-1-testvector.
- Onjuiste en hergebruikte TOTP-codes afgewezen.
- Versleutelen en ontsleutelen van het MFA-geheim gecontroleerd.
- Aantal en uniciteit van gegenereerde herstelcodes gecontroleerd.

Alle bovenstaande controles slaagden.

## Grenzen en gebruik

- TOTP beschermt tegen gestolen wachtwoorden, maar is phishing-gevoelig: een aanvaller kan iemand in real time naar een code vragen. WebAuthn/passkeys zouden later sterkere phishingbescherming bieden.
- Verlies van zowel telefoon als herstelcodes vraagt om een geverifieerde beheerder-reset. Bij verlies van toegang tot het enige adminaccount is een herstelprocedure via de hostingbeheerder nodig.
- Neem `mfa.key` op in dezelfde beveiligde back-up als de private gebruikersbestanden. Zonder die sleutel werken authenticatorcodes niet meer; herstelcodes blijven bruikbaar om opnieuw toegang te krijgen.
- Encryptie beschermt de databasekopie van gebruikers tegen directe uitlezing zolang de aparte sleutel veilig blijft. Een aanvaller met volledige leesrechten op alle installatiebestanden kan ook die sleutel bemachtigen.
- De server moet HTTPS blijven afdwingen en de private map buiten de webroot houden; de bestaande tenantcode controleert die voorwaarden.
- Deze wijziging is nog niet op de live website of een productie-installatie uitgerold. Eerst is acceptatie op een aparte installatie nodig, inclusief enrollment, login, herstelcode, reset, uitschakelen en verplicht beleid.

## Bestanden

- `release/public/mfa.php`: TOTP, configuratie, encryptie en herstelcodes.
- `release/public/mfa_settings.php`: gebruikersinstellingen en installatiebeleid.
- `tests/test_mfa.php`: gerichte MFA-helperchecks.
