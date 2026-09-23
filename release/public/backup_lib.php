<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';

require_once __DIR__.'/security.php';

function backupConfigPath(): string { return tenantPrivateFile('backup_config.php'); }
function backupStatusPath(): string { return tenantPrivateFile('backup_status.php'); }

function backupDefaults(): array {
    return ['enabled'=>false,'keep_database'=>30,'keep_gui'=>12,'gui_interval_days'=>7,'cron_token'=>''];
}

function backupConfig(): array {
    $config=backupDefaults();$path=backupConfigPath();
    if(is_file($path)){$saved=include $path;if(is_array($saved))$config=array_replace($config,$saved);}
    return $config;
}

function saveBackupConfig(array $config): void {
    $php="<?php\ndeclare(strict_types=1);\n// Server-lokale back-upconfiguratie; nooit opnemen in Git of installatie-ZIP.\nreturn ".var_export($config,true).";\n";
    $temporary=backupConfigPath().'.tmp-'.bin2hex(random_bytes(5));
    if(file_put_contents($temporary,$php,LOCK_EX)===false||!rename($temporary,backupConfigPath())){@unlink($temporary);throw new RuntimeException('De back-upinstellingen konden niet worden opgeslagen.');}
    @chmod(backupConfigPath(),0600);if(function_exists('opcache_invalidate'))@opcache_invalidate(backupConfigPath(),true);
}

function backupStatus(): array {
    $path=backupStatusPath();if(!is_file($path))return [];$status=include $path;return is_array($status)?$status:[];
}

function saveBackupStatus(array $status): void {
    $php="<?php\ndeclare(strict_types=1);\nreturn ".var_export($status,true).";\n";
    if(file_put_contents(backupStatusPath(),$php,LOCK_EX)!==false){@chmod(backupStatusPath(),0600);if(function_exists('opcache_invalidate'))@opcache_invalidate(backupStatusPath(),true);}
}

function backupQuoteIdentifier(string $name): string { return '`'.str_replace('`','``',$name).'`'; }

function backupWriteLine($stream,string $line=''): void {
    if(gzwrite($stream,$line."\n")===false)throw new RuntimeException('De SQL-export kon niet worden geschreven.');
}

function createDatabaseBackup(PDO $pdo,string $database): string {
    $temp=tempnam(tenantDirectory('tmp'),'vve-db-');if($temp===false)throw new RuntimeException('Geen tijdelijke opslag beschikbaar.');
    $file=$temp.'.sql.gz';@unlink($temp);$gz=gzopen($file,'wb9');if($gz===false)throw new RuntimeException('De gecomprimeerde export kon niet worden aangemaakt.');
    try{
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->beginTransaction();
        backupWriteLine($gz,'-- VvEGUI databaseback-up');backupWriteLine($gz,'-- Gemaakt: '.date(DATE_ATOM));
        backupWriteLine($gz,'SET NAMES utf8mb4;');backupWriteLine($gz,'SET FOREIGN_KEY_CHECKS=0;');backupWriteLine($gz,'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";');
        $tables=[];$views=[];$stmt=$pdo->query('SHOW FULL TABLES');
        while($row=$stmt->fetch(PDO::FETCH_NUM)){if(($row[1]??'')==='VIEW')$views[]=(string)$row[0];else $tables[]=(string)$row[0];}
        foreach($tables as $table){
            $quoted=backupQuoteIdentifier($table);$create=$pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM);
            backupWriteLine($gz);backupWriteLine($gz,'DROP TABLE IF EXISTS '.$quoted.';');backupWriteLine($gz,(string)($create[1]??'').';');
            $rows=$pdo->query('SELECT * FROM '.$quoted);$columns=null;$batch=[];
            while($row=$rows->fetch(PDO::FETCH_ASSOC)){
                if($columns===null)$columns=array_keys($row);
                $values=[];foreach($row as $value)$values[]=$value===null?'NULL':$pdo->quote((string)$value);
                $batch[]='('.implode(',',$values).')';
                if(count($batch)>=100){backupWriteLine($gz,'INSERT INTO '.$quoted.' ('.implode(',',array_map('backupQuoteIdentifier',$columns)).') VALUES '.implode(',',$batch).';');$batch=[];}
            }
            if($batch&&$columns!==null)backupWriteLine($gz,'INSERT INTO '.$quoted.' ('.implode(',',array_map('backupQuoteIdentifier',$columns)).') VALUES '.implode(',',$batch).';');
        }
        foreach($views as $view){$quoted=backupQuoteIdentifier($view);$create=$pdo->query('SHOW CREATE VIEW '.$quoted)->fetch(PDO::FETCH_ASSOC);$sql=(string)($create['Create View']??'');if($sql!==''){backupWriteLine($gz);backupWriteLine($gz,'DROP VIEW IF EXISTS '.$quoted.';');backupWriteLine($gz,$sql.';');}}
        backupWriteLine($gz);backupWriteLine($gz,'SET FOREIGN_KEY_CHECKS=1;');$pdo->commit();
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();gzclose($gz);@unlink($file);throw $error;}
    gzclose($gz);return $file;
}

function guiBackupExcluded(string $relative): bool {
    $relative=str_replace('\\','/',$relative);if(preg_match('~^private/(backups|sessions|tmp|rate-limits)(/|$)~',$relative))return true;$first=explode('/',$relative)[0]??'';$name=basename($relative);
    if(in_array($first,['.git','.codex-odp-test','tmp','sharepoint-audit','strato-security-review'],true))return true;
    if(in_array($name,['.DS_Store','backup_config.php','backup_status.php'],true))return true;
    return (bool)preg_match('/^(?:VVEGUI_|vvegui-).+\.zip$/i',$name);
}

function createGuiBackup(): string {
    if(!class_exists('ZipArchive'))throw new RuntimeException('De PHP ZIP-extensie ontbreekt; de applicatie- en bestandenback-up kan niet worden gemaakt.');
    $temp=tempnam(tenantDirectory('tmp'),'vve-gui-');if($temp===false)throw new RuntimeException('Geen tijdelijke opslag beschikbaar.');$file=$temp.'.zip';@unlink($temp);
    $zip=new ZipArchive();if($zip->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Het GUI-archief kon niet worden aangemaakt.');
    try{
        $root=realpath(dirname(__DIR__));if($root===false)throw new RuntimeException('De GUI-map is niet beschikbaar.');
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::LEAVES_ONLY);
        foreach($iterator as $item){if(!$item->isFile()||$item->isLink())continue;$path=$item->getRealPath();if($path===false||!str_starts_with($path,$root.DIRECTORY_SEPARATOR))continue;$relative=substr($path,strlen($root)+1);if(guiBackupExcluded($relative))continue;if(!$zip->addFile($path,'vve-instance/'.str_replace('\\','/',$relative)))throw new RuntimeException('Een GUI-bestand kon niet aan het archief worden toegevoegd.');}
    }catch(Throwable $error){$zip->close();@unlink($file);throw $error;}
    if(!$zip->close()||!is_file($file)){@unlink($file);throw new RuntimeException('Het GUI-archief kon niet worden voltooid.');}return $file;
}

function localBackupRoot(): string { return tenantDirectory('backups'); }

function ensureLocalBackupDirectories(): string {
    $root=localBackupRoot();
    foreach([$root,$root.'/database',$root.'/gui'] as $directory){if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('De beveiligde back-upmap kon niet worden aangemaakt.');}
    @chmod($root,0700);@chmod($root.'/database',0700);@chmod($root.'/gui',0700);
    $protection="Require all denied\nDeny from all\n";
    if(file_put_contents($root.'/.htaccess',$protection,LOCK_EX)===false)throw new RuntimeException('De webbeveiliging van de back-upmap kon niet worden ingesteld.');
    if(!is_file($root.'/index.html'))@file_put_contents($root.'/index.html','',LOCK_EX);
    return $root;
}

function storeLocalBackup(string $source,string $subdirectory,string $name): string {
    $destination=localBackupRoot().'/'.$subdirectory.'/'.$name;
    if(!@rename($source,$destination)){if(!@copy($source,$destination))throw new RuntimeException('Het back-upbestand kon niet op de webspace worden opgeslagen.');@unlink($source);}
    @chmod($destination,0600);return $destination;
}

function pruneLocalBackups(string $subdirectory,string $pattern,int $keep): void {
    $files=glob(localBackupRoot().'/'.$subdirectory.'/*')?:[];$files=array_values(array_filter($files,fn($file)=>is_file($file)&&preg_match($pattern,basename($file))));rsort($files,SORT_STRING);
    foreach(array_slice($files,max(2,min(365,$keep))) as $file)@unlink($file);
}

function runLocalBackup(array $config,bool $requireEnabled=true,bool $forceGui=false): array {
    if($requireEnabled&&empty($config['enabled']))throw new RuntimeException('Automatische lokale back-ups zijn niet ingeschakeld.');
    $lock=fopen(tenantDirectory('tmp').'/vve-local-backup.lock','c');if($lock===false||!flock($lock,LOCK_EX|LOCK_NB)){if(is_resource($lock))fclose($lock);throw new RuntimeException('Er loopt al een back-up.');}
    $previous=backupStatus();$started=date(DATE_ATOM);saveBackupStatus(array_replace($previous,['running'=>true,'started_at'=>$started]));$file='';$guiFile='';
    try{
        $root=ensureLocalBackupDirectories();$dbConfig=loadConfigCompat(tenantPrivateFile('config.php'));$pdo=db($dbConfig);$file=createDatabaseBackup($pdo,(string)$dbConfig['database']);
        $now=date(DATE_ATOM);$dbName='vve-database-'.date('Ymd-His').'.sql.gz';$stored=storeLocalBackup($file,'database',$dbName);$file='';
        $result=['running'=>false,'ok'=>true,'finished_at'=>$now,'location'=>$root,'database'=>['finished_at'=>$now,'filename'=>$dbName,'bytes'=>(int)filesize($stored)],'gui'=>$previous['gui']??null];
        $lastGui=strtotime((string)($previous['gui']['finished_at']??''))?:0;$interval=max(1,min(31,(int)$config['gui_interval_days']));
        if($forceGui||$lastGui<time()-($interval*86400)){$guiFile=createGuiBackup();$guiName='vve-gui-'.date('Ymd-His').'.zip';$storedGui=storeLocalBackup($guiFile,'gui',$guiName);$guiFile='';$result['gui']=['finished_at'=>date(DATE_ATOM),'filename'=>$guiName,'bytes'=>(int)filesize($storedGui)];}
        pruneLocalBackups('database','/^vve-database-\d{8}-\d{6}\.sql\.gz$/',max(2,(int)$config['keep_database']));pruneLocalBackups('gui','/^vve-gui-\d{8}-\d{6}\.zip$/',max(2,(int)$config['keep_gui']));saveBackupStatus($result);return $result;
    }catch(Throwable $error){$id=securityIncident($error);$safeMessage=preg_replace('/[\r\n]+/',' ',trim($error->getMessage()));saveBackupStatus(array_replace($previous,['running'=>false,'ok'=>false,'finished_at'=>date(DATE_ATOM),'error'=>'Back-up mislukt: '.$safeMessage.' Foutreferentie: '.$id]));throw $error;}finally{if($file!==''&&is_file($file))@unlink($file);if($guiFile!==''&&is_file($guiFile))@unlink($guiFile);flock($lock,LOCK_UN);fclose($lock);}
}
