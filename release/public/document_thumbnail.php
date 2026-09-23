<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/security.php';
try{
  requireLogin();
  $id=(int)($_GET['id']??0);
  if($id<1)throw new RuntimeException('Ongeldig document.');
  $pdo=db(loadConfigCompat(tenantPrivateFile('config.php')));
  $s=$pdo->prepare('SELECT Bestandspad,Mimetype FROM DocumentBibliotheek WHERE DocumentID=? AND VerwijderdOp IS NULL');
  $s->execute([$id]);$d=$s->fetch();
  $allowed=['image/jpeg','image/png','image/gif','image/webp'];
  if(!$d||!in_array((string)$d['Mimetype'],$allowed,true)){http_response_code(404);exit;}
  $file=resolveStoredFile((string)$d['Bestandspad']);
  header('Content-Type: '.(string)$d['Mimetype']);
  header('Content-Length: '.filesize($file));
  header('Content-Disposition: inline');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: private, max-age=300');
  readfile($file);
}catch(Throwable $e){securityIncident($e);http_response_code(404);}
