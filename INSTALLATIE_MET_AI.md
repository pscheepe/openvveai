# Route 3 — installatie met AI als stap-voor-stap gids

Deze route is bedoeld voor iemand die de handelingen zelf in STRATO uitvoert en
tijdens elke stap uitleg en vraag-en-antwoord wil. Een andere hostingprovider
is technisch mogelijk, maar de VvE-beheerder moet die zelfstandig inrichten en
controleren; de stappen en schermnamen hier zijn op STRATO gericht.
Voor doel, benodigdheden en betekenis van hosting, domein, database, e-mail en
API-account, plus de afbakening met de financiële administratie, begin bij
[het installatieoverzicht](ONBOARDING_NIEUWE_VVE.md).

## Belangrijke status

**Een AI-installatiewizard die zelf inlogt, bestanden aanmaakt of instellingen
wijzigt is niet aanwezig in versie 8.50-beta.** Deze gids beschrijft veilig
gebruik van een algemene AI-chat als uitleg naast de STRATO-webpagina. Jij
klikt zelf en controleert elke handeling. De daadwerkelijke installatie van
8.50-beta heeft voor de serverstappen nog een technisch beheerder nodig die
SSH gebruikt. De voorbeelden gaan uit van een nieuwe VvE, nieuw domein en lege
database. Geef een AI nooit toegang tot je hosting-, e-mail-, database- of
API-account.

## Zo gebruik je AI veilig als begeleider

<!-- ***INSTRUCTIEVIDEO: link toevoegen zodra de video over AI-begeleiding beschikbaar is.*** -->

Open [de STRATO-webhandleiding](INSTALLATIE_STRATO_WEB.md) in één tab en een
AI-chat in een andere. Vraag de AI telkens om **één volgende handeling**. Deel
alleen de schermtitel, menutekst en generieke foutmelding. Typ of upload geen
wachtwoorden, klantnummer, API-sleutel, databasehost/-naam, persoonlijke
gegevens, echte VvE-documenten of een screenshot met zulke waarden.

Kopieer deze startprompt in een AI-chat:

> Begeleid mij stap voor stap bij een OpenVvE AI 8.50-beta testinstallatie op
> STRATO. Ik voer zelf alle handelingen uit. Geef per antwoord maar één stap:
> noem het STRATO-menupad, wat ik op het scherm moet zien, wat ik moet
> invullen zonder geheimen te vragen, hoe ik controleer dat het gelukt is en
> wat ik moet doen als het scherm anders is. Stop bij database-import,
> installatiebestanden of SSH als de stap buiten de STRATO-webhandleiding
> valt. Vraag nooit om wachtwoorden, API-sleutels, klantnummers,
> persoonsgegevens of echte VvE-bestanden. Vertel duidelijk dat de huidige
> bèta voor schema-installatie en het eerste beheerdersaccount SSH nodig
> heeft. Gebruik fictieve testgegevens en wijzig geen bestaande bestanden of
> gegevens.

## Werkwijze per stap

1. **Noem je voortgang.** Bijvoorbeeld: “Ik heb een domein gekozen; ik ben nog
   niet ingelogd.” Vraag de AI alleen naar de eerstvolgende stap.
2. **Lees het antwoord en vergelijk het met STRATO’s actuele hulp.** Open de
   gelinkte screenshotbron in de [STRATO-handleiding](INSTALLATIE_STRATO_WEB.md)
   wanneer je het juiste menu niet vindt.
3. **Voer zelf één handeling uit.** Controleer de weergegeven status voordat je
   verdergaat; laat AI niet raden of een wijziging gelukt is.
4. **Bij een fout:** stuur alleen de fouttekst nadat je domeinen,
   gebruikersnamen, IP-adressen, paden en eventuele codes hebt verwijderd.
   Vraag om mogelijke oorzaken en hoe je veilig kunt controleren.
5. **Stop bij twijfel over kosten, domeinen, niet-lege mappen of databases.**
   Vraag eerst het VvE-bestuur of de hostingprovider.
6. **Stop als de begeleider SSH-commando’s nodig heeft.** Dat is een harde
   grens van de browserroute in bèta 8.50; laat het afronden door de beheerder
   uit [de technische handleiding](INSTALLATIE_TECHNISCH.md).
7. **Test met fictieve gegevens.** Gebruik geen echte bewonersgegevens om
   een onbekende stap of AI-antwoord uit te proberen.

## Voorbeelden van veilige vragen

- “Ik zie in de STRATO-login het menu ‘Databases en webspace’. Welke stap in
  de handleiding is dit?”
- “Mijn scherm zegt dat een certificaat nog wordt geactiveerd. Hoe controleer
  ik de status zonder iets nieuws te bestellen?”
- “In de SFTP-hulp staat een veld Startmap. Hoe weet ik of dit de map voor
  alleen deze testinstallatie is?”
- “Ik krijg een foutmelding met een bestandspad. Ik heb domein en gebruikersnaam
  verwijderd; leg uit wat ik veilig kan controleren.”

## Wat AI hier niet mag doen

AI mag niet inloggen, betaalde producten bestellen, een domein verhuizen,
bestaande bestanden/database overschrijven, instellingen op eigen gezag
wijzigen of beweren dat een stap is geslaagd zonder dat jij de status hebt
gezien. Gebruik nooit de chat als wachtwoordkluis. Een AI-antwoord is geen
bewijs dat de installatie veilig staat.

## Wat nodig is voor een echte AI-installatiewizard

Om deze route later volledig in de website zelf uit te voeren, is eerst een
eenmalige beveiligde webinstaller nodig die zonder SSH een lege installatie
kan voorbereiden, valideren en afsluiten. Daarbovenop kunnen een voortgangs-
checklist, STRATO-schermgids en een optionele AI-vraag-en-antwoordfunctie
worden gebouwd. Die wizard mag geen providerwachtwoorden of API-sleutels naar
een externe AI sturen en mag geen database- of bestandswijziging zonder
expliciete bevestiging uitvoeren. Dit is toekomstig ontwikkelwerk, geen
bestaande 8.50-beta-functie.
