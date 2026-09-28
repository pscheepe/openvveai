<?php
declare(strict_types=1);
require __DIR__.'/../release/public/lookup_catalogs.php';

$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE ActivaCategorie (ActivaCatID INTEGER PRIMARY KEY, ActivaCategorie TEXT)');
$db->exec("INSERT INTO ActivaCategorie VALUES (1,'Bouwkundig'),(2,'Installaties')");
$db->exec('CREATE TABLE OnderhoudsCategorie (OnderhoudsCategorieID INTEGER PRIMARY KEY, OnderhoudsCategorie TEXT)');
$db->exec("INSERT INTO OnderhoudsCategorie VALUES (4,'Schilderwerk')");
$db->exec('CREATE TABLE StabuCodes (StabuCode TEXT PRIMARY KEY, Omschrijving TEXT)');
$db->exec("INSERT INTO StabuCodes VALUES ('21.10','Betonwerk')");
$lookups=catalogLookups($db,[
    'Activa'=>['ActivaCatID'=>[1=>'Bouw en constructie'],'Functie'=>['Hoofdonderdeel','Eigen lifttype']],
    'maintenance_plans'=>['OnderhoudsCategorie'=>[],'StabuCode'=>['21'=>'Eigen tekst betonwerk','21.10'=>'Oude detailcode']],
]);
if($lookups['Activa']['ActivaCatID'][1]!=='Bouw en constructie'
    ||$lookups['Activa']['ActivaCatID'][2]!=='Installaties'
    ||$lookups['maintenance_plans']['OnderhoudsCategorie'][4]!=='Schilderwerk'
    ||$lookups['maintenance_plans']['StabuCode']['21']!=='Eigen tekst betonwerk'
    ||!in_array('Goed',$lookups['Activa']['Toestand'],true)
    ||!in_array('Buiten gebruik',$lookups['Activa']['Toestand'],true)
    ||!in_array('Eigen lifttype',$lookups['Activa']['Functie'],true)
    ||in_array('Installatie',$lookups['Activa']['Functie'],true)
    ||!in_array('Waar',$lookups['common']['Compleet'],true)
    ||isset($lookups['maintenance_plans']['StabuCode']['21.10'])
    ||count($lookups['maintenance_plans']['StabuCode'])!==55
    ||$lookups['maintenance_plans']['StabuCode']['80']!=='LIFTINSTALLATIES')throw new RuntimeException('Catalogusinhoud onjuist.');
$db->exec('DROP TABLE StabuCodes');
if(catalogLookups($db,$lookups)['maintenance_plans']['StabuCode']['21']!=='Eigen tekst betonwerk')throw new RuntimeException('Bewaarde STABU-tekst ontbreekt.');
$fresh=catalogLookups($db,['Activa'=>['ActivaCatID'=>[1=>'Bouwkundig']]]);
if(!in_array('Installatie',$fresh['Activa']['Functie'],true)||!in_array('Goed',$fresh['Activa']['Toestand'],true))throw new RuntimeException('Standaardkeuzelijsten ontbreken op nieuwe installatie.');
foreach([
    ['contacts','Status','Actief'],['contacts','Aanhef','De heer'],['contacts','VVEFunctie','Voorzitter'],
    ['addresses','AdresSoort','Woonadres'],['relations','Status','Actief'],['Activa','Status','Actief'],
    ['maintenance_plans','Status','Gepland'],['meetings','Soort','Algemene ledenvergadering'],
    ['projects','Status','groen'],['common','Compleet','Niet waar'],
] as [$group,$field,$expected])if(!in_array($expected,$fresh[$group][$field]??[],true))throw new RuntimeException("Standaardkeuzelijst ontbreekt: {$group}.{$field}.");
$edited=catalogEditedRows('StabuCode',array_replace($lookups['maintenance_plans']['StabuCode'],['21'=>'Constructief betonwerk']),[],[]);
if($edited['21']!=='Constructief betonwerk'||count($edited)!==55)throw new RuntimeException('STABU-tekst bewerken onjuist.');
try {
    catalogEditedRows('StabuCode',$lookups['maintenance_plans']['StabuCode'],['22.00'],['Metselwerk']);
    throw new RuntimeException('STABU-detailcode is geaccepteerd.');
} catch(RuntimeException $error) {
    if($error->getMessage()==='STABU-detailcode is geaccepteerd.')throw $error;
}
try {
    catalogEditedRows('ActivaCatID',[1=>'Bouwkundig'],[1],['Dubbel']);
    throw new RuntimeException('Dubbele code is geaccepteerd.');
} catch(RuntimeException $error) {
    if($error->getMessage()==='Dubbele code is geaccepteerd.')throw $error;
}
echo "Catalogusbron en bewerkte labels: OK\n";
