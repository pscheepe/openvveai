<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';

function mailSecretsPath(): string { return tenantPrivateFile('mail_secrets.php'); }
function mailProfileKey(array $account): string {
    return hash('sha256',mb_strtolower(trim((string)$account['Username']))."\0".mb_strtolower(trim((string)$account['ImapHost'])));
}
function mailSecrets(): array {
    $path=mailSecretsPath();
    if(!is_file($path))return ['profiles'=>[]];
    $value=require $path;
    return is_array($value)&&isset($value['profiles'])&&is_array($value['profiles'])?$value:['profiles'=>[]];
}
function mailPassword(array $account): ?string {
    $secrets=mailSecrets();$value=$secrets['profiles'][mailProfileKey($account)]??null;
    return is_string($value)&&$value!==''?$value:null;
}
function saveMailPassword(array $account,string $password): void {
    if($password==='')throw new RuntimeException('Vul het mailboxwachtwoord in.');
    $path=mailSecretsPath();$secrets=mailSecrets();$secrets['profiles'][mailProfileKey($account)]=$password;
    $contents="<?php\n// Server-lokaal geheim; niet opnemen in Git of installatie-ZIP.\nreturn ".var_export($secrets,true).";\n";
    $temporary=$path.'.tmp-'.bin2hex(random_bytes(6));
    if(file_put_contents($temporary,$contents,LOCK_EX)===false)throw new RuntimeException('Het wachtwoordbestand kon niet worden geschreven.');
    @chmod($temporary,0600);
    if(!rename($temporary,$path)){@unlink($temporary);throw new RuntimeException('Het wachtwoordbestand kon niet veilig worden geplaatst.');}
    if(function_exists('opcache_invalidate'))@opcache_invalidate($path,true);
}
function clearMailPassword(array $account): void {
    $path=mailSecretsPath();$secrets=mailSecrets();$key=mailProfileKey($account);if(!array_key_exists($key,$secrets['profiles']))return;unset($secrets['profiles'][$key]);
    $contents="<?php\n// Server-lokaal geheim; niet opnemen in Git of installatie-ZIP.\nreturn ".var_export($secrets,true).";\n";
    if(file_put_contents($path,$contents,LOCK_EX)===false)throw new RuntimeException('Het wachtwoordbestand kon niet worden bijgewerkt.');
    @chmod($path,0600);if(function_exists('opcache_invalidate'))@opcache_invalidate($path,true);
}
