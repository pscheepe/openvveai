<?php
declare(strict_types=1);
require_once __DIR__.'/stabu_main_groups.php';

/** Read the historical category tables without copying data from another VvE. */
function catalogTable(PDO $db, array $tableNames, array $idNames, array $labelNames): array {
    foreach ($tableNames as $table) {
        try {
            $query=$db->query('SELECT * FROM `'.$table.'` LIMIT 0');
            $columns=[];
            for ($i=0; $i<$query->columnCount(); $i++) {
                $meta=$query->getColumnMeta($i);
                $columns[strtolower((string)($meta['name']??''))]=(string)($meta['name']??'');
            }
            $id=''; $label='';
            foreach ($idNames as $candidate) if (isset($columns[strtolower($candidate)])) { $id=$columns[strtolower($candidate)]; break; }
            foreach ($labelNames as $candidate) if (isset($columns[strtolower($candidate)])) { $label=$columns[strtolower($candidate)]; break; }
            if ($id==='' || $label==='') continue;
            $safeId=str_replace('`','``',$id);
            $safeLabel=str_replace('`','``',$label);
            $rows=$db->query('SELECT `'.$safeId.'` AS catalog_id, `'.$safeLabel.'` AS catalog_label FROM `'.$table.'` ORDER BY `'.$safeId.'`')->fetchAll(PDO::FETCH_ASSOC);
            $result=[];
            foreach ($rows as $row) {
                $code=trim((string)$row['catalog_id']);
                $name=trim((string)$row['catalog_label']);
                if ($code!=='' && $name!=='') $result[$code]=$name;
            }
            if($result)return $result;
        } catch (PDOException $error) {
            // A clean installation may not include this optional historical table.
        }
    }
    return [];
}

function catalogLookups(PDO $db, array $lookups): array {
    // Vul oudere installaties aan met de keuzelijsten uit het installatieprofiel.
    // Een bestaande ingestelde lijst is leidend; ontbrekende lijsten krijgen defaults.
    $defaults=[
        'contacts'=>['Status'=>['Actief','Niet actief'],'Aanhef'=>['De heer','Mevrouw'],'VVEFunctie'=>['Voorzitter','Secretaris','Penningmeester','Bestuurslid']],
        'addresses'=>['AdresSoort'=>['Woonadres','Postadres','Werkadres']],
        'relations'=>['Status'=>['Actief','Niet actief']],
        'Activa'=>[
            'Status'=>['Actief','Niet actief'],
            'Functie'=>['Hoofdonderdeel','Constructie','Installatie','Ruimte','Toegang','Veiligheid','Energie','Water en riolering','Communicatie'],
            'Toestand'=>['Nieuw','Goed','Redelijk','Matig','Slecht','Buiten gebruik'],
        ],
        'maintenance_plans'=>['Status'=>['Gepland','In uitvoering','Afgerond','Vervallen']],
        'meetings'=>['Soort'=>['Algemene ledenvergadering','Bestuursvergadering']],
        'projects'=>['Status'=>['groen','oranje','rood']],
        'common'=>['Compleet'=>['Waar','Niet waar']],
        'resident_portal'=>[
            'TypeMelding'=>['Probleem of storing','Vraag','Klacht','Suggestie'],
            'OnderwerpMelding'=>['Lift','Water of lekkage','Verwarming of ventilatie','Schoonmaak of algemene ruimte','Geluid of overlast','Deur, slot of sleutel','Afval','Anders'],
            'LocatieMelding'=>['In mijn appartement','Hal A','Hal B','Hal C','Hal D','Andere algemene ruimte','Buiten bij het gebouw','Anders of ik weet het niet'],
            'UrgentieMelding'=>['Spoed — direct gevaar of snel erger','Dringend — vandaag bekijken','Normaal — binnen enkele dagen','Geen haast'],
        ],
    ];
    foreach($defaults as $group=>$fields){
        foreach($fields as $field=>$values){
            $hasSavedList=array_key_exists($field,(array)($lookups[$group]??[]));
            $current=$hasSavedList?array_values((array)$lookups[$group][$field]):$values;
            $merged=[];
            foreach($current as $value){
                $value=trim((string)$value);
                if($value!==''&&!in_array($value,$merged,true))$merged[]=$value;
            }
            $lookups[$group][$field]=$merged;
        }
    }
    try{
        $storedProjectStatuses=$db->query("SELECT DISTINCT TRIM(Status) FROM VveGuiProjecten WHERE Status IS NOT NULL AND TRIM(Status)<>'' ORDER BY Status")->fetchAll(PDO::FETCH_COLUMN);
        $lookups['projects']['Status']=array_values(array_unique(array_merge((array)$lookups['projects']['Status'],array_map('strval',$storedProjectStatuses))));
    }catch(PDOException $error){
        // Projecten zijn optioneel totdat de module voor het eerst wordt geopend.
    }
    $sources=[
        ['Activa','ActivaCatID',['ActivaCategorie'],['ActivaCatID'],['ActivaCategorie']],
        ['maintenance_plans','OnderhoudsCategorie',['OnderhoudsCategorie'],['OnderhoudsCategorieID'],['OnderhoudsCategorie']],
        ['maintenance_plans','StabuCode',['StabuCodes','STABUCodes','StabuCode'],['StabuCode','Code'],['Omschrijving','Beschrijving','StabuOmschrijving','Naam']],
    ];
    foreach ($sources as [$group,$field,$tables,$ids,$labels]) {
        $current=(array)($lookups[$group][$field]??[]);
        if($field==='StabuCode'){
            $main=stabuMainGroups();
            foreach($main as $code=>$label){
                $saved=trim((string)($current[$code]??''));
                if($saved!=='')$main[$code]=$saved;
            }
            $lookups[$group][$field]=$main;
            continue;
        }
        // Saved labels take precedence; the database supplies missing IDs.
        $lookups[$group][$field]=$current+catalogTable($db,$tables,$ids,$labels);
    }
    return $lookups;
}

function catalogEditedRows(string $field,array $posted,array $newCodes,array $newLabels): array {
    if($field==='StabuCode'){
        $main=stabuMainGroups();
        if($newCodes||array_diff_key($posted,$main)||array_diff_key($main,$posted))
            throw new RuntimeException('Gebruik alleen de STABU-hoofdgroepen uit de bronlijst.');
    }
    $result=[];
    foreach($posted as $code=>$label)$result[(string)$code]=trim((string)$label);
    foreach($newCodes as $i=>$code){
        $code=trim((string)$code); $label=trim((string)($newLabels[$i]??''));
        if($code===''&&$label==='')continue;
        if($code===''||$label==='')throw new RuntimeException('Vul bij nieuwe keuzes zowel code als tekst in.');
        if(array_key_exists($code,$result))throw new RuntimeException('Een code staat dubbel in de keuzelijst.');
        $result[$code]=$label;
    }
    foreach($result as $code=>$label){
        if($label===''||mb_strlen($label)>255)throw new RuntimeException('Elke keuzetekst moet 1 tot 255 tekens bevatten.');
        if($field==='StabuCode'){
            if(!preg_match('/^[A-Za-z0-9 ._\/-]{1,32}$/D',(string)$code))throw new RuntimeException('Ongeldige STABU-code.');
        } elseif(!ctype_digit((string)$code)||strlen((string)$code)>10||(int)$code<1)throw new RuntimeException('Categorie-ID moet een positief getal zijn.');
    }
    return $result;
}
