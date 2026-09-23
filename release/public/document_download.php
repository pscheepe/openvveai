<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/security.php';
try{
  requireLogin();$id=(int)($_GET['id']??0);if($id<1)throw new RuntimeException('Ongeldig document.');$pdo=db(loadConfigCompat(tenantPrivateFile('config.php')));$s=$pdo->prepare('SELECT OrigineleNaam,Bestandspad,Mimetype,Bestandsgrootte FROM DocumentBibliotheek WHERE DocumentID=? AND VerwijderdOp IS NULL');$s->execute([$id]);$d=$s->fetch();if(!$d){http_response_code(404);exit('Niet gevonden.');}$file=resolveStoredFile((string)$d['Bestandspad']);$name=str_replace(["\r","\n",'"'],['_','_','_'],(string)$d['OrigineleNaam']);$inline=($_GET['view']??'')==='1'&&(string)$d['Mimetype']==='application/pdf';header('Content-Type: '.(string)$d['Mimetype']);header('Content-Length: '.filesize($file));header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="'.$name.'"; filename*=UTF-8\'\''.rawurlencode($name));header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');readfile($file);
}catch(Throwable $e){securityIncident($e);http_response_code(404);echo 'Niet gevonden.';}
