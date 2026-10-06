<?php
declare(strict_types=1);

function mfaPolicy(): string {
    $file=tenantPrivateFile('mfa_config.php');
    if(!is_file($file)) return 'off';
    $cfg=include $file; $mode=is_array($cfg)?(string)($cfg['policy']??'off'):'off';
    return in_array($mode,['off','optional','required'],true)?$mode:'off';
}
function mfaSavePolicy(string $policy): void {
    if(!in_array($policy,['off','optional','required'],true)) throw new InvalidArgumentException('Ongeldige MFA-instelling.');
    $file=tenantPrivateFile('mfa_config.php');$tmp=tempnam(dirname($file),'mfa-');
    if($tmp===false)throw new RuntimeException('Instelling opslaan mislukt.');
    try { if(file_put_contents($tmp,"<?php\nreturn ".var_export(['policy'=>$policy],true).";\n",LOCK_EX)===false)throw new RuntimeException('Instelling opslaan mislukt.');chmod($tmp,0600);if(!rename($tmp,$file))throw new RuntimeException('Instelling opslaan mislukt.');if(function_exists('opcache_invalidate'))opcache_invalidate($file,true); } finally {if(is_file($tmp))unlink($tmp);}
}
function mfaKey(): string {
    $file=tenantPrivateFile('mfa.key');
    if(is_file($file)){if(is_link($file))throw new RuntimeException('MFA-sleutel ongeldig.');$key=file_get_contents($file);if($key===false||strlen($key)!==32)throw new RuntimeException('MFA-sleutel ongeldig.');return $key;}
    $key=random_bytes(32);$h=@fopen($file,'x');if(!$h){if(is_file($file))return mfaKey();throw new RuntimeException('MFA-sleutel kon niet worden aangemaakt.');}
    try{if(!fwrite($h,$key))throw new RuntimeException('MFA-sleutel kon niet worden opgeslagen.');}finally{fclose($h);}chmod($file,0600);return $key;
}
function mfaEncrypt(string $plain): string { $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',mfaKey(),OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new RuntimeException('MFA-geheim kon niet worden beveiligd.');return base64_encode($iv.$tag.$cipher); }
function mfaDecrypt(string $value): string { $raw=base64_decode($value,true);if($raw===false||strlen($raw)<29)throw new RuntimeException('MFA-geheim is ongeldig.');$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',mfaKey(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));if($plain===false)throw new RuntimeException('MFA-geheim kan niet worden gelezen.');return $plain; }
function mfaBase32(string $bytes): string { $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';foreach(str_split($bytes) as $c)$bits.=str_pad(decbin(ord($c)),8,'0',STR_PAD_LEFT);$out='';foreach(str_split($bits,5) as $chunk)$out.=$alphabet[bindec(str_pad($chunk,5,'0'))];return $out; }
function mfaBase32Decode(string $text): string { $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';foreach(str_split(strtoupper(rtrim($text,'='))) as $c){$n=strpos($alphabet,$c);if($n===false)throw new RuntimeException('Ongeldige MFA-code.');$bits.=str_pad(decbin($n),5,'0',STR_PAD_LEFT);} $out='';foreach(str_split($bits,8) as $chunk)if(strlen($chunk)===8)$out.=chr(bindec($chunk));return $out; }
function mfaNewSecret(): string { return mfaBase32(random_bytes(20)); }
function mfaMatchedStep(string $secret,string $code,?int $now=null,?int $afterStep=null): ?int {
    $code=preg_replace('/\s+/','',$code)??'';if(!preg_match('/^\d{6}$/D',$code))return null;
    $key=mfaBase32Decode($secret);$counter=(int)floor(($now??time())/30);
    for($offset=-1;$offset<=1;$offset++){$step=$counter+$offset;if($afterStep!==null&&$step<=$afterStep)continue;$msg=pack('N2',0,($step&0xffffffff));$hash=hash_hmac('sha1',$msg,$key,true);$idx=ord($hash[19])&15;$num=((ord($hash[$idx])&127)<<24)|((ord($hash[$idx+1])&255)<<16)|((ord($hash[$idx+2])&255)<<8)|(ord($hash[$idx+3])&255);if(hash_equals(str_pad((string)($num%1000000),6,'0',STR_PAD_LEFT),$code))return $step;}return null;
}
function mfaCodeValid(string $secret,string $code,?int $now=null): bool { return mfaMatchedStep($secret,$code,$now)!==null; }
function mfaIssueRecoveryCodes(): array { $codes=[];for($i=0;$i<10;$i++)$codes[]=strtoupper(bin2hex(random_bytes(5)));return $codes; }
function mfaVerifyLogin(string $code): bool {
    $name=(string)($_SESSION['mfa_pending_user']??'');$at=(int)($_SESSION['mfa_pending_at']??0);
    if($name===''||$at<time()-300)return false;
    enforceLoginRateLimit($name);
    $users=loadUsers();$u=$users[$name]??null;if(!is_array($u)||empty($u['enabled'])||empty($u['mfa_secret']))return false;
    $candidate=strtoupper(preg_replace('/[^a-fA-F0-9]/','',$code)??'');
    foreach((array)($u['mfa_recovery_hashes']??[]) as $i=>$hash)if(is_string($hash)&&password_verify($candidate,$hash)){unset($users[$name]['mfa_recovery_hashes'][$i]);$users[$name]['mfa_recovery_hashes']=array_values($users[$name]['mfa_recovery_hashes']);saveUsers($users);completeLogin($name,$users[$name]);return true;}
    $secret=mfaDecrypt((string)$u['mfa_secret']);
    $step=mfaMatchedStep($secret,$code,null,(int)($u['mfa_last_step']??-1));
    if($step!==null){$users[$name]['mfa_last_step']=$step;saveUsers($users);completeLogin($name,$users[$name]);return true;}
    recordFailedLogin($name);return false;
}
