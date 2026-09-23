<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__ . '/auth.php';
requireLogin();

try {
    $config=loadConfigCompat(tenantPrivateFile('config.php'));
    $pdo=db($config);
    $type=(string)($_GET['type']??'');$id=(string)($_GET['id']??'');
    if($id===''||!ctype_digit($id))throw new SafeInputException('Ongeldig bestand.');
    $queries=[
        'meeting'=>['SELECT Bestandspad,Bestandsnaam,Mimetype FROM `VergaderingBestanden` WHERE BestandID=?',true],
        'image'=>['SELECT Bestandspad,Bestandsnaam,Mimetype FROM `ActivaAfbeeldingen` WHERE AfbeeldingID=?',false],
        'asset-document'=>['SELECT Bestandspad,Bestandsnaam,Mimetype FROM `ActivaDocumenten` WHERE DocumentID=? AND Type="bestand"',true],
        'maintenance'=>['SELECT Bestandspad,Bestandsnaam,Mimetype FROM `OnderhoudsplanDocumenten` WHERE DocumentID=? AND Type="bestand"',true],
    ];
    if(!isset($queries[$type]))throw new SafeInputException('Ongeldig bestandstype.');
    [$sql,$attachment]=$queries[$type];$stmt=$pdo->prepare($sql);$stmt->execute([$id]);$row=$stmt->fetch();
    if(!$row){http_response_code(404);throw new SafeInputException('Bestand niet gevonden.');}
    $file=resolveStoredFile((string)$row['Bestandspad']);
    if($type==='image'&&!in_array((string)$row['Mimetype'],['image/png','image/jpeg','image/bmp','image/x-ms-bmp'],true)){
        http_response_code(415);throw new SafeInputException('Dit oude afbeeldingsformaat wordt niet veilig weergegeven.');
    }
    $name=str_replace(["\r","\n",'"'],['_','_','_'],basename((string)$row['Bestandsnaam']));
    header('Content-Type: '.((string)$row['Mimetype']?:'application/octet-stream'));
    header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
    header('Content-Disposition: '.($attachment?'attachment':'inline').'; filename="'.$name.'"');
    header('Content-Length: '.filesize($file));readfile($file);exit;
} catch(Throwable $e) {
    if(http_response_code()<400)http_response_code($e instanceof SafeInputException?400:500);
    if(!$e instanceof SafeInputException)securityIncident($e);
    echo 'Bestand kan niet worden geleverd.';exit;
}
