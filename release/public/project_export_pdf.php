<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/maintenance_mjop.php';
requireLogin();
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$id){http_response_code(400);exit('Ongeldig project.');}
$db=db(loadConfigCompat(tenantPrivateFile('config.php')));
$query=function(string $sql,array $params=[])use($db):array{$stmt=$db->prepare($sql);$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);};
$project=$query('SELECT * FROM VveGuiProjecten WHERE ProjectID=?',[$id])[0]??null;
if(!$project){http_response_code(404);exit('Project niet gevonden.');}
$mjop=trim((string)($project['MJOPReferentie']??''))!==''?maintenanceMjopFind($db,trim((string)$project['MJOPReferentie'])):null;
$lookup=[];if(is_file(tenantPrivateFile('lookups.php'))){$loaded=include tenantPrivateFile('lookups.php');if(is_array($loaded))$lookup=$loaded;}
$stabuCode=trim((string)($project['StabuCode']??''));
$stabuLabel=$stabuCode!==''?(string)(($lookup['maintenance_plans']['StabuCode'][$stabuCode]??'')):'';
$checklist=$query('SELECT Fase,Criterium,Datum,Compleet FROM ProjectChecklist WHERE ProjectID=? ORDER BY ChecklistID',[$id]);
$assets=$query('SELECT a.Naam FROM ProjectActiva p JOIN Activa a ON a.ActivaID=p.ActivaID WHERE p.ProjectID=? ORDER BY a.Naam',[$id]);
$relations=$query("SELECT COALESCE(NULLIF(TRIM(r.naam),''),NULLIF(TRIM(r.alias),''),CONCAT('Relatie ',r.relatie_id)) AS Naam FROM ProjectRelaties p JOIN relations r ON r.relatie_id=p.RelatieID WHERE p.ProjectID=? ORDER BY Naam",[$id]);
$documents=$query('SELECT d.Titel,d.OrigineleNaam FROM ProjectDocumenten p JOIN DocumentBibliotheek d ON d.DocumentID=p.DocumentID WHERE p.ProjectID=? AND d.VerwijderdOp IS NULL ORDER BY d.Titel',[$id]);
$emails=$query('SELECT e.Subject,e.Sender,e.SentAt FROM ProjectEmails p JOIN EmailBerichten e ON e.EmailBerichtID=p.EmailBerichtID WHERE p.ProjectID=? ORDER BY e.SentAt DESC',[$id]);
$actions=$query('SELECT Actie,Verantwoordelijk,DatumCompleet,Compleet FROM ProjectActies WHERE ProjectID=? ORDER BY Compleet,ActieID DESC',[$id]);

function projectPdfText(string $value):string{
    $encoded=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$value);
    return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],(string)$encoded);
}
function projectPdfLines(string $value,int $width=84):array{
    $value=str_replace(["\r\n","\r"],"\n",trim($value));
    if($value==='')return [];
    $lines=[];
    foreach(explode("\n",$value) as $paragraph){
        $paragraph=trim(preg_replace('/[ \t]+/u',' ',$paragraph)??'');
        if($paragraph===''){$lines[]='';continue;}
        foreach(explode("\n",wordwrap($paragraph,$width,"\n",true)) as $line)$lines[]=$line;
    }
    return $lines;
}
$pages=[];$stream='';$y=0;
$newPage=function()use(&$pages,&$stream,&$y):void{
    if($stream!=='')$pages[]=$stream;
    $stream="0.09 0.13 0.17 rg\n0 785 595 57 re f\n";
    $stream.="BT /F2 17 Tf 1 1 1 rg 35 807 Td (VvE Beheer) Tj ET\n";
    $stream.="BT /F1 9 Tf 0.83 0.89 0.94 rg 35 792 Td (Projectrapport) Tj ET\n";
    $y=760;
};
$draw=function(string $text,float $x,float $size=10,bool $bold=false,array $color=[0.10,0.14,0.18])use(&$stream,&$y):void{
    $stream.=sprintf("BT /%s %.1f Tf %.3f %.3f %.3f rg %.1f %.1f Td (%s) Tj ET\n",$bold?'F2':'F1',$size,$color[0],$color[1],$color[2],$x,$y,projectPdfText($text));
};
$ensure=function(float $height)use(&$y,$newPage):void{if($y-$height<45)$newPage();};
$write=function(string $text,int $width=84,int $indent=0,bool $bold=false)use(&$y,$draw,$ensure):void{
    foreach(projectPdfLines($text,$width) as $line){$ensure(17);if($line!=='')$draw($line,38+$indent,$bold?11:10,$bold);$y-=($line===''?9:15);}
};
$section=function(string $title)use(&$y,&$stream,$draw,$ensure):void{
    $ensure(43);$y-=10;$stream.=sprintf("0.90 0.95 1 rg\n32 %.1f 531 26 re f\n",$y-6);$draw($title,40,12,true,[0.09,0.27,0.48]);$y-=33;
};
$newPage();
$write((string)$project['Naam'],60,0,true);$y-=3;
$write('Project #'.$id.'  |  Status: '.(string)$project['Status'].'  |  Laatste wijziging: '.(string)$project['vvegui_modified_at'].' door '.(string)$project['vvegui_modified_by'],100);
if($stabuCode!=='')$write('STABU-code: '.$stabuCode.($stabuLabel!==''?' — '.$stabuLabel:''),100);
if(trim((string)($project['MJOPReferentie']??''))!=='')$write('MJOP-referentie: '.($mjop?$mjop['label']:(string)$project['MJOPReferentie']),100);
foreach(['Haalbaarheid'=>'Haalbaarheid','Planning'=>'Planning','Uitvoering'=>'Uitvoering','OpleveringNazorg'=>'Oplevering en nazorg'] as $field=>$title){
    $section($title);
    $write(trim((string)$project[$field])?:'Geen tekst vastgelegd.');
    $items=array_values(array_filter($checklist,static fn($row)=>(string)$row['Fase']===$field));
    if($items){$y-=5;$write('CHECKLIST',84,0,true);foreach($items as $item){$mark=(int)$item['Compleet']?'[x]':'[ ]';$date=trim((string)($item['Datum']??''));$write($mark.'  '.(string)$item['Criterium'].($date!==''?'  |  '.$date:''),78,8);}}
}
$section('Gekoppelde activa');foreach($assets as $item)$write('- '.(string)$item['Naam'],80,8);if(!$assets)$write('Geen activa gekoppeld.');
$section('Uitvoerende firma\'s / relaties');foreach($relations as $item)$write('- '.(string)$item['Naam'],80,8);if(!$relations)$write('Geen relaties gekoppeld.');
$section('Gekoppelde bestanden');foreach($documents as $item)$write('- '.(string)($item['Titel']?:$item['OrigineleNaam']),80,8);if(!$documents)$write('Geen bestanden gekoppeld.');
$section('Gekoppelde e-mails');foreach($emails as $mail)$write('- '.(string)($mail['Subject']?:'(geen onderwerp)').'  |  '.(string)$mail['SentAt'].'  |  '.(string)$mail['Sender'],80,8);if(!$emails)$write('Geen e-mails gekoppeld.');
$section('Actieregister');foreach($actions as $action)$write('- '.(string)$action['Actie'].'  |  '.(string)$action['Verantwoordelijk'].'  |  '.((int)$action['Compleet']?'gereed':'open').(!empty($action['DatumCompleet'])?'  |  '.(string)$action['DatumCompleet']:''),80,8);if(!$actions)$write('Geen actiepunten.');
if($stream!=='')$pages[]=$stream;
$objects=[];$pageIds=[];$next=5;foreach($pages as $_){$pageIds[]=$next;$next+=2;}
$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
$objects[2]='<< /Type /Pages /Kids ['.implode(' ',array_map(static fn($pageId)=>$pageId.' 0 R',$pageIds)).'] /Count '.count($pages).' >>';
$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
$objects[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
foreach($pages as $index=>$page){
    $pageId=$pageIds[$index];$contentId=$pageId+1;
    $page.=sprintf("BT /F1 8 Tf 0.45 0.50 0.55 rg 35 25 Td (Pagina %d van %d - %s) Tj ET\n",$index+1,count($pages),projectPdfText(date('d-m-Y H:i')));
    $objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';
    $objects[$contentId]='<< /Length '.strlen($page)." >>\nstream\n".$page."\nendstream";
}
ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];
foreach($objects as $objectId=>$object){$offsets[$objectId]=strlen($pdf);$pdf.=$objectId." 0 obj\n".$object."\nendobj\n";}
$xref=strlen($pdf);$size=max(array_keys($objects))+1;$pdf.="xref\n0 ".$size."\n0000000000 65535 f \n";
for($objectId=1;$objectId<$size;$objectId++)$pdf.=sprintf('%010d 00000 n ',(int)($offsets[$objectId]??0))."\n";
$pdf.="trailer\n<< /Size ".$size." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="VvE-project-'.$id.'-'.date('Ymd').'.pdf"');
header('Content-Length: '.strlen($pdf));
echo $pdf;
