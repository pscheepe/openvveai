# Bewonersportaal

De release bevat deze bewonerspagina's:

- `bewonersportaal.php` (ook bereikbaar via `/bewoners`): mededelingen, vrijgegeven documenten, vraagbaak en meldingslink.
- `bewonersmelding.php`: openbaar meldingsformulier met CSRF-bescherming, invoercontrole en snelheidsbeperking.
- `bewonersportaal_api.php`: lokale zoekactie in de vrijgegeven documenten en actieve vraag-antwoorditems. De AI-provider wordt alleen gebruikt als een beheerder dit inschakelt.
- `resident_document.php`: serveert uitsluitend documenten die het bestuur expliciet heeft vrijgegeven.
- `resident_portal_admin.php`: beheerpagina voor admins.

## Inrichten per VvE

1. Plaats de release zoals beschreven in de installatiehandleiding. De bestaande vaste tenantconfiguratie en databaseverbinding blijven leidend.
2. Stel onder **Instellingen → E-mail** een actief account met SMTP-instellingen in. Meldingen worden naar het e-mailadres van dat account gestuurd.
3. Open **Bewonersportaal** in de adminbalk. Selecteer daar per document of het aan bewoners getoond mag worden. Vrijgave is standaard uit.
4. Voeg alleen gecontroleerde mededelingen en vraag-antwoorditems toe. AI-antwoorden zijn optioneel en gebruiken uitsluitend tekst uit expliciet vrijgegeven documenten.
5. Deel de URL `https://<tenant-host>/bewoners` met bewoners.

Er is geen schemawijziging nodig: documenten gebruiken de bestaande documentbibliotheek; beheerinhoud wordt tenantgebonden opgeslagen in de private uploads-map. De portalbron bevat geen voorgeselecteerde bewonersdocumenten, mededelingen, gebruikers, e-mailadressen of andere gegevens uit een andere VvE.

## Beveiligingsgrenzen

- Alle beheerwijzigingen vereisen de bestaande OpenVvE adminlogin en CSRF-token.
- Het openbare portaal publiceert geen databasecontacten. Alleen expliciet vrijgegeven documenten zijn bereikbaar.
- Documentbestanden blijven in de private tenantopslag en worden via `resident_document.php` gevalideerd en aangeboden.
- Meldingsteksten en bewonervragen worden niet opgeslagen in de database door deze module; meldingen worden per e-mail doorgestuurd. Met AI ingeschakeld worden de vraag en begrensde passages uit vrijgegeven bestanden aan de geconfigureerde AI-provider verstrekt.
- Stel een zichtbaar contactadres, privacy-informatie en meldings-/bewaarprocedure voor de eigen VvE in voordat bewoners de site gebruiken.
