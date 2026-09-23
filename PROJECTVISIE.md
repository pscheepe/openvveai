# OpenVvE AI — projectvisie voor de openbare uitgave

**Status:** privé-ontwikkeling, nog niet openbaar gepubliceerd. De beoogde naam op de
website is **OpenVvE AI**; `OpenVvEAI` kan als compacte repository- en
pakketnaam worden gebruikt. Het geregistreerde domein `openvveai.nl` is
bedoeld voor de toekomstige projectwebsite. Deze tekst wijzigt geen DNS,
hosting of bestaande demo-installatie.

## Doel

OpenVvE AI wordt gratis open-source software voor VvE-beheer. Een VvE kan
de software zelf installeren, gebruiken en aanpassen. Het project biedt
broncode, installatie-instructies en documentatie. Een fictieve demo kan
de werking tonen. Bijdragen en foutmeldingen kunnen na publicatie via de
publieke repository worden ingediend.

Het project is geen helpdesk, beheerde hostingdienst of dienst met een
beschikbaarheidsbelofte. Er is geen abonnement, SLA of storingsdienst
verbonden aan het gebruik van de software. De gebruiker beheert de eigen
installatie, toegangsrechten, beveiliging en back-ups. Eventueel incidenteel
advies staat los van het softwareproject en schept geen doorlopende
ondersteuningsverplichting.

## Concepttekst voor de website

> **OpenVvE AI**  
> Gratis open-source software voor VvE-beheer.  
> Installeer, beheer en pas het zelf aan.

> OpenVvE AI is communitysoftware. De software is beschikbaar zonder
> abonnement of verplichte ondersteuning. Gebruikers beheren hun eigen
> installatie. Bijdragen en verbeteringen zijn welkom via de publieke
> repository.

De publieke website mag alleen functies als beschikbaar presenteren nadat
ze in de openbare uitgave werken en zijn gedocumenteerd. Een Docker-installatie,
API-documentatie en een publieke demo zijn gewenste uitbreidingen, geen
bestaande publicatiebeloften.

## Voor publicatie afronden

- Maak een **nieuwe repository zonder de geschiedenis** van de oorspronkelijke
  VvE-toepassing. Neem uitsluitend de gecontroleerde schone broncode en
  gegevensvrije schema's over; geen databaseback-ups, exports, uploads,
  configuraties, wachtwoorden, sessies of interne deploymentbestanden.
- Controleer alle bestanden en de uiteindelijke repositorygeschiedenis op
  persoonsgegevens, geheimen, organisatiespecifieke gegevens en persoonlijke
  hostnamen. Controleer ook demo-gegevens en gegenereerde pakketten.
- Publiceer de gekozen AGPL-3.0-licentie voor de eigen code. Controleer vóór
  openbaarmaking de rechten op de code en de logo's, en behoud de afzonderlijke
  LGPL-3.0-licentie van de meegeleverde PDF-parser.
- Documenteer installatie, configuratie, updates en migraties, beveiliging,
  back-ups en herstel. Test een lege installatie en twee strikt gescheiden
  VvE-installaties met fictieve gegevens op de ondersteunde omgeving.
- Leg vast hoe kwetsbaarheden gemeld kunnen worden zonder gegevens van een
  VvE in openbare issues te plaatsen. Publiceer heldere grenzen aan
  ondersteuning en laat juridische tekst toetsen vóór publicatie.
- Koppel `openvveai.nl` pas aan de projectwebsite wanneer code, documentatie,
  licentie en publicatie-informatie klaar zijn. Een eventuele demo bevat
  uitsluitend fictieve gegevens en heeft een eigen beveiligings- en
  resetbeleid.

Een tekst over ontbreken van garantie of aansprakelijkheid moet aansluiten
op de gekozen licentie en de toepasselijke wetgeving; deze projectvisie is
nog geen definitieve juridische disclaimer.
