<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/maintenance_mjop.php';
requireLogin();
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$id){http_response_code(400);exit('Ongeldig onderhoudsplan.');}
$db=db(loadConfigCompat(tenantPrivateFile('config.php')));
$query=static function(string $sql,array $params=[])use($db):array{$stmt=$db->prepare($sql);$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);};
$plan=$query('SELECT * FROM Onderhoudsplannen WHERE OnderhoudsplanID=?',[$id])[0]??null;
if(!$plan){http_response_code(404);exit('Onderhoudsplan niet gevonden.');}
$mjop=trim((string)($plan['MJOPReferentie']??''))!==''?maintenanceMjopFind($db,trim((string)$plan['MJOPReferentie'])):null;
$assets=$query('SELECT Naam FROM Activa WHERE ActivaID=?',[(int)($plan['ActivaID']??0)]);
$relations=$query("SELECT COALESCE(NULLIF(TRIM(r.naam),''),NULLIF(TRIM(r.alias),''),CONCAT('Relatie ',u.RelatieID)) AS Naam FROM OnderhoudsplanUitvoerders u JOIN relations r ON r.relatie_id=u.RelatieID WHERE u.OnderhoudsplanID=? ORDER BY Naam",[$id]);
$legacyId=(int)($plan['Uitvoerder']??0);
if($legacyId){$legacy=$query("SELECT COALESCE(NULLIF(TRIM(naam),''),NULLIF(TRIM(alias),''),CONCAT('Relatie ',relatie_id)) AS Naam FROM relations WHERE relatie_id=?",[$legacyId]);if($legacy && !in_array($legacy[0]['Naam'],array_column($relations,'Naam'),true))$relations[]=$legacy[0];}
$documents=$query('SELECT Titel,Bestandsnaam,Url FROM OnderhoudsplanDocumenten WHERE OnderhoudsplanID=? ORDER BY Volgorde,DocumentID',[$id]);
$actions=$query('SELECT Actie,Verantwoordelijk,DatumCompleet,Compleet FROM OnderhoudsplanActies WHERE OnderhoudsplanID=? ORDER BY Compleet,ActieID DESC',[$id]);
$photos=$query('SELECT FotoID,Bestandsnaam,Bestandspad,Mimetype FROM OnderhoudsplanFotos WHERE OnderhoudsplanID=? ORDER BY FotoID',[$id]);
function maintenancePdfText(string $value):string{$encoded=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$value);return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],(string)$encoded);}
function maintenancePdfLines(string $value,int $width=84):array{$value=str_replace(["\r\n","\r"],"\n",trim($value));if($value==='')return []; $lines=[];foreach(explode("\n",$value) as $paragraph){$paragraph=trim(preg_replace('/[ \t]+/u',' ',$paragraph)??'');if($paragraph===''){$lines[]='';continue;}foreach(explode("\n",wordwrap($paragraph,$width,"\n",true)) as $line)$lines[]=$line;}return $lines;}
$pages=[];$stream='';$y=0;$imageItems=[];
$newPage=function()use(&$pages,&$stream,&$y):void{if($stream!=='')$pages[]=$stream;$stream="0.09 0.13 0.17 rg\n0 785 595 57 re f\n";$stream.="BT /F2 17 Tf 1 1 1 rg 35 807 Td (VvE Beheer) Tj ET\n";$stream.="BT /F1 9 Tf 0.83 0.89 0.94 rg 35 792 Td (Werkopdracht onderhoud) Tj ET\n";$y=760;};
$draw=function(string $text,float $x,float $size=10,bool $bold=false,array $color=[0.10,0.14,0.18])use(&$stream,&$y):void{$stream.=sprintf("BT /%s %.1f Tf %.3f %.3f %.3f rg %.1f %.1f Td (%s) Tj ET\n",$bold?'F2':'F1',$size,$color[0],$color[1],$color[2],$x,$y,maintenancePdfText($text));};
$ensure=function(float $height)use(&$y,$newPage):void{if($y-$height<45)$newPage();};
$write=function(string $text,int $width=84,int $indent=0,bool $bold=false)use(&$y,$draw,$ensure):void{foreach(maintenancePdfLines($text,$width) as $line){$ensure(17);if($line!=='')$draw($line,38+$indent,$bold?11:10,$bold);$y-=($line===''?9:15);}};
$section=function(string $title)use(&$y,&$stream,$draw,$ensure):void{$ensure(43);$y-=10;$stream.=sprintf("0.90 0.95 1 rg\n32 %.1f 531 26 re f\n",$y-6);$draw($title,40,12,true,[0.09,0.27,0.48]);$y-=33;};
$newPage();$write((string)($plan['Naam']?:'Onderhoudsplan #'.$id),60,0,true);$y-=4;
$write('Werkopdracht / onderhoudsplan #'.$id.'  |  Status: '.(string)($plan['Status']??'-').'  |  Rapportdatum: '.date('d-m-Y'),100);
$section('Locatie en planning');
$write('Activum: '.(string)($assets[0]['Naam']??'Niet gekoppeld'));
foreach(['OnderhoudsCategorie'=>'Categorie','DatumInspectie'=>'Datum inspectie','Planjaar'=>'Planjaar','UitvoeringsJaar'=>'Uitvoeringsjaar','StabuCode'=>'STABU-code','MJOPReferentie'=>'MJOP-referentie','PlanBedrag'=>'Planbedrag'] as $field=>$label)if(trim((string)($plan[$field]??''))!=='')$write($label.': '.($field==='MJOPReferentie'&&$mjop?$mjop['label']:(string)$plan[$field]));
$section('Uitvoerende firma’s');foreach($relations as $item)$write('- '.(string)$item['Naam'],80,8);if(!$relations)$write('Nog geen uitvoerder gekoppeld.');
$section('Werkomschrijving');$write(trim((string)($plan['Werkomschrijving']??''))?:'Geen werkomschrijving vastgelegd.');
$section('Opmerkingen en bijzonderheden');$write(trim((string)($plan['Opmerkingen']??''))?:'Geen opmerkingen vastgelegd.');
$section('Actiepunten');foreach($actions as $item)$write(((int)$item['Compleet']?'[x] ':'[ ] ').(string)$item['Actie'].'  |  '.(string)($item['Verantwoordelijk']?:'geen verantwoordelijke').(!empty($item['DatumCompleet'])?'  |  gereed op '.(string)$item['DatumCompleet']:''),76,8);if(!$actions)$write('Geen actiepunten vastgelegd.');
$section('Gekoppelde documenten');foreach($documents as $item){$title=(string)($item['Titel']??'');if($title==='')$title=(string)($item['Bestandsnaam']??'');if($title==='')$title=(string)($item['Url']??'');$write('- '.($title?:'Document'),80,8);}if(!$documents)$write('Geen documenten gekoppeld.');
$section('Uitvoering en akkoord');$write('Werkzaamheden uitgevoerd op: ____________________    Door: ____________________');$y-=10;$write('Opmerkingen bij uitvoering: _______________________________________________________');$y-=10;$write('Handtekening uitvoerder: ____________________    Handtekening opdrachtgever: ____________________');
if($photos){$section('Foto’s');foreach($photos as $photo){$write((string)$photo['Bestandsnaam'],75,0,true);try{$file=resolveStoredFile((string)$photo['Bestandspad']);$bytes=file_get_contents($file);if($bytes===false)continue;$jpeg=null;$size=@getimagesizefromstring($bytes);if(!$size)continue;if(function_exists('imagecreatefromstring')){$source=@imagecreatefromstring($bytes);if($source){$w=imagesx($source);$h=imagesy($source);$ratio=min(1,1000/max($w,$h));$target=imagecreatetruecolor(max(1,(int)round($w*$ratio)),max(1,(int)round($h*$ratio)));imagefill($target,0,0,imagecolorallocate($target,255,255,255));imagecopyresampled($target,$source,0,0,0,0,imagesx($target),imagesy($target),$w,$h);ob_start();imagejpeg($target,null,78);$jpeg=ob_get_clean();$size=[imagesx($target),imagesy($target)];imagedestroy($target);imagedestroy($source);}}if($jpeg===null && $photo['Mimetype']==='image/jpeg' && (int)($size['channels']??3)===3)$jpeg=$bytes;if(!$jpeg)continue;$displayW=min(500,230*$size[0]/max(1,$size[1]));$displayH=min(230,500*$size[1]/max(1,$size[0]));$ensure($displayH+25);$name='Im'.(count($imageItems)+1);$imageItems[]=['name'=>$name,'bytes'=>$jpeg,'width'=>$size[0],'height'=>$size[1]];$stream.=sprintf("q %.1f 0 0 %.1f 38 %.1f cm /%s Do Q\n",$displayW,$displayH,$y-$displayH,$name);$y-=$displayH+20;}catch(Throwable $ignored){$write('(Foto kon niet in het rapport worden opgenomen.)');}}}
if($stream!=='')$pages[]=$stream;
$objects=[];$pageIds=[];$next=5;foreach($pages as $_){$pageIds[]=$next;$next+=2;}
$imageRefs=[];foreach($imageItems as $item){$imageRefs[$item['name']]=$next++;$objects[$imageRefs[$item['name']]]='<< /Type /XObject /Subtype /Image /Width '.$item['width'].' /Height '.$item['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($item['bytes'])." >>\nstream\n".$item['bytes']."\nendstream";}
$xobjects='';foreach($imageRefs as $name=>$ref)$xobjects.='/'.$name.' '.$ref.' 0 R ';
$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';$objects[2]='<< /Type /Pages /Kids ['.implode(' ',array_map(static fn($pageId)=>$pageId.' 0 R',$pageIds)).'] /Count '.count($pages).' >>';
$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';$objects[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
foreach($pages as $index=>$page){$pageId=$pageIds[$index];$contentId=$pageId+1;$page.=sprintf("BT /F1 8 Tf 0.45 0.50 0.55 rg 35 25 Td (Pagina %d van %d - %s) Tj ET\n",$index+1,count($pages),maintenancePdfText(date('d-m-Y H:i')));$objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> /XObject << '.$xobjects.'>> >> /Contents '.$contentId.' 0 R >>';$objects[$contentId]='<< /Length '.strlen($page)." >>\nstream\n".$page."\nendstream";}
ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $objectId=>$object){$offsets[$objectId]=strlen($pdf);$pdf.=$objectId." 0 obj\n".$object."\nendobj\n";}$xref=strlen($pdf);$count=max(array_keys($objects))+1;$pdf.="xref\n0 ".$count."\n0000000000 65535 f \n";for($objectId=1;$objectId<$count;$objectId++)$pdf.=sprintf('%010d 00000 n ',(int)($offsets[$objectId]??0))."\n";$pdf.="trailer\n<< /Size ".$count." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="VvE-werkopdracht-onderhoudsplan-'.$id.'-'.date('Ymd').'.pdf"');header('Content-Length: '.strlen($pdf));header('X-Content-Type-Options: nosniff');echo $pdf;
