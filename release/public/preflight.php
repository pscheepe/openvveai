<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/security.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

try{
    $pdo=db(loadConfigCompat(tenantPrivateFile('config.php')));
    $required=['VVEGUI_ID_STACK','VVEGUI_ID_SEQUENCE','Sleutels','VergaderingBestanden','ActivaAfbeeldingen','ActivaDocumenten','OnderhoudsplanDocumenten'];
    $stmt=$pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()');
    $tables=array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN));$missing=array_values(array_diff($required,$tables));
    if($missing){fwrite(STDERR,'Preflight mislukt: voer eerst de bestaande schema-initialisatie/migratie uit. Ontbrekende tabellen: '.implode(', ',$missing).PHP_EOL);exit(1);}
    fwrite(STDOUT,'Preflight geslaagd: vereiste applicatietabellen bestaan; er zijn geen schemawijzigingen uitgevoerd.'.PHP_EOL);
}catch(Throwable $e){$id=securityIncident($e);fwrite(STDERR,'Preflight mislukt. Referentie: '.$id.PHP_EOL);exit(1);}
