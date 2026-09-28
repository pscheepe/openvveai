<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/security.php';
try {
  requireLogin();
  $id=(int)($_GET['id']??0);
  if($id<1)throw new RuntimeException('Ongeldig document.');
  $pdo=db(loadConfigCompat(tenantPrivateFile('config.php')));
  $s=$pdo->prepare('SELECT OrigineleNaam,Bestandspad,Mimetype,Bestandsgrootte FROM DocumentBibliotheek WHERE DocumentID=? AND VerwijderdOp IS NULL');
  $s->execute([$id]);$d=$s->fetch();
  if(!$d){http_response_code(404);exit('Niet gevonden.');}
  $file=resolveStoredFile((string)$d['Bestandspad']);
  $name=str_replace(["\r","\n",'"'],['_','_','_'],(string)$d['OrigineleNaam']);
  $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
  $view=($_GET['view']??'')==='1';
  if($view&&in_array($ext,['eml','msg'],true)){
    require_once __DIR__.'/document_text.php';
    $text=$ext==='eml'?documentEmlText($file):documentMsgText($file);
    header('Content-Type: text/html; charset=utf-8');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");
    header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
    $safe=htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    echo '<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$safe.'</title><style>body{font:16px system-ui,sans-serif;max-width:900px;margin:32px auto;padding:0 18px;color:#17202a}pre{white-space:pre-wrap;word-break:break-word;background:#f3f6f9;border:1px solid #d7e0e8;border-radius:8px;padding:18px}</style><h1>'.$safe.'</h1><pre>'.htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</pre>'.($ext==='msg'?'<p>Weergave op basis van tekst die uit het Outlook-bestand kon worden hersteld; controleer het originele bestand bij ontbrekende opmaak of bijlagen.</p>':'').'</html>';
    exit;
  }
  $inlineTypes=['application/pdf','image/jpeg','image/png','image/gif','image/webp','image/tiff','text/plain'];
  $inline=$view&&in_array((string)$d['Mimetype'],$inlineTypes,true);
  header('Content-Type: '.(string)$d['Mimetype']);
  header('Content-Length: '.filesize($file));
  header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="'.$name.'"; filename*=UTF-8\'\''.rawurlencode($name));
  header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');readfile($file);
}catch(Throwable $e){securityIncident($e);http_response_code(404);echo 'Niet gevonden.';}
