<?php
declare(strict_types=1);
require_once __DIR__.'/security.php';
require_once __DIR__.'/mail_credentials.php';

function residentPortalContent(): array {
    $defaults=['documents'=>[],'newsletters'=>[],'notices'=>[],'qa'=>[],'ai_enabled'=>false];
    $path=uploadDirectory('resident-portal').DIRECTORY_SEPARATOR.'resident_portal.php';
    if(!is_file($path))return $defaults;
    if(function_exists('opcache_invalidate'))@opcache_invalidate($path,true);
    clearstatcache(true,$path);
    $saved=require $path;
    return is_array($saved)?array_replace($defaults,$saved):$defaults;
}

function residentPortalSaveContent(array $content): void {
    $path=uploadDirectory('resident-portal').DIRECTORY_SEPARATOR.'resident_portal.php';
    $safe=[
        'documents'=>array_values(array_unique(array_filter(array_map('intval',(array)($content['documents']??[])),static fn($id)=>$id>0))),
        'newsletters'=>array_values(array_unique(array_filter(array_map('intval',(array)($content['newsletters']??[])),static fn($id)=>$id>0))),
        'notices'=>array_values(array_slice((array)($content['notices']??[]),0,10)),
        'qa'=>array_values(array_slice((array)($content['qa']??[]),0,100)),
        'ai_enabled'=>!empty($content['ai_enabled']),
    ];
    foreach(['notices','qa'] as $group){
        $safe[$group]=array_values(array_filter(array_map(static function($item)use($group):array{
            if(!is_array($item))return [];
            if($group==='notices')return ['title'=>mb_substr(trim((string)($item['title']??'')),0,180),'body'=>mb_substr(trim((string)($item['body']??'')),0,3000),'until'=>preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($item['until']??''))?(string)$item['until']:'','active'=>!empty($item['active'])];
            return ['question'=>mb_substr(trim((string)($item['question']??'')),0,500),'answer'=>mb_substr(trim((string)($item['answer']??'')),0,4000),'document_id'=>max(0,(int)($item['document_id']??0)),'active'=>!empty($item['active'])];
        },$safe[$group]),static fn($item)=>$group==='notices'?($item['title']!==''&&$item['body']!==''):($item['question']!==''&&$item['answer']!=='')));
    }
    $safe['newsletters']=array_values(array_intersect($safe['newsletters'],$safe['documents']));
    $source="<?php\ndeclare(strict_types=1);\n// Beheerd via het bewonersportaal; uitsluitend expliciet vrijgegeven documenten zijn openbaar.\nreturn ".var_export($safe,true).";\n";
    $temporary=$path.'.tmp-'.bin2hex(random_bytes(6));
    if(file_put_contents($temporary,$source,LOCK_EX)===false)throw new RuntimeException('Bewonersinhoud kon niet worden opgeslagen.');
    @chmod($temporary,0600);
    if(!rename($temporary,$path)){@unlink($temporary);throw new RuntimeException('Bewonersinhoud kon niet veilig worden opgeslagen.');}
    if(function_exists('opcache_invalidate'))@opcache_invalidate($path,true);
}

function residentPortalDb(): PDO {
    return db(loadConfigCompat(tenantPrivateFile('config.php')));
}

function residentPortalDocuments(PDO $pdo,array $ids): array {
    $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn($id)=>$id>0)));
    if(!$ids)return [];
    $marks=implode(',',array_fill(0,count($ids),'?'));
    $stmt=$pdo->prepare('SELECT d.DocumentID,d.Titel,d.OrigineleNaam,d.Mimetype,d.Documentdatum,d.Bestandsgrootte,d.MapID,m.Pad AS MapNaam FROM DocumentBibliotheek d LEFT JOIN DocumentMappen m ON m.MapID=d.MapID WHERE d.DocumentID IN ('.$marks.') AND d.IsHuidig=1 AND d.VerwijderdOp IS NULL ORDER BY COALESCE(d.Documentdatum,d.GewijzigdOp) DESC,d.DocumentID DESC');
    $stmt->execute($ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function residentPortalDocumentIsVisible(int $id): bool {
    return $id>0&&in_array($id,array_map('intval',(array)(residentPortalContent()['documents']??[])),true);
}

function residentPortalDocumentSuggestion(string $title,string $folder): bool {
    return (bool)preg_match('/bewoner|bewoners|nieuwsbrief|huisregel|huishoudelijk reglement|handleiding|informatieblad|afvalkalender|storingsnummer/iu',$title.' '.$folder);
}

function residentPortalSmtpConfig(PDO $pdo): array {
    try{
        $exists=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='EmailAccounts'")->fetchColumn()===1;
        if($exists){
            $account=$pdo->query('SELECT * FROM EmailAccounts WHERE IsActive=1 ORDER BY IsDefault DESC,EmailAccountID ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            if($account){
                $password=mailPassword($account);
                if($password===null)throw new SafeInputException('Het standaard e-mailaccount heeft nog geen opgeslagen wachtwoord.');
                return ['host'=>(string)$account['SmtpHost'],'port'=>(int)$account['SmtpPort'],'security'=>(string)$account['SmtpSecurity'],'username'=>(string)$account['Username'],'password'=>$password,'from'=>(string)$account['EmailAddress'],'from_name'=>(string)$account['SenderName']];
            }
        }
    }catch(SafeInputException $e){throw $e;}catch(Throwable $e){error_log('[resident-mail-config] '.$e->getMessage());}
    $path=dirname(uploadRoot()).DIRECTORY_SEPARATOR.'smtp_config.php';
    if(!is_file($path))throw new SafeInputException('Er is nog geen actief e-mailaccount voor verzending ingesteld.');
    $config=require $path;
    if(!is_array($config))throw new RuntimeException('Ongeldige SMTP-configuratie.');
    foreach(['host','port','username','password','from'] as $key)if(trim((string)($config[$key]??''))==='')throw new SafeInputException('De SMTP-configuratie is niet compleet.');
    return $config;
}

function residentPortalSmtpResponse($fp): string {
    $response='';
    while(!feof($fp)){$line=fgets($fp,515);if($line===false)break;$response.=$line;if(strlen($line)>=4&&$line[3]===' ')break;}
    return $response;
}

function residentPortalSmtpCommand($fp,string $command,array $codes): string {
    if(fwrite($fp,$command."\r\n")===false)throw new RuntimeException('E-mailopdracht kon niet worden verzonden.');
    $response=residentPortalSmtpResponse($fp);$code=(int)substr(trim($response),0,3);
    if(!in_array($code,$codes,true))throw new RuntimeException('Het e-mailbericht kon niet worden afgeleverd.');
    return $response;
}

function residentPortalSendMail(PDO $pdo,string $subject,string $plain,string $html,string $replyTo=''): void {
    $config=residentPortalSmtpConfig($pdo);
    $recipient=(string)($config['from']??'');
    if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))throw new SafeInputException('Het ingestelde e-mailadres voor verzending is ongeldig.');$security=strtolower((string)($config['security']??'ssl'));$transport=$security==='ssl'?'ssl://':'';$errno=0;$errstr='';
    $fp=@stream_socket_client($transport.$config['host'].':'.$config['port'],$errno,$errstr,20,STREAM_CLIENT_CONNECT);
    if(!$fp)throw new RuntimeException('Het e-mailbericht kon niet worden afgeleverd.');
    stream_set_timeout($fp,20);
    try{
        $greeting=residentPortalSmtpResponse($fp);if((int)substr(trim($greeting),0,3)!==220)throw new RuntimeException('Het e-mailbericht kon niet worden afgeleverd.');
        residentPortalSmtpCommand($fp,'EHLO '.(string)tenantConfig()['host'],[250]);
        if($security==='starttls'){
            residentPortalSmtpCommand($fp,'STARTTLS',[220]);
            if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('De beveiligde e-mailverbinding kon niet worden gestart.');
            residentPortalSmtpCommand($fp,'EHLO '.(string)tenantConfig()['host'],[250]);
        }
        residentPortalSmtpCommand($fp,'AUTH LOGIN',[334]);residentPortalSmtpCommand($fp,base64_encode((string)$config['username']),[334]);residentPortalSmtpCommand($fp,base64_encode((string)$config['password']),[235]);
        residentPortalSmtpCommand($fp,'MAIL FROM:<'.(string)$config['from'].'>',[250]);residentPortalSmtpCommand($fp,'RCPT TO:<'.$recipient.'>',[250,251]);
        residentPortalSmtpCommand($fp,'DATA',[354]);
        $from=(string)$config['from'];$fromName=str_replace(["\r","\n"],'',(string)($config['from_name']??'VvE Beheer'));$boundary='=_ResidentVVE_'.bin2hex(random_bytes(12));
        $headers='From: '.mb_encode_mimeheader($fromName,'UTF-8').' <'.$from.">\r\n".'To: '.$recipient."\r\n".($replyTo!==''&&filter_var($replyTo,FILTER_VALIDATE_EMAIL)?'Reply-To: '.$replyTo."\r\n":'').'Subject: '.mb_encode_mimeheader(str_replace(["\r","\n"],'',$subject),'UTF-8')."\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $body=$headers."\r\n--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".str_replace("\r\n","\n",$plain)."\r\n--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".str_replace("\r\n","\n",$html)."\r\n--$boundary--\r\n";
        $body=preg_replace('/(?m)^\./','..',$body)??$body;
        if(fwrite($fp,$body."\r\n.\r\n")===false)throw new RuntimeException('Het e-mailbericht kon niet worden afgeleverd.');
        $response=residentPortalSmtpResponse($fp);if((int)substr(trim($response),0,3)!==250)throw new RuntimeException('Het e-mailbericht kon niet worden afgeleverd.');
        residentPortalSmtpCommand($fp,'QUIT',[221]);
    }finally{fclose($fp);}
}
