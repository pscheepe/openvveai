<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/security.php';

function folderRedirect(string $message,bool $error=true,int $map=0): never {
  $_SESSION['document_folder_flash']=['message'=>$message,'error'=>$error];
  header('Location: documents.php'.($map>0?'?map='.$map:''),true,303);exit;
}

try{
  requireAdmin();verifyCsrf();$map=(int)($_POST['map']??0);
  if($map<1)folderRedirect('Kies eerst één specifieke map die u wilt wissen.');
  $pdo=db(loadConfigCompat(tenantPrivateFile('config.php')));
  $s=$pdo->prepare('SELECT Naam,Pad FROM DocumentMappen WHERE MapID=? AND Actief=1');$s->execute([$map]);$folder=$s->fetch();
  if(!$folder)folderRedirect('Map bestaat niet of is al gewist.');
  $c=$pdo->prepare('SELECT COUNT(*) FROM DocumentMappen WHERE ParentMapID=? AND Actief=1');$c->execute([$map]);$children=(int)$c->fetchColumn();
  $d=$pdo->prepare('SELECT COUNT(*) FROM DocumentBibliotheek WHERE MapID=? AND IsHuidig=1 AND VerwijderdOp IS NULL');$d->execute([$map]);$documents=(int)$d->fetchColumn();
  if($documents>0||$children>0){$parts=[];if($documents>0)$parts[]=$documents.' bestand'.($documents===1?'':'en');if($children>0)$parts[]=$children.' submap'.($children===1?'':'pen');folderRedirect('Deze map is niet leeg: '.implode(' en ',$parts).'. Verwijder de bestanden eerst afzonderlijk en wis submappen van onder naar boven.',true,$map);}
  if(($_POST['confirm']??'')!=='1'){
    $path=htmlspecialchars((string)$folder['Pad'],ENT_QUOTES,'UTF-8');$token=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8');
    ?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Lege map wissen</title><style>body{font:16px system-ui;background:#f3f6f9;color:#17202a;margin:0;padding:24px}.box{max-width:620px;margin:10vh auto;background:#fff;border:1px solid #d7e0e8;border-radius:12px;padding:24px}.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{border:1px solid #9fb9d8;background:#e8f1fb;color:#174a8b;border-radius:7px;padding:10px 14px;text-decoration:none;font:inherit;font-weight:700;cursor:pointer}.danger{background:#fff1f1;border-color:#d9aaaa;color:#8d3030}</style></head><body><main class="box"><h1>Lege map wissen?</h1><p>De map <strong><?=$path?></strong> is leeg. Alleen deze map wordt uit de mappenlijst verwijderd.</p><p>Bestanden en submappen worden nooit automatisch meegewist.</p><div class="actions"><a class="btn" href="documents.php?map=<?=$map?>">Annuleren</a><form method="post"><input type="hidden" name="csrf" value="<?=$token?>"><input type="hidden" name="map" value="<?=$map?>"><input type="hidden" name="confirm" value="1"><button class="btn danger" type="submit">Ja, lege map wissen</button></form></div></main></body></html><?php exit;
  }
  $u=$pdo->prepare('UPDATE DocumentMappen SET Actief=0 WHERE MapID=? AND Actief=1');$u->execute([$map]);
  if(!$u->rowCount())folderRedirect('Map kon niet worden gewist.');
  folderRedirect('Lege map "'.(string)$folder['Pad'].'" is gewist.',false);
}catch(Throwable $e){$id=securityIncident($e);folderRedirect('De mapcontrole is mislukt. Foutreferentie: '.$id);}
