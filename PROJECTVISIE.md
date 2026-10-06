# OpenVvE AI — projectvisie voor de openbare uitgave

**Status:** de repository is nog privé. De beoogde naam op de website is
**OpenVvE AI**; `OpenVvEAI` kan als compacte repository- en
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

## Publicatiestatus

Deze repository bevat de opgeschoonde broncode en niet de oorspronkelijke
Git-geschiedenis, databaseback-ups, exports, uploads, configuraties, wachtwoorden,
sessies of deploymentbestanden van de oude VvE-toepassing. De repository is op
dit moment nog privé.

De applicatiecode, eigen documentatie, logo's en instructievideo's zijn gekozen
onder AGPL-3.0-only; de PDF-parser behoudt LGPL-3.0. De maker heeft verklaard de
rechten op de eigen code en media te bezitten. De naam-/logocontroles en de
beveiligingsreview hebben beperkingen; zie
[`docs/PUBLICATION_READINESS.md`](docs/PUBLICATION_READINESS.md) en
[`docs/SECURITY_REVIEW_GITHUB.md`](docs/SECURITY_REVIEW_GITHUB.md).

De bestaande installatie-, beveiligings-, support- en licentiedocumenten horen
bij deze bron. De deploymentchecklist in [`RELEASING.md`](RELEASING.md) vraagt
om een providergerichte acceptatietest voordat de software als productiegeschikt
wordt beschreven. Die test en een onafhankelijke penetratietest zijn niet
uitgevoerd.

Een eventuele projectwebsite of demo moet alleen functies tonen die werkelijk
beschikbaar zijn. Demo's gebruiken fictieve gegevens en hebben eigen
beveiligings- en resetprocedures. Een status of domeinwijziging wordt niet door
deze projectvisie uitgevoerd.

Een tekst over ontbreken van garantie of aansprakelijkheid moet aansluiten
op de gekozen licentie en de toepasselijke wetgeving; deze projectvisie is
nog geen definitieve juridische disclaimer.
