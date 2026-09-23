<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function maintenanceExtraOut(array $value): never { echo json_encode($value, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function maintenanceExtraSchema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS OnderhoudsplanActies (ActieID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, OnderhoudsplanID INT UNSIGNED NOT NULL, Actie TEXT NOT NULL, Verantwoordelijk VARCHAR(255) NULL, DatumCompleet DATE NULL, Compleet TINYINT(1) NOT NULL DEFAULT 0, vvegui_modified_at DATETIME NOT NULL, vvegui_modified_by VARCHAR(128) NOT NULL, INDEX idx_onderhoudsplan_actie (OnderhoudsplanID,Compleet)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS OnderhoudsplanFotos (FotoID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, OnderhoudsplanID INT UNSIGNED NOT NULL, Bestandsnaam VARCHAR(255) NOT NULL, Bestandspad VARCHAR(500) NOT NULL, Mimetype VARCHAR(100) NOT NULL, Bestandsgrootte INT UNSIGNED NOT NULL, vvegui_modified_at DATETIME NOT NULL, vvegui_modified_by VARCHAR(128) NOT NULL, INDEX idx_onderhoudsplan_foto (OnderhoudsplanID)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS OnderhoudsplanUitvoerders (OnderhoudsplanID INT UNSIGNED NOT NULL, RelatieID INT UNSIGNED NOT NULL, PRIMARY KEY (OnderhoudsplanID,RelatieID), INDEX idx_onderhoudsplan_relatie (RelatieID)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function maintenanceExtraDate(string $date): ?string {
    if($date==='')return null;
    if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$date,$parts)||!checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1]))throw new RuntimeException('Ongeldige datum.');
    return $date;
}
try {
    $user=requireLogin();
    $db=db(loadConfigCompat(tenantPrivateFile('config.php')));
    
    $planId=(int)($_GET['id']??0);
    if($planId<1)throw new RuntimeException('Onderhoudsplan-ID ontbreekt.');
    $check=$db->prepare('SELECT COUNT(*) FROM Onderhoudsplannen WHERE OnderhoudsplanID=?');$check->execute([$planId]);
    if(!(int)$check->fetchColumn())throw new RuntimeException('Onderhoudsplan niet gevonden.');
    $action=(string)($_GET['action']??'list');
    if($action==='list') {
        $s=$db->prepare('SELECT ActieID,Actie,Verantwoordelijk,DatumCompleet,Compleet FROM OnderhoudsplanActies WHERE OnderhoudsplanID=? ORDER BY Compleet,ActieID DESC');$s->execute([$planId]);$actions=$s->fetchAll(PDO::FETCH_ASSOC);
        $s=$db->prepare('SELECT FotoID,Bestandsnaam FROM OnderhoudsplanFotos WHERE OnderhoudsplanID=? ORDER BY FotoID DESC');$s->execute([$planId]);$photos=$s->fetchAll(PDO::FETCH_ASSOC);
        $s=$db->prepare("SELECT u.RelatieID,COALESCE(NULLIF(TRIM(r.naam),''),NULLIF(TRIM(r.alias),''),CONCAT('Relatie ',u.RelatieID)) AS Naam FROM OnderhoudsplanUitvoerders u JOIN relations r ON r.relatie_id=u.RelatieID WHERE u.OnderhoudsplanID=? ORDER BY Naam");$s->execute([$planId]);$relations=$s->fetchAll(PDO::FETCH_ASSOC);
        maintenanceExtraOut(['ok'=>true,'actions'=>$actions,'photos'=>$photos,'relations'=>$relations]);
    }
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Ongeldige aanvraag.');
    requireAdmin();verifyCsrf();
    $body=json_decode(file_get_contents('php://input'),true);if(!is_array($body))$body=[];
    if($action==='save_action'){
        $text=trim((string)($body['Actie']??''));if($text===''||mb_strlen($text)>2000)throw new RuntimeException('Vul een actie van maximaal 2000 tekens in.');
        $who=substr(trim((string)($body['Verantwoordelijk']??'')),0,255);
        $date=maintenanceExtraDate((string)($body['DatumCompleet']??''));$done=!empty($body['Compleet'])?1:0;$aid=(int)($body['ActieID']??0);$now=date('Y-m-d H:i:s');
        if($aid){$s=$db->prepare('SELECT COUNT(*) FROM OnderhoudsplanActies WHERE ActieID=? AND OnderhoudsplanID=?');$s->execute([$aid,$planId]);if(!(int)$s->fetchColumn())throw new RuntimeException('Actiepunt niet gevonden.');$s=$db->prepare('UPDATE OnderhoudsplanActies SET Actie=?,Verantwoordelijk=?,DatumCompleet=?,Compleet=?,vvegui_modified_at=?,vvegui_modified_by=? WHERE ActieID=? AND OnderhoudsplanID=?');$s->execute([$text,$who,$date,$done,$now,(string)$user['username'],$aid,$planId]);}
        else {$s=$db->prepare('INSERT INTO OnderhoudsplanActies (OnderhoudsplanID,Actie,Verantwoordelijk,DatumCompleet,Compleet,vvegui_modified_at,vvegui_modified_by) VALUES (?,?,?,?,?,?,?)');$s->execute([$planId,$text,$who,$date,$done,$now,(string)$user['username']]);}
        maintenanceExtraOut(['ok'=>true]);
    }
    if($action==='delete_action'){$aid=(int)($body['ActieID']??0);$s=$db->prepare('DELETE FROM OnderhoudsplanActies WHERE ActieID=? AND OnderhoudsplanID=?');$s->execute([$aid,$planId]);if(!$s->rowCount())throw new RuntimeException('Actiepunt niet gevonden.');maintenanceExtraOut(['ok'=>true]);}
    if($action==='link_relation'||$action==='unlink_relation'){
        $rid=(int)($body['RelatieID']??0);if($rid<1)throw new RuntimeException('Kies een uitvoerder.');
        if($action==='link_relation'){$s=$db->prepare('SELECT COUNT(*) FROM relations WHERE relatie_id=?');$s->execute([$rid]);if(!(int)$s->fetchColumn())throw new RuntimeException('Relatie niet gevonden.');$s=$db->prepare('INSERT IGNORE INTO OnderhoudsplanUitvoerders (OnderhoudsplanID,RelatieID) VALUES (?,?)');}
        else $s=$db->prepare('DELETE FROM OnderhoudsplanUitvoerders WHERE OnderhoudsplanID=? AND RelatieID=?');
        $s->execute([$planId,$rid]);maintenanceExtraOut(['ok'=>true]);
    }
    if($action==='upload_photo'){
        $file=$_FILES['photo']??null;if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Foto-upload mislukt.');
        $size=(int)$file['size'];if($size<1||$size>15*1024*1024)throw new RuntimeException('Foto moet kleiner zijn dan 15 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
        if(!$ext||!getimagesize($file['tmp_name']))throw new RuntimeException('Alleen geldige JPG-, PNG- en WebP-foto’s zijn toegestaan.');
        $filename=bin2hex(random_bytes(16)).'.'.$ext;$path=uploadDirectory('onderhoudsplannen_fotos').'/'.$filename;
        if(!move_uploaded_file($file['tmp_name'],$path))throw new RuntimeException('Foto kon niet worden opgeslagen.');
        $s=$db->prepare('INSERT INTO OnderhoudsplanFotos (OnderhoudsplanID,Bestandsnaam,Bestandspad,Mimetype,Bestandsgrootte,vvegui_modified_at,vvegui_modified_by) VALUES (?,?,?,?,?,?,?)');
        $s->execute([$planId,substr(basename((string)$file['name']),0,255),storedPath('onderhoudsplannen_fotos',$filename),$mime,$size,date('Y-m-d H:i:s'),(string)$user['username']]);maintenanceExtraOut(['ok'=>true]);
    }
    if($action==='delete_photo'){$fid=(int)($body['FotoID']??0);$s=$db->prepare('SELECT Bestandspad FROM OnderhoudsplanFotos WHERE FotoID=? AND OnderhoudsplanID=?');$s->execute([$fid,$planId]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Foto niet gevonden.');$s=$db->prepare('DELETE FROM OnderhoudsplanFotos WHERE FotoID=? AND OnderhoudsplanID=?');$s->execute([$fid,$planId]);try{$path=resolveStoredFile((string)$row['Bestandspad']);if(is_file($path))unlink($path);}catch(Throwable $ignored){}maintenanceExtraOut(['ok'=>true]);}
    throw new RuntimeException('Onbekende handeling.');
} catch(Throwable $e){$safe=$e instanceof RuntimeException&&!($e instanceof PDOException);http_response_code($safe?400:500);maintenanceExtraOut(['ok'=>false,'error'=>$safe?$e->getMessage():'Interne fout. Referentie: '.securityIncident($e)]);}
