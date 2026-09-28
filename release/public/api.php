<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/mail_credentials.php';
require_once __DIR__ . '/maintenance_mjop.php';
require_once __DIR__ . '/lookup_catalogs.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $user = requireLogin();
    $config = loadConfigCompat(tenantPrivateFile('config.php'));
    $pdo = db($config);
    $entity = (string)($_GET['entity'] ?? '');
    $action = (string)($_GET['action'] ?? 'meta');
    $map = discover($pdo);
    repairZeroContactId($pdo,$map,(string)($user['username']??'system'));

    if ($action === 'session') out(['ok'=>true,'user'=>$user,'csrf'=>csrfToken()]);
    if ($action === 'health') {
        $dbName=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        out(['ok'=>true,'database'=>$dbName,'server_time'=>date('Y-m-d H:i:s'),'php'=>PHP_VERSION,'user'=>$user['username'] ?? '']);
    }
    if ($action === 'meta') {
        $info=[];
        foreach ($map as $e=>$table) $info[$e]=['table'=>$table,'primary'=>primaryKey($pdo,$table,$e),'columns'=>columns($pdo,$table),'count'=>countRows($pdo,$table)];
        $statusOptions=[];
        foreach($map as $e=>$table){
            $cols=$info[$e]['columns']; $statusCol=actualCol($cols,'Status');
            if($statusCol){
                $sqlStatus="SELECT DISTINCT TRIM(CAST(".qi($statusCol)." AS CHAR)) AS v FROM ".qi($table)." WHERE ".qi($statusCol)." IS NOT NULL AND TRIM(CAST(".qi($statusCol)." AS CHAR))<>'' ORDER BY v"; $st=$pdo->query($sqlStatus)->fetchAll(PDO::FETCH_COLUMN);
                $statusOptions[$e]=array_map(fn($v)=>['value'=>$v,'label'=>$v],$st);
            }else $statusOptions[$e]=[];
        }
        $loadedLookups=loadLookups($pdo);
        foreach($statusOptions as $group=>$options){
            $configured=(array)($loadedLookups[$group]['Status']??[]);
            $merged=[];$seen=[];
            foreach(array_merge(array_map(static fn($x)=>(string)($x['value']??''),$options),array_map('strval',array_values($configured))) as $value){
                $value=trim((string)$value);if($value===''||isset($seen[$value]))continue;$seen[$value]=true;$merged[]=['value'=>$value,'label'=>$value];
            }
            $statusOptions[$group]=$merged;
        }
        if(isset($map['maintenance_plans'])){
            $statusOptions['maintenance_plans']=array_map(fn($v)=>['value'=>$v,'label'=>$v],(array)($loadedLookups['maintenance_plans']['Status']??[]));
        }
        out(['ok'=>true,'entities'=>$info,'lookups'=>$loadedLookups,'required'=>requiredFields($pdo,$map),'statusOptions'=>$statusOptions,'maintenanceOptions'=>maintenanceOptions($pdo,$map),'user'=>$user,'csrf'=>csrfToken()]);
    }
    if($action==='contact_picker'){
        requireAdmin();
        if(!isset($map['contacts'],$map['addresses']))throw new RuntimeException('Contacten of adressen zijn niet beschikbaar.');
        $contactTable=$map['contacts'];$contactPk=primaryKey($pdo,$contactTable,'contacts');$rows=listRows($pdo,$contactTable,'','__all__');
        $addressTable=$map['addresses'];$addressCols=columns($pdo,$addressTable);$contactRef=findCol($addressCols,['ContactID','contact_id']);$residentRef=findCol($addressCols,['Bewoner_ContactID','bewoner_contact_id']);
        $addressFields=[];foreach(['Straat','Huisnummer','Postcode','Woonplaats','Hal','Etage'] as $candidate){$actual=actualCol($addressCols,$candidate);if($actual)$addressFields[]=$actual;}
        $addresses=$pdo->query('SELECT * FROM '.qi($addressTable))->fetchAll(PDO::FETCH_ASSOC);$byContact=[];
        foreach($addresses as $address){$parts=[];foreach($addressFields as $field){$value=trim((string)($address[$field]??''));if($value!=='')$parts[]=$value;}$label=implode(', ',$parts);if($label==='')continue;foreach(array_filter([$contactRef?$address[$contactRef]??null:null,$residentRef?$address[$residentRef]??null:null],static fn($v)=>$v!==null&&$v!==''&&$v!=='0') as $id){$key=(string)$id;$byContact[$key][]=$label;}}
        foreach($rows as &$row){$id=(string)($row[$contactPk]??'');$row['_picker_address']=implode(' · ',array_values(array_unique($byContact[$id]??[])));}unset($row);
        out(['ok'=>true,'rows'=>$rows]);
    }
    if($action==='asset_order'){
        if(!isset($map['Activa']))throw new RuntimeException('Activa zijn niet beschikbaar.');
        $pdo->exec("CREATE TABLE IF NOT EXISTS `VveguiActivaVolgorde` (`ActivaID` INT(10) UNSIGNED NOT NULL,`Volgorde` INT NOT NULL DEFAULT 0,PRIMARY KEY (`ActivaID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if($_SERVER['REQUEST_METHOD']==='POST'){
            requireAdmin();verifyCsrf();$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))$body=[];
            $parent=trim((string)($body['parent_id']??''));$ids=array_values(array_unique(array_filter(array_map('intval',(array)($body['ids']??[])),static fn($v)=>$v>0)));
            if($parent!==''&&!ctype_digit($parent))throw new RuntimeException('Ongeldig hoofdonderdeel.');
            $table=$map['Activa'];$pk=primaryKey($pdo,$table,'Activa');$cols=columns($pdo,$table);$parentCol=actualCol($cols,'ParentActivaID');if(!$parentCol)throw new RuntimeException('De activa-hiërarchie is niet beschikbaar.');
            if($parent!==''&&$parent!=='0'){$check=$pdo->prepare('SELECT COUNT(*) FROM '.qi($table).' WHERE '.qi($pk).'=?');$check->execute([$parent]);if((int)$check->fetchColumn()!==1)throw new RuntimeException('Hoofdonderdeel bestaat niet.');$q=$pdo->prepare('SELECT '.qi($pk).' FROM '.qi($table).' WHERE '.qi($parentCol).'=? ORDER BY '.qi($pk));$q->execute([$parent]);}
            else $q=$pdo->query('SELECT '.qi($pk).' FROM '.qi($table).' WHERE '.qi($parentCol).' IS NULL OR '.qi($parentCol).'=0 ORDER BY '.qi($pk));
            $siblings=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));sort($ids);$expected=$siblings;sort($expected);if($ids!==$expected)throw new RuntimeException('De lijst is intussen gewijzigd. Ververs de pagina en probeer opnieuw.');
            $pdo->beginTransaction();try{$up=$pdo->prepare('INSERT INTO `VveguiActivaVolgorde` (ActivaID,Volgorde) VALUES (?,?) ON DUPLICATE KEY UPDATE Volgorde=VALUES(Volgorde)');foreach((array)($body['ids']??[]) as $i=>$id)$up->execute([(int)$id,($i+1)*10]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
            $nameCol=actualCol($cols,'Naam');$labels=[];if($ids&&$nameCol){$marks=implode(',',array_fill(0,count($ids),'?'));$names=$pdo->prepare('SELECT '.qi($pk).' AS id,'.qi($nameCol).' AS label FROM '.qi($table).' WHERE '.qi($pk).' IN ('.$marks.')');$names->execute($ids);$byId=[];foreach($names->fetchAll(PDO::FETCH_ASSOC) as $item)$byId[(string)$item['id']]=(string)$item['label'];foreach($ids as $itemId)if(isset($byId[(string)$itemId]))$labels[]=$byId[(string)$itemId];}$groupLabel='het hoofdniveau';if($parent!==''&&$parent!=='0'&&$nameCol){$parentName=$pdo->prepare('SELECT '.qi($nameCol).' FROM '.qi($table).' WHERE '.qi($pk).'=?');$parentName->execute([$parent]);$groupLabel='“'.(string)$parentName->fetchColumn().'”';}$shown=array_slice($labels,0,12);$detail=$shown?implode(', ',$shown):count($ids).' activa';if(count($labels)>12)$detail.=' en '.(count($labels)-12).' overige activa';$description='De volgorde van de activa binnen '.$groupLabel.' is aangepast. Nieuwe volgorde: '.$detail.'.';recordActivity($pdo,$user,'activa-volgorde-gewijzigd','database','Activa',$parent?:'hoofdgroepen',$description);out(['ok'=>true]);
        }
        $table=$map['Activa'];$cols=columns($pdo,$table);$pk=primaryKey($pdo,$table,'Activa');$parentCol=actualCol($cols,'ParentActivaID');$nameCol=actualCol($cols,'Naam');
        if($parentCol){$select='SELECT '.qi($pk).' AS id,'.qi($parentCol).' AS parent'.($nameCol?','.qi($nameCol).' AS label':''). ' FROM '.qi($table).' ORDER BY '.qi($parentCol).','.($nameCol?qi($nameCol).',':'').qi($pk);$groups=[];foreach($pdo->query($select)->fetchAll(PDO::FETCH_ASSOC) as $row){$key=trim((string)($row['parent']??''));if($key===''||$key==='0')$key='0';$groups[$key][]=$row;}$get=$pdo->query('SELECT ActivaID,Volgorde FROM `VveguiActivaVolgorde`')->fetchAll(PDO::FETCH_KEY_PAIR);$up=$pdo->prepare('INSERT INTO `VveguiActivaVolgorde` (ActivaID,Volgorde) VALUES (?,?) ON DUPLICATE KEY UPDATE Volgorde=VALUES(Volgorde)');foreach($groups as $siblings){usort($siblings,static function($a,$b)use($get){$ai=(string)$a['id'];$bi=(string)$b['id'];$aHas=array_key_exists($ai,$get);$bHas=array_key_exists($bi,$get);if($aHas&&$bHas)return(int)$get[$ai]<=>(int)$get[$bi];if($aHas!==$bHas)return$aHas?-1:1;return strnatcasecmp((string)($a['label']??$ai),(string)($b['label']??$bi));});$rank=0;foreach($siblings as $row){$id=(string)$row['id'];if(array_key_exists($id,$get)){$rank=max($rank,(int)$get[$id]);continue;}$rank+=10;$up->execute([(int)$id,$rank]);$get[$id]=$rank;}}}
        $rows=$pdo->query('SELECT ActivaID,Volgorde FROM `VveguiActivaVolgorde`')->fetchAll(PDO::FETCH_KEY_PAIR);out(['ok'=>true,'order'=>$rows,'csrf'=>csrfToken()]);
    }
    if ($action === 'meeting_actions') {
        if(!isset($map['meetings'],$map['actions'])) throw new RuntimeException('Vergaderingen/ActieRegister niet beschikbaar.');
        $mid=trim((string)($_GET['meeting_id']??'')); if($mid==='') throw new RuntimeException('Vergadering-ID ontbreekt.');
        $mt=$map['meetings']; $mpk=primaryKey($pdo,$mt,'meetings');
        $q=$pdo->prepare('SELECT COUNT(*) FROM '.qi($mt).' WHERE '.qi($mpk).'=?'); $q->execute([$mid]); if(!(int)$q->fetchColumn()) throw new RuntimeException('Vergadering niet gevonden.');
        $at=$map['actions']; $ac=columns($pdo,$at); $fk=findCol($ac,['VergaderingID']); if(!$fk) throw new RuntimeException('VergaderingID ontbreekt in ActieRegister.');
        $sql='SELECT * FROM '.qi($at).' WHERE '.qi($fk).'=?'; $params=[$mid];
        if(isset($_GET['open_only']) && $_GET['open_only']==='1'){ $cc=findCol($ac,['Compleet']); if($cc){$sql.=" AND LOWER(TRIM(CAST(".qi($cc)." AS CHAR))) NOT IN ('waar','true','1','ja','gereed','compleet')";} }
        $sql.=' ORDER BY '.(findCol($ac,['DatumCompleet'])?qi(findCol($ac,['DatumCompleet'])).' ASC, ':'').' '.qi(primaryKey($pdo,$at,'actions')).' ASC';
        $st=$pdo->prepare($sql);$st->execute($params);out(['ok'=>true,'rows'=>$st->fetchAll()]);
    }
    if ($action === 'meeting_documents') {
        if(!isset($map['meetings'])) throw new RuntimeException('Vergaderingen niet beschikbaar.');
        $mid=trim((string)($_GET['meeting_id']??'')); if($mid==='') throw new RuntimeException('Vergadering-ID ontbreekt.');
        out(['ok'=>true,'documents'=>meetingDocuments($pdo,$mid)]);
    }
    if ($action === 'library_documents') {
        $q=trim((string)($_GET['q']??''));$params=[];$where="IsHuidig=1 AND VerwijderdOp IS NULL";
        if($q!==''){$where.=' AND (Titel LIKE ? OR OrigineleNaam LIKE ? OR Tags LIKE ?)';$like='%'.$q.'%';$params=[$like,$like,$like];}
        $stmt=$pdo->prepare('SELECT DocumentID,Titel,OrigineleNaam,Mimetype FROM DocumentBibliotheek WHERE '.$where.' ORDER BY GewijzigdOp DESC,DocumentID DESC LIMIT 30');$stmt->execute($params);
        out(['ok'=>true,'documents'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if ($action === 'link_library_document') {
        requireAdmin();verifyCsrf();$recordId=trim((string)($_GET['id']??''));$documentId=(int)($_POST['document_id']??0);
        if($recordId===''||$documentId<1)throw new RuntimeException('Record of document ontbreekt.');
        $stmt=$pdo->prepare('SELECT DocumentID,Titel,OrigineleNaam FROM DocumentBibliotheek WHERE DocumentID=? AND IsHuidig=1 AND VerwijderdOp IS NULL');$stmt->execute([$documentId]);$document=$stmt->fetch(PDO::FETCH_ASSOC);if(!$document)throw new RuntimeException('Document niet gevonden.');
        $url='document_download.php?id='.$documentId.'&view=1';$title=(string)($document['Titel']?:$document['OrigineleNaam']);
        if($entity==='Activa'){$stmt=$pdo->prepare('SELECT COUNT(*) FROM ActivaDocumenten WHERE ActivaID=? AND Url=?');$stmt->execute([$recordId,$url]);if(!(int)$stmt->fetchColumn()){$stmt=$pdo->prepare('INSERT INTO ActivaDocumenten (ActivaID,Type,Url,Titel,Volgorde,vvegui_modified_at,vvegui_modified_by) VALUES (?,?,?,?,?,?,?)');$stmt->execute([$recordId,'link',$url,$title,9999,date('Y-m-d H:i:s'),(string)$user['username']]);}recordActivity($pdo,$user,'bibliotheekdocument-gekoppeld','database','Activa',$recordId,$title);out(['ok'=>true,'documents'=>assetDocuments($pdo,$recordId)]);}
        if($entity==='maintenance_plans'){$stmt=$pdo->prepare('SELECT COUNT(*) FROM OnderhoudsplanDocumenten WHERE OnderhoudsplanID=? AND Url=?');$stmt->execute([$recordId,$url]);if(!(int)$stmt->fetchColumn()){$stmt=$pdo->prepare('INSERT INTO OnderhoudsplanDocumenten (OnderhoudsplanID,Type,Url,Titel,Volgorde,vvegui_modified_at,vvegui_modified_by) VALUES (?,?,?,?,?,?,?)');$stmt->execute([$recordId,'link',$url,$title,9999,date('Y-m-d H:i:s'),(string)$user['username']]);}recordActivity($pdo,$user,'bibliotheekdocument-gekoppeld','database','maintenance_plans',$recordId,$title);out(['ok'=>true,'documents'=>maintenanceDocuments($pdo,$recordId)]);}
        throw new RuntimeException('Documentkoppeling is voor dit recordtype nog niet beschikbaar.');
    }
    if ($action === 'upload_meeting_files') {
        requireAdmin(); verifyCsrf();
        if(!isset($map['meetings'])) throw new RuntimeException('Vergaderingen niet beschikbaar.');
        $mid=trim((string)($_GET['meeting_id']??'')); if($mid==='') throw new RuntimeException('Vergadering-ID ontbreekt.');
        $mpk=primaryKey($pdo,$map['meetings'],'meetings');$q=$pdo->prepare('SELECT COUNT(*) FROM '.qi($map['meetings']).' WHERE '.qi($mpk).'=?');$q->execute([$mid]);if(!(int)$q->fetchColumn())throw new RuntimeException('Vergadering niet gevonden.');
        $files=$_FILES['documents']??null;if(!$files||!isset($files['name'])||!is_array($files['name']))throw new SafeInputException('Geen bestanden ontvangen.');
        $allowed=['pdf','doc','docx','xls','xlsx','csv','txt','rtf','odt','ods','jpg','jpeg','png','gif','webp'];$uploaded=[];
        foreach($files['name'] as $i=>$orig){$err=(int)($files['error'][$i]??UPLOAD_ERR_NO_FILE);if($err===UPLOAD_ERR_NO_FILE)continue;if($err!==UPLOAD_ERR_OK)throw new SafeInputException('Upload mislukt voor '.$orig.'.');$tmp=(string)$files['tmp_name'][$i];$size=(int)($files['size'][$i]??0);if($size<=0||$size>25*1024*1024)throw new SafeInputException('Bestand '.$orig.' is groter dan 25 MB of ongeldig.');$ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));if(!in_array($ext,$allowed,true))throw new SafeInputException('Bestandstype niet toegestaan: '.$orig.'.');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp)?:'application/octet-stream';if(!allowedDocumentMime($ext,$mime))throw new SafeInputException('De inhoud van '.$orig.' komt niet overeen met het bestandstype.');$filename=bin2hex(random_bytes(16)).'.'.($ext==='jpeg'?'jpg':$ext);$dir=uploadDirectory('vergaderingen');$dest=$dir.'/'.$filename;if(!move_uploaded_file($tmp,$dest))throw new RuntimeException('Bestand kon niet worden opgeslagen.');$rel=storedPath('vergaderingen',$filename);$st=$pdo->prepare('INSERT INTO `VergaderingBestanden` (VergaderingID,Bestandsnaam,Bestandspad,Mimetype,Bestandsgrootte,vvegui_modified_at,vvegui_modified_by) VALUES (?,?,?,?,?,?,?)');$st->execute([$mid,$orig,$rel,$mime,$size,date('Y-m-d H:i:s'),(string)$user['username']]);$uploaded[]=$orig;}
        recordActivity($pdo,$user,'vergaderbestanden-geupload','database','meetings',$mid,count($uploaded).' bestand(en) toegevoegd.');
        out(['ok'=>true,'uploaded'=>$uploaded,'documents'=>meetingDocuments($pdo,$mid)]);
    }
    if ($action === 'send_action_reminder') {
        requireAdmin(); verifyCsrf();
        if(!isset($map['meetings'],$map['actions'])) throw new RuntimeException('Vergaderingen/ActieRegister niet beschikbaar.');
        $mid=trim((string)($_GET['meeting_id']??'')); if($mid==='') throw new RuntimeException('Vergadering-ID ontbreekt.');
        $body=json_decode(file_get_contents('php://input'),true);if(!is_array($body))$body=[];$to=trim((string)($body['to']??''));if($to==='')throw new RuntimeException('Voer minimaal één e-mailadres in.');
        $recipients=array_values(array_filter(array_map('trim',preg_split('/[,;\s]+/',$to)),fn($x)=>filter_var($x,FILTER_VALIDATE_EMAIL)));if(!$recipients)throw new RuntimeException('Geen geldig e-mailadres opgegeven.');
        $mt=$map['meetings'];$meeting=getRow($pdo,$mt,'meetings',$mid);if(!$meeting)throw new RuntimeException('Vergadering niet gevonden.');$actions=meetingActions($pdo,$map,$mid,true);
        $date=firstValue($meeting,['Datum']);$sort=firstValue($meeting,['Soort']);$subject='Herinnering openstaande actiepunten '.tenantConfig()['name'];
        $html='<html><body style="font-family:Arial,sans-serif;color:#17202a"><h2>'.$subject.'</h2><p><strong>Vergadering:</strong> '.htmlspecialchars(($date?dateDisplayNl($date).' · ':'').$sort,ENT_QUOTES,'UTF-8').'</p><p>Onderstaande actiepunten staan nog open.</p>';
        if($actions){$html.='<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;border-color:#cbd3dc;width:100%"><thead><tr style="background:#eaf4fb"><th>Datum</th><th>Actie</th><th>Verantwoordelijk</th><th>Omschrijving</th><th>Datum gereed</th><th>Gereed</th></tr></thead><tbody>';foreach($actions as $a){$html.='<tr><td>'.htmlspecialchars(dateDisplayNl(firstValue($a,['Datum'])),ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars(firstValue($a,['Actie']),ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars(firstValue($a,['Verantwoordelijk']),ENT_QUOTES,'UTF-8').'</td><td>'.nl2br(htmlspecialchars(firstValue($a,['Omschrijving']),ENT_QUOTES,'UTF-8')).'</td><td>'.htmlspecialchars(dateDisplayNl(firstValue($a,['DatumCompleet'])),ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars(firstValue($a,['Compleet']),ENT_QUOTES,'UTF-8').'</td></tr>';} $html.='</tbody></table>';}else{$html.='<p><strong>Er zijn geen openstaande actiepunten.</strong></p>';} $html.='<p style="margin-top:20px">VvE Beheer</p></body></html>';
        $plain='Herinnering openstaande actiepunten VvE Beheer\n\nVergadering: '.($date?dateDisplayNl($date).' · ':'').$sort."\n\n";foreach($actions as $a)$plain.='- datum: '.dateDisplayNl(firstValue($a,['Datum'])).' | '.firstValue($a,['Actie']).' | '.firstValue($a,['Verantwoordelijk']).' | '.firstValue($a,['Omschrijving']).' | datum gereed: '.dateDisplayNl(firstValue($a,['DatumCompleet'])).' | gereed: '.firstValue($a,['Compleet'])."\n";
        $smtp=loadSmtpConfig($pdo); smtpSendHtml($recipients,$subject,$html,$plain,$smtp); recordActivity($pdo,$user,'actieherinnering-verzonden','database','meetings',$mid,count($recipients).' ontvanger(s); '.count($actions).' open actie(s).'); out(['ok'=>true,'sent_to'=>$recipients,'open_actions'=>count($actions)]);
    }
    needEntity($entity,$map);
    if ($action === 'list') {
        $q=trim((string)($_GET['q']??''));
        $status=trim((string)($_GET['status']??'active'));
        out(['ok'=>true,'entity'=>$entity,'rows'=>listRows($pdo,$map[$entity],$q,$status)]);
    }
    if ($action === 'get') {
        $id=trim((string)($_GET['id']??''));
        if ($id==='') throw new RuntimeException('ID ontbreekt.');
        $row=getRow($pdo,$map[$entity],$entity,$id);
        if (!$row) throw new RuntimeException('Record niet gevonden.');
        out(['ok'=>true,'entity'=>$entity,'row'=>$row,'audit'=>auditInfo($row)]);
    }
    if ($action === 'related') {
        $id=trim((string)($_GET['id']??''));
        if ($id==='') throw new RuntimeException('ID ontbreekt.');
        out(['ok'=>true,'entity'=>$entity,'related'=>related($pdo,$map,$entity,$id)]);
    }
    if ($action === 'set_relation_contacts') {
        requireAdmin(); verifyCsrf();
        $relationId=trim((string)($_GET['relation_id']??''));
        if($relationId==='' || $relationId==='0') throw new RuntimeException('Relatie-ID ontbreekt.');
        $body=json_decode(file_get_contents('php://input'),true); if(!is_array($body))$body=[];
        $contactIds=array_values(array_unique(array_filter(array_map(static fn($v)=>trim((string)$v),(array)($body['contact_ids']??[])),static fn($v)=>$v!==''&&$v!=='0')));
        setRelationContacts($pdo,$map,$relationId,$contactIds);
        recordActivity($pdo,$user,'contacten-gekoppeld','database','relations',$relationId,count($contactIds).' contact(en) aan relatie gekoppeld.');
        out(['ok'=>true,'relation_id'=>$relationId,'contact_ids'=>$contactIds]);
    }
    if ($action === 'save') {
        requireAdmin(); verifyCsrf();
        $id=isset($_GET['id']) && $_GET['id']!=='' ? (string)$_GET['id'] : null;
        $body=json_decode(file_get_contents('php://input'),true); if(!is_array($body)) $body=[];
        if($entity==='Activa' && ($id===null || $id==='')){
            $actPk=primaryKey($pdo,$map['Activa'],'Activa');
            if(array_key_exists($actPk,$body) && trim((string)$body[$actPk])!==''){
                $candidate=trim((string)$body[$actPk]);
                $check=$pdo->prepare('SELECT COUNT(*) FROM '.qi($map['Activa']).' WHERE '.qi($actPk).'=?');
                $check->execute([$candidate]);
                if((int)$check->fetchColumn()===1)$id=$candidate;
            }
        }
        validateRequired($pdo,$map,$entity,$body,$id);
        if($entity==='Sleutels'){
            if(array_key_exists('ActivaID',$body) && trim((string)$body['ActivaID'])!==''){
                $aid=trim((string)$body['ActivaID']); if(!ctype_digit($aid)) throw new RuntimeException('Ongeldig Activum.');
                if(!isset($map['Activa'])) throw new RuntimeException('Activa zijn niet beschikbaar.');
                $at=$map['Activa'];$apk=primaryKey($pdo,$at,'Activa');$ac=columns($pdo,$at);$q=$pdo->prepare('SELECT COUNT(*) FROM '.qi($at).' WHERE '.qi($apk).'=?');$q->execute([$aid]);
                if((int)$q->fetchColumn()!==1) throw new RuntimeException('Ongeldig Activum: het gekozen Activum bestaat niet.');
            }
            $ud=trim((string)($body['Uitleendatum']??''));$rd=trim((string)($body['Retourdatum']??''));
            if($ud!=='' && $rd!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$ud) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$rd) && $rd<$ud) throw new RuntimeException('Retourdatum kan niet vóór de uitleendatum liggen.');
        }
        if($entity==='Activa' && array_key_exists('ActivaCatID',$body) && trim((string)$body['ActivaCatID'])!==''){
            $lookups=loadLookups($pdo);
            $cats=(array)($lookups['Activa']['ActivaCatID']??[]);
            if(!lookupIdExists($cats,trim((string)$body['ActivaCatID']))) throw new RuntimeException('Ongeldige Activa-categorie.');
        }
        if($entity==='maintenance_plans'){
            if(array_key_exists('Uitvoerder',$body) && trim((string)$body['Uitvoerder'])!==''){
                $rid=trim((string)$body['Uitvoerder']);
                if(!ctype_digit($rid)) throw new RuntimeException('Ongeldige Uitvoerder: er moet een Relatie-ID worden opgeslagen.');
                if(!isset($map['relations'])) throw new RuntimeException('Relaties zijn niet beschikbaar.');
                $rt=$map['relations']; $rpk=primaryKey($pdo,$rt,'relations'); $rc=columns($pdo,$rt);
                $q=$pdo->prepare('SELECT COUNT(*) FROM '.qi($rt).' WHERE '.qi($rpk).'=?');
                $q->execute([$rid]);
                if((int)$q->fetchColumn()!==1) throw new RuntimeException('Ongeldige Uitvoerder: het gekozen Relatie-ID bestaat niet.');
            }
            if(array_key_exists('ActivaID',$body) && trim((string)$body['ActivaID'])!==''){
                $aid=trim((string)$body['ActivaID']);
                if(!ctype_digit($aid)) throw new RuntimeException('Ongeldig Activum: er moet een Activum-ID worden opgeslagen.');
                if(!isset($map['Activa'])) throw new RuntimeException('Activa zijn niet beschikbaar.');
                $at=$map['Activa']; $apk=primaryKey($pdo,$at,'Activa'); $ac=columns($pdo,$at);
                $q=$pdo->prepare('SELECT COUNT(*) FROM '.qi($at).' WHERE '.qi($apk).'=? AND '.activePredicate($ac));
                $q->execute([$aid]);
                if((int)$q->fetchColumn()!==1) throw new RuntimeException('Ongeldig Activum: het gekozen Activum-ID bestaat niet of is niet actief.');
            }
            if(array_key_exists('OnderhoudsCategorie',$body) && trim((string)$body['OnderhoudsCategorie'])!==''){
                $cats=(array)(loadLookups($pdo)['maintenance_plans']['OnderhoudsCategorie']??[]);
                if(!lookupIdExists($cats,trim((string)$body['OnderhoudsCategorie']))) throw new RuntimeException('Ongeldige onderhoudscategorie.');
            }
            if(array_key_exists('Status',$body)){
                $allowed=(array)(loadLookups($pdo)['maintenance_plans']['Status']??[]);
                if($body['Status']!==null && $body['Status']!=='' && !in_array((string)$body['Status'],array_map('strval',$allowed),true)) throw new RuntimeException('Ongeldige status voor onderhoudsplan.');
            }
            if(array_key_exists('Indexering',$body) && $body['Indexering']!==null && $body['Indexering']!=='' && !is_numeric(str_replace(',','.',(string)$body['Indexering']))) throw new RuntimeException('Indexering moet een percentage zijn.');
            if(array_key_exists('StabuCode',$body) && $body['StabuCode']!==null && $body['StabuCode']!==''){ $stabu=(array)(loadLookups($pdo)['maintenance_plans']['StabuCode']??[]); if(!array_key_exists((string)$body['StabuCode'],$stabu) && !array_key_exists((int)$body['StabuCode'],$stabu)) throw new RuntimeException('Ongeldige STABU-code.'); }
        }
        $newId=saveRow($pdo,$map[$entity],$entity,$id,$body,(string)$user['username']);
        // Bij een nieuw dieper record worden de bestaande relationele verbindingen
        // direct vastgelegd; ID-velden blijven voor de gebruiker verborgen.
        $parentContact=trim((string)($_GET['parent_contact_id']??''));
        $parentRelation=trim((string)($_GET['parent_relation_id']??''));
        if($id===null && $newId!=='' && ($entity==='addresses' || $entity==='relations')){
            if($entity==='addresses' && ($parentContact!=='' || $parentRelation!==''))
                linkAddressToParent($pdo,$map,$newId,$parentContact,$parentRelation);
            elseif($entity==='relations' && $parentContact!=='')
                linkRelationToParent($pdo,$map,$newId,$parentContact);
        }
        $loggedRow=getRow($pdo,$map[$entity],$entity,$newId);
        $loggedName=activityRecordName($entity,is_array($loggedRow)?$loggedRow:[]);
        recordActivity($pdo,$user,$id===null?'record-aangemaakt':'record-gewijzigd','database',$entity,$newId,activityEntityLabel($entity).' '.($id===null?'aangemaakt':'gewijzigd').($loggedName!==''?': '.$loggedName:'').'.');
        out(['ok'=>true,'entity'=>$entity,'id'=>$newId]);
    }
    if ($action === 'images') {
        if($entity!=='Activa') throw new RuntimeException('Afbeeldingen zijn alleen beschikbaar voor Activa.');
        $id=trim((string)($_GET['id']??'')); if($id==='') throw new RuntimeException('Activum-ID ontbreekt.');
        out(['ok'=>true,'images'=>assetImages($pdo,$id)]);
    }
    if ($action === 'upload_image') {
        requireAdmin(); verifyCsrf();
        if($entity!=='Activa') throw new RuntimeException('Afbeeldingen zijn alleen beschikbaar voor Activa.');
        $id=trim((string)($_GET['id']??'')); if($id==='') throw new RuntimeException('Activum-ID ontbreekt.');
        $actPk=primaryKey($pdo,$map['Activa'],'Activa');
        $q=$pdo->prepare('SELECT COUNT(*) FROM '.qi($map['Activa']).' WHERE '.qi($actPk).'=?');$q->execute([$id]);
        if(!(int)$q->fetchColumn()) throw new RuntimeException('Activum niet gevonden.');
        $files=$_FILES['images']??null;
        if(!$files || !isset($files['name']) || !is_array($files['name'])) throw new RuntimeException('Geen afbeeldingen ontvangen.');
        $count=count($files['name']); if($count>20) throw new RuntimeException('Maximaal 20 afbeeldingen per upload.');
        $uploaded=[];
        for($i=0;$i<$count;$i++) {
            $err=(int)($files['error'][$i]??UPLOAD_ERR_NO_FILE);
            if($err===UPLOAD_ERR_NO_FILE) continue;
            if($err!==UPLOAD_ERR_OK) throw new RuntimeException('Upload mislukt voor '.(string)($files['name'][$i]??'afbeelding').'.');
            $tmp=(string)$files['tmp_name'][$i]; $orig=(string)$files['name'][$i]; $size=(int)($files['size'][$i]??0);
            if($size<=0 || $size>10*1024*1024) throw new RuntimeException('Afbeelding '.$orig.' is groter dan 10 MB of ongeldig.');
            $ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));
            if(!in_array($ext,['png','jpg','jpeg','bmp'],true)) throw new SafeInputException('Bestandstype niet toegestaan: '.$orig.'. Gebruik PNG, JPEG of BMP.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
            $allowedMime=['image/png','image/jpeg','image/bmp','image/x-ms-bmp'];
            if(!in_array($mime,$allowedMime,true)) throw new RuntimeException('Bestand '.$orig.' wordt niet als toegestane afbeelding herkend.');
            if(@getimagesize($tmp)===false) throw new SafeInputException('Bestand '.$orig.' is geen geldige afbeelding.');
            $safeExt=$ext==='jpeg'?'jpg':$ext; $filename=bin2hex(random_bytes(16)).'.'.$safeExt; $dir=uploadDirectory('activa');
            $dest=$dir.'/'.$filename; if(!move_uploaded_file($tmp,$dest)) throw new RuntimeException('Afbeelding kon niet worden opgeslagen.');
            $rel=storedPath('activa',$filename);
            $s=$pdo->prepare('INSERT INTO '.qi('ActivaAfbeeldingen').' (ActivaID,Bestandsnaam,Bestandspad,Mimetype,Bestandsgrootte,Titel,Volgorde,vvegui_modified_at,vvegui_modified_by) VALUES (?,?,?,?,?,?,?,?,?)');
            $s->execute([$id,$orig,$rel,$mime,$size,pathinfo($orig,PATHINFO_FILENAME),$i,date('Y-m-d H:i:s'),(string)$user['username']]);
            $uploaded[]=$orig;
        }
        recordActivity($pdo,$user,'activa-afbeeldingen-geupload','database','Activa',$id,count($uploaded).' afbeelding(en) toegevoegd.');
        out(['ok'=>true,'uploaded'=>$uploaded,'images'=>assetImages($pdo,$id)]);
    }
    if ($action === 'delete_image') {
        requireAdmin(); verifyCsrf();
        if($entity!=='Activa') throw new RuntimeException('Afbeeldingen zijn alleen beschikbaar voor Activa.');
        $imageId=trim((string)($_GET['image_id']??'')); if($imageId==='') throw new RuntimeException('Afbeelding-ID ontbreekt.');
        $s=$pdo->prepare('SELECT Bestandspad FROM '.qi('ActivaAfbeeldingen').' WHERE AfbeeldingID=?');$s->execute([$imageId]);$path=$s->fetchColumn();
        if($path===false) throw new RuntimeException('Afbeelding niet gevonden.');
        deleteStoredFile((string)$path);
        $d=$pdo->prepare('DELETE FROM '.qi('ActivaAfbeeldingen').' WHERE AfbeeldingID=?');$d->execute([$imageId]);
        recordActivity($pdo,$user,'activa-afbeelding-verwijderd','database','ActivaAfbeelding',$imageId,'Afbeelding verwijderd.');
        out(['ok'=>true,'id'=>$imageId]);
    }
    if ($action === 'documents') {
        if($entity==='Activa'){
            $id=trim((string)($_GET['id']??''));if($id==='')throw new RuntimeException('Activum-ID ontbreekt.');
            out(['ok'=>true,'documents'=>assetDocuments($pdo,$id)]);
        }
        if($entity!=='maintenance_plans') throw new RuntimeException('Documenten zijn alleen beschikbaar voor onderhoudsplannen.');
        $id=trim((string)($_GET['id']??''));if($id==='')throw new RuntimeException('Onderhoudsplan-ID ontbreekt.');out(['ok'=>true,'documents'=>maintenanceDocuments($pdo,$id)]);
    }
    if ($action === 'upload_document') {
        requireAdmin(); verifyCsrf();
        if($entity==='Activa'){
            $id=trim((string)($_GET['id']??''));if($id==='')throw new RuntimeException('Activum-ID ontbreekt.');
            $pk=primaryKey($pdo,$map['Activa'],'Activa');$q=$pdo->prepare('SELECT COUNT(*) FROM '.qi($map['Activa']).' WHERE '.qi($pk).'=?');$q->execute([$id]);if(!(int)$q->fetchColumn())throw new RuntimeException('Activum niet gevonden.');
            $files=$_FILES['documents']??null;if(!$files||!isset($files['name'])||!is_array($files['name']))throw new RuntimeException('Geen documenten ontvangen.');
            $allowed=['pdf','doc','docx','xls','xlsx','csv','txt','rtf','odt','ods','jpg','jpeg','png','gif','webp'];$uploaded=[];$order=0;
            foreach($files['name'] as $i=>$orig){
                $err=(int)($files['error'][$i]??UPLOAD_ERR_NO_FILE);if($err===UPLOAD_ERR_NO_FILE)continue;
                if($err!==UPLOAD_ERR_OK)throw new RuntimeException('Upload mislukt voor '.$orig.'.');
                $tmp=(string)($files['tmp_name'][$i]??'');$size=(int)($files['size'][$i]??0);
                if($size<=0||$size>25*1024*1024)throw new RuntimeException('Bestand '.$orig.' is groter dan 25 MB of ongeldig.');
                $ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));if(!in_array($ext,$allowed,true))throw new RuntimeException('Bestandstype niet toegestaan: '.$orig.'.');
                $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp)?:'application/octet-stream';
                if(!allowedDocumentMime($ext,$mime))throw new SafeInputException('De inhoud van '.$orig.' komt niet overeen met het bestandstype.');
                $filename=bin2hex(random_bytes(16)).'.'.($ext==='jpeg'?'jpg':$ext);$dir=uploadDirectory('activa_documenten');
                $dest=$dir.'/'.$filename;if(!move_uploaded_file($tmp,$dest))throw new RuntimeException('Bestand kon niet worden opgeslagen.');
                $rel=storedPath('activa_documenten',$filename);
                $s=$pdo->prepare('INSERT INTO `ActivaDocumenten` (ActivaID,Type,Bestandsnaam,Bestandspad,Mimetype,Bestandsgrootte,Titel,Volgorde,vvegui_modified_at,vvegui_modified_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
                $s->execute([$id,'bestand',$orig,$rel,$mime,$size,pathinfo($orig,PATHINFO_FILENAME),$order++,date('Y-m-d H:i:s'),(string)$user['username']]);$uploaded[]=$orig;
            }
            recordActivity($pdo,$user,'activa-documenten-geupload','database','Activa',$id,count($uploaded).' document(en) toegevoegd.');
            out(['ok'=>true,'uploaded'=>$uploaded,'documents'=>assetDocuments($pdo,$id)]);
        }
        if($entity!=='maintenance_plans')throw new RuntimeException('Documenten zijn alleen beschikbaar voor onderhoudsplannen.');$id=trim((string)($_GET['id']??''));if($id==='')throw new RuntimeException('Onderhoudsplan-ID ontbreekt.');
        $pk=primaryKey($pdo,$map['maintenance_plans'],'maintenance_plans');$q=$pdo->prepare('SELECT COUNT(*) FROM '.qi($map['maintenance_plans']).' WHERE '.qi($pk).'=?');$q->execute([$id]);if(!(int)$q->fetchColumn())throw new RuntimeException('Onderhoudsplan niet gevonden.');
        $files=$_FILES['documents']??null;if(!$files||!isset($files['name'])||!is_array($files['name']))throw new RuntimeException('Geen documenten ontvangen.');$allowed=['pdf','doc','docx','xls','xlsx','csv','txt','rtf','odt','ods','jpg','jpeg','png','gif','webp'];$uploaded=[];$order=0;
        foreach($files['name'] as $i=>$orig){$err=(int)($files['error'][$i]??UPLOAD_ERR_NO_FILE);if($err===UPLOAD_ERR_NO_FILE)continue;if($err!==UPLOAD_ERR_OK)throw new SafeInputException('Upload mislukt voor '.$orig.'.');$tmp=(string)$files['tmp_name'][$i];$size=(int)($files['size'][$i]??0);if($size<=0||$size>25*1024*1024)throw new SafeInputException('Bestand '.$orig.' is groter dan 25 MB of ongeldig.');$ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION));if(!in_array($ext,$allowed,true))throw new SafeInputException('Bestandstype niet toegestaan: '.$orig.'.');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp)?:'application/octet-stream';if(!allowedDocumentMime($ext,$mime))throw new SafeInputException('De inhoud van '.$orig.' komt niet overeen met het bestandstype.');$filename=bin2hex(random_bytes(16)).'.'.($ext==='jpeg'?'jpg':$ext);$dir=uploadDirectory('onderhoudsplannen');$dest=$dir.'/'.$filename;if(!move_uploaded_file($tmp,$dest))throw new RuntimeException('Bestand kon niet worden opgeslagen.');$rel=storedPath('onderhoudsplannen',$filename);$s=$pdo->prepare('INSERT INTO `OnderhoudsplanDocumenten` (OnderhoudsplanID,Type,Bestandsnaam,Bestandspad,Mimetype,Bestandsgrootte,Titel,Volgorde,vvegui_modified_at,vvegui_modified_by) VALUES (?,?,?,?,?,?,?,?,?,?)');$s->execute([$id,'bestand',$orig,$rel,$mime,$size,pathinfo($orig,PATHINFO_FILENAME),$order++,date('Y-m-d H:i:s'),(string)$user['username']]);$uploaded[]=$orig;}
        recordActivity($pdo,$user,'onderhoudsdocumenten-geupload','database','maintenance_plans',$id,count($uploaded).' document(en) toegevoegd.');
        out(['ok'=>true,'uploaded'=>$uploaded,'documents'=>maintenanceDocuments($pdo,$id)]);
    }
    if ($action === 'add_document_link') {
        requireAdmin(); verifyCsrf(); if($entity!=='maintenance_plans')throw new RuntimeException('Documenten zijn alleen beschikbaar voor onderhoudsplannen.');$id=trim((string)($_GET['id']??''));$url=trim((string)($_POST['url']??''));$title=trim((string)($_POST['title']??''));if($id===''||$url===''||!filter_var($url,FILTER_VALIDATE_URL))throw new RuntimeException('Voer een geldige documentlink in.');$s=$pdo->prepare('INSERT INTO `OnderhoudsplanDocumenten` (OnderhoudsplanID,Type,Url,Titel,Volgorde,vvegui_modified_at,vvegui_modified_by) VALUES (?,?,?,?,?,?,?)');$s->execute([$id,'link',$url,$title!==''?$title:$url,9999,date('Y-m-d H:i:s'),(string)$user['username']]);recordActivity($pdo,$user,'onderhoudslink-toegevoegd','database','maintenance_plans',$id,$title!==''?$title:$url);out(['ok'=>true,'documents'=>maintenanceDocuments($pdo,$id)]);
    }
    if ($action === 'delete_source_link') {
        requireAdmin();verifyCsrf();$linkId=(int)($_GET['link_id']??0);if($linkId<1)throw new RuntimeException('Koppeling-ID ontbreekt.');$d=$pdo->prepare('DELETE FROM ActivaBronKoppelingen WHERE KoppelingID=?');$d->execute([$linkId]);if(!$d->rowCount())throw new RuntimeException('Koppeling niet gevonden.');recordActivity($pdo,$user,'activa-bronkoppeling-verwijderd','database','ActivaBronKoppeling',(string)$linkId,'Koppeling verwijderd.');out(['ok'=>true,'id'=>$linkId]);
    }
    if ($action === 'delete_document') {
        requireAdmin(); verifyCsrf();
        if($entity==='Activa'){
            $docId=trim((string)($_GET['document_id']??''));if($docId==='')throw new RuntimeException('Document-ID ontbreekt.');
            $s=$pdo->prepare('SELECT Bestandspad FROM `ActivaDocumenten` WHERE DocumentID=?');$s->execute([$docId]);$path=$s->fetchColumn();if($path===false)throw new RuntimeException('Document niet gevonden.');
            if($path)deleteStoredFile((string)$path);
            $d=$pdo->prepare('DELETE FROM `ActivaDocumenten` WHERE DocumentID=?');$d->execute([$docId]);recordActivity($pdo,$user,'activa-document-verwijderd','database','ActivaDocument',$docId,'Document verwijderd.');out(['ok'=>true,'id'=>$docId]);
        }
        if($entity!=='maintenance_plans')throw new RuntimeException('Documenten zijn alleen beschikbaar voor onderhoudsplannen.');$docId=trim((string)($_GET['document_id']??''));if($docId==='')throw new RuntimeException('Document-ID ontbreekt.');$s=$pdo->prepare('SELECT Bestandspad FROM `OnderhoudsplanDocumenten` WHERE DocumentID=?');$s->execute([$docId]);$path=$s->fetchColumn();if($path===false)throw new RuntimeException('Document niet gevonden.');if($path)deleteStoredFile((string)$path);$d=$pdo->prepare('DELETE FROM `OnderhoudsplanDocumenten` WHERE DocumentID=?');$d->execute([$docId]);recordActivity($pdo,$user,'onderhoudsdocument-verwijderd','database','OnderhoudsplanDocument',$docId,'Document of link verwijderd.');out(['ok'=>true,'id'=>$docId]);
    }
    if ($action === 'delete') {
        requireAdmin(); verifyCsrf();
        $id=trim((string)($_GET['id']??'')); if($id==='') throw new RuntimeException('ID ontbreekt.');
        $rowBeforeDelete=getRow($pdo,$map[$entity],$entity,$id);
        $deletedId=deleteRow($pdo,$map[$entity],$entity,$id);
        $deletedName=activityRecordName($entity,is_array($rowBeforeDelete)?$rowBeforeDelete:[]);
        recordActivity($pdo,$user,'record-gedeactiveerd','database',$entity,$deletedId,activityEntityLabel($entity).' gedeactiveerd'.($deletedName!==''?': '.$deletedName:'').'.');
        out(['ok'=>true,'entity'=>$entity,'id'=>$deletedId]);
    }
    throw new RuntimeException('Onbekende actie.');
} catch(Throwable $e) {
    $code=http_response_code(); if($code<400)$code=$e instanceof SafeInputException?400:500; http_response_code($code);
    $message=($e instanceof SafeInputException || in_array($code,[401,403,404,429],true))?$e->getMessage():'Er is een onverwachte fout opgetreden. Referentie: '.securityIncident($e);
    echo json_encode(['ok'=>false,'error'=>$message],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
function out(array $d): never { echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function activityEntityLabel(string $entity): string {
    return [
        'contacts'=>'Contact','relations'=>'Relatie','addresses'=>'Adres',
        'Activa'=>'Activum','maintenance_plans'=>'Onderhoudsplan',
        'meetings'=>'Vergadering','actions'=>'Actiepunt','Sleutels'=>'Sleutel'
    ][$entity] ?? $entity;
}
function activityRecordName(string $entity,array $row): string {
    $value=function(array $names)use($row):string{foreach($names as $name){foreach($row as $key=>$val){if(keyNorm((string)$key)===keyNorm($name)&&$val!==null&&trim((string)$val)!=='')return trim((string)$val);}}return '';};
    if($entity==='contacts')return trim(implode(' ',array_filter([$value(['VoorNaam']),$value(['Tussenvoegsel']),$value(['AchterNaam'])])));
    if($entity==='addresses')return trim(implode(' ',array_filter([$value(['Straat']),$value(['Huisnummer']),$value(['Toevoegsel'])])));
    if($entity==='meetings')return trim(implode(' · ',array_filter([$value(['Datum']),$value(['Soort']),$value(['Locatie'])])));
    if($entity==='actions')return $value(['Actie','Omschrijving']);
    if($entity==='Sleutels')return $value(['Sleutelnummer','Naam','Omschrijving']);
    return $value(['Naam','Titel','Alias','Omschrijving','Werkomschrijving']);
}
function qi(string $n): string { if(!preg_match('/^[A-Za-z0-9_ .:-]+$/',$n)) throw new RuntimeException('Onveilige database-identificatie.'); return '`'.str_replace('`','``',$n).'`'; }

/*
 * v7.51 ID-integriteit:
 * Iedere nieuwe GUI-entiteit krijgt een ID uit een persistente append-only stack.
 * Een uitgegeven ID blijft geregistreerd, ook als het record later fysiek verdwijnt.
 */
function ensureIdStack(PDO $p,array $m): void {
    $p->exec("CREATE TABLE IF NOT EXISTS `VVEGUI_ID_STACK` (
      `EntityName` VARCHAR(64) NOT NULL,
      `RecordID` BIGINT UNSIGNED NOT NULL,
      `AllocatedAt` DATETIME NOT NULL,
      `AllocatedBy` VARCHAR(128) NULL,
      PRIMARY KEY (`EntityName`,`RecordID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $p->exec("CREATE TABLE IF NOT EXISTS `VVEGUI_ID_SEQUENCE` (
      `EntityName` VARCHAR(64) NOT NULL,
      `LastID` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY (`EntityName`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach($m as $e=>$t){
        if(!in_array($e,['contacts','addresses','relations','Activa','maintenance_plans','meetings','actions'],true)) continue;
        $pk=primaryKey($p,$t,$e); $cols=columns($p,$t);
        $at=in_array('vvegui_modified_at',$cols,true)?qi('vvegui_modified_at'):'NULL';
        $by=in_array('vvegui_modified_by',$cols,true)?qi('vvegui_modified_by'):'NULL';
        $sql="INSERT IGNORE INTO `VVEGUI_ID_STACK` (`EntityName`,`RecordID`,`AllocatedAt`,`AllocatedBy`)
              SELECT ?, CAST(".qi($pk)." AS UNSIGNED), COALESCE($at,NOW()), $by
              FROM ".qi($t)."
              WHERE ".qi($pk)." IS NOT NULL
                AND CAST(".qi($pk)." AS CHAR) REGEXP '^[0-9]+$'";
        $s=$p->prepare($sql);$s->execute([$e]);
        $s=$p->prepare("SELECT COALESCE(MAX(`RecordID`),0) FROM `VVEGUI_ID_STACK` WHERE `EntityName`=?");
        $s->execute([$e]);$maxStack=(int)$s->fetchColumn();
        $s=$p->prepare("INSERT INTO `VVEGUI_ID_SEQUENCE` (`EntityName`,`LastID`) VALUES (?,?) ON DUPLICATE KEY UPDATE `LastID`=GREATEST(`LastID`,VALUES(`LastID`))");
        $s->execute([$e,$maxStack]);
    }
}
function allocatePersistentId(PDO $p,string $entity,string $table,string $pk,string $who): string {
    // De sequence-row is de fysieke "top" van de stack. FOR UPDATE maakt
    // gelijktijdige inserts voor dezelfde entity sequentieel.
    $s=$p->prepare("SELECT `LastID` FROM `VVEGUI_ID_SEQUENCE` WHERE `EntityName`=? FOR UPDATE");
    $s->execute([$entity]);$last=$s->fetchColumn();
    if($last===false){
        $s=$p->prepare("SELECT COALESCE(MAX(CAST(".qi($pk)." AS UNSIGNED)),0) FROM ".qi($table)." WHERE ".qi($pk)." IS NOT NULL");
        $s->execute();$last=(int)$s->fetchColumn();
        $s=$p->prepare("INSERT INTO `VVEGUI_ID_SEQUENCE` (`EntityName`,`LastID`) VALUES (?,?)");
        $s->execute([$entity,$last]);
        $s=$p->prepare("SELECT `LastID` FROM `VVEGUI_ID_SEQUENCE` WHERE `EntityName`=? FOR UPDATE");
        $s->execute([$entity]);$last=(int)$s->fetchColumn();
    }else{$last=(int)$last;}

    $next=$last+1;
    while(true){
        $s=$p->prepare("SELECT 1 FROM `VVEGUI_ID_STACK` WHERE `EntityName`=? AND `RecordID`=? LIMIT 1");
        $s->execute([$entity,$next]);if($s->fetchColumn()!==false){$next++;continue;}
        $s=$p->prepare("SELECT 1 FROM ".qi($table)." WHERE ".qi($pk)."=? LIMIT 1");
        $s->execute([$next]);if($s->fetchColumn()!==false){$next++;continue;}
        break;
    }
    $s=$p->prepare("UPDATE `VVEGUI_ID_SEQUENCE` SET `LastID`=? WHERE `EntityName`=?");
    $s->execute([$next,$entity]);
    $s=$p->prepare("INSERT INTO `VVEGUI_ID_STACK` (`EntityName`,`RecordID`,`AllocatedAt`,`AllocatedBy`) VALUES (?,?,?,?)");
    $s->execute([$entity,$next,date('Y-m-d H:i:s'),$who]);
    return(string)$next;
}
function repairZeroContactId(PDO $p,array $m,string $who): void {
    if(!isset($m['contacts']))return;
    $table=$m['contacts'];$pk=primaryKey($p,$table,'contacts');
    $count=$p->query('SELECT COUNT(*) FROM '.qi($table).' WHERE '.qi($pk).'=0')->fetchColumn();
    if((int)$count!==1)return;
    $max=(int)$p->query('SELECT COALESCE(MAX(CAST('.qi($pk).' AS UNSIGNED)),0) FROM '.qi($table))->fetchColumn();
    $next=max(1,$max+1);
    $p->beginTransaction();
    try{
        $q=$p->prepare('UPDATE '.qi($table).' SET '.qi($pk).'=?'.(in_array('vvegui_modified_at',columns($p,$table),true)?', '.qi('vvegui_modified_at').'=?':'').(in_array('vvegui_modified_by',columns($p,$table),true)?', '.qi('vvegui_modified_by').'=?':'').' WHERE '.qi($pk).'=0');
        $values=[$next];if(in_array('vvegui_modified_at',columns($p,$table),true))$values[]=date('Y-m-d H:i:s');if(in_array('vvegui_modified_by',columns($p,$table),true))$values[]=$who;$q->execute($values);
        $tables=tables($p);
        if(in_array('VVEGUI_ID_STACK',$tables,true)){$q=$p->prepare('INSERT IGNORE INTO `VVEGUI_ID_STACK` (`EntityName`,`RecordID`,`AllocatedAt`,`AllocatedBy`) VALUES (?,?,?,?)');$q->execute(['contacts',$next,date('Y-m-d H:i:s'),$who]);}
        if(in_array('VVEGUI_ID_SEQUENCE',$tables,true)){$q=$p->prepare('INSERT INTO `VVEGUI_ID_SEQUENCE` (`EntityName`,`LastID`) VALUES (?,?) ON DUPLICATE KEY UPDATE `LastID`=GREATEST(`LastID`,VALUES(`LastID`))');$q->execute(['contacts',$next]);}
        $p->commit();
    }catch(Throwable $e){if($p->inTransaction())$p->rollBack();}
}

function loadSmtpConfig(PDO $pdo): array {
    try {
        $hasAccounts=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='EmailAccounts'")->fetchColumn()===1;
        if($hasAccounts){
            $account=$pdo->query('SELECT * FROM EmailAccounts WHERE IsActive=1 ORDER BY IsDefault DESC, EmailAccountID ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            if($account){
                $password=mailPassword($account);
                if($password===null)throw new SafeInputException('Het wachtwoord van het standaard e-mailaccount is nog niet server-lokaal ingesteld. Open Instellingen, E-mail en daarna Zoekindex om het wachtwoord op te slaan.');
                return [
                    'host'=>(string)$account['SmtpHost'], 'port'=>(int)$account['SmtpPort'],
                    'security'=>(string)$account['SmtpSecurity'], 'username'=>(string)$account['Username'],
                    'password'=>$password, 'from'=>(string)$account['EmailAddress'],
                    'from_name'=>(string)$account['SenderName'],
                ];
            }
        }
    }catch(SafeInputException $e){throw $e;}catch(Throwable $e){error_log('[smtp-config] '.$e->getMessage());}
    $file = tenantPrivateFile('smtp_config.php');
    if (!is_file($file)) throw new SafeInputException('Er is geen actief e-mailaccount voor verzenden ingesteld. Open Instellingen, E-mail en configureer eerst een standaardaccount.');
    $cfg = include $file;
    if (!is_array($cfg)) throw new RuntimeException('Ongeldige SMTP-configuratie.');
    foreach (['host','port','username','password','from'] as $k) {
        if (!isset($cfg[$k]) || trim((string)$cfg[$k]) === '') throw new RuntimeException('SMTP-configuratie ontbreekt: '.$k.'.');
    }
    if ((string)$cfg['password'] === 'VUL_HIER_HET_STRATO_WACHTWOORD_IN') throw new RuntimeException('Het STRATO SMTP-wachtwoord is nog niet ingevuld.');
    return $cfg;
}
function smtpReadResponse($fp): string {
    $data=''; while(!feof($fp)){ $line=fgets($fp,515); if($line===false)break; $data.=$line; if(strlen($line)>=4 && $line[3]===' ')break; } return $data;
}
function smtpExpectCode($fp,array $codes): string {
    $r=smtpReadResponse($fp); $code=(int)substr(trim($r),0,3);
    if(!in_array($code,$codes,true)) throw new RuntimeException('STRATO SMTP-fout ('.$code.'): '.trim($r)); return $r;
}
function smtpCmd($fp,string $cmd,array $codes): string {
    if(fwrite($fp,$cmd."\r\n")===false) throw new RuntimeException('SMTP-opdracht kon niet worden verzonden.');
    return smtpExpectCode($fp,$codes);
}
function smtpSendHtml(array $recipients,string $subject,string $html,string $plain,array $cfg): void {
    $security=strtolower((string)($cfg['security']??'ssl'));
    $transport=$security==='ssl'?'ssl://':'';
    $errno=0;$errstr=''; $fp=@stream_socket_client($transport.$cfg['host'].':'.$cfg['port'],$errno,$errstr,20,STREAM_CLIENT_CONNECT);
    if(!$fp) throw new RuntimeException('Kan geen verbinding maken met STRATO SMTP: '.$errstr.' ('.$errno.').');
    stream_set_timeout($fp,20);
    try{
        smtpExpectCode($fp,[220]); smtpCmd($fp,'EHLO '.tenantConfig()['host'],[250]);
        if($security==='starttls'){
            smtpCmd($fp,'STARTTLS',[220]);
            if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('De beveiligde SMTP-verbinding kon niet worden gestart.');
            smtpCmd($fp,'EHLO '.tenantConfig()['host'],[250]);
        }
        smtpCmd($fp,'AUTH LOGIN',[334]); smtpCmd($fp,base64_encode($cfg['username']),[334]); smtpCmd($fp,base64_encode($cfg['password']),[235]);
        $from=$cfg['from']; smtpCmd($fp,'MAIL FROM:<'.$from.'>',[250]);
        foreach($recipients as $to) smtpCmd($fp,'RCPT TO:<'.$to.'>',[250,251]);
        smtpCmd($fp,'DATA',[354]);
        $subject=str_replace(["\r","\n"],'',$subject); $fromName=str_replace(["\r","\n"],'',(string)($cfg['from_name']??'VvE Beheer'));
        $boundary='=_VVEGUI_'.bin2hex(random_bytes(12));
        $headers='From: '.mb_encode_mimeheader($fromName,'UTF-8').' <'.$from.">\r\n".'Reply-To: '.$from."\r\n".'To: '.implode(', ',$recipients)."\r\n".'Subject: '.mb_encode_mimeheader($subject,'UTF-8')."\r\n".'MIME-Version: 1.0'."\r\n".'Content-Type: multipart/alternative; boundary="'.$boundary.'"'."\r\n";
        $body=$headers."\r\n".'--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".str_replace("\r\n","\n",$plain)."\r\n".'--'.$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".str_replace("\r\n","\n",$html)."\r\n".'--'.$boundary."--\r\n";
        $body=preg_replace('/(?m)^\./','..',$body);
        if(fwrite($fp,$body."\r\n.\r\n")===false) throw new RuntimeException('SMTP-bericht kon niet worden verzonden.');
        smtpExpectCode($fp,[250]); smtpCmd($fp,'QUIT',[221]);
    } finally { fclose($fp); }
}
function loadLookups(?PDO $db=null): array { $f=tenantPrivateFile('lookups.php');$x=is_file($f)?include $f:[];$lookups=is_array($x)?$x:[];return $db?catalogLookups($db,$lookups):$lookups; }
function lookupIdLabelOptions(array $values): array {
    $out=[];
    foreach($values as $id=>$label){
        if(is_array($label)) continue;
        $id=(string)$id; $label=(string)$label;
        // Ondersteun zowel associative ID => label als de settings-vorm 'ID | label'.
        if(is_int($id) || ctype_digit($id)) {
            $out[]=['id'=>$id,'label'=>trim($label)];
        } else {
            $parts=explode('|',$label,2);
            $out[]=['id'=>trim($parts[0]),'label'=>trim($parts[1]??$parts[0])];
        }
    }
    return array_values(array_filter($out,fn($x)=>$x['id']!=='' && $x['label']!==''));
}
function lookupIdExists(array $values,string $id): bool { foreach(lookupIdLabelOptions($values) as $x) if((string)$x['id']===$id)return true; return false; }
function ensureKeysTable(PDO $p): void {
    $q=$p->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Sleutels'");
    if((int)$q->fetchColumn()===0){
        $p->exec("CREATE TABLE `Sleutels` (
            `SleutelID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `Functie` VARCHAR(255) NULL,
            `Omschrijving` TEXT NULL,
            `Verantwoordelijk` VARCHAR(255) NULL,
            `Merk` VARCHAR(255) NULL,
            `Sleutelnummer` VARCHAR(100) NULL,
            `Locatie` VARCHAR(255) NULL,
            `UitgeleendAan` VARCHAR(255) NULL,
            `Uitleendatum` DATE NULL,
            `Retourdatum` DATE NULL,
            `Update` DATETIME NULL,
            `Actief` VARCHAR(20) NULL DEFAULT 'Waar',
            `ActivaID` INT(10) UNSIGNED NULL,
            PRIMARY KEY (`SleutelID`),
            KEY `idx_sleutels_activa` (`ActivaID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
function tables(PDO $p): array { return $p->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN); }
function columns(PDO $p,string $t): array { $s=$p->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION");$s->execute([$t]);return$s->fetchAll(PDO::FETCH_COLUMN); }
function discover(PDO $p): array {
    $ts=tables($p);
    $defs=[
        'contacts'=>['Contacten','contacts','contacten'],
        'addresses'=>['Adressen','addresses','adressen'],
        'relations'=>['Relaties','relations','relaties'],
        'Activa'=>['Activa'],
        // Activa- en onderhoudscategorieën zijn lookup-only en zijn geen database-entiteiten.
        'maintenance_plans'=>['Onderhoudsplannen'],
        'meetings'=>['Vergaderingen'],
        'actions'=>['ActieRegister']
    ];
    $m=[];
    foreach($defs as $e=>$cands){
        foreach($cands as $c) if(in_array($c,$ts,true)){ $m[$e]=$c; break; }
        if(in_array($e,['contacts','addresses','relations'],true) && !isset($m[$e]))
            throw new RuntimeException("Tabel voor {$e} niet gevonden. Beschikbare tabellen: ".implode(', ',$ts));
    }
    foreach($ts as $t){
        // Deze historische categorie-tabellen worden volledig genegeerd.
        if(in_array($t,['ActivaCategorie','OnderhoudsCategorie','VveInstallation'],true)) continue;
        if(in_array($t,array_values($m),true)) continue;
        $m[$t]=$t;
    }
    return $m;
}
function needEntity(string $e,array $m): void { if(!isset($m[$e]))throw new RuntimeException('Ongeldige entity.'); }
function primaryKey(PDO $p,string $t,string $e): string { $s=$p->prepare("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME='PRIMARY' ORDER BY ORDINAL_POSITION LIMIT 1");$s->execute([$t]);$x=$s->fetchColumn();if($x!==false&&$x!=='')return(string)$x;$f=['contacts'=>['ContactID','contact_id','id','ID'],'addresses'=>['Id','AdresID','address_id','id','ID'],'relations'=>['RelatieID','relatie_id','id','ID']];foreach(($f[$e]??['id','ID']) as $k)if(in_array($k,columns($p,$t),true))return$k;throw new RuntimeException("Primaire sleutel van {$t} kon niet worden bepaald."); }
function countRows(PDO $p,string $t): int { return(int)$p->query('SELECT COUNT(*) FROM '.qi($t))->fetchColumn(); }
function activePredicate(array $cols): string {
    $activeCol=actualCol($cols,'Actief');
    $statusCol=actualCol($cols,'Status');
    if($activeCol) return "LOWER(TRIM(CAST(".qi($activeCol)." AS CHAR))) IN ('waar','true','1','ja','actief','active')";
    if($statusCol) return "LOWER(TRIM(CAST(".qi($statusCol)." AS CHAR))) IN ('actief','active')";
    return '1=0';
}
function listRows(PDO $p,string $t,string $q,string $status='active'): array {
    $c=columns($p,$t);$sql='SELECT * FROM '.qi($t);$params=[];$where=[];
    if($q!==''){$parts=[];foreach($c as $x){$parts[]='CAST('.qi($x).' AS CHAR) LIKE ?';$params[]='%'.$q.'%';}$where[]='('.implode(' OR ',$parts).')';}
    $statusCol=actualCol($c,'Status');$activeCol=actualCol($c,'Actief');
    if($status==='__all__' || $status==='all' || $status===''){}
    elseif($status==='active'){
        if($activeCol){$where[]="LOWER(TRIM(CAST(".qi($activeCol)." AS CHAR))) IN ('waar','true','1','ja','actief','active')";}
        elseif($statusCol){$where[]="LOWER(TRIM(CAST(".qi($statusCol)." AS CHAR))) IN ('actief','active')";}
    }elseif($status==='inactive'){
        if($activeCol){$where[]="LOWER(TRIM(CAST(".qi($activeCol)." AS CHAR))) NOT IN ('waar','true','1','ja','actief','active')";}
        elseif($statusCol){$where[]="LOWER(TRIM(CAST(".qi($statusCol)." AS CHAR))) NOT IN ('actief','active')";}
    }elseif($statusCol){
        $where[]='TRIM(CAST('.qi($statusCol).' AS CHAR)) = ?'; $params[]=$status;
    }
    if($where)$sql.=' WHERE '.implode(' AND ',$where);if($status!=='__all__')$sql.=' LIMIT 500';$s=$p->prepare($sql);$s->execute($params);return$s->fetchAll();
}

function getRow(PDO $p,string $t,string $e,string $id): ?array { $k=primaryKey($p,$t,$e);$s=$p->prepare('SELECT * FROM '.qi($t).' WHERE '.qi($k).'=? LIMIT 1');$s->execute([$id]);$r=$s->fetch();return$r?:null; }
function auditInfo(array $r): array { $d=$r['vvegui_modified_at']??null;$b=$r['vvegui_modified_by']??null;if(!$d)foreach(['Gewijzigd','gewijzigd','Update','updated_at_source','LaatstGewijzigd','LastModified','UpdatedAt'] as $k)if(isset($r[$k])&&$r[$k]!==''){$d=$r[$k];break;}if(!$b)foreach(['Gewijzigd door','gewijzigd_door'] as $k)if(isset($r[$k])&&$r[$k]!==''){$b=$r[$k];break;}if(!$b&&$d)$b='Bronimport';return['date'=>$d?:'—','by'=>$b?:'—']; }
function ensureAuditColumns(PDO $p,string $t): void { $c=columns($p,$t);if(!in_array('vvegui_modified_at',$c,true))$p->exec('ALTER TABLE '.qi($t).' ADD COLUMN vvegui_modified_at DATETIME NULL');$c=columns($p,$t);if(!in_array('vvegui_modified_by',$c,true))$p->exec('ALTER TABLE '.qi($t).' ADD COLUMN vvegui_modified_by VARCHAR(128) NULL'); }

function ensureContactOptionalColumns(PDO $p,string $t): void {
    $c=columns($p,$t);
    $defs=['Etage'=>'VARCHAR(20) NULL','Bergingsgang'=>'VARCHAR(20) NULL','Appartementsgrootte'=>'VARCHAR(30) NULL'];
    foreach($defs as $name=>$type){
        if(!in_array($name,$c,true)){$p->exec('ALTER TABLE '.qi($t).' ADD COLUMN '.qi($name).' '.$type);$c[]=$name;}
    }
}
function setColumnValue(PDO $p,string $t,string $column,string $value,string $pk,string $id): void {
    $s=$p->prepare('UPDATE '.qi($t).' SET '.qi($column).'=? WHERE '.qi($pk).'=?');$s->execute([$value,$id]);
}
function linkAddressToParent(PDO $p,array $m,string $addressId,string $contactId='',string $relationId=''): void {
    $t=$m['addresses'];$cols=columns($p,$t);$pk=primaryKey($p,$t,'addresses');
    $aContact=findCol($cols,['ContactID','contact_id']);$aResident=findCol($cols,['Bewoner_ContactID','bewoner_contact_id']);$aRel=findCol($cols,['RelatieID','relatie_id']);
    if($contactId!==''){
        if(!getRow($p,$m['contacts'],'contacts',$contactId))throw new RuntimeException('Gekozen contact bestaat niet.');
        if($aContact)setColumnValue($p,$t,$aContact,$contactId,$pk,$addressId);
        elseif($aResident)setColumnValue($p,$t,$aResident,$contactId,$pk,$addressId);
    }
    if($relationId!==''){
        if(!getRow($p,$m['relations'],'relations',$relationId))throw new RuntimeException('Gekozen relatie bestaat niet.');
        if($aRel)setColumnValue($p,$t,$aRel,$relationId,$pk,$addressId);
    }
}
function linkRelationToParent(PDO $p,array $m,string $relationId,string $contactId): void {
    $t=$m['contacts'];$cols=columns($p,$t);$pk=primaryKey($p,$t,'contacts');$cr=findCol($cols,['RelatieID','relatie_id']);
    if($cr)setColumnValue($p,$t,$cr,$relationId,$pk,$contactId);
}
function setRelationContacts(PDO $p,array $m,string $relationId,array $contactIds): void {
    if(!getRow($p,$m['relations'],'relations',$relationId))throw new RuntimeException('Gekozen relatie bestaat niet.');
    $t=$m['contacts'];$cols=columns($p,$t);$pk=primaryKey($p,$t,'contacts');
    foreach($contactIds as $contactId)if(!getRow($p,$t,'contacts',$contactId))throw new RuntimeException('Een gekozen contact bestaat niet meer.');
    $link=relationContactLinkTable($p,$m);
    $p->beginTransaction();
    try{
        if($link){
            $clear=$p->prepare('DELETE FROM '.qi($link['table']).' WHERE '.qi($link['relation']).'=?');$clear->execute([$relationId]);
            if($contactIds){$set=$p->prepare('INSERT INTO '.qi($link['table']).' ('.qi($link['contact']).','.qi($link['relation']).') VALUES (?,?)');foreach($contactIds as $contactId)$set->execute([$contactId,$relationId]);}
        }else{
            $relationCol=findCol($cols,['RelatieID','relatie_id']);
            if(!$relationCol)throw new RuntimeException('De database bevat geen contact-relatiekoppeling.');
            $clear=$p->prepare('UPDATE '.qi($t).' SET '.qi($relationCol).'=0 WHERE '.qi($relationCol).'=?');$clear->execute([$relationId]);
            if($contactIds){$ph=implode(',',array_fill(0,count($contactIds),'?'));$set=$p->prepare('UPDATE '.qi($t).' SET '.qi($relationCol).'=? WHERE '.qi($pk).' IN ('.$ph.')');$set->execute(array_merge([$relationId],$contactIds));}
        }
        $p->commit();
    }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}
function relationContactLinkTable(PDO $p,array $m): ?array {
    foreach(tables($p) as $table){
        if(strtolower($table)===strtolower($m['contacts'])||strtolower($table)===strtolower($m['addresses'])||strtolower($table)===strtolower($m['relations']))continue;
        if(!preg_match('/contact.*relat|relat.*contact/i',$table))continue;
        $cols=columns($p,$table);$contact=findCol($cols,['ContactID','contact_id']);$relation=findCol($cols,['RelatieID','relatie_id']);
        if($contact&&$relation)return['table'=>$table,'contact'=>$contact,'relation'=>$relation];
    }
    return null;
}
function findCol(array $cols,array $names): ?string { foreach($names as $n)if(in_array($n,$cols,true))return$n;return null; }
function listByIds(PDO $p,string $t,string $pk,array $ids): array { $ids=array_values(array_unique(array_filter(array_map('strval',$ids),static fn($v)=>$v!==''&&$v!=='0')));if(!$ids)return[];$ph=implode(',',array_fill(0,count($ids),'?'));$s=$p->prepare('SELECT * FROM '.qi($t).' WHERE '.qi($pk).' IN ('.$ph.')');$s->execute($ids);return$s->fetchAll(); }
function related(PDO $p,array $m,string $e,string $id): array {
    $out=['addresses'=>[],'contacts'=>[],'relations'=>[],'maintenance_plans'=>[],'keys'=>[]];
    if($e==='maintenance_plans'){
        $out['documents']=maintenanceDocuments($p,$id);
        $t=$m['maintenance_plans'];$r=getRow($p,$t,'maintenance_plans',$id);
        if($r){
            $fk=findCol(columns($p,$t),['ActivaID','ActivaId']);
            if($fk&&isset($m['Activa'])&&($r[$fk]??'')!==''){$ap=primaryKey($p,$m['Activa'],'Activa');$q=$p->prepare('SELECT * FROM '.qi($m['Activa']).' WHERE '.qi($ap).'=?');$q->execute([$r[$fk]]);$x=$q->fetch();if($x)$out['asset']=$x;}
            $rk=findCol(columns($p,$t),['Uitvoerder','UitvoerderID','RelatieID','RelatieId']);
            if($rk&&isset($m['relations'])&&($r[$rk]??'')!==''){$rp=primaryKey($p,$m['relations'],'relations');$q=$p->prepare('SELECT * FROM '.qi($m['relations']).' WHERE '.qi($rp).'=?');$q->execute([$r[$rk]]);$x=$q->fetch();if($x)$out['relation']=$x;}
        }
        return $out;
    }
    if($e==='Activa'){
        $out['documents']=assetDocuments($p,$id);
        $actT=$m['Activa'];$actPk=primaryKey($p,$actT,'Activa');
        if($actPk){
            if(isset($m['maintenance_plans'])){
                $mpT=$m['maintenance_plans'];$mpCols=columns($p,$mpT);$mpFk=findCol($mpCols,['ActivaID','ActivaId']);
                if($mpFk){$mpPk=primaryKey($p,$mpT,'maintenance_plans');$s=$p->prepare('SELECT * FROM '.qi($mpT).' WHERE '.qi($mpFk).'=? ORDER BY '.qi($mpPk).' ASC');$s->execute([$id]);$out['maintenance_plans']=$s->fetchAll();}
            }
            if(isset($m['Sleutels'])){
                $keyCols=columns($p,$m['Sleutels']);$keyFk=findCol($keyCols,['ActivaID','ActivaId']);
                if($keyFk){$keyPk=primaryKey($p,$m['Sleutels'],'Sleutels');$s=$p->prepare('SELECT * FROM '.qi($m['Sleutels']).' WHERE '.qi($keyFk).'=? ORDER BY '.qi($keyPk).' ASC');$s->execute([$id]);$out['keys']=$s->fetchAll();}
            }
        }
        return $out;
    }
    $cc=columns($p,$m['contacts']);$ac=columns($p,$m['addresses']);$cp=primaryKey($p,$m['contacts'],'contacts');$rp=primaryKey($p,$m['relations'],'relations');$aContact=findCol($ac,['ContactID','contact_id']);$aResident=findCol($ac,['Bewoner_ContactID','bewoner_contact_id']);$aRel=findCol($ac,['RelatieID','relatie_id']);$cRel=findCol($cc,['RelatieID','relatie_id']);
    if($e==='contacts'){
        if($id==='0')return$out;
        $conds=[];$vals=[];if($aContact){$conds[]=qi($aContact).'=?';$vals[]=$id;}if($aResident){$conds[]=qi($aResident).'=?';$vals[]=$id;}if($conds){$s=$p->prepare('SELECT * FROM '.qi($m['addresses']).' WHERE '.implode(' OR ',$conds));$s->execute($vals);$out['addresses']=$s->fetchAll();}
        $relationIds=[];if($cRel){$s=$p->prepare('SELECT '.qi($cRel).' FROM '.qi($m['contacts']).' WHERE '.qi($cp).'=?');$s->execute([$id]);$v=$s->fetchColumn();if($v!==false&&$v!=='')$relationIds[]=$v;}
        if($link=relationContactLinkTable($p,$m)){$s=$p->prepare('SELECT '.qi($link['relation']).' FROM '.qi($link['table']).' WHERE '.qi($link['contact']).'=?');$s->execute([$id]);$relationIds=array_merge($relationIds,$s->fetchAll(PDO::FETCH_COLUMN));}
        $out['relations']=listByIds($p,$m['relations'],$rp,$relationIds);
    }elseif($e==='addresses'){
        $r=getRow($p,$m['addresses'],'addresses',$id);if($r){$ids=[];if($aContact&&!empty($r[$aContact]))$ids[]=$r[$aContact];if($aResident&&!empty($r[$aResident]))$ids[]=$r[$aResident];$out['contacts']=listByIds($p,$m['contacts'],$cp,$ids);if($aRel&&!empty($r[$aRel]))$out['relations']=listByIds($p,$m['relations'],$rp,[$r[$aRel]]);}
    }else{
        if($cRel){$s=$p->prepare('SELECT * FROM '.qi($m['contacts']).' WHERE '.qi($cRel).'=?');$s->execute([$id]);$out['contacts']=$s->fetchAll();}
        if($link=relationContactLinkTable($p,$m)){$s=$p->prepare('SELECT '.qi($link['contact']).' FROM '.qi($link['table']).' WHERE '.qi($link['relation']).'=?');$s->execute([$id]);$out['contacts']=array_merge($out['contacts'],listByIds($p,$m['contacts'],$cp,$s->fetchAll(PDO::FETCH_COLUMN)));}
        if($out['contacts']){$seen=[];$out['contacts']=array_values(array_filter($out['contacts'],function($row)use(&$seen,$cp){$id=(string)($row[$cp]??'');if($id===''||isset($seen[$id]))return false;$seen[$id]=true;return true;}));}
        if($aRel){$s=$p->prepare('SELECT * FROM '.qi($m['addresses']).' WHERE '.qi($aRel).'=?');$s->execute([$id]);$out['addresses']=$s->fetchAll();}
    } return$out;
}
function ensureKeysSchema(PDO $p,array $m): void {
    if(!isset($m['Sleutels'])) return;
    $t=$m['Sleutels']; $c=columns($p,$t);
    foreach([
        'Functie'=>'VARCHAR(255) NULL','Omschrijving'=>'TEXT NULL','Verantwoordelijk'=>'VARCHAR(255) NULL','Merk'=>'VARCHAR(255) NULL',
        'Sleutelnummer'=>'VARCHAR(100) NULL','Locatie'=>'VARCHAR(255) NULL','UitgeleendAan'=>'VARCHAR(255) NULL',
        'Uitleendatum'=>'DATE NULL','Retourdatum'=>'DATE NULL','Actief'=>'VARCHAR(20) NULL','ActivaID'=>'INT(10) UNSIGNED NULL'
    ] as $n=>$type){
        if(!in_array($n,$c,true)){$p->exec('ALTER TABLE '.qi($t).' ADD COLUMN '.qi($n).' '.$type);$c[]=$n;}
    }
    $q=$p->prepare('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=\'ActivaID\' AND REFERENCED_TABLE_NAME IS NOT NULL');$q->execute([$t]);
    if((int)$q->fetchColumn()===0 && isset($m['Activa'])){
        // Relation is intentional: Sleutels is otherwise standalone.
        $at=$m['Activa'];$apk=primaryKey($p,$at,'Activa');
        try{$p->exec('ALTER TABLE '.qi($t).' ADD CONSTRAINT '.qi('fk_sleutels_activa').' FOREIGN KEY (`ActivaID`) REFERENCES '.qi($at).' ('.qi($apk).') ON DELETE SET NULL ON UPDATE CASCADE');}catch(Throwable $e){}
    }
    ensureAuditColumns($p,$t);
}
function ensureMeetingSchema(PDO $p,array $m): void {
    if(!isset($m['meetings'])) return;
    $t=$m['meetings']; $c=columns($p,$t);
    foreach(['Datum'=>'DATE NULL','Soort'=>'VARCHAR(100) NULL','Locatie'=>'VARCHAR(255) NULL','Aanwezig'=>'TEXT NULL','Agenda'=>'LONGTEXT NULL','Verslag'=>'LONGTEXT NULL','Actief'=>'VARCHAR(20) NULL'] as $n=>$type){if(!in_array($n,$c,true)){$p->exec('ALTER TABLE '.qi($t).' ADD COLUMN '.qi($n).' '.$type);$c[]=$n;}}
    ensureAuditColumns($p,$t);
    if(!isset($m['actions'])) return;
    $a=$m['actions'];$ac=columns($p,$a);
    foreach(['VergaderingID'=>'INT(10) UNSIGNED NULL','Datum'=>'DATE NULL','Actie'=>'TEXT NULL','Verantwoordelijk'=>'VARCHAR(255) NULL','Omschrijving'=>'TEXT NULL','DatumCompleet'=>'DATE NULL','Compleet'=>'VARCHAR(20) NULL','Update'=>'DATETIME NULL'] as $n=>$type){if(!in_array($n,$ac,true)){$p->exec('ALTER TABLE '.qi($a).' ADD COLUMN '.qi($n).' '.$type);$ac[]=$n;}}
    ensureAuditColumns($p,$a); ensureMeetingFilesTable($p);
}
function ensureMeetingFilesTable(PDO $p): void { $p->exec("CREATE TABLE IF NOT EXISTS `VergaderingBestanden` (`BestandID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`VergaderingID` BIGINT UNSIGNED NOT NULL,`Bestandsnaam` VARCHAR(255) NOT NULL,`Bestandspad` VARCHAR(500) NOT NULL,`Mimetype` VARCHAR(150) NULL,`Bestandsgrootte` BIGINT UNSIGNED NULL,`vvegui_modified_at` DATETIME NULL,`vvegui_modified_by` VARCHAR(128) NULL,PRIMARY KEY (`BestandID`),KEY `idx_VergaderingBestanden_VergaderingID` (`VergaderingID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); }
function meetingDocuments(PDO $p,string $id): array { $s=$p->prepare('SELECT * FROM `VergaderingBestanden` WHERE VergaderingID=? ORDER BY BestandID');$s->execute([$id]);$out=[];foreach($s->fetchAll() as $r){$r['url']='download.php?type=meeting&id='.rawurlencode((string)$r['BestandID']);unset($r['Bestandspad']);$out[]=$r;}return$out; }
function firstValue(array $r,array $keys): string { foreach($keys as $k)if(isset($r[$k])&&$r[$k]!==null&&$r[$k]!=='')return(string)$r[$k];return''; }
function dateDisplayNl(string $v): string { $v=trim($v);if($v===''||str_starts_with($v,'0000-00-00'))return'';if(preg_match('/^(\d{4})-(\d{2})-(\d{2})/',$v,$m))return$m[3].'-'.$m[2].'-'.$m[1];return$v; }
function meetingActions(PDO $p,array $m,string $mid,bool $openOnly=false): array {if(!isset($m['actions']))return[];$t=$m['actions'];$c=columns($p,$t);$fk=findCol($c,['VergaderingID']);if(!$fk)return[];$sql='SELECT * FROM '.qi($t).' WHERE '.qi($fk).'=?';$params=[$mid];if($openOnly){$cc=findCol($c,['Compleet']);if($cc)$sql.=" AND LOWER(TRIM(CAST(".qi($cc)." AS CHAR))) NOT IN ('waar','true','1','ja','gereed','compleet')";}$dk=findCol($c,['Datum']);$dc=findCol($c,['DatumCompleet']);$pk=primaryKey($p,$t,'actions');$order=[];if($dk)$order[]=qi($dk).' ASC';if($dc)$order[]=qi($dc).' ASC';$order[]=qi($pk).' ASC';$sql.=' ORDER BY '.implode(', ',$order);$st=$p->prepare($sql);$st->execute($params);return$st->fetchAll();}
function removeLookupCategoryForeignKeys(PDO $p,array $m): void {
    // Categorieën zijn lookup-only. De historische database-tabellen mogen dus
    // geen referentiële beperking meer opleggen aan de opgeslagen ID-waarde.
    // De twee FK-namen hieronder zijn de namen die in de huidige databasefout
    // daadwerkelijk worden gemeld. Daarnaast zoeken we eventuele anders
    // benoemde FK's op dezelfde kolom op.
    $targets=[];
    if(isset($m['Activa'])) $targets[]=[$m['Activa'],'ActivaCatID',['fk_activa_categorie']];
    if(isset($m['maintenance_plans'])) $targets[]=[$m['maintenance_plans'],'OnderhoudsCategorie',['fk_onderhoud_categorie']];

    foreach($targets as [$table,$column,$knownNames]){
        $toDrop=[];

        // Eerst de bekende constraintnamen rechtstreeks controleren.
        foreach($knownNames as $name){
            $q=$p->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME=? AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE='FOREIGN KEY'"
            );
            $q->execute([$table,$name]);
            if((int)$q->fetchColumn()>0) $toDrop[$name]=true;
        }

        // Daarna alle FK's op de betreffende kolom ophalen. Dit vangt ook
        // handmatig hernoemde constraints op.
        $q=$p->prepare(
            "SELECT DISTINCT k.CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.TABLE_CONSTRAINTS t
               ON t.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA
              AND t.TABLE_NAME=k.TABLE_NAME
              AND t.CONSTRAINT_NAME=k.CONSTRAINT_NAME
              AND t.CONSTRAINT_TYPE='FOREIGN KEY'
             WHERE k.CONSTRAINT_SCHEMA=DATABASE()
               AND k.TABLE_SCHEMA=DATABASE()
               AND k.TABLE_NAME=?
               AND k.COLUMN_NAME=?
               AND k.REFERENCED_TABLE_NAME IS NOT NULL"
        );
        $q->execute([$table,$column]);
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $name){
            if($name!==false && $name!=='') $toDrop[(string)$name]=true;
        }

        foreach(array_keys($toDrop) as $constraint){
            try {
                $p->exec('ALTER TABLE '.qi($table).' DROP FOREIGN KEY '.qi($constraint));
            } catch(PDOException $e) {
                throw new RuntimeException(
                    'Lookup-only configuratie kan de foreign key `'.$constraint.'` op `'.$table.'.'.$column.'` niet verwijderen: '.$e->getMessage(),
                    0,$e
                );
            }
        }

        // Harde eindcontrole. Zolang de FK bestaat, mag de API niet doen alsof
        // lookup-only is ingesteld.
        $q=$p->prepare(
            "SELECT COUNT(*)
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.TABLE_CONSTRAINTS t
               ON t.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA
              AND t.TABLE_NAME=k.TABLE_NAME
              AND t.CONSTRAINT_NAME=k.CONSTRAINT_NAME
              AND t.CONSTRAINT_TYPE='FOREIGN KEY'
             WHERE k.CONSTRAINT_SCHEMA=DATABASE()
               AND k.TABLE_SCHEMA=DATABASE()
               AND k.TABLE_NAME=?
               AND k.COLUMN_NAME=?
               AND k.REFERENCED_TABLE_NAME IS NOT NULL"
        );
        $q->execute([$table,$column]);
        if((int)$q->fetchColumn()>0){
            throw new RuntimeException('Lookup-only configuratie mislukt: er staat nog steeds een foreign key op `'.$table.'.'.$column.'`.');
        }
    }
}

function ensureAssetSchema(PDO $p,string $activaTable): void {
    $c=columns($p,$activaTable);
    $defs=[
        'ParentActivaID'=>'INT(10) UNSIGNED NULL',
        'Fabrikant'=>'VARCHAR(255) NULL',
        'Leverancier'=>'VARCHAR(255) NULL',
        'Merk'=>'VARCHAR(255) NULL',
        'Type'=>'VARCHAR(255) NULL',
        'Serienummer'=>'VARCHAR(255) NULL',
        'Grootte'=>'VARCHAR(255) NULL',
        'Vermogen'=>'VARCHAR(255) NULL',
        'Aansluitwaarde'=>'VARCHAR(255) NULL',
        'Spanning'=>'VARCHAR(255) NULL',
        'Stroomsterkte'=>'VARCHAR(255) NULL',
        'Levensduur'=>'VARCHAR(255) NULL',
        'Locatie'=>'VARCHAR(255) NULL',
        'Toestand'=>'VARCHAR(100) NULL'
    ];
    foreach($defs as $name=>$type){
        if(!in_array($name,$c,true)){$p->exec('ALTER TABLE '.qi($activaTable).' ADD COLUMN '.qi($name).' '.$type);$c[]=$name;}
    }
}

function ensureMaintenanceSchema(PDO $p,array $m): void {
    if(!isset($m['maintenance_plans'])) return;
    $t=$m['maintenance_plans']; $c=columns($p,$t);
    $added=[];
    foreach(['Naam'=>'VARCHAR(255) NULL','Uitvoerder'=>'INT(10) UNSIGNED NULL','MJOPReferentie'=>'VARCHAR(255) NULL','Opmerkingen'=>'TEXT NULL'] as $n=>$type){
        if(!in_array($n,$c,true)){ $p->exec('ALTER TABLE '.qi($t).' ADD COLUMN '.qi($n).' '.$type); $added[]=$n; $c[]=$n; }
    }
    if(in_array('Uitvoerder',$added,true) && in_array('UitvoerderID',$c,true)){
        $p->exec('UPDATE '.qi($t).' SET '.qi('Uitvoerder').'='.qi('UitvoerderID').' WHERE '.qi('Uitvoerder').' IS NULL AND '.qi('UitvoerderID').' IS NOT NULL');
    }
    $p->exec("CREATE TABLE IF NOT EXISTS `OnderhoudsplanDocumenten` (
      `DocumentID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `OnderhoudsplanID` INT(10) UNSIGNED NOT NULL,
      `Type` VARCHAR(20) NOT NULL DEFAULT 'bestand',
      `Bestandsnaam` VARCHAR(255) NULL,
      `Bestandspad` VARCHAR(500) NULL,
      `Url` VARCHAR(2000) NULL,
      `Mimetype` VARCHAR(150) NULL,
      `Bestandsgrootte` INT UNSIGNED NULL,
      `Titel` VARCHAR(255) NULL,
      `Volgorde` INT NOT NULL DEFAULT 0,
      `vvegui_modified_at` DATETIME NULL,
      `vvegui_modified_by` VARCHAR(128) NULL,
      PRIMARY KEY (`DocumentID`), KEY `idx_onderhoudsplan_documenten_plan` (`OnderhoudsplanID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Een bestaande tabel wordt door CREATE TABLE IF NOT EXISTS niet bijgewerkt.
    // Voeg daarom alleen werkelijk ontbrekende kolommen toe. Bestaande structuur/data blijft intact.
    $docCols=columns($p,'OnderhoudsplanDocumenten');
    // MySQL behandelt kolomnamen niet hoofdlettergevoelig. Vergelijk daarom
    // case-insensitief, zodat bestaande `url` en `Url` niet als verschillend
    // worden gezien en nooit opnieuw worden toegevoegd.
    $docColsNorm=[]; foreach($docCols as $existing) $docColsNorm[strtolower((string)$existing)]=true;
    $docDefs=[
      'OnderhoudsplanID'=>'INT(10) UNSIGNED NOT NULL',
      'Type'=>"VARCHAR(20) NOT NULL DEFAULT 'bestand'",
      'Bestandsnaam'=>'VARCHAR(255) NULL',
      'Bestandspad'=>'VARCHAR(500) NULL',
      'Url'=>'VARCHAR(2000) NULL',
      'Mimetype'=>'VARCHAR(150) NULL',
      'Bestandsgrootte'=>'INT UNSIGNED NULL',
      'Titel'=>'VARCHAR(255) NULL',
      'Volgorde'=>'INT NOT NULL DEFAULT 0',
      'vvegui_modified_at'=>'DATETIME NULL',
      'vvegui_modified_by'=>'VARCHAR(128) NULL'
    ];
    foreach($docDefs as $n=>$type){
      $norm=strtolower($n);
      if(!isset($docColsNorm[$norm])){
        $p->exec('ALTER TABLE `OnderhoudsplanDocumenten` ADD COLUMN '.qi($n).' '.$type);
        $docCols[]=$n; $docColsNorm[$norm]=true;
      }
    }
}
function maintenanceOptions(PDO $p,array $m): array {
    $out=['assets'=>[],'relations'=>[],'categories'=>[],'stabu'=>[],'mjop'=>[],'stabu_source'=>'instellingen en database'];
    if(isset($m['Activa'])){
        $t=$m['Activa'];$c=columns($p,$t);$pk=primaryKey($p,$t,'Activa');$name=findCol($c,['Naam','Name','Omschrijving']);
        if($name){
            $sql='SELECT '.qi($pk).' AS id,'.qi($name).' AS label FROM '.qi($t).' WHERE '.activePredicate($c).' ORDER BY '.qi($name);
            $out['assets']=$p->query($sql)->fetchAll();
        }
    }
    if(isset($m['relations'])){
        $t=$m['relations'];$c=columns($p,$t);$pk=primaryKey($p,$t,'relations');$name=actualCol($c,'Naam');$alias=actualCol($c,'Alias');
        if($name||$alias){
            $parts=[];if($name)$parts[]="NULLIF(TRIM(CAST(".qi($name)." AS CHAR)), '')";if($alias)$parts[]="NULLIF(TRIM(CAST(".qi($alias)." AS CHAR)), '')";
            $expr='COALESCE('.implode(',',$parts).')';
            $sql='SELECT '.qi($pk).' AS id,'.$expr.' AS label FROM '.qi($t).' WHERE '.$expr.' IS NOT NULL ORDER BY '.$expr;
            $out['relations']=$p->query($sql)->fetchAll();
        }
    }
    // De opgeslagen labels krijgen voorrang; ontbrekende opties komen uit de catalogustabellen.
    $out['categories']=lookupIdLabelOptions((array)(loadLookups($p)['maintenance_plans']['OnderhoudsCategorie']??[]));
    $lookup=(array)(loadLookups($p)['maintenance_plans']['StabuCode']??[]);
    foreach($lookup as $code=>$label)$out['stabu'][]=['code'=>(string)$code,'label'=>(string)$label];
    $out['mjop']=maintenanceMjopOptions($p);
    return $out;
}
function maintenanceDocuments(PDO $p,string $id): array {
    if(!in_array('OnderhoudsplanDocumenten',tables($p),true)) return [];
    $s=$p->prepare('SELECT DocumentID,OnderhoudsplanID,Type,Bestandsnaam,Bestandspad,Url,Mimetype,Bestandsgrootte,Titel,Volgorde FROM `OnderhoudsplanDocumenten` WHERE OnderhoudsplanID=? ORDER BY Volgorde,DocumentID');$s->execute([$id]);$rows=$s->fetchAll();
    foreach($rows as &$r){$r['url']=$r['Type']==='bestand'?'download.php?type=maintenance&id='.rawurlencode((string)$r['DocumentID']):(string)($r['Url']??'');unset($r['Bestandspad']);} return $rows;
}
function ensureAssetImagesTable(PDO $p,string $activaTable): void {
    $p->exec("CREATE TABLE IF NOT EXISTS `ActivaAfbeeldingen` (
        `AfbeeldingID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `ActivaID` INT(10) UNSIGNED NOT NULL,
        `Bestandsnaam` VARCHAR(255) NOT NULL,
        `Bestandspad` VARCHAR(500) NOT NULL,
        `Mimetype` VARCHAR(100) NULL,
        `Bestandsgrootte` INT UNSIGNED NULL,
        `Titel` VARCHAR(255) NULL,
        `Volgorde` INT NOT NULL DEFAULT 0,
        `vvegui_modified_at` DATETIME NULL,
        `vvegui_modified_by` VARCHAR(128) NULL,
        PRIMARY KEY (`AfbeeldingID`),
        KEY `idx_activa_afbeeldingen_activa` (`ActivaID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $q=$p->prepare("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ActivaAfbeeldingen' AND CONSTRAINT_NAME='fk_activa_afbeeldingen_activa' LIMIT 1");$q->execute();
    if(!$q->fetchColumn()) {
        try { $p->exec('ALTER TABLE `ActivaAfbeeldingen` ADD CONSTRAINT `fk_activa_afbeeldingen_activa` FOREIGN KEY (`ActivaID`) REFERENCES '.qi($activaTable).' (`ActivaID`) ON DELETE CASCADE ON UPDATE CASCADE'); } catch(Throwable $e) {}
    }
}
function assetImages(PDO $p,string $id): array {
    $s=$p->prepare('SELECT AfbeeldingID,ActivaID,Bestandsnaam,Bestandspad,Mimetype,Bestandsgrootte,Titel,Volgorde FROM '.qi('ActivaAfbeeldingen').' WHERE ActivaID=? ORDER BY Volgorde,AfbeeldingID');$s->execute([$id]);$rows=$s->fetchAll();
    foreach($rows as &$r){$r['url']='download.php?type=image&id='.rawurlencode((string)$r['AfbeeldingID']);unset($r['Bestandspad']);}
    return $rows;
}
function ensureAssetDocumentsSchema(PDO $p,string $activaTable): void {
    $p->exec("CREATE TABLE IF NOT EXISTS `ActivaDocumenten` (
      `DocumentID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      `ActivaID` INT(10) UNSIGNED NOT NULL,
      `Type` VARCHAR(20) NOT NULL DEFAULT 'bestand',
      `Bestandsnaam` VARCHAR(255) NULL,
      `Bestandspad` VARCHAR(500) NULL,
      `Url` VARCHAR(2000) NULL,
      `Mimetype` VARCHAR(150) NULL,
      `Bestandsgrootte` INT UNSIGNED NULL,
      `Titel` VARCHAR(255) NULL,
      `Volgorde` INT NOT NULL DEFAULT 0,
      `vvegui_modified_at` DATETIME NULL,
      `vvegui_modified_by` VARCHAR(128) NULL,
      PRIMARY KEY (`DocumentID`), KEY `idx_activa_documenten_activa` (`ActivaID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $q=$p->prepare("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ActivaDocumenten' AND CONSTRAINT_NAME='fk_activa_documenten_activa' LIMIT 1");$q->execute();
    if(!$q->fetchColumn()){
        try{$apk=primaryKey($p,$activaTable,'Activa');$p->exec('ALTER TABLE `ActivaDocumenten` ADD CONSTRAINT `fk_activa_documenten_activa` FOREIGN KEY (`ActivaID`) REFERENCES '.qi($activaTable).' ('.qi($apk).') ON DELETE CASCADE ON UPDATE CASCADE');}catch(Throwable $e){}
    }
    $p->exec("CREATE TABLE IF NOT EXISTS `ActivaBronKoppelingen` (`KoppelingID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`ActivaID` INT(10) UNSIGNED NOT NULL,`BronType` VARCHAR(20) NOT NULL,`BronID` BIGINT UNSIGNED NOT NULL,`Titel` VARCHAR(255) NULL,`AangemaaktOp` DATETIME NOT NULL,`AangemaaktDoor` VARCHAR(128) NULL,PRIMARY KEY (`KoppelingID`),UNIQUE KEY `uq_activa_bron` (`ActivaID`,`BronType`,`BronID`),KEY `idx_bron` (`BronType`,`BronID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function assetDocuments(PDO $p,string $id): array {
    $available=tables($p);$rows=[];
    if(in_array('ActivaDocumenten',$available,true)){$s=$p->prepare('SELECT DocumentID,ActivaID,Type,Bestandsnaam,Bestandspad,Url,Mimetype,Bestandsgrootte,Titel,Volgorde FROM `ActivaDocumenten` WHERE ActivaID=? ORDER BY Volgorde,DocumentID');$s->execute([$id]);$rows=$s->fetchAll();}
    foreach($rows as &$r){$r['url']=$r['Type']==='bestand'?'download.php?type=asset-document&id='.rawurlencode((string)$r['DocumentID']):(string)($r['Url']??'');unset($r['Bestandspad']);}
    unset($r);
    if(in_array('ActivaBronKoppelingen',$available,true)&&in_array('DocumentBibliotheek',$available,true)){$s=$p->prepare("SELECT k.KoppelingID,k.BronID,k.Titel KoppelingTitel,d.Titel,d.OrigineleNaam,d.Bron,d.ExterneUrl FROM ActivaBronKoppelingen k JOIN DocumentBibliotheek d ON d.DocumentID=k.BronID WHERE k.ActivaID=? AND k.BronType='document' AND d.IsHuidig=1 AND d.VerwijderdOp IS NULL ORDER BY k.KoppelingID");$s->execute([$id]);foreach($s->fetchAll() as $d)$rows[]=['KoppelingID'=>(int)$d['KoppelingID'],'DocumentID'=>'bron-'.(int)$d['KoppelingID'],'Type'=>'bron-document','Titel'=>$d['Titel']?:($d['KoppelingTitel']?:$d['OrigineleNaam']),'Bestandsnaam'=>$d['OrigineleNaam'],'url'=>$d['Bron']==='externe-link'?(string)$d['ExterneUrl']:'document_download.php?id='.rawurlencode((string)$d['BronID']).'&view=1'];}
    if(in_array('ActivaBronKoppelingen',$available,true)&&in_array('EmailBerichten',$available,true)){$s=$p->prepare("SELECT k.KoppelingID,k.BronID,k.Titel KoppelingTitel,e.Subject,e.Sender,e.SentAt FROM ActivaBronKoppelingen k JOIN EmailBerichten e ON e.EmailBerichtID=k.BronID WHERE k.ActivaID=? AND k.BronType='email' ORDER BY e.SentAt DESC,k.KoppelingID DESC");$s->execute([$id]);foreach($s->fetchAll() as $e)$rows[]=['KoppelingID'=>(int)$e['KoppelingID'],'DocumentID'=>'bron-'.(int)$e['KoppelingID'],'Type'=>'bron-email','Titel'=>$e['Subject']?:($e['KoppelingTitel']?:'E-mail'),'Bestandsnaam'=>$e['Sender'].($e['SentAt']?' · '.$e['SentAt']:''),'url'=>'email_view.php?id='.rawurlencode((string)$e['BronID'])];}
    return $rows;
}
function keyNorm(string $k): string { return preg_replace('/[^a-z0-9]/','',strtolower($k)); }
function actualCol(array $cols,string $wanted): ?string { $n=keyNorm($wanted); foreach($cols as $c) if(keyNorm($c)===$n)return $c; return null; }
function requiredFields(PDO $p,array $m): array { $out=['contacts'=>[],'addresses'=>[],'relations'=>[]];$c=columns($p,$m['contacts']);foreach(['AchterNaam'] as $x){$a=actualCol($c,$x);if($a)$out['contacts'][]=$a;}$r=columns($p,$m['relations']);$a=actualCol($r,'Naam');if($a)$out['relations'][]=$a;return$out; }
function validateRequired(PDO $p,array $m,string $e,array $body,?string $id): void { if($id!==null)return;$req=requiredFields($p,$m)[$e]??[];$missing=[];foreach($req as $k)if(!isset($body[$k])||trim((string)$body[$k])==='')$missing[]=$k;if($missing)throw new RuntimeException('Verplichte velden ontbreken: '.implode(', ',$missing)); }
function isIdField(string $k,string $pk): bool { $n=keyNorm($k); return $k===$pk || $n==='id' || str_ends_with($n,'id'); }
function saveRow(PDO $p,string $t,string $e,mixed $id,array $data,string $who): string {
    $cols=columns($p,$t);$pk=primaryKey($p,$t,$e);$safe=[];
    foreach($data as $k=>$v){
        if(!in_array($k,$cols,true)||$k===$pk||in_array($k,['legacy_json','vvegui_modified_at','vvegui_modified_by','Update','Gewijzigd','Gewijzigd door'],true))continue;
        $safe[$k]=($v==='')?null:$v;
    }
    if(!$safe)throw new RuntimeException('Geen wijzigbare velden ontvangen.');
    if(in_array('vvegui_modified_at',$cols,true))$safe['vvegui_modified_at']=date('Y-m-d H:i:s');
    if(in_array('vvegui_modified_by',$cols,true))$safe['vvegui_modified_by']=$who;
    if($id!==null&&$id!==''){
        $set=[];$vals=[];
        foreach($safe as $k=>$v){$set[]=qi($k).'=?';$vals[]=$v;}
        $vals[]=$id;$q=$p->prepare('UPDATE '.qi($t).' SET '.implode(', ',$set).' WHERE '.qi($pk).'=?');$q->execute($vals);
        return(string)$id;
    }
    if(!$p->inTransaction())$p->beginTransaction();
    try{
        $newId=allocatePersistentId($p,$e,$t,$pk,$who);
        $safe[$pk]=$newId;$names=array_keys($safe);
        $q=$p->prepare('INSERT INTO '.qi($t).' ('.implode(',',array_map('qi',$names)).') VALUES ('.implode(',',array_fill(0,count($names),'?')).')');
        $q->execute(array_values($safe));$p->commit();return$newId;
    }catch(Throwable $ex){if($p->inTransaction())$p->rollBack();throw$ex;}
}
function deleteRow(PDO $p,string $t,string $e,string $id): string {
    $pk=primaryKey($p,$t,$e);$cols=columns($p,$t);if(!getRow($p,$t,$e,$id))throw new RuntimeException('Record niet gevonden.');
    $active=actualCol($cols,'Actief');$status=actualCol($cols,'Status');
    if($active){
        $set=qi($active).'=?';$vals=['Niet waar'];
        if(in_array('vvegui_modified_at',$cols,true)){$set.=', '.qi('vvegui_modified_at').'=?';$vals[]=date('Y-m-d H:i:s');}
        if(in_array('vvegui_modified_by',$cols,true)){$set.=', '.qi('vvegui_modified_by').'=?';$vals[]='admin';}
        $vals[]=$id;$q=$p->prepare('UPDATE '.qi($t).' SET '.$set.' WHERE '.qi($pk).'=?');$q->execute($vals);
    }elseif($status){
        $set=qi($status).'=?';$vals=['Inactief'];
        if(in_array('vvegui_modified_at',$cols,true)){$set.=', '.qi('vvegui_modified_at').'=?';$vals[]=date('Y-m-d H:i:s');}
        $vals[]=$id;$q=$p->prepare('UPDATE '.qi($t).' SET '.$set.' WHERE '.qi($pk).'=?');$q->execute($vals);
    }else throw new RuntimeException('Dit recordtype heeft geen Actief-/Statusveld en kan niet veilig worden gedeactiveerd.');
    return$id;
}
