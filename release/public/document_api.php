<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/security.php';
require_once __DIR__.'/document_text.php';
require_once __DIR__.'/activity.php';
header('Content-Type: application/json; charset=utf-8');

function jout(array $data, int $status=200): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function documentDb(): PDO { $c=loadConfigCompat(tenantPrivateFile('config.php')); return db($c); }
function documentWriter(): array { $u=requireLogin(); if(!in_array((string)($u['role']??''),['admin','bestuur'],true)) throw new RuntimeException('Onvoldoende rechten.'); return $u; }
function documentAdmin(): array { $u=requireLogin(); if((string)($u['role']??'')!=='admin') throw new SafeInputException('Alleen een administrator mag mappen aanmaken.'); return $u; }
function ensureAssetSourceLinks(PDO $pdo): void { $pdo->exec("CREATE TABLE IF NOT EXISTS `ActivaBronKoppelingen` (`KoppelingID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`ActivaID` INT(10) UNSIGNED NOT NULL,`BronType` VARCHAR(20) NOT NULL,`BronID` BIGINT UNSIGNED NOT NULL,`Titel` VARCHAR(255) NULL,`AangemaaktOp` DATETIME NOT NULL,`AangemaaktDoor` VARCHAR(128) NULL,PRIMARY KEY (`KoppelingID`),UNIQUE KEY `uq_activa_bron` (`ActivaID`,`BronType`,`BronID`),KEY `idx_bron` (`BronType`,`BronID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); }
function documentAssets(PDO $pdo): array { return $pdo->query("SELECT ActivaID,ParentActivaID,COALESCE(NULLIF(Naam,''),CONCAT('Activum #',ActivaID)) Naam FROM Activa ORDER BY Naam,ActivaID")->fetchAll(PDO::FETCH_ASSOC); }
function documentIndexReason(string $status, ?string $error): string {
  $error=mb_strtolower((string)$error);
  if($status==='ocr-nodig')return 'Geen selecteerbare tekst gevonden; OCR is nodig.';
  if($status==='overgeslagen')return 'Automatische verwerking is overgeslagen vanwege de ingestelde veiligheidslimiet.';
  if($status==='niet-geindexeerd')return 'Dit document is nog niet door de zoekindex verwerkt.';
  if(str_contains($error,'zip-ondersteuning')||str_contains($error,'ziparchive'))return 'De vereiste PHP ZIP-ondersteuning ontbreekt op de server.';
  if(str_contains($error,'password')||str_contains($error,'encrypt')||str_contains($error,'secured'))return 'Het PDF-bestand is waarschijnlijk met een wachtwoord beveiligd of versleuteld.';
  if(str_contains($error,'xref')||str_contains($error,'object')||str_contains($error,'reference')||str_contains($error,'trailer'))return 'De PDF heeft een afwijkende of beschadigde interne structuur die de tekstparser niet kan lezen.';
  if(str_contains($error,'memory')||str_contains($error,'size')||str_contains($error,'large'))return 'Het document overschrijdt een limiet voor veilige automatische verwerking.';
  return $status==='mislukt'?'De tekstparser kon dit document niet betrouwbaar verwerken.':'Automatische tekstextractie is voor dit document niet beschikbaar.';
}
function folderSegment(string $name): string {
  $name=trim($name);
  if($name===''||$name==='.'||$name==='..'||mb_strlen($name)>150||preg_match('/[\\x00-\\x1f\\x7f\\\\\/]/u',$name))throw new SafeInputException('Ongeldige mapnaam.');
  return $name;
}
function ensureFolderPath(PDO $pdo,int $parentId,string $relative,array $writer): int {
  $check=$pdo->prepare('SELECT MapID,Pad FROM DocumentMappen WHERE MapID=? AND Actief=1');$check->execute([$parentId]);$parent=$check->fetch();if(!$parent)throw new SafeInputException('Doelmap bestaat niet.');
  $parts=array_values(array_filter(explode('/',str_replace('\\','/',$relative)),static fn($p)=>trim($p)!==''));
  if(count($parts)>20)throw new SafeInputException('De mappenstructuur is te diep (maximaal 20 niveaus).');
  $id=(int)$parent['MapID'];$path=(string)$parent['Pad'];
  foreach($parts as $part){$part=folderSegment($part);$path.='/'.$part;if(mb_strlen($path)>700)throw new SafeInputException('Het volledige mappad is te lang.');$s=$pdo->prepare('SELECT MapID FROM DocumentMappen WHERE Pad=? AND Actief=1');$s->execute([$path]);$found=$s->fetchColumn();if($found){$id=(int)$found;continue;}$i=$pdo->prepare('INSERT INTO DocumentMappen (ParentMapID,Naam,Pad,Sorteervolgorde,Actief,AangemaaktOp,AangemaaktDoor) VALUES (?,?,?,0,1,?,?)');try{$i->execute([$id,$part,$path,date('Y-m-d H:i:s'),$writer['username']]);$id=(int)$pdo->lastInsertId();}catch(PDOException $e){$s->execute([$path]);$found=$s->fetchColumn();if(!$found)throw $e;$id=(int)$found;}}
  return $id;
}
function libraryMime(string $ext,string $mime): bool {
  $m=[
    'pdf'=>['application/pdf'],'doc'=>['application/msword','application/x-ole-storage'],'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip'],'dotx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.template','application/zip','application/x-zip','application/x-zip-compressed','application/octet-stream'],
    'xls'=>['application/vnd.ms-excel','application/x-ole-storage'],'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip'],
    'csv'=>['text/plain','text/csv','application/csv'],'txt'=>['text/plain'],'md'=>['text/plain','text/markdown'],'rtf'=>['application/rtf','text/rtf','text/plain','application/octet-stream'],
    'xml'=>['application/xml','text/xml','text/plain'],'html'=>['text/html','application/xhtml+xml','text/plain'],'htm'=>['text/html','application/xhtml+xml','text/plain'],
    'odt'=>['application/vnd.oasis.opendocument.text','application/zip'],'ods'=>['application/vnd.oasis.opendocument.spreadsheet','application/zip'],'odp'=>['application/vnd.oasis.opendocument.presentation','application/zip'],'odg'=>['application/vnd.oasis.opendocument.graphics','application/zip'],
    'ppt'=>['application/vnd.ms-powerpoint','application/x-ole-storage'],'pptx'=>['application/vnd.openxmlformats-officedocument.presentationml.presentation','application/zip'],
    'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png'],'gif'=>['image/gif'],'webp'=>['image/webp'],'tif'=>['image/tiff'],'tiff'=>['image/tiff'],
    'heic'=>['image/heic','image/heif','image/heic-sequence','image/heif-sequence'],'heif'=>['image/heif','image/heic','image/heif-sequence','image/heic-sequence'],
    'mp3'=>['audio/mpeg'],'m4a'=>['audio/mp4','audio/x-m4a'],'aac'=>['audio/aac'],'wav'=>['audio/wav','audio/x-wav'],'ogg'=>['audio/ogg'],
    'mp4'=>['video/mp4'],'webm'=>['video/webm'],'mov'=>['video/quicktime'],
    // Outlook .msg is an OLE-container. STRATO/fileinfo reports these files
    // commonly as application/vnd.ms-office, but other legitimate reports
    // occur on different PHP/fileinfo versions.
    'msg'=>['application/vnd.ms-office','application/vnd.ms-outlook','application/x-ole-storage','application/octet-stream'],
    'eml'=>['message/rfc822','application/eml','text/eml','text/plain'],
    'accdb'=>['application/x-msaccess','application/msaccess','application/vnd.ms-access','application/octet-stream'],
    'zip'=>['application/zip','application/x-zip-compressed','application/x-zip','application/octet-stream'],
    'pages'=>['application/zip','application/vnd.apple.pages','application/x-iwork-pages-sffpages'],'numbers'=>['application/zip','application/vnd.apple.numbers','application/x-iwork-numbers-sffnumbers'],'key'=>['application/zip','application/vnd.apple.keynote','application/x-iwork-keynote-sffkey']
  ]; return isset($m[$ext])&&in_array($mime,$m[$ext],true);
}

try {
  $u=requireLogin(); $pdo=documentDb();  $action=(string)($_GET['action']??'list');
  if($action==='source_links'){
    $type=(string)($_GET['type']??'document');$id=(int)($_GET['id']??0);if(!in_array($type,['document','email'],true)||$id<1)throw new SafeInputException('Ongeldige bron.');
    if($_SERVER['REQUEST_METHOD']==='POST'){
      verifyCsrf();$w=documentWriter();$raw=json_decode((string)file_get_contents('php://input'),true);$assetIds=array_values(array_unique(array_filter(array_map('intval',(array)($raw['asset_ids']??[])),static fn($v)=>$v>0)));if(count($assetIds)>100)throw new SafeInputException('Kies maximaal 100 activa.');
      $title=trim((string)($raw['title']??''));$valid=[];if($assetIds){$marks=implode(',',array_fill(0,count($assetIds),'?'));$q=$pdo->prepare('SELECT ActivaID FROM Activa WHERE ActivaID IN ('.$marks.')');$q->execute($assetIds);$valid=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));if(count($valid)!==count($assetIds))throw new SafeInputException('Een gekozen activum bestaat niet meer.');}
      $pdo->beginTransaction();try{$pdo->prepare('DELETE FROM ActivaBronKoppelingen WHERE BronType=? AND BronID=?')->execute([$type,$id]);$ins=$pdo->prepare('INSERT INTO ActivaBronKoppelingen (ActivaID,BronType,BronID,Titel,AangemaaktOp,AangemaaktDoor) VALUES (?,?,?,?,?,?)');foreach($valid as $aid)$ins->execute([$aid,$type,$id,mb_substr($title,0,255),date('Y-m-d H:i:s'),$w['username']]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}recordActivity($pdo,$w,'bron-activa-gekoppeld','documenten',$type,(string)$id,count($valid).' activum/activa');jout(['ok'=>true,'asset_ids'=>$valid]);
    }
    $s=$pdo->prepare('SELECT ActivaID FROM ActivaBronKoppelingen WHERE BronType=? AND BronID=? ORDER BY ActivaID');$s->execute([$type,$id]);jout(['ok'=>true,'assets'=>documentAssets($pdo),'asset_ids'=>array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN)),'csrf'=>csrfToken()]);
  }
  if($action==='organize_document'){
    $id=(int)($_GET['id']??0);if($id<1)throw new SafeInputException('Ongeldig document.');
    if($_SERVER['REQUEST_METHOD']==='POST'){
      verifyCsrf();$w=documentWriter();$raw=json_decode((string)file_get_contents('php://input'),true);$map=(int)($raw['map_id']??0);if($map<1)throw new SafeInputException('Kies een doelmap.');$m=$pdo->prepare('SELECT Pad FROM DocumentMappen WHERE MapID=? AND Actief=1');$m->execute([$map]);$path=$m->fetchColumn();if(!$path)throw new SafeInputException('De doelmap bestaat niet.');$d=$pdo->prepare('UPDATE DocumentBibliotheek SET MapID=?,GewijzigdOp=?,GewijzigdDoor=? WHERE DocumentID=? AND IsHuidig=1 AND VerwijderdOp IS NULL');$d->execute([$map,date('Y-m-d H:i:s'),$w['username'],$id]);if(!$d->rowCount())throw new SafeInputException('Document kon niet worden verplaatst.');recordActivity($pdo,$w,'document-verplaatst','documenten','document',(string)$id,(string)$path);jout(['ok'=>true,'map_id'=>$map,'path'=>$path]);
    }
    $d=$pdo->prepare('SELECT DocumentID,MapID,Titel,OrigineleNaam FROM DocumentBibliotheek WHERE DocumentID=? AND IsHuidig=1 AND VerwijderdOp IS NULL');$d->execute([$id]);$document=$d->fetch(PDO::FETCH_ASSOC);if(!$document)throw new SafeInputException('Document niet gevonden.');jout(['ok'=>true,'document'=>$document,'csrf'=>csrfToken()]);
  }
  if($action==='folders'){$r=$pdo->query('SELECT MapID,ParentMapID,Naam,Pad FROM DocumentMappen WHERE Actief=1 ORDER BY Pad')->fetchAll();jout(['ok'=>true,'folders'=>$r,'canManageFolders'=>(string)($u['role']??'')==='admin','csrf'=>csrfToken()]);}
  if($action==='index_status'){
    documentAdmin();
    $summary=['gereed'=>0,'niet-ondersteund'=>0,'ocr-nodig'=>0,'overgeslagen'=>0,'mislukt'=>0,'niet-geindexeerd'=>0];
    $counts=$pdo->query('SELECT COALESCE(ti.Status,\'niet-geindexeerd\') Status,COUNT(*) Aantal FROM DocumentBibliotheek d LEFT JOIN DocumentTekstIndex ti ON ti.DocumentID=d.DocumentID WHERE d.IsHuidig=1 AND d.VerwijderdOp IS NULL GROUP BY COALESCE(ti.Status,\'niet-geindexeerd\')')->fetchAll();
    foreach($counts as $row)$summary[(string)$row['Status']]=(int)$row['Aantal'];
    $issues=$pdo->query('SELECT d.DocumentID,d.OrigineleNaam,m.Pad MapNaam,COALESCE(ti.Status,\'niet-geindexeerd\') Status,ti.Foutmelding FROM DocumentBibliotheek d JOIN DocumentMappen m ON m.MapID=d.MapID LEFT JOIN DocumentTekstIndex ti ON ti.DocumentID=d.DocumentID WHERE d.IsHuidig=1 AND d.VerwijderdOp IS NULL AND (ti.DocumentID IS NULL OR ti.Status NOT IN (\'gereed\',\'niet-ondersteund\')) ORDER BY COALESCE(ti.Status,\'niet-geindexeerd\'),m.Pad,d.OrigineleNaam LIMIT 100')->fetchAll();
    foreach($issues as &$issue){$rawError=(string)($issue['Foutmelding']??'');$lowerError=mb_strtolower($rawError);$permanentPdfFailure=str_contains($lowerError,'password')||str_contains($lowerError,'encrypt')||str_contains($lowerError,'secured');$issue['Reden']=documentIndexReason((string)$issue['Status'],$rawError);$issue['OpnieuwProberen']=(string)$issue['Status']==='mislukt'&&!$permanentPdfFailure;unset($issue['Foutmelding']);}unset($issue);
    jout(['ok'=>true,'summary'=>$summary,'issues'=>$issues,'csrf'=>csrfToken()]);
  }
  if($action==='reindex_one'){
    verifyCsrf();documentAdmin();$id=(int)($_GET['id']??0);if($id<1)throw new SafeInputException('Ongeldig document.');
    $s=$pdo->prepare('SELECT DocumentID,OrigineleNaam,Bestandspad FROM DocumentBibliotheek WHERE DocumentID=? AND IsHuidig=1 AND VerwijderdOp IS NULL');$s->execute([$id]);$row=$s->fetch();if(!$row)throw new SafeInputException('Huidig document niet gevonden.');
    $status=indexDocumentText($pdo,$id,resolveStoredFile((string)$row['Bestandspad']),(string)$row['OrigineleNaam']);jout(['ok'=>true,'status'=>$status]);
  }
  if($action==='create_folder'){verifyCsrf();$w=documentAdmin();$parent=(int)($_POST['parent']??0);$name=folderSegment((string)($_POST['name']??''));if($parent>0){$id=ensureFolderPath($pdo,$parent,$name,$w);}else{$s=$pdo->prepare('SELECT MapID FROM DocumentMappen WHERE Pad=? AND Actief=1');$s->execute([$name]);if($s->fetchColumn())throw new SafeInputException('Deze hoofdmap bestaat al.');$i=$pdo->prepare('INSERT INTO DocumentMappen (ParentMapID,Naam,Pad,Sorteervolgorde,Actief,AangemaaktOp,AangemaaktDoor) VALUES (NULL,?,?,0,1,?,?)');$i->execute([$name,$name,date('Y-m-d H:i:s'),$w['username']]);$id=(int)$pdo->lastInsertId();}recordActivity($pdo,$w,'map-aangemaakt','documenten','map',(string)$id,$name);jout(['ok'=>true,'id'=>$id]);}
  if($action==='delete_folder'){
    verifyCsrf();documentAdmin();$map=(int)($_POST['map']??0);if($map<1)throw new SafeInputException('Kies eerst een map die u wilt wissen.');
    $s=$pdo->prepare('SELECT Naam,Pad FROM DocumentMappen WHERE MapID=? AND Actief=1');$s->execute([$map]);$folder=$s->fetch();if(!$folder)throw new SafeInputException('Map bestaat niet of is al gewist.');
    $c=$pdo->prepare('SELECT COUNT(*) FROM DocumentMappen WHERE ParentMapID=? AND Actief=1');$c->execute([$map]);$children=(int)$c->fetchColumn();
    $d=$pdo->prepare('SELECT COUNT(*) FROM DocumentBibliotheek WHERE MapID=? AND IsHuidig=1 AND VerwijderdOp IS NULL');$d->execute([$map]);$documents=(int)$d->fetchColumn();
    if($documents>0||$children>0){$parts=[];if($documents>0)$parts[]=$documents.' bestand'.($documents===1?'':'en');if($children>0)$parts[]=$children.' submap'.($children===1?'':'pen');$message='Deze map is niet leeg: '.implode(' en ',$parts).'. Verwijder de bestanden eerst afzonderlijk en wis submappen van onder naar boven.';if(($_POST['check']??'')==='1')jout(['ok'=>true,'deletable'=>false,'message'=>$message]);throw new SafeInputException($message);}
    if(($_POST['check']??'')==='1')jout(['ok'=>true,'deletable'=>true,'path'=>$folder['Pad']]);
    $u=$pdo->prepare('UPDATE DocumentMappen SET Actief=0 WHERE MapID=? AND Actief=1');$u->execute([$map]);if(!$u->rowCount())throw new SafeInputException('Map kon niet worden gewist.');recordActivity($pdo,currentUser()??[],'map-gewist','documenten','map',(string)$map,(string)$folder['Pad']);jout(['ok'=>true,'path'=>$folder['Pad']]);
  }
  if($action==='list'){
    $map=(int)($_GET['map']??0);$q=trim((string)($_GET['q']??''));$trash=($_GET['trash']??'0')==='1';
    $sql='SELECT SQL_CALC_FOUND_ROWS d.DocumentID,d.VersieGroepID,d.Versienummer,d.IsHuidig,d.MapID,d.Titel,d.OrigineleNaam,d.Mimetype,d.Bestandsgrootte,d.Sha256,d.Tags,d.Documentdatum,d.Bron,d.Bronpad,d.ExterneUrl,d.Omschrijving,d.AangemaaktOp,d.AangemaaktDoor,d.GewijzigdOp,d.GewijzigdDoor,d.VerwijderdOp,m.Pad MapNaam FROM DocumentBibliotheek d JOIN DocumentMappen m ON m.MapID=d.MapID WHERE d.IsHuidig=1 AND '.($trash?'d.VerwijderdOp IS NOT NULL':'d.VerwijderdOp IS NULL');$a=[];
    if($map>0){$p=$pdo->prepare('SELECT Pad FROM DocumentMappen WHERE MapID=? AND Actief=1');$p->execute([$map]);$folderPath=$p->fetchColumn();if(!$folderPath)throw new SafeInputException('Map bestaat niet.');$sql.=' AND (m.Pad=? OR m.Pad LIKE ?)';$a[]=$folderPath;$a[]=$folderPath.'/%';}
    if($q!==''){$sql.=' AND (d.Titel LIKE ? OR d.OrigineleNaam LIKE ? OR d.Tags LIKE ? OR d.Omschrijving LIKE ? OR d.ExterneUrl LIKE ? OR EXISTS (SELECT 1 FROM DocumentTekstIndex ti WHERE ti.DocumentID=d.DocumentID AND ti.Status=\'gereed\' AND ti.Tekst LIKE ?))';$like='%'.mb_substr($q,0,150).'%';array_push($a,$like,$like,$like,$like,$like,$like);}
    $sql.=' ORDER BY d.GewijzigdOp DESC LIMIT 250';$s=$pdo->prepare($sql);$s->execute($a);$documents=$s->fetchAll();$found=(int)$pdo->query('SELECT FOUND_ROWS()')->fetchColumn();$total=(int)$pdo->query('SELECT COUNT(*) FROM DocumentBibliotheek WHERE IsHuidig=1 AND VerwijderdOp IS NULL')->fetchColumn();
    if($q!==''&&$documents){$ids=array_map(static fn($row)=>(int)$row['DocumentID'],$documents);$marks=implode(',',array_fill(0,count($ids),'?'));$hit=$pdo->prepare('SELECT ti.DocumentID,MAX(CASE WHEN ti.Status=\'gereed\' AND ti.Tekst LIKE ? THEN 1 ELSE 0 END) InhoudTreffer,MAX(SUBSTRING(ti.Tekst,GREATEST(1,LOCATE(?,ti.Tekst)-120),320)) TrefferFragment,GROUP_CONCAT(DISTINCT CASE WHEN p.Tekst LIKE ? THEN p.Paginanummer END ORDER BY p.Paginanummer SEPARATOR \',\') TrefferPaginas FROM DocumentTekstIndex ti LEFT JOIN DocumentTekstPagina p ON p.DocumentID=ti.DocumentID WHERE ti.DocumentID IN ('.$marks.') GROUP BY ti.DocumentID');$hit->execute(array_merge([$like,mb_substr($q,0,150),$like],$ids));$hits=[];foreach($hit->fetchAll() as $row)$hits[(int)$row['DocumentID']]=$row;foreach($documents as &$document){$match=$hits[(int)$document['DocumentID']]??null;$document['InhoudTreffer']=$match?(int)$match['InhoudTreffer']:0;$document['TrefferPaginas']=$match?(string)($match['TrefferPaginas']??''):'';$fragment=$match?(string)($match['TrefferFragment']??''):'';$fragment=preg_replace('/\s+/u',' ',trim($fragment))??'';$document['TrefferFragment']=mb_substr($fragment,0,320);}unset($document);}
    jout(['ok'=>true,'documents'=>$documents,'found'=>$found,'total'=>$total,'csrf'=>csrfToken()]);
  }
  if($action==='add_link'){
    verifyCsrf();$w=documentWriter();$map=(int)($_GET['map']??0);
    if($map<1)throw new SafeInputException('Kies eerst een map.');
    $check=$pdo->prepare('SELECT 1 FROM DocumentMappen WHERE MapID=? AND Actief=1');$check->execute([$map]);if(!$check->fetchColumn())throw new SafeInputException('Map bestaat niet.');
    $title=trim((string)($_POST['title']??''));$url=trim((string)($_POST['url']??''));$description=trim((string)($_POST['description']??''));$tags=trim((string)($_POST['tags']??''));
    if($title===''||mb_strlen($title)>255)throw new SafeInputException('Vul een titel van maximaal 255 tekens in.');
    if($url===''||mb_strlen($url)>2048||filter_var($url,FILTER_VALIDATE_URL)===false)throw new SafeInputException('Vul een geldige HTTPS-link in.');
    $parts=parse_url($url);if(mb_strtolower((string)($parts['scheme']??''))!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))throw new SafeInputException('Alleen een HTTPS-link zonder gebruikersnaam of wachtwoord is toegestaan.');
    if(mb_strlen($description)>5000)throw new SafeInputException('De omschrijving mag maximaal 5000 tekens bevatten.');
    if(mb_strlen($tags)>1000)throw new SafeInputException('De tags mogen maximaal 1000 tekens bevatten.');
    $now=date('Y-m-d H:i:s');$displayName='Extern bestand · '.(string)$parts['host'];$hash=hash('sha256',$url);
    $s=$pdo->prepare('INSERT INTO DocumentBibliotheek (VersieGroepID,Versienummer,IsHuidig,MapID,Titel,OrigineleNaam,Bestandspad,Mimetype,Bestandsgrootte,Sha256,Tags,Bron,Bronpad,ExterneUrl,Omschrijving,AangemaaktOp,AangemaaktDoor,GewijzigdOp,GewijzigdDoor) VALUES (0,1,1,?,?,?,\'\',\'text/uri-list\',0,?,?,\'externe-link\',?,?,?,?,?,?,?)');
    $s->execute([$map,mb_substr($title,0,255),$displayName,$hash,mb_substr($tags,0,1000),$url,$url,$description,$now,$w['username'],$now,$w['username']]);
    $id=(int)$pdo->lastInsertId();$pdo->prepare('UPDATE DocumentBibliotheek SET VersieGroepID=? WHERE DocumentID=?')->execute([$id,$id]);
    $i=$pdo->prepare('INSERT INTO DocumentTekstIndex (DocumentID,Status,ExtractorVersie,Tekst,Foutmelding,BijgewerktOp) VALUES (?,\'niet-ondersteund\',5,NULL,\'Externe link; alleen metadata wordt geïndexeerd.\',?)');$i->execute([$id,$now]);
    recordActivity($pdo,$w,'externe-link-toegevoegd','documenten','document',(string)$id,$title.' · '.$url);jout(['ok'=>true,'id'=>$id]);
  }
  if($action==='upload'){
    $uploadStage='toegang-en-uploadontvangst';
    verifyCsrf();$w=documentWriter();$map=(int)($_GET['map']??0);
    if($map<1)throw new SafeInputException('Kies een map.');
    $f=$_FILES['file']??null;if(!$f||is_array($f['name']??null))throw new SafeInputException('Kies één bestand.');
    $uploadError=(int)$f['error'];
    if($uploadError!==UPLOAD_ERR_OK){
      if($uploadError===UPLOAD_ERR_INI_SIZE)throw new SafeInputException('Het bestand is groter dan de uploadlimiet van de server (upload_max_filesize: '.ini_get('upload_max_filesize').'; post_max_size: '.ini_get('post_max_size').'). Verhoog deze limieten bij STRATO of kies een kleiner bestand.');
      if($uploadError===UPLOAD_ERR_FORM_SIZE)throw new SafeInputException('Het bestand is groter dan de uploadlimiet van dit formulier.');
      if($uploadError===UPLOAD_ERR_PARTIAL)throw new SafeInputException('De upload is onderweg afgebroken. Probeer het bestand opnieuw te uploaden.');
      if($uploadError===UPLOAD_ERR_NO_FILE)throw new SafeInputException('Er is geen bestand ontvangen.');
      throw new SafeInputException('Upload mislukt (PHP-uploadcode '.$uploadError.').');
    }
    $uploadStage='bestandsgrootte-controleren';$size=(int)$f['size'];if($size<1||$size>250*1024*1024)throw new SafeInputException('Bestand is leeg of groter dan 250 MB.');
    $uploadStage='bestandstype-controleren';
    $orig=basename((string)$f['name']);$ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));$tmp=(string)$f['tmp_name'];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp)?:'application/octet-stream';
    if(!libraryMime($ext,$mime))throw new SafeInputException('Bestandstype .'.$ext.' met inhoudstype '.$mime.' is niet toegestaan.');
    $uploadStage='mappenstructuur-controleren';
    $relative=trim((string)($_POST['relativePath']??''));if($relative!==''){$relative=str_replace('\\','/',$relative);$directory=dirname($relative);if($directory!=='.'&&$directory!==''){$w=documentAdmin();$map=ensureFolderPath($pdo,$map,$directory,$w);}}
    $check=$pdo->prepare('SELECT 1 FROM DocumentMappen WHERE MapID=? AND Actief=1');$check->execute([$map]);if(!$check->fetchColumn())throw new SafeInputException('Map bestaat niet.');
    $uploadStage='controlegetal-berekenen';
    $hash=hash_file('sha256',$tmp);if(!is_string($hash))throw new RuntimeException('Controlegetal kon niet worden berekend.');
    $uploadStage='opslaglocatie-controleren';
    $name=bin2hex(random_bytes(24)).'.'.($ext==='jpeg'?'jpg':$ext);$dir=uploadDirectory('bibliotheek');$dest=$dir.'/'.$name;
    $title=trim((string)($_POST['title']??''));$tags=trim((string)($_POST['tags']??''));$now=date('Y-m-d H:i:s');
    $uploadStage='vorige-versie-query-voorbereiden';$insertedId=0;$oldDisabled=false;
    try{
      $previous=$pdo->prepare('SELECT DocumentID,VersieGroepID,Versienummer,Sha256,Titel,Tags FROM DocumentBibliotheek WHERE MapID=? AND OrigineleNaam=? AND IsHuidig=1 AND VerwijderdOp IS NULL ORDER BY DocumentID DESC LIMIT 1');
      $uploadStage='vorige-versie-query-uitvoeren';$previous->execute([$map,$orig]);$old=$previous->fetch();
      if($old&&hash_equals((string)$old['Sha256'],$hash))throw new SafeInputException('Dit bestand is identiek aan de huidige versie en is niet opnieuw opgeslagen.');
      if($title==='')$title=$old?(string)$old['Titel']:pathinfo($orig,PATHINFO_FILENAME);if($tags===''&&$old)$tags=(string)($old['Tags']??'');
      $uploadStage='bestand-opslag';if(!move_uploaded_file($tmp,$dest))throw new RuntimeException('Bestand kon niet worden opgeslagen.');
      $group=$old?(int)($old['VersieGroepID']?:$old['DocumentID']):0;$version=$old?((int)$old['Versienummer']+1):1;
      $uploadStage='record-invoegen';$s=$pdo->prepare('INSERT INTO DocumentBibliotheek (VersieGroepID,Versienummer,IsHuidig,MapID,Titel,OrigineleNaam,Bestandspad,Mimetype,Bestandsgrootte,Sha256,Tags,Bron,AangemaaktOp,AangemaaktDoor,GewijzigdOp,GewijzigdDoor) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
      $s->execute([$group,$version,$old?0:1,$map,mb_substr($title,0,255),$orig,storedPath('bibliotheek',$name),$mime,$size,$hash,mb_substr($tags,0,1000),'handmatig',$now,$w['username'],$now,$w['username']]);
      $id=(int)$pdo->lastInsertId();$insertedId=$id;
      if(!$old){$uploadStage='versiegroep-koppelen';$g=$pdo->prepare('UPDATE DocumentBibliotheek SET VersieGroepID=? WHERE DocumentID=?');$g->execute([$id,$id]);}
      if($old){$uploadStage='vorige-versie-afsluiten';$off=$pdo->prepare('UPDATE DocumentBibliotheek SET IsHuidig=0 WHERE DocumentID=?');$off->execute([(int)$old['DocumentID']]);$oldDisabled=true;$uploadStage='nieuwe-versie-activeren';$on=$pdo->prepare('UPDATE DocumentBibliotheek SET IsHuidig=1 WHERE DocumentID=?');$on->execute([$id]);}
      $uploadStage='tekst-indexeren';$indexStatus=indexDocumentText($pdo,$id,$dest,$orig);
      $uploadStage='afronden';recordActivity($pdo,$w,$old?'document-versie-toegevoegd':'document-geupload','documenten','document',(string)$id,$orig.' · versie '.$version);jout(['ok'=>true,'id'=>$id,'version'=>$version,'map'=>$map,'indexStatus'=>$indexStatus]);
    }catch(Throwable $e){if($insertedId>0){try{$pdo->prepare('DELETE FROM DocumentBibliotheek WHERE DocumentID=?')->execute([$insertedId]);}catch(Throwable $ignored){}}if($oldDisabled&&$old){try{$pdo->prepare('UPDATE DocumentBibliotheek SET IsHuidig=1 WHERE DocumentID=?')->execute([(int)$old['DocumentID']]);}catch(Throwable $ignored){}}if(is_file($dest))@unlink($dest);throw $e;}
  }
  if($action==='versions'){$id=(int)($_GET['id']??0);if($id<1)throw new SafeInputException('Ongeldig document.');$g=$pdo->prepare('SELECT VersieGroepID FROM DocumentBibliotheek WHERE DocumentID=?');$g->execute([$id]);$group=(int)$g->fetchColumn();if($group<1)throw new SafeInputException('Document niet gevonden.');$s=$pdo->prepare('SELECT DocumentID,Versienummer,IsHuidig,Titel,OrigineleNaam,Bestandsgrootte,Sha256,Bron,ExterneUrl,AangemaaktOp,AangemaaktDoor,GewijzigdOp,GewijzigdDoor FROM DocumentBibliotheek WHERE VersieGroepID=? ORDER BY Versienummer DESC,DocumentID DESC');$s->execute([$group]);jout(['ok'=>true,'versions'=>$s->fetchAll(),'csrf'=>csrfToken()]);}
  if($action==='reindex'){$uploadStage='herindexeren';$w=documentAdmin();verifyCsrf();$batchSize=5;$s=$pdo->query('SELECT d.DocumentID,d.OrigineleNaam,d.Bestandspad FROM DocumentBibliotheek d LEFT JOIN DocumentTekstIndex ti ON ti.DocumentID=d.DocumentID WHERE d.IsHuidig=1 AND d.VerwijderdOp IS NULL AND (ti.DocumentID IS NULL OR ti.ExtractorVersie<6) ORDER BY d.DocumentID LIMIT '.$batchSize);$rows=$s->fetchAll();$counts=['gereed'=>0,'niet-ondersteund'=>0,'mislukt'=>0,'ocr-nodig'=>0,'overgeslagen'=>0];$mark=$pdo->prepare('INSERT INTO DocumentTekstIndex (DocumentID,Status,ExtractorVersie,Foutmelding,BijgewerktOp) VALUES (?,\'mislukt\',6,\'Indexering werd door de server afgebroken; probeer dit document afzonderlijk opnieuw.\',?) ON DUPLICATE KEY UPDATE Status=VALUES(Status),ExtractorVersie=6,Foutmelding=VALUES(Foutmelding),BijgewerktOp=VALUES(BijgewerktOp)');foreach($rows as $row){$mark->execute([(int)$row['DocumentID'],date('Y-m-d H:i:s')]);try{$path=resolveStoredFile((string)$row['Bestandspad']);$status=indexDocumentText($pdo,(int)$row['DocumentID'],$path,(string)$row['OrigineleNaam']);}catch(Throwable $e){$status='mislukt';$f=$pdo->prepare('UPDATE DocumentTekstIndex SET Status=\'mislukt\',ExtractorVersie=6,Foutmelding=?,BijgewerktOp=? WHERE DocumentID=?');$f->execute([mb_substr($e->getMessage(),0,500),date('Y-m-d H:i:s'),(int)$row['DocumentID']]);}$counts[$status]++;}recordActivity($pdo,$w,'zoekindex-bijgewerkt','documenten','zoekindex','',count($rows).' documenten verwerkt.');jout(['ok'=>true,'processed'=>count($rows),'remaining'=>count($rows)===$batchSize,'counts'=>$counts]);}
  if($action==='restore_version'){verifyCsrf();$w=documentWriter();$id=(int)($_GET['id']??0);if($id<1)throw new SafeInputException('Ongeldige versie.');$insertedId=0;$currentDisabled=false;$s=$pdo->prepare('SELECT * FROM DocumentBibliotheek WHERE DocumentID=?');$s->execute([$id]);$source=$s->fetch();if(!$source)throw new SafeInputException('Versie niet gevonden.');$group=(int)$source['VersieGroepID'];$c=$pdo->prepare('SELECT DocumentID,Versienummer FROM DocumentBibliotheek WHERE VersieGroepID=? AND IsHuidig=1');$c->execute([$group]);$current=$c->fetch();if(!$current)throw new SafeInputException('Huidige versie niet gevonden.');if((int)$current['DocumentID']===$id)throw new SafeInputException('Dit is al de huidige versie.');try{$version=(int)$current['Versienummer']+1;$now=date('Y-m-d H:i:s');$i=$pdo->prepare('INSERT INTO DocumentBibliotheek (VersieGroepID,Versienummer,IsHuidig,MapID,Titel,OrigineleNaam,Bestandspad,Mimetype,Bestandsgrootte,Sha256,Tags,Documentdatum,Bron,Bronpad,AangemaaktOp,AangemaaktDoor,GewijzigdOp,GewijzigdDoor) VALUES (?,?,0,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$i->execute([$group,$version,$source['MapID'],$source['Titel'],$source['OrigineleNaam'],$source['Bestandspad'],$source['Mimetype'],$source['Bestandsgrootte'],$source['Sha256'],$source['Tags'],$source['Documentdatum'],'herstel-versie',$source['Bronpad'],$now,$w['username'],$now,$w['username']]);$insertedId=(int)$pdo->lastInsertId();$off=$pdo->prepare('UPDATE DocumentBibliotheek SET IsHuidig=0 WHERE DocumentID=?');$off->execute([(int)$current['DocumentID']]);$currentDisabled=true;$pdo->prepare('UPDATE DocumentBibliotheek SET IsHuidig=1 WHERE DocumentID=?')->execute([$insertedId]);indexDocumentText($pdo,$insertedId,resolveStoredFile((string)$source['Bestandspad']),(string)$source['OrigineleNaam']);recordActivity($pdo,$w,'document-versie-hersteld','documenten','document',(string)$insertedId,(string)$source['OrigineleNaam'].' · versie '.$version);jout(['ok'=>true,'id'=>$insertedId,'version'=>$version]);}catch(Throwable $e){if($insertedId>0){try{$pdo->prepare('DELETE FROM DocumentBibliotheek WHERE DocumentID=?')->execute([$insertedId]);}catch(Throwable $ignored){}}if($currentDisabled){try{$pdo->prepare('UPDATE DocumentBibliotheek SET IsHuidig=1 WHERE DocumentID=?')->execute([(int)$current['DocumentID']]);}catch(Throwable $ignored){}}throw $e;}}
  if($action==='trash'||$action==='restore'){verifyCsrf();$w=documentWriter();$id=(int)($_GET['id']??0);if($id<1)throw new SafeInputException('Ongeldig document.');$n=$pdo->prepare('SELECT COALESCE(NULLIF(Titel,\'\'),OrigineleNaam) FROM DocumentBibliotheek WHERE DocumentID=?');$n->execute([$id]);$documentName=(string)($n->fetchColumn()?:'Document');$sql=$action==='trash'?'UPDATE DocumentBibliotheek SET VerwijderdOp=?,VerwijderdDoor=?,GewijzigdOp=?,GewijzigdDoor=? WHERE DocumentID=? AND VerwijderdOp IS NULL':'UPDATE DocumentBibliotheek SET VerwijderdOp=NULL,VerwijderdDoor=NULL,GewijzigdOp=?,GewijzigdDoor=? WHERE DocumentID=? AND VerwijderdOp IS NOT NULL';$s=$pdo->prepare($sql);$now=date('Y-m-d H:i:s');$s->execute($action==='trash'?[$now,$w['username'],$now,$w['username'],$id]:[$now,$w['username'],$id]);if(!$s->rowCount())throw new SafeInputException('Documentstatus kon niet worden gewijzigd.');recordActivity($pdo,$w,$action==='trash'?'document-naar-prullenbak':'document-hersteld','documenten','document',(string)$id,$documentName);jout(['ok'=>true]);}
  throw new SafeInputException('Onbekende actie.');
} catch(SafeInputException $e){jout(['ok'=>false,'error'=>$e->getMessage()],400);} catch(Throwable $e){$id=securityIncident($e);$stage=isset($uploadStage)?' (fase '.$uploadStage.')':'';jout(['ok'=>false,'error'=>'De aanvraag is mislukt'.$stage.'. Foutreferentie: '.$id],500);}
