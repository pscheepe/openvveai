<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/auth.php';

function activityDb(): PDO {
    $config=loadConfigCompat(tenantPrivateFile('config.php'));
    return db($config);
}

function recordActivity(PDO $pdo,array $user,string $action,string $section,string $objectType='',string $objectId='',string $description=''): void {
    try {
        $sql='INSERT INTO ActiviteitenLog (DatumTijd,Gebruikersnaam,Weergavenaam,Rol,Actie,Onderdeel,ObjectType,ObjectID,Omschrijving) VALUES (?,?,?,?,?,?,?,?,?)';
        $stmt=$pdo->prepare($sql);
        $stmt->execute([
            date('Y-m-d H:i:s'),
            mb_substr((string)($user['username']??'onbekend'),0,128),
            mb_substr((string)($user['name']??$user['username']??'Onbekend'),0,150),
            mb_substr((string)($user['role']??'onbekend'),0,32),
            mb_substr($action,0,80),mb_substr($section,0,80),
            $objectType===''?null:mb_substr($objectType,0,80),
            $objectId===''?null:mb_substr($objectId,0,128),
            mb_substr($description,0,500),
        ]);
    } catch (Throwable $e) {
        error_log('[activity-log] '.$e->getMessage());
    }
}
