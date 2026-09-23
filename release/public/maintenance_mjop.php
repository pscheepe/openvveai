<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';

function maintenanceMjopDefinitions(): array {
    return [
        ['code'=>'01','name'=>'Gevels','page'=>0],
        ['code'=>'02','name'=>'Balkons','page'=>0],
        ['code'=>'03','name'=>'Buitenkozijnen','page'=>0],
        ['code'=>'04','name'=>'Beglazing','page'=>0],
        ['code'=>'05','name'=>'Daken','page'=>0],
        ['code'=>'06','name'=>'Schoorstenen','page'=>0],
        ['code'=>'09','name'=>'Buitenschilderwerk','page'=>0],
        ['code'=>'21','name'=>'Binnenwanden','page'=>0],
        ['code'=>'22','name'=>'Vloeren','page'=>0],
        ['code'=>'23','name'=>'Plafonds','page'=>0],
        ['code'=>'24','name'=>'Binnenkozijnen','page'=>0],
        ['code'=>'25','name'=>'Binnentrappen','page'=>0],
        ['code'=>'26','name'=>'Inrichting','page'=>0],
        ['code'=>'29','name'=>'Binnenschilderwerk','page'=>0],
        ['code'=>'41','name'=>'Klimaatinstallaties','page'=>0],
        ['code'=>'42','name'=>'Gas, water en sanitair','page'=>0],
        ['code'=>'43','name'=>'Vuilafvoervoorziening','page'=>0],
        ['code'=>'44','name'=>'Elektrische installaties','page'=>0],
        ['code'=>'45','name'=>'Transportvoorziening','page'=>0],
        ['code'=>'46','name'=>'Beveiligingsinstallaties','page'=>0],
        ['code'=>'51','name'=>'Terreinafwerkingen e.d.','page'=>0],
        ['code'=>'61','name'=>'Diversen','page'=>0],
        ['code'=>'69','name'=>'Staartkosten','page'=>0],
    ];
}

function maintenanceEnsureMjopSchema(PDO $db): void {
    // Schema and generic categories are installed once by install.php.
}

function maintenanceMjopOptions(PDO $db): array {
    maintenanceEnsureMjopSchema($db);
    $rows=$db->query('SELECT `Code`,`Naam`,`Pagina`,`DocumentID` FROM `MJOPHoofdgroepen` ORDER BY CAST(`Code` AS UNSIGNED),`Code`')->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static function(array $row):array{
        $documentId=(int)($row['DocumentID']??0);
        return [
            'id'=>(string)$row['Code'],
            'code'=>(string)$row['Code'],
            'name'=>(string)$row['Naam'],
            'label'=>(string)$row['Code'].' — '.(string)$row['Naam'],
            'page'=>(int)$row['Pagina'],
            'document_id'=>$documentId ?: null,
            'url'=>$documentId ? 'document_download.php?id='.$documentId.'&view=1#page='.(int)$row['Pagina'] : '',
        ];
    },$rows);
}

function maintenanceMjopFind(PDO $db,string $code): ?array {
    foreach(maintenanceMjopOptions($db) as $item)if($item['code']===$code)return $item;
    return null;
}
