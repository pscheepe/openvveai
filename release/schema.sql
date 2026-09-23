-- Empty schema; no VvE rows. Use install.php in a NEW empty database.
SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `ActieRegister` (
  `ActieID` int(10) UNSIGNED NOT NULL,
  `VergaderingID` int(10) UNSIGNED NOT NULL,
  `Datum` date DEFAULT NULL,
  `Actie` varchar(255) DEFAULT NULL,
  `Verantwoordelijk` varchar(255) DEFAULT NULL,
  `Omschrijving` longtext DEFAULT NULL,
  `DatumCompleet` date DEFAULT NULL,
  `Compleet` varchar(50) DEFAULT NULL,
  `Update` datetime DEFAULT NULL,
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Activa` (
  `ActivaID` int(10) UNSIGNED NOT NULL,
  `Afbeelding` varchar(500) DEFAULT NULL,
  `Naam` varchar(255) DEFAULT NULL,
  `Functie` varchar(255) DEFAULT NULL,
  `VerantwoordelijkTCLid` varchar(255) DEFAULT NULL,
  `Omschrijving` longtext DEFAULT NULL,
  `Aanschafdatum` datetime DEFAULT NULL,
  `Datum ingebruikname` datetime DEFAULT NULL,
  `Aanschafwaarde` decimal(14,2) DEFAULT NULL,
  `Afschrijftermijn Jaren` decimal(10,2) DEFAULT NULL,
  `Afgeschreven Jaren` decimal(10,2) DEFAULT NULL,
  `Af te schrijven` decimal(14,2) DEFAULT NULL,
  `Huidige boekwaarde` decimal(14,2) DEFAULT NULL,
  `Kosten` decimal(14,2) DEFAULT NULL,
  `Update` datetime DEFAULT NULL,
  `Actief` varchar(50) DEFAULT NULL,
  `ActivaCatID` int(10) UNSIGNED DEFAULT NULL,
  `ParentActivaID` int(10) UNSIGNED DEFAULT NULL,
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(128) DEFAULT NULL,
  `Fabrikant` varchar(255) DEFAULT NULL,
  `Leverancier` varchar(255) DEFAULT NULL,
  `Merk` varchar(255) DEFAULT NULL,
  `Type` varchar(255) DEFAULT NULL,
  `Serienummer` varchar(255) DEFAULT NULL,
  `Grootte` varchar(255) DEFAULT NULL,
  `Vermogen` varchar(255) DEFAULT NULL,
  `Aansluitwaarde` varchar(255) DEFAULT NULL,
  `Spanning` varchar(255) DEFAULT NULL,
  `Stroomsterkte` varchar(255) DEFAULT NULL,
  `Levensduur` varchar(255) DEFAULT NULL,
  `Locatie` varchar(255) DEFAULT NULL,
  `Toestand` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ActivaAfbeeldingen` (
  `AfbeeldingID` int(10) UNSIGNED NOT NULL,
  `ActivaID` int(10) UNSIGNED NOT NULL,
  `Bestandsnaam` varchar(255) NOT NULL,
  `Bestandspad` varchar(500) NOT NULL,
  `Mimetype` varchar(100) DEFAULT NULL,
  `Bestandsgrootte` int(10) UNSIGNED DEFAULT NULL,
  `Titel` varchar(255) DEFAULT NULL,
  `Volgorde` int(11) NOT NULL DEFAULT 0,
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ActivaBronKoppelingen` (
  `KoppelingID` bigint(20) UNSIGNED NOT NULL,
  `ActivaID` int(10) UNSIGNED NOT NULL,
  `BronType` varchar(20) NOT NULL,
  `BronID` bigint(20) UNSIGNED NOT NULL,
  `Titel` varchar(255) DEFAULT NULL,
  `AangemaaktOp` datetime NOT NULL,
  `AangemaaktDoor` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ActivaCategorie` (
  `ActivaCatID` int(10) UNSIGNED NOT NULL,
  `ActivaCategorie` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ActivaDocumenten` (
  `DocumentID` int(10) UNSIGNED NOT NULL,
  `ActivaID` int(10) UNSIGNED NOT NULL,
  `Type` varchar(20) NOT NULL DEFAULT 'bestand',
  `Bestandsnaam` varchar(255) DEFAULT NULL,
  `Bestandspad` varchar(500) DEFAULT NULL,
  `Url` varchar(2000) DEFAULT NULL,
  `Mimetype` varchar(150) DEFAULT NULL,
  `Bestandsgrootte` int(10) UNSIGNED DEFAULT NULL,
  `Titel` varchar(255) DEFAULT NULL,
  `Volgorde` int(11) NOT NULL DEFAULT 0,
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ActiviteitenLog` (
  `LogID` bigint(20) UNSIGNED NOT NULL,
  `DatumTijd` datetime NOT NULL,
  `Gebruikersnaam` varchar(128) NOT NULL,
  `Weergavenaam` varchar(150) NOT NULL,
  `Rol` varchar(32) NOT NULL,
  `Actie` varchar(80) NOT NULL,
  `Onderdeel` varchar(80) NOT NULL,
  `ObjectType` varchar(80) DEFAULT NULL,
  `ObjectID` varchar(128) DEFAULT NULL,
  `Omschrijving` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `addresses` (
  `address_id` bigint(20) NOT NULL,
  `contact_id` bigint(20) DEFAULT NULL,
  `relatie_id` bigint(20) DEFAULT NULL,
  `adres_soort` varchar(128) DEFAULT NULL,
  `naam_sleutelhouder` varchar(255) DEFAULT NULL,
  `straat` varchar(255) DEFAULT NULL,
  `huisnummer` varchar(32) DEFAULT NULL,
  `postcode` varchar(32) DEFAULT NULL,
  `woonplaats` varchar(128) DEFAULT NULL,
  `mobiel` varchar(64) DEFAULT NULL,
  `telefoon_huis` varchar(64) DEFAULT NULL,
  `email_prive` varchar(255) DEFAULT NULL,
  `email_werk` varchar(255) DEFAULT NULL,
  `land` varchar(128) DEFAULT NULL,
  `actief` varchar(32) DEFAULT NULL,
  `gewijzigd` varchar(64) DEFAULT NULL,
  `gewijzigd_door` varchar(128) DEFAULT NULL,
  `bewoner_contact_id` bigint(20) DEFAULT NULL,
  `legacy_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`legacy_json`)),
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contacts` (
  `contact_id` bigint(20) NOT NULL,
  `huisnummer` varchar(32) DEFAULT NULL,
  `hal` varchar(32) DEFAULT NULL,
  `aanhef` varchar(64) DEFAULT NULL,
  `achternaam` varchar(255) DEFAULT NULL,
  `tussenvoegsel` varchar(128) DEFAULT NULL,
  `voorletters` varchar(64) DEFAULT NULL,
  `voornaam` varchar(255) DEFAULT NULL,
  `status` varchar(64) DEFAULT NULL,
  `actief` varchar(32) DEFAULT NULL,
  `bewoner` varchar(32) DEFAULT NULL,
  `eigenaar` varchar(32) DEFAULT NULL,
  `huurder` varchar(32) DEFAULT NULL,
  `verhuurder` varchar(32) DEFAULT NULL,
  `vve_lid` varchar(32) DEFAULT NULL,
  `bestuurslid` varchar(32) DEFAULT NULL,
  `telefoon` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `opmerkingen` longtext DEFAULT NULL,
  `updated_at_source` varchar(64) DEFAULT NULL,
  `legacy_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`legacy_json`)),
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(128) DEFAULT NULL,
  `Etage` varchar(20) DEFAULT NULL,
  `Bergingsgang` varchar(20) DEFAULT NULL,
  `Appartementsgrootte` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contact_relations` (
  `relation_contact_id` bigint(20) NOT NULL,
  `contact_id` bigint(20) DEFAULT NULL,
  `relatie_id` bigint(20) DEFAULT NULL,
  `contact_label` varchar(255) DEFAULT NULL,
  `updated_at_source` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `DefinitieLineItems` (
  `DefinitieLineItemID` int(10) UNSIGNED NOT NULL,
  `Titel` varchar(255) DEFAULT NULL,
  `Activum-id voor compliance` varchar(255) DEFAULT NULL,
  `ProjektID` int(10) UNSIGNED DEFAULT NULL,
  `LineItemNaam` varchar(500) DEFAULT NULL,
  `Compleet` varchar(50) DEFAULT NULL,
  `Update` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `DocumentBibliotheek` (
  `DocumentID` bigint(20) UNSIGNED NOT NULL,
  `VersieGroepID` bigint(20) UNSIGNED DEFAULT NULL,
  `Versienummer` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `IsHuidig` tinyint(1) NOT NULL DEFAULT 1,
  `MapID` bigint(20) UNSIGNED NOT NULL,
  `Titel` varchar(255) NOT NULL,
  `OrigineleNaam` varchar(255) NOT NULL,
  `Bestandspad` varchar(500) NOT NULL,
  `Mimetype` varchar(150) NOT NULL,
  `Bestandsgrootte` bigint(20) UNSIGNED NOT NULL,
  `Sha256` char(64) NOT NULL,
  `Tags` varchar(1000) DEFAULT NULL,
  `Documentdatum` date DEFAULT NULL,
  `Bron` varchar(50) NOT NULL DEFAULT 'handmatig',
  `Bronpad` varchar(1000) DEFAULT NULL,
  `ExterneUrl` varchar(2048) DEFAULT NULL,
  `Omschrijving` text DEFAULT NULL,
  `VerwijderdOp` datetime DEFAULT NULL,
  `VerwijderdDoor` varchar(128) DEFAULT NULL,
  `AangemaaktOp` datetime NOT NULL,
  `AangemaaktDoor` varchar(128) NOT NULL,
  `GewijzigdOp` datetime NOT NULL,
  `GewijzigdDoor` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `DocumentMappen` (
  `MapID` bigint(20) UNSIGNED NOT NULL,
  `ParentMapID` bigint(20) UNSIGNED DEFAULT NULL,
  `Naam` varchar(150) NOT NULL,
  `Pad` varchar(700) NOT NULL,
  `Sorteervolgorde` int(11) NOT NULL DEFAULT 0,
  `Actief` tinyint(1) NOT NULL DEFAULT 1,
  `AangemaaktOp` datetime NOT NULL,
  `AangemaaktDoor` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `DocumentTekstIndex` (
  `DocumentID` bigint(20) UNSIGNED NOT NULL,
  `Status` enum('gereed','niet-ondersteund','mislukt','ocr-nodig','overgeslagen') NOT NULL,
  `ExtractorVersie` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `Tekst` longtext DEFAULT NULL,
  `Foutmelding` varchar(500) DEFAULT NULL,
  `BijgewerktOp` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `DocumentTekstPagina` (
  `DocumentID` bigint(20) UNSIGNED NOT NULL,
  `Paginanummer` int(10) UNSIGNED NOT NULL,
  `Tekst` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `EmailAccounts` (
  `EmailAccountID` bigint(20) UNSIGNED NOT NULL,
  `Label` varchar(100) NOT NULL,
  `EmailAddress` varchar(254) NOT NULL,
  `SenderName` varchar(150) NOT NULL,
  `Username` varchar(254) NOT NULL,
  `ImapHost` varchar(253) NOT NULL DEFAULT 'imap.strato.de',
  `ImapPort` smallint(5) UNSIGNED NOT NULL DEFAULT 993,
  `ImapSecurity` enum('ssl','starttls') NOT NULL DEFAULT 'ssl',
  `SmtpHost` varchar(253) NOT NULL DEFAULT 'smtp.strato.de',
  `SmtpPort` smallint(5) UNSIGNED NOT NULL DEFAULT 465,
  `SmtpSecurity` enum('ssl','starttls') NOT NULL DEFAULT 'ssl',
  `IsDefault` tinyint(1) NOT NULL DEFAULT 0,
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `ModifiedAt` datetime NOT NULL,
  `ModifiedBy` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `EmailBerichten` (
  `EmailBerichtID` bigint(20) UNSIGNED NOT NULL,
  `EmailAccountID` bigint(20) UNSIGNED NOT NULL,
  `FolderName` varchar(500) NOT NULL,
  `FolderHash` char(64) NOT NULL,
  `UidValidity` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `MessageUid` bigint(20) UNSIGNED NOT NULL,
  `MessageId` varchar(500) DEFAULT NULL,
  `Subject` varchar(998) NOT NULL,
  `Sender` varchar(998) NOT NULL,
  `Recipients` text DEFAULT NULL,
  `SentAt` datetime DEFAULT NULL,
  `BodyText` longtext DEFAULT NULL,
  `AttachmentNames` text DEFAULT NULL,
  `IndexedAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ImplementerenLineItems` (
  `ImplementatieLineItemID` int(10) UNSIGNED NOT NULL,
  `ProjektID` int(10) UNSIGNED DEFAULT NULL,
  `LineItemNaam` varchar(500) DEFAULT NULL,
  `Compleet` varchar(50) DEFAULT NULL,
  `Update` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `legacy_records` (
  `id` bigint(20) NOT NULL,
  `source_table` varchar(128) NOT NULL,
  `source_row` int(11) NOT NULL,
  `source_key` varchar(255) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `MJOPHoofdgroepen` (
  `Code` varchar(10) NOT NULL,
  `Naam` varchar(255) NOT NULL,
  `Pagina` int(10) UNSIGNED NOT NULL,
  `DocumentID` int(10) UNSIGNED DEFAULT NULL,
  `vvegui_modified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Offertes` (
  `OfferteID` int(10) UNSIGNED NOT NULL,
  `ProjektID` int(10) UNSIGNED DEFAULT NULL,
  `OnderhoudsPlanID` int(10) UNSIGNED DEFAULT NULL,
  `OfferteNummer` varchar(255) DEFAULT NULL,
  `OfferteDatum` datetime DEFAULT NULL,
  `OfferteOmschrijving` longtext DEFAULT NULL,
  `OfferteBedrag` decimal(14,2) DEFAULT NULL,
  `Opdracht` varchar(50) DEFAULT NULL,
  `OpdrachtDatum` datetime DEFAULT NULL,
  `OpdrachtBedrag` decimal(14,2) DEFAULT NULL,
  `OpdrachtUitgevoerd` varchar(50) DEFAULT NULL,
  `WerkelijkeKosten` decimal(14,2) DEFAULT NULL,
  `Update` datetime DEFAULT NULL,
  `SharePointLink` text DEFAULT NULL,
  `Relatie` varchar(255) DEFAULT NULL,
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `OnderhoudsCategorie` (
  `OnderhoudsCategorieID` int(10) UNSIGNED NOT NULL,
  `OnderhoudsCategorie` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `OnderhoudsplanActies` (
  `ActieID` int(10) UNSIGNED NOT NULL,
  `OnderhoudsplanID` int(10) UNSIGNED NOT NULL,
  `Actie` text NOT NULL,
  `Verantwoordelijk` varchar(255) DEFAULT NULL,
  `DatumCompleet` date DEFAULT NULL,
  `Compleet` tinyint(1) NOT NULL DEFAULT 0,
  `vvegui_modified_at` datetime NOT NULL,
  `vvegui_modified_by` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `OnderhoudsPlanDocumenten` (
  `DocumentID` int(10) UNSIGNED NOT NULL,
  `OnderhoudsplanID` int(11) DEFAULT NULL,
  `DocumentType` varchar(20) NOT NULL,
  `Bestandsnaam` varchar(255) DEFAULT NULL,
  `Bestandspad` varchar(500) DEFAULT NULL,
  `Mimetype` varchar(150) DEFAULT NULL,
  `Bestandsgrootte` bigint(20) DEFAULT NULL,
  `Url` varchar(1000) DEFAULT NULL,
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `OnderhoudsplanDocumenten` (
  `DocumentID` bigint(20) UNSIGNED NOT NULL,
  `OnderhoudsplanID` bigint(20) UNSIGNED NOT NULL,
  `Type` varchar(20) NOT NULL,
  `Naam` varchar(255) NOT NULL,
  `URL` varchar(1000) DEFAULT NULL,
  `Bestandspad` varchar(500) DEFAULT NULL,
  `Mimetype` varchar(150) DEFAULT NULL,
  `Bestandsgrootte` bigint(20) UNSIGNED DEFAULT NULL,
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(128) DEFAULT NULL,
  `Bestandsnaam` varchar(255) DEFAULT NULL,
  `Titel` varchar(255) DEFAULT NULL,
  `Volgorde` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `OnderhoudsplanFotos` (
  `FotoID` int(10) UNSIGNED NOT NULL,
  `OnderhoudsplanID` int(10) UNSIGNED NOT NULL,
  `Bestandsnaam` varchar(255) NOT NULL,
  `Bestandspad` varchar(500) NOT NULL,
  `Mimetype` varchar(100) NOT NULL,
  `Bestandsgrootte` int(10) UNSIGNED NOT NULL,
  `vvegui_modified_at` datetime NOT NULL,
  `vvegui_modified_by` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Onderhoudsplannen` (
  `OnderhoudsplanID` int(10) UNSIGNED NOT NULL,
  `ActivaID` int(10) UNSIGNED DEFAULT NULL,
  `Planjaar` int(11) DEFAULT NULL,
  `UitvoeringsJaar` int(11) DEFAULT NULL,
  `DatumInspectie` datetime DEFAULT NULL,
  `Status` varchar(100) DEFAULT NULL,
  `Indexering` decimal(12,4) DEFAULT NULL,
  `Werkomschrijving` longtext DEFAULT NULL,
  `PlanBedrag` decimal(14,2) DEFAULT NULL,
  `Actief` varchar(50) DEFAULT NULL,
  `Update` datetime DEFAULT NULL,
  `SharePointLink` text DEFAULT NULL,
  `OnderhoudsCategorie` int(10) UNSIGNED DEFAULT NULL,
  `StabuCode` varchar(100) DEFAULT NULL,
  `Documenten` text DEFAULT NULL,
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(128) DEFAULT NULL,
  `MJOPReferentie` varchar(255) DEFAULT NULL,
  `RelatieID` int(11) DEFAULT NULL,
  `Naam` varchar(255) DEFAULT NULL,
  `UitvoerderRelatieID` bigint(20) DEFAULT NULL,
  `OnderhoudsCategorieNaam` varchar(100) DEFAULT NULL,
  `Uitvoerder` int(10) UNSIGNED DEFAULT NULL,
  `Opmerkingen` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `OnderhoudsplanUitvoerders` (
  `OnderhoudsplanID` int(10) UNSIGNED NOT NULL,
  `RelatieID` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `OntwikkelingLineItems` (
  `OntwikkelingLineItemID` int(10) UNSIGNED NOT NULL,
  `ProjektID` int(10) UNSIGNED DEFAULT NULL,
  `LineItemNaam` varchar(500) DEFAULT NULL,
  `Compleet` varchar(50) DEFAULT NULL,
  `Update` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ProjectActies` (
  `ActieID` int(10) UNSIGNED NOT NULL,
  `ProjectID` int(10) UNSIGNED NOT NULL,
  `Actie` text NOT NULL,
  `Verantwoordelijk` varchar(255) DEFAULT NULL,
  `DatumCompleet` date DEFAULT NULL,
  `Compleet` tinyint(1) NOT NULL DEFAULT 0,
  `vvegui_modified_at` datetime NOT NULL,
  `vvegui_modified_by` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ProjectActiva` (
  `ProjectID` int(10) UNSIGNED NOT NULL,
  `ActivaID` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ProjectChecklist` (
  `ChecklistID` int(10) UNSIGNED NOT NULL,
  `ProjectID` int(10) UNSIGNED NOT NULL,
  `Fase` varchar(32) NOT NULL,
  `Criterium` varchar(500) NOT NULL,
  `Datum` date DEFAULT NULL,
  `Compleet` tinyint(1) NOT NULL DEFAULT 0,
  `vvegui_modified_at` datetime NOT NULL,
  `vvegui_modified_by` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ProjectDocumentDatum` (
  `DocumentID` bigint(20) UNSIGNED NOT NULL,
  `Inhoudsdatum` date NOT NULL,
  `BevestigdOp` datetime NOT NULL,
  `BevestigdDoor` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ProjectDocumenten` (
  `ProjectID` int(10) UNSIGNED NOT NULL,
  `DocumentID` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ProjectEmails` (
  `ProjectID` int(10) UNSIGNED NOT NULL,
  `EmailBerichtID` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Projecten` (
  `ProjektID` int(10) UNSIGNED NOT NULL,
  `ProjektNaam` varchar(255) DEFAULT NULL,
  `Actief` varchar(50) DEFAULT NULL,
  `Fase` varchar(100) DEFAULT NULL,
  `ProjektCategorie` int(10) UNSIGNED DEFAULT NULL,
  `Stakeholders` longtext DEFAULT NULL,
  `Prioriteit` varchar(100) DEFAULT NULL,
  `Status` varchar(100) DEFAULT NULL,
  `ProcentCompleet` decimal(5,2) DEFAULT NULL,
  `PlanDatumCompleet` date DEFAULT NULL,
  `DatumCompleet` date DEFAULT NULL,
  `StrategiePlanTijd` varchar(100) DEFAULT NULL,
  `StrategiePlanDatum` date DEFAULT NULL,
  `StrategieGoNoGo` varchar(50) DEFAULT NULL,
  `DefinitiePlanTijd` varchar(100) DEFAULT NULL,
  `DefinitiePlanDatum` date DEFAULT NULL,
  `DefinitieGoNoGo` varchar(50) DEFAULT NULL,
  `OntwikkelingPlanTijd` varchar(100) DEFAULT NULL,
  `OntwikkelingPlanDatum` date DEFAULT NULL,
  `OntwikkelingGoNoGo` varchar(50) DEFAULT NULL,
  `TestPlanTijd` varchar(100) DEFAULT NULL,
  `TestPlanDatum` date DEFAULT NULL,
  `TestGoNoGo` varchar(50) DEFAULT NULL,
  `ImplementatiePlanTijd` varchar(100) DEFAULT NULL,
  `ImplementatiePlanDatum` date DEFAULT NULL,
  `ImplementatieGoNoGo` varchar(50) DEFAULT NULL,
  `Update` datetime DEFAULT NULL,
  `SharePointLink` text DEFAULT NULL,
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ProjectFotos` (
  `FotoID` int(10) UNSIGNED NOT NULL,
  `ProjectID` int(10) UNSIGNED NOT NULL,
  `Bestandsnaam` varchar(255) NOT NULL,
  `Bestandspad` varchar(500) NOT NULL,
  `Mimetype` varchar(100) NOT NULL,
  `Bestandsgrootte` int(10) UNSIGNED NOT NULL,
  `vvegui_modified_at` datetime NOT NULL,
  `vvegui_modified_by` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ProjectRelaties` (
  `ProjectID` int(10) UNSIGNED NOT NULL,
  `RelatieID` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ProjektCategorieen` (
  `ProjektCategorieID` int(10) UNSIGNED NOT NULL,
  `ProjektCategorie` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ProjektTeams` (
  `ProjektTeamID` int(10) UNSIGNED NOT NULL,
  `ProjektID` int(10) UNSIGNED DEFAULT NULL,
  `ProjektTeamLid` varchar(255) DEFAULT NULL,
  `Update` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `relations` (
  `relatie_id` bigint(20) NOT NULL,
  `naam` varchar(255) DEFAULT NULL,
  `alias` varchar(255) DEFAULT NULL,
  `categorie_id` bigint(20) DEFAULT NULL,
  `soort_id` bigint(20) DEFAULT NULL,
  `btw_nummer` varchar(128) DEFAULT NULL,
  `kvk_nummer` varchar(128) DEFAULT NULL,
  `bank_relatie` varchar(128) DEFAULT NULL,
  `bic` varchar(32) DEFAULT NULL,
  `nieuwsbrief` varchar(32) DEFAULT NULL,
  `notities` longtext DEFAULT NULL,
  `updated_at_source` varchar(64) DEFAULT NULL,
  `actief` varchar(32) DEFAULT NULL,
  `legacy_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`legacy_json`)),
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `RisikoAnalyse` (
  `FMEA_ID` int(10) UNSIGNED NOT NULL,
  `OrgID` int(11) DEFAULT NULL,
  `ProjektID` int(10) UNSIGNED DEFAULT NULL,
  `RisikoCategorie` varchar(255) DEFAULT NULL,
  `WatMisKanGaan` longtext DEFAULT NULL,
  `Effect` longtext DEFAULT NULL,
  `ErnstGraad` int(11) DEFAULT NULL,
  `VermoedelijkeOorzaak` longtext DEFAULT NULL,
  `Waarschijnlijkheid` int(11) DEFAULT NULL,
  `Maatregelen` longtext DEFAULT NULL,
  `DetectieGraad` int(11) DEFAULT NULL,
  `Tijdvenster` varchar(100) DEFAULT NULL,
  `Verantwoordelijk` varchar(255) DEFAULT NULL,
  `Actief` varchar(50) DEFAULT NULL,
  `Update` datetime DEFAULT NULL,
  `RisikoPriortieit` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Sleutels` (
  `SleutelID` int(10) UNSIGNED NOT NULL,
  `Functie` varchar(255) DEFAULT NULL,
  `Omschrijving` longtext DEFAULT NULL,
  `Verantwoordelijk` varchar(255) DEFAULT NULL,
  `Merk` varchar(255) DEFAULT NULL,
  `Sleutelnummer` varchar(255) DEFAULT NULL,
  `UitgeleendAan` varchar(255) DEFAULT NULL,
  `Uitleendatum` datetime DEFAULT NULL,
  `Retourdatum` datetime DEFAULT NULL,
  `Update` datetime DEFAULT NULL,
  `Actief` varchar(50) DEFAULT NULL,
  `ActivaID` int(10) UNSIGNED DEFAULT NULL,
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(128) DEFAULT NULL,
  `Locatie` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `StakeholderAnalyse` (
  `StakeholderID` int(10) UNSIGNED NOT NULL,
  `ProjektID` int(10) UNSIGNED DEFAULT NULL,
  `StakeholderGroep` varchar(255) DEFAULT NULL,
  `StakeHolder` varchar(255) DEFAULT NULL,
  `Standpunt` varchar(255) DEFAULT NULL,
  `DatumMeting` date DEFAULT NULL,
  `Doel` longtext DEFAULT NULL,
  `Invloed` varchar(100) DEFAULT NULL,
  `Actie` longtext DEFAULT NULL,
  `ActieDoor` varchar(255) DEFAULT NULL,
  `DatumActieGereed` date DEFAULT NULL,
  `Gereed` varchar(50) DEFAULT NULL,
  `Opmerkingen` longtext DEFAULT NULL,
  `Actief` varchar(50) DEFAULT NULL,
  `Update` datetime DEFAULT NULL,
  `SharePointLink` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `StrategieLineItems` (
  `StrategieLineItemID` int(10) UNSIGNED NOT NULL,
  `ProjektID` int(10) UNSIGNED DEFAULT NULL,
  `LineItemNaam` varchar(500) DEFAULT NULL,
  `Compleet` varchar(50) DEFAULT NULL,
  `Update` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `TestenLineItems` (
  `TestenLineItemID` int(10) UNSIGNED NOT NULL,
  `ProjektID` int(10) UNSIGNED DEFAULT NULL,
  `LineItemNaam` varchar(500) DEFAULT NULL,
  `Compleet` varchar(50) DEFAULT NULL,
  `Update` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `VergaderingBestanden` (
  `BestandID` bigint(20) UNSIGNED NOT NULL,
  `VergaderingID` bigint(20) UNSIGNED NOT NULL,
  `Bestandsnaam` varchar(255) NOT NULL,
  `Bestandspad` varchar(500) NOT NULL,
  `Mimetype` varchar(150) DEFAULT NULL,
  `Bestandsgrootte` bigint(20) UNSIGNED DEFAULT NULL,
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `VergaderingDocumenten` (
  `DocumentID` bigint(20) UNSIGNED NOT NULL,
  `VergaderingID` int(10) UNSIGNED NOT NULL,
  `Bestandsnaam` varchar(255) NOT NULL,
  `Bestandspad` varchar(500) NOT NULL,
  `Mimetype` varchar(150) DEFAULT NULL,
  `Bestandsgrootte` int(10) UNSIGNED DEFAULT NULL,
  `Volgorde` int(11) NOT NULL DEFAULT 0,
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `Vergaderingen` (
  `VergaderingID` int(10) UNSIGNED NOT NULL,
  `Datum` date DEFAULT NULL,
  `Soort` varchar(255) DEFAULT NULL,
  `Locatie` varchar(500) DEFAULT NULL,
  `Aanwezig` longtext DEFAULT NULL,
  `Agenda` longtext DEFAULT NULL,
  `Verslag` longtext DEFAULT NULL,
  `Actief` varchar(50) DEFAULT NULL,
  `Update` datetime DEFAULT NULL,
  `vvegui_modified_at` datetime DEFAULT NULL,
  `vvegui_modified_by` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `VveGuiProjecten` (
  `ProjectID` int(10) UNSIGNED NOT NULL,
  `Naam` varchar(255) NOT NULL,
  `Status` varchar(24) NOT NULL DEFAULT 'groen',
  `StabuCode` varchar(32) DEFAULT NULL,
  `MJOPReferentie` varchar(10) DEFAULT NULL,
  `Haalbaarheid` mediumtext DEFAULT NULL,
  `Planning` mediumtext DEFAULT NULL,
  `Uitvoering` mediumtext DEFAULT NULL,
  `OpleveringNazorg` mediumtext DEFAULT NULL,
  `vvegui_modified_at` datetime NOT NULL,
  `vvegui_modified_by` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `VveGuiVersielog` (
  `VersieLogID` int(10) UNSIGNED NOT NULL,
  `Versienummer` varchar(32) NOT NULL,
  `Versiedatum` date NOT NULL,
  `Omschrijving` text NOT NULL,
  `vvegui_modified_at` datetime NOT NULL,
  `vvegui_modified_by` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `VVEGUI_ID_SEQUENCE` (
  `EntityName` varchar(64) NOT NULL,
  `LastID` bigint(20) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `VVEGUI_ID_STACK` (
  `EntityName` varchar(64) NOT NULL,
  `RecordID` bigint(20) UNSIGNED NOT NULL,
  `AllocatedAt` datetime NOT NULL,
  `AllocatedBy` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ActieRegister`
  ADD PRIMARY KEY (`ActieID`),
  ADD KEY `idx_actie_vergadering` (`VergaderingID`);

ALTER TABLE `Activa`
  ADD PRIMARY KEY (`ActivaID`),
  ADD KEY `idx_activa_cat` (`ActivaCatID`),
  ADD KEY `idx_activa_parent` (`ParentActivaID`);

ALTER TABLE `ActivaAfbeeldingen`
  ADD PRIMARY KEY (`AfbeeldingID`),
  ADD KEY `idx_activa_afbeeldingen_activa` (`ActivaID`);

ALTER TABLE `ActivaBronKoppelingen`
  ADD PRIMARY KEY (`KoppelingID`),
  ADD UNIQUE KEY `uq_activa_bron` (`ActivaID`,`BronType`,`BronID`),
  ADD KEY `idx_bron` (`BronType`,`BronID`);

ALTER TABLE `ActivaCategorie`
  ADD PRIMARY KEY (`ActivaCatID`);

ALTER TABLE `ActivaDocumenten`
  ADD PRIMARY KEY (`DocumentID`),
  ADD KEY `idx_activa_documenten_activa` (`ActivaID`);

ALTER TABLE `ActiviteitenLog`
  ADD PRIMARY KEY (`LogID`),
  ADD KEY `idx_activiteitenlog_datum` (`DatumTijd`),
  ADD KEY `idx_activiteitenlog_gebruiker` (`Gebruikersnaam`),
  ADD KEY `idx_activiteitenlog_actie` (`Actie`);

ALTER TABLE `addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `idx_addr_contact` (`contact_id`),
  ADD KEY `idx_addr_bewoner` (`bewoner_contact_id`),
  ADD KEY `idx_addr_postcode` (`postcode`),
  ADD KEY `idx_addr_huisnummer` (`huisnummer`);

ALTER TABLE `contacts`
  ADD PRIMARY KEY (`contact_id`),
  ADD KEY `idx_contact_huisnummer` (`huisnummer`),
  ADD KEY `idx_contact_achternaam` (`achternaam`),
  ADD KEY `idx_contact_status` (`status`);

ALTER TABLE `contact_relations`
  ADD PRIMARY KEY (`relation_contact_id`),
  ADD KEY `idx_cr_contact` (`contact_id`),
  ADD KEY `idx_cr_relatie` (`relatie_id`);

ALTER TABLE `DefinitieLineItems`
  ADD PRIMARY KEY (`DefinitieLineItemID`),
  ADD KEY `idx_def_project` (`ProjektID`);

ALTER TABLE `DocumentBibliotheek`
  ADD PRIMARY KEY (`DocumentID`),
  ADD KEY `idx_documentbibliotheek_map` (`MapID`),
  ADD KEY `idx_documentbibliotheek_sha` (`Sha256`),
  ADD KEY `idx_documentbibliotheek_verwijderd` (`VerwijderdOp`),
  ADD KEY `idx_documentbibliotheek_versiegroep` (`VersieGroepID`,`Versienummer`),
  ADD KEY `idx_documentbibliotheek_huidig` (`MapID`,`IsHuidig`,`VerwijderdOp`);

ALTER TABLE `DocumentMappen`
  ADD PRIMARY KEY (`MapID`),
  ADD UNIQUE KEY `uq_documentmappen_pad` (`Pad`),
  ADD KEY `idx_documentmappen_parent` (`ParentMapID`);

ALTER TABLE `DocumentTekstIndex`
  ADD PRIMARY KEY (`DocumentID`);

ALTER TABLE `DocumentTekstIndex` ADD FULLTEXT KEY `ft_documenttekstindex_tekst` (`Tekst`);

ALTER TABLE `DocumentTekstPagina`
  ADD PRIMARY KEY (`DocumentID`,`Paginanummer`);

ALTER TABLE `DocumentTekstPagina` ADD FULLTEXT KEY `ft_documenttekstpagina_tekst` (`Tekst`);

ALTER TABLE `EmailAccounts`
  ADD PRIMARY KEY (`EmailAccountID`),
  ADD UNIQUE KEY `uq_EmailAccounts_EmailAddress` (`EmailAddress`),
  ADD KEY `idx_EmailAccounts_ActiveDefault` (`IsActive`,`IsDefault`);

ALTER TABLE `EmailBerichten`
  ADD PRIMARY KEY (`EmailBerichtID`),
  ADD UNIQUE KEY `uq_EmailBerichten_Source` (`EmailAccountID`,`FolderHash`,`UidValidity`,`MessageUid`),
  ADD KEY `idx_EmailBerichten_SentAt` (`SentAt`);

ALTER TABLE `ImplementerenLineItems`
  ADD PRIMARY KEY (`ImplementatieLineItemID`),
  ADD KEY `idx_impl_project` (`ProjektID`);

ALTER TABLE `legacy_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_legacy_table_key` (`source_table`,`source_key`),
  ADD KEY `idx_legacy_table_row` (`source_table`,`source_row`);

ALTER TABLE `MJOPHoofdgroepen`
  ADD PRIMARY KEY (`Code`),
  ADD KEY `idx_mjop_document` (`DocumentID`);

ALTER TABLE `Offertes`
  ADD PRIMARY KEY (`OfferteID`),
  ADD KEY `idx_offerte_project` (`ProjektID`),
  ADD KEY `idx_offerte_onderhoud` (`OnderhoudsPlanID`);

ALTER TABLE `OnderhoudsCategorie`
  ADD PRIMARY KEY (`OnderhoudsCategorieID`);

ALTER TABLE `OnderhoudsplanActies`
  ADD PRIMARY KEY (`ActieID`),
  ADD KEY `idx_onderhoudsplan_actie` (`OnderhoudsplanID`,`Compleet`);

ALTER TABLE `OnderhoudsPlanDocumenten`
  ADD PRIMARY KEY (`DocumentID`);

ALTER TABLE `OnderhoudsplanDocumenten`
  ADD PRIMARY KEY (`DocumentID`),
  ADD KEY `idx_onderhoudsplan_documenten_plan` (`OnderhoudsplanID`);

ALTER TABLE `OnderhoudsplanFotos`
  ADD PRIMARY KEY (`FotoID`),
  ADD KEY `idx_onderhoudsplan_foto` (`OnderhoudsplanID`);

ALTER TABLE `Onderhoudsplannen`
  ADD PRIMARY KEY (`OnderhoudsplanID`),
  ADD KEY `idx_onderhoud_activa` (`ActivaID`),
  ADD KEY `idx_onderhoud_cat` (`OnderhoudsCategorie`);

ALTER TABLE `OnderhoudsplanUitvoerders`
  ADD PRIMARY KEY (`OnderhoudsplanID`,`RelatieID`),
  ADD KEY `idx_onderhoudsplan_relatie` (`RelatieID`);

ALTER TABLE `OntwikkelingLineItems`
  ADD PRIMARY KEY (`OntwikkelingLineItemID`),
  ADD KEY `idx_ontwikk_project` (`ProjektID`);

ALTER TABLE `ProjectActies`
  ADD PRIMARY KEY (`ActieID`),
  ADD KEY `idx_project_actie` (`ProjectID`,`Compleet`);

ALTER TABLE `ProjectActiva`
  ADD PRIMARY KEY (`ProjectID`,`ActivaID`),
  ADD KEY `idx_project_activa` (`ActivaID`);

ALTER TABLE `ProjectChecklist`
  ADD PRIMARY KEY (`ChecklistID`),
  ADD KEY `idx_project_checklist` (`ProjectID`,`Fase`,`ChecklistID`);

ALTER TABLE `ProjectDocumentDatum`
  ADD PRIMARY KEY (`DocumentID`);

ALTER TABLE `ProjectDocumenten`
  ADD PRIMARY KEY (`ProjectID`,`DocumentID`),
  ADD KEY `idx_project_document` (`DocumentID`);

ALTER TABLE `ProjectEmails`
  ADD PRIMARY KEY (`ProjectID`,`EmailBerichtID`),
  ADD KEY `idx_project_email` (`EmailBerichtID`);

ALTER TABLE `Projecten`
  ADD PRIMARY KEY (`ProjektID`),
  ADD KEY `idx_project_cat` (`ProjektCategorie`);

ALTER TABLE `ProjectFotos`
  ADD PRIMARY KEY (`FotoID`),
  ADD KEY `idx_project_foto` (`ProjectID`);

ALTER TABLE `ProjectRelaties`
  ADD PRIMARY KEY (`ProjectID`,`RelatieID`),
  ADD KEY `idx_project_relatie` (`RelatieID`);

ALTER TABLE `ProjektCategorieen`
  ADD PRIMARY KEY (`ProjektCategorieID`);

ALTER TABLE `ProjektTeams`
  ADD PRIMARY KEY (`ProjektTeamID`),
  ADD KEY `idx_projectteam_project` (`ProjektID`);

ALTER TABLE `relations`
  ADD PRIMARY KEY (`relatie_id`),
  ADD KEY `idx_rel_naam` (`naam`);

ALTER TABLE `RisikoAnalyse`
  ADD PRIMARY KEY (`FMEA_ID`),
  ADD KEY `idx_risiko_project` (`ProjektID`);

ALTER TABLE `Sleutels`
  ADD PRIMARY KEY (`SleutelID`),
  ADD KEY `idx_sleutel_activa` (`ActivaID`);

ALTER TABLE `StakeholderAnalyse`
  ADD PRIMARY KEY (`StakeholderID`),
  ADD KEY `idx_stakeholder_project` (`ProjektID`);

ALTER TABLE `StrategieLineItems`
  ADD PRIMARY KEY (`StrategieLineItemID`),
  ADD KEY `idx_strat_project` (`ProjektID`);

ALTER TABLE `TestenLineItems`
  ADD PRIMARY KEY (`TestenLineItemID`),
  ADD KEY `idx_test_project` (`ProjektID`);

ALTER TABLE `VergaderingBestanden`
  ADD PRIMARY KEY (`BestandID`),
  ADD KEY `idx_VergaderingBestanden_VergaderingID` (`VergaderingID`);

ALTER TABLE `VergaderingDocumenten`
  ADD PRIMARY KEY (`DocumentID`),
  ADD KEY `idx_vergadering_documenten_vergadering` (`VergaderingID`);

ALTER TABLE `Vergaderingen`
  ADD PRIMARY KEY (`VergaderingID`);

ALTER TABLE `VveGuiProjecten`
  ADD PRIMARY KEY (`ProjectID`),
  ADD KEY `idx_project_status` (`Status`),
  ADD KEY `idx_project_naam` (`Naam`);

ALTER TABLE `VveGuiVersielog`
  ADD PRIMARY KEY (`VersieLogID`),
  ADD KEY `idx_versielog_datum` (`Versiedatum`,`VersieLogID`),
  ADD KEY `idx_versielog_versie` (`Versienummer`);

ALTER TABLE `VVEGUI_ID_SEQUENCE`
  ADD PRIMARY KEY (`EntityName`);

ALTER TABLE `VVEGUI_ID_STACK`
  ADD PRIMARY KEY (`EntityName`,`RecordID`);

ALTER TABLE `ActieRegister`
  MODIFY `ActieID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `Activa`
  MODIFY `ActivaID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ActivaAfbeeldingen`
  MODIFY `AfbeeldingID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ActivaBronKoppelingen`
  MODIFY `KoppelingID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ActivaCategorie`
  MODIFY `ActivaCatID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ActivaDocumenten`
  MODIFY `DocumentID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ActiviteitenLog`
  MODIFY `LogID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `contact_relations`
  MODIFY `relation_contact_id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `DefinitieLineItems`
  MODIFY `DefinitieLineItemID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `DocumentBibliotheek`
  MODIFY `DocumentID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `DocumentMappen`
  MODIFY `MapID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `EmailAccounts`
  MODIFY `EmailAccountID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `EmailBerichten`
  MODIFY `EmailBerichtID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ImplementerenLineItems`
  MODIFY `ImplementatieLineItemID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `legacy_records`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `Offertes`
  MODIFY `OfferteID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `OnderhoudsCategorie`
  MODIFY `OnderhoudsCategorieID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `OnderhoudsplanActies`
  MODIFY `ActieID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `OnderhoudsPlanDocumenten`
  MODIFY `DocumentID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `OnderhoudsplanDocumenten`
  MODIFY `DocumentID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `OnderhoudsplanFotos`
  MODIFY `FotoID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `Onderhoudsplannen`
  MODIFY `OnderhoudsplanID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `OntwikkelingLineItems`
  MODIFY `OntwikkelingLineItemID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ProjectActies`
  MODIFY `ActieID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ProjectChecklist`
  MODIFY `ChecklistID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `Projecten`
  MODIFY `ProjektID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ProjectFotos`
  MODIFY `FotoID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ProjektCategorieen`
  MODIFY `ProjektCategorieID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ProjektTeams`
  MODIFY `ProjektTeamID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `RisikoAnalyse`
  MODIFY `FMEA_ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `Sleutels`
  MODIFY `SleutelID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `StakeholderAnalyse`
  MODIFY `StakeholderID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `StrategieLineItems`
  MODIFY `StrategieLineItemID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `TestenLineItems`
  MODIFY `TestenLineItemID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `VergaderingBestanden`
  MODIFY `BestandID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `VergaderingDocumenten`
  MODIFY `DocumentID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `Vergaderingen`
  MODIFY `VergaderingID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `VveGuiProjecten`
  MODIFY `ProjectID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `VveGuiVersielog`
  MODIFY `VersieLogID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ActieRegister`
  ADD CONSTRAINT `fk_actieregister_vergadering` FOREIGN KEY (`VergaderingID`) REFERENCES `Vergaderingen` (`VergaderingID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `Activa`
  ADD CONSTRAINT `fk_activa_parent` FOREIGN KEY (`ParentActivaID`) REFERENCES `Activa` (`ActivaID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `ActivaAfbeeldingen`
  ADD CONSTRAINT `fk_activa_afbeeldingen_activa` FOREIGN KEY (`ActivaID`) REFERENCES `Activa` (`ActivaID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `ActivaDocumenten`
  ADD CONSTRAINT `fk_activa_documenten_activa` FOREIGN KEY (`ActivaID`) REFERENCES `Activa` (`ActivaID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `DefinitieLineItems`
  ADD CONSTRAINT `fk_def_project` FOREIGN KEY (`ProjektID`) REFERENCES `Projecten` (`ProjektID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `DocumentBibliotheek`
  ADD CONSTRAINT `fk_documentbibliotheek_map` FOREIGN KEY (`MapID`) REFERENCES `DocumentMappen` (`MapID`);

ALTER TABLE `DocumentMappen`
  ADD CONSTRAINT `fk_documentmappen_parent` FOREIGN KEY (`ParentMapID`) REFERENCES `DocumentMappen` (`MapID`);

ALTER TABLE `DocumentTekstIndex`
  ADD CONSTRAINT `fk_documenttekstindex_document` FOREIGN KEY (`DocumentID`) REFERENCES `DocumentBibliotheek` (`DocumentID`) ON DELETE CASCADE;

ALTER TABLE `DocumentTekstPagina`
  ADD CONSTRAINT `fk_documenttekstpagina_document` FOREIGN KEY (`DocumentID`) REFERENCES `DocumentBibliotheek` (`DocumentID`) ON DELETE CASCADE;

ALTER TABLE `EmailBerichten`
  ADD CONSTRAINT `fk_EmailBerichten_Account` FOREIGN KEY (`EmailAccountID`) REFERENCES `EmailAccounts` (`EmailAccountID`) ON DELETE CASCADE;

ALTER TABLE `ImplementerenLineItems`
  ADD CONSTRAINT `fk_impl_project` FOREIGN KEY (`ProjektID`) REFERENCES `Projecten` (`ProjektID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `Offertes`
  ADD CONSTRAINT `fk_offerte_onderhoud` FOREIGN KEY (`OnderhoudsPlanID`) REFERENCES `Onderhoudsplannen` (`OnderhoudsplanID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_offerte_project` FOREIGN KEY (`ProjektID`) REFERENCES `Projecten` (`ProjektID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `Onderhoudsplannen`
  ADD CONSTRAINT `fk_onderhoud_activa` FOREIGN KEY (`ActivaID`) REFERENCES `Activa` (`ActivaID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `OntwikkelingLineItems`
  ADD CONSTRAINT `fk_ontwikk_project` FOREIGN KEY (`ProjektID`) REFERENCES `Projecten` (`ProjektID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `Projecten`
  ADD CONSTRAINT `fk_project_categorie` FOREIGN KEY (`ProjektCategorie`) REFERENCES `ProjektCategorieen` (`ProjektCategorieID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `ProjektTeams`
  ADD CONSTRAINT `fk_projectteam_project` FOREIGN KEY (`ProjektID`) REFERENCES `Projecten` (`ProjektID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `RisikoAnalyse`
  ADD CONSTRAINT `fk_risiko_project` FOREIGN KEY (`ProjektID`) REFERENCES `Projecten` (`ProjektID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `Sleutels`
  ADD CONSTRAINT `fk_sleutel_activa` FOREIGN KEY (`ActivaID`) REFERENCES `Activa` (`ActivaID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `StakeholderAnalyse`
  ADD CONSTRAINT `fk_stakeholder_project` FOREIGN KEY (`ProjektID`) REFERENCES `Projecten` (`ProjektID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `StrategieLineItems`
  ADD CONSTRAINT `fk_strat_project` FOREIGN KEY (`ProjektID`) REFERENCES `Projecten` (`ProjektID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `TestenLineItems`
  ADD CONSTRAINT `fk_test_project` FOREIGN KEY (`ProjektID`) REFERENCES `Projecten` (`ProjektID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `VergaderingDocumenten`
  ADD CONSTRAINT `fk_vergadering_documenten_vergadering` FOREIGN KEY (`VergaderingID`) REFERENCES `Vergaderingen` (`VergaderingID`) ON DELETE CASCADE ON UPDATE CASCADE;

CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `v_ai_contact_adressen`  AS SELECT `c`.`contact_id` AS `contact_id`, concat_ws(' ',nullif(`c`.`voornaam`,''),nullif(`c`.`voorletters`,''),nullif(`c`.`tussenvoegsel`,''),nullif(`c`.`achternaam`,'')) AS `naam`, `c`.`huisnummer` AS `appartement`, `c`.`bewoner` AS `bewoner`, `c`.`eigenaar` AS `eigenaar`, `c`.`huurder` AS `huurder`, `c`.`status` AS `status`, `a`.`address_id` AS `address_id`, `a`.`adres_soort` AS `adres_soort`, `a`.`straat` AS `straat`, `a`.`huisnummer` AS `adres_huisnummer`, `a`.`postcode` AS `postcode`, `a`.`woonplaats` AS `woonplaats`, `a`.`email_prive` AS `email_prive`, `a`.`mobiel` AS `mobiel`, `a`.`actief` AS `adres_actief` FROM (`contacts` `c` left join `addresses` `a` on(`a`.`contact_id` = `c`.`contact_id` or `a`.`bewoner_contact_id` = `c`.`contact_id`));

CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `v_ai_wie_woont_waar`  AS SELECT `v_ai_contact_adressen`.`contact_id` AS `contact_id`, `v_ai_contact_adressen`.`naam` AS `naam`, `v_ai_contact_adressen`.`appartement` AS `appartement`, `v_ai_contact_adressen`.`status` AS `status`, `v_ai_contact_adressen`.`bewoner` AS `bewoner`, `v_ai_contact_adressen`.`eigenaar` AS `eigenaar`, `v_ai_contact_adressen`.`huurder` AS `huurder`, `v_ai_contact_adressen`.`address_id` AS `address_id`, `v_ai_contact_adressen`.`adres_soort` AS `adres_soort`, `v_ai_contact_adressen`.`straat` AS `straat`, `v_ai_contact_adressen`.`adres_huisnummer` AS `adres_huisnummer`, `v_ai_contact_adressen`.`postcode` AS `postcode`, `v_ai_contact_adressen`.`woonplaats` AS `woonplaats`, `v_ai_contact_adressen`.`email_prive` AS `email_prive`, `v_ai_contact_adressen`.`mobiel` AS `mobiel`, `v_ai_contact_adressen`.`adres_actief` AS `adres_actief` FROM `v_ai_contact_adressen` WHERE `v_ai_contact_adressen`.`bewoner` = 'Waar' OR `v_ai_contact_adressen`.`eigenaar` = 'Waar' OR `v_ai_contact_adressen`.`huurder` = 'Waar' OR `v_ai_contact_adressen`.`appartement` is not null;

CREATE TABLE `VveInstallation` (`id` TINYINT UNSIGNED NOT NULL PRIMARY KEY, `instance_id` CHAR(32) NOT NULL, `schema_version` INT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
