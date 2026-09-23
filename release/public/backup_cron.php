<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';

require_once __DIR__.'/backup_lib.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    $config=backupConfig();$provided=(string)($_GET['token']??'');$expected=(string)($config['cron_token']??'');
    if($expected===''||$provided===''||!hash_equals($expected,$provided)){http_response_code(403);throw new RuntimeException('Geen toegang.');}
    $status=backupStatus();if(!empty($status['running'])&&!empty($status['started_at'])&&strtotime((string)$status['started_at'])>time()-1800){http_response_code(409);throw new RuntimeException('Er loopt al een back-up.');}
    $result=runLocalBackup($config);echo json_encode(['ok'=>true,'backup'=>$result],JSON_UNESCAPED_SLASHES);
}catch(Throwable $error){if(http_response_code()<400)http_response_code(500);echo json_encode(['ok'=>false,'error'=>$error->getMessage()],JSON_UNESCAPED_SLASHES);}
