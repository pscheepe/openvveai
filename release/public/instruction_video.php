<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/auth.php';
requireLogin();
// Laat andere pagina- en API-verzoeken doorgaan terwijl een lange video streamt.
session_write_close();

try {
    $key=(string)($_GET['v']??'');
    $file=instructionVideoPath($key);
    if(!is_file($file)){
        http_response_code(404);
        exit('Instructievideo is nog niet geplaatst.');
    }
    $size=filesize($file);
    if($size===false)throw new RuntimeException('Videobestand is niet leesbaar.');
    $start=0;$end=$size-1;
    $range=(string)($_SERVER['HTTP_RANGE']??'');
    if($range!==''&&preg_match('/bytes=(\d*)-(\d*)/', $range,$m)){
        if($m[1]!==''&&$m[2]!==''){$start=(int)$m[1];$end=(int)$m[2];}
        elseif($m[1]!==''){$start=(int)$m[1];}
        elseif($m[2]!==''){$start=max(0,$size-(int)$m[2]);}
        if($start<0||$start>=$size||$end<$start){header('Content-Range: bytes */'.$size);http_response_code(416);exit;}
        $end=min($end,$size-1);http_response_code(206);header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
    }
    $length=$end-$start+1;
    header('Content-Type: video/mp4');
    header('Content-Length: '.$length);
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    if(($_SERVER['REQUEST_METHOD']??'GET')==='HEAD')exit;
    $handle=fopen($file,'rb');if($handle===false)throw new RuntimeException('Videobestand is niet leesbaar.');
    fseek($handle,$start);$remaining=$length;
    while($remaining>0&&!feof($handle)){if(connection_aborted())break;$chunk=fread($handle,min(1024*1024,$remaining));if($chunk===false)break;echo$chunk;$remaining-=strlen($chunk);flush();}
    fclose($handle);
} catch(Throwable $error) {
    securityIncident($error);if(!headers_sent())http_response_code(404);echo'Instructievideo is niet beschikbaar.';
}
