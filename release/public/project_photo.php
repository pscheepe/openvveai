<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/auth.php';
try {
    requireLogin();$id=(int)($_GET['id']??0);if($id<1)throw new RuntimeException('Ongeldige foto.');
    $db=db(loadConfigCompat(tenantPrivateFile('config.php')));
    $s=$db->prepare('SELECT Bestandspad,Mimetype FROM ProjectFotos WHERE FotoID=?');$s->execute([$id]);$row=$s->fetch();if(!$row)throw new RuntimeException('Foto niet gevonden.');
    $file=resolveStoredFile((string)$row['Bestandspad']);header('Content-Type: '.$row['Mimetype']);header('Content-Length: '.filesize($file));header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');readfile($file);
}catch(Throwable $e){http_response_code(404);echo 'Niet gevonden.';}
