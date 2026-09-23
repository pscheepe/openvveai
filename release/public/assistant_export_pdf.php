<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/auth.php';
requireLogin();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
verifyCsrf();
$html=(string)($_POST['content']??'');$question=trim((string)($_POST['question']??''));
if($html===''||strlen($html)>1500000){http_response_code(400);exit('Geen geldige zoekresultaten ontvangen.');}
function pdfText(string $value):string{$value=html_entity_decode(strip_tags($value),ENT_QUOTES|ENT_HTML5,'UTF-8');$value=preg_replace('/\s+/u',' ',trim($value))??'';$encoded=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$value);return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],(string)$encoded);}
function pdfLines(string $text,int $width):array{$plain=html_entity_decode(strip_tags($text),ENT_QUOTES|ENT_HTML5,'UTF-8');$plain=str_replace(["\r\n","\r"],"\n",trim($plain));if($plain==='')return [];$lines=[];foreach(preg_split('/\n\s*\n/u',$plain)?:[] as $paragraph){$paragraph=preg_replace('/[ \t]+/u',' ',trim($paragraph))??'';if($paragraph==='')continue;if($lines)$lines[]='';array_push($lines,...explode("\n",wordwrap($paragraph,$width,"\n",true)));}return $lines;}
$dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8"><main>'.$html.'</main>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);libxml_clear_errors();$xp=new DOMXPath($dom);
$answerNode=$xp->query("//div[contains(concat(' ',normalize-space(@class),' '),' answer ') and not(contains(concat(' ',normalize-space(@class),' '),' error '))]")->item(0);$answerBlocks=[];
if($answerNode)foreach($answerNode->childNodes as $node){$text=trim((string)$node->textContent);if($text==='')continue;$tag=$node instanceof DOMElement?mb_strtolower($node->tagName):'';$answerBlocks[]=['text'=>$text,'heading'=>in_array($tag,['h1','h2','h3','h4'],true)];}
if(!$answerBlocks&&$answerNode)$answerBlocks[]=['text'=>trim($answerNode->textContent),'heading'=>false];
$sources=[];foreach($xp->query("//article[contains(concat(' ',normalize-space(@class),' '),' source ')]") as $article){$title=$xp->query('.//h3',$article)->item(0);$meta=$xp->query(".//*[contains(concat(' ',normalize-space(@class),' '),' meta ')]",$article)->item(0);$snippet=$xp->query(".//*[contains(concat(' ',normalize-space(@class),' '),' snippet ')]",$article)->item(0);$sources[]=['title'=>$title?trim($title->textContent):'Bron','meta'=>$meta?trim($meta->textContent):'','snippet'=>$snippet?trim($snippet->textContent):''];}
$pages=[];$stream='';$y=800;
$newPage=function()use(&$pages,&$stream,&$y):void{if($stream!=='')$pages[]=$stream;$stream="0.075 0.125 0.17 rg\n0 770 595 72 re f\nBT /F2 20 Tf 1 1 1 rg 38 808 Td (VvE Beheer) Tj ET\nBT /F1 10 Tf 0.84 0.88 0.91 rg 38 788 Td (Zoekresultaten uit het VvE-archief) Tj ET\n";$y=748;};
$newPage();
$drawText=function(array $lines,float $x,float &$cursor,float $size=10,bool $bold=false,array $rgb=[0.10,0.14,0.18],float $leading=13)use(&$stream):void{$font=$bold?'F2':'F1';foreach($lines as $line){$stream.=sprintf("BT /%s %.1f Tf %.3f %.3f %.3f rg %.1f %.1f Td (%s) Tj ET\n",$font,$size,$rgb[0],$rgb[1],$rgb[2],$x,$cursor,pdfText($line));$cursor-=$leading;}};
$ensure=function(float $needed)use(&$y,$newPage):void{if($y-$needed<38)$newPage();};
$questionLines=pdfLines($question,78);$height=42+count($questionLines)*14;$ensure($height);$stream.="0.96 0.97 0.98 rg\n32 ".($y-$height+8)." 531 ".($height-8)." re f\n";$y-=18;$drawText(['VRAAG'],48,$y,9,true,[0.28,0.36,0.44],12);$drawText($questionLines,48,$y,12,true,[0.08,0.13,0.18],15);$y-=16;
$answerLines=[];foreach($answerBlocks as $block){if($answerLines)$answerLines[]=['text'=>'','heading'=>false];foreach(pdfLines((string)$block['text'],$block['heading']?72:82) as $line)$answerLines[]=['text'=>$line,'heading'=>(bool)$block['heading']];}$firstAnswerPage=true;
while($answerLines){
    $labelHeight=$firstAnswerPage?30:28;
    if($y<110)$newPage();
    $maximum=max(1,(int)floor(($y-50-$labelHeight)/13));
    $chunk=array_splice($answerLines,0,$maximum);$height=$labelHeight+count($chunk)*14+10;$bottom=$y-$height+8;
    $stream.="0.91 0.95 1 rg\n32 {$bottom} 531 ".($height-8)." re f\n";$y-=18;
    $drawText([$firstAnswerPage?'AI-ANTWOORD':'AI-ANTWOORD (VERVOLG)'],48,$y,9,true,[0.09,0.31,0.56],12);
    foreach($chunk as $line){$isHeading=(bool)$line['heading'];$drawText([(string)$line['text']],48,$y,$isHeading?12.5:10,$isHeading,[0.09,0.31,0.56],$isHeading?18:13);}$y=$bottom-14;$firstAnswerPage=false;
    if($answerLines)$newPage();
}
foreach($sources as $index=>$source){$titleLines=pdfLines($source['title'],76);$metaLines=pdfLines($source['meta'],92);$snippetLines=array_slice(pdfLines($source['snippet'],88),0,18);$height=42+count($titleLines)*15+count($metaLines)*12+count($snippetLines)*13;$ensure($height);$bottom=$y-$height+8;$stream.="0.82 0.87 0.91 RG 0.7 w\n32 {$bottom} 531 ".($height-8)." re S\n";$y-=17;$drawText(['BRON '.($index+1)],48,$y,8,true,[0.09,0.31,0.56],11);$drawText($titleLines,48,$y,12,true,[0.08,0.13,0.18],15);$drawText($metaLines,48,$y,8,false,[0.36,0.44,0.51],11);$y-=3;$drawText($snippetLines,48,$y,9,false,[0.10,0.14,0.18],12);$y=$bottom-14;}
if($stream!=='')$pages[]=$stream;
$objects=[];$pageIds=[];$next=5;foreach($pages as $_){$pageIds[]=$next;$next+=2;}$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';$objects[2]='<< /Type /Pages /Kids ['.implode(' ',array_map(static fn($id)=>$id.' 0 R',$pageIds)).'] /Count '.count($pageIds).' >>';$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';$objects[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
foreach($pages as $i=>$pageStream){$pageId=$pageIds[$i];$contentId=$pageId+1;$footer=sprintf("BT /F1 8 Tf 0.45 0.50 0.55 rg 32 20 Td (Gemaakt %s - pagina %d van %d) Tj ET\n",pdfText(date('d-m-Y H:i')),$i+1,count($pages));$pageStream.=$footer;$objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';$objects[$contentId]='<< /Length '.strlen($pageStream)." >>\nstream\n{$pageStream}\nendstream";}
ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n{$object}\nendobj\n";}$xref=strlen($pdf);$count=max(array_keys($objects))+1;$pdf.="xref\n0 {$count}\n0000000000 65535 f \n";for($id=1;$id<$count;$id++)$pdf.=sprintf('%010d 00000 n ',(int)($offsets[$id]??0))."\n";$pdf.="trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="VvE-zoekresultaten-'.date('Ymd-His').'.pdf"');header('Content-Length: '.strlen($pdf));echo $pdf;
