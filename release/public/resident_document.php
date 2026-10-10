<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/security.php';
require_once __DIR__.'/resident_portal_lib.php';
header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
try{
    $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$id||!residentPortalDocumentIsVisible((int)$id)){http_response_code(404);exit('Document niet gevonden.');}
    $pdo=residentPortalDb();$stmt=$pdo->prepare('SELECT OrigineleNaam,Bestandspad,Mimetype FROM DocumentBibliotheek WHERE DocumentID=? AND IsHuidig=1 AND VerwijderdOp IS NULL');$stmt->execute([(int)$id]);$doc=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$doc){http_response_code(404);exit('Document niet gevonden.');}
    $file=resolveStoredFile((string)$doc['Bestandspad']);$mime=(string)$doc['Mimetype'];$name=str_replace(["\r","\n",'"'],['_','_','_'],(string)$doc['OrigineleNaam']);$view=($_GET['view']??'')==='1';
    if($view&&in_array($mime,['application/pdf','image/jpeg','image/png','image/gif','image/webp','image/tiff','text/plain'],true)){
        header('Content-Type: '.$mime);header('Content-Length: '.filesize($file));header('Content-Disposition: inline; filename="'.$name.'"; filename*=UTF-8\'\''.rawurlencode($name));
    }else{
        header('Content-Type: application/octet-stream');header('Content-Length: '.filesize($file));header('Content-Disposition: attachment; filename="'.$name.'"; filename*=UTF-8\'\''.rawurlencode($name));
    }
    readfile($file);
}catch(Throwable $e){securityIncident($e);http_response_code(404);echo 'Document niet gevonden.';}
