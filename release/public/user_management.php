<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
ob_start(static fn(string $html):string=>str_replace(
    ['GUI v8.26','padding:9px 12px;border:1px solid #bfc8d2','button{background:#e8f1fb','<a href="ai_settings.php">AI</a><a href="activity_log.php">','</nav>'],
    ['<a class="badge" href="version_log.php" title="Bekijk wijzigingen per versie">GUI V8.60</a>','padding:7px 10px;font-size:12px;line-height:1.2;border:1px solid #bfc8d2','button{padding:7px 9px;font-size:12px;line-height:1.2;background:#e8f1fb','<a href="ai_settings.php">AI</a><a href="backup_settings.php">Back-ups</a><a href="activity_log.php">','<a href="version_log.php">Versies</a></nav>'],
    $html
));
require_once __DIR__.'/auth.php';
require_once __DIR__.'/activity.php';
$admin=requireAdmin();
$message=''; $error='';

function cleanUsername(string $value): string { return strtolower(trim($value)); }
function validateAccount(string $username,string $name,string $password,bool $passwordRequired): void {
    if(!preg_match('/^[a-z0-9][a-z0-9._-]{2,39}$/',$username)) throw new InvalidArgumentException('De gebruikersnaam moet 3–40 kleine letters, cijfers, punten, streepjes of underscores bevatten.');
    if(trim($name)==='' || strlen(trim($name))>200) throw new InvalidArgumentException('Vul een naam van maximaal 100 tekens in.');
    if($passwordRequired || $password!=='') validateSafePassword($password);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        verifyCsrf();
        $users=loadUsers();
        $action=(string)($_POST['action']??'');
        if($action==='create'){
            $username=cleanUsername((string)($_POST['username']??'')); $name=trim((string)($_POST['name']??''));
            $password=(string)($_POST['password']??''); $confirm=(string)($_POST['confirm']??'');
            validateAccount($username,$name,$password,true);
            if($password!==$confirm) throw new InvalidArgumentException('De twee wachtwoorden zijn niet gelijk.');
            if(isset($users[$username])) throw new InvalidArgumentException('Deze gebruikersnaam bestaat al.');
            $users[$username]=['password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'bestuur','name'=>$name,'enabled'=>true,'must_change_password'=>true];
            saveUsers($users); try{recordActivity(activityDb(),$admin,'gebruiker-aangemaakt','gebruikers','gebruiker',$username,$name);}catch(Throwable $ignored){} $message='Bestuursaccount '.$username.' is aangemaakt.';
        } elseif($action==='update'){
            $oldUsername=cleanUsername((string)($_POST['old_username']??''));
            $username=cleanUsername((string)($_POST['username']??''));
            if(!isset($users[$oldUsername]) || !is_array($users[$oldUsername])) throw new InvalidArgumentException('Account niet gevonden.');
            $name=trim((string)($_POST['name']??'')); $role=(string)($_POST['role']??'bestuur'); $password=(string)($_POST['password']??''); $passwordConfirm=(string)($_POST['password_confirm']??''); $enabled=isset($_POST['enabled']);
            if(!in_array($role,['admin','bestuur'],true)) throw new InvalidArgumentException('Ongeldige rol.');
            validateAccount($username,$name,$password,false);
            if($password!=='' && $password!==$passwordConfirm) throw new InvalidArgumentException('De twee tijdelijke wachtwoorden zijn niet gelijk.');
            if($username!==$oldUsername && isset($users[$username])) throw new InvalidArgumentException('Deze nieuwe gebruikersnaam bestaat al.');
            $isSelf=hash_equals(strtolower((string)$admin['username']),$oldUsername);
            if($isSelf && (!$enabled || $role!=='admin')) throw new InvalidArgumentException('U kunt uw eigen adminaccount niet uitschakelen of verlagen.');
            $candidate=$users; $updated=$candidate[$oldUsername];
            $updated['name']=$name; $updated['role']=$role; $updated['enabled']=$enabled;
            if($username!==$oldUsername) unset($candidate[$oldUsername]);
            $candidate[$username]=$updated;
            if($password!=='') {
                $candidate[$username]['password_hash']=password_hash($password,PASSWORD_DEFAULT);
                $candidate[$username]['must_change_password']=true;
            }
            $enabledAdmins=array_filter($candidate,fn($u)=>is_array($u)&&!empty($u['enabled'])&&($u['role']??'')==='admin');
            if(!$enabledAdmins) throw new InvalidArgumentException('Er moet minimaal één actief adminaccount blijven.');
            saveUsers($candidate);
            try{recordActivity(activityDb(),$admin,'gebruiker-gewijzigd','gebruikers','gebruiker',$username,$name.' · rol '.$role.' · '.($enabled?'actief':'inactief'));}catch(Throwable $ignored){}
            if($isSelf){$_SESSION['user']['username']=$username;$_SESSION['user']['name']=$name;$_SESSION['user']['role']=$role;$_SESSION['user']['must_change_password']=!empty($candidate[$username]['must_change_password']);$admin=$_SESSION['user'];}
            $message=$password!==''?'Voor '.$username.' is een nieuw tijdelijk wachtwoord ingesteld. Bij de volgende aanmelding moet dit worden gewijzigd.':'Account '.$username.' is bijgewerkt.';
        } elseif($action==='reset_mfa'){
            $target=cleanUsername((string)($_POST['username']??''));
            if($target===''||$target===strtolower((string)$admin['username'])) throw new InvalidArgumentException('Voor uw eigen account gebruikt u herstelcode of persoonlijke MFA-instellingen.');
            if(!isset($users[$target])||!is_array($users[$target]))throw new InvalidArgumentException('Account niet gevonden.');
            unset($users[$target]['mfa_secret'],$users[$target]['mfa_recovery_hashes'],$users[$target]['mfa_last_step']);
            saveUsers($users);try{recordActivity(activityDb(),$admin,'mfa-gereset','gebruikers','gebruiker',$target,'MFA opnieuw instellen vereist');}catch(Throwable $ignored){}
            $message='MFA voor '.$target.' is gereset. De gebruiker moet opnieuw instellen als MFA verplicht is.';
        }
    }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'De wijziging kon niet veilig worden opgeslagen.';}
}
$users=loadUsers(); ksort($users);
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gebruikersbeheer</title><style>
.top{min-height:84px}
:root{--blue:#2457a6;--dark:#17202a;--line:#d7e0e8}*{box-sizing:border-box}body{margin:0;background:#f3f6f9;color:#17202a;font:15px system-ui,-apple-system,Segoe UI,Arial,sans-serif}.top{padding:10px 22px;background:var(--dark);color:#fff;display:flex;align-items:center;gap:18px}.logo{width:86px;height:64px;object-fit:contain;background:#fff;border-radius:8px}.brand h1{margin:0;font-size:25px;line-height:1.05}.brand small{opacity:.82}.status{margin-left:auto;text-align:right}.status>strong,.status>span{display:block}.status>span{font-size:12px;opacity:.82}.userbar{display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-top:4px}.badge,.topbtn{color:#fff;border:1px solid rgba(255,255,255,.35);padding:3px 7px;border-radius:5px;font-size:11px;text-decoration:none}.topbtn{background:var(--blue);font-weight:700}.wrap{max-width:1050px;margin:20px auto;padding:0 14px}.tabs{display:flex;gap:8px;margin-bottom:12px}.tabs a{padding:9px 12px;border:1px solid #bfc8d2;border-radius:7px;color:#17202a;text-decoration:none}.tabs .active{background:var(--blue);color:#fff}.card{background:#fff;border:1px solid var(--line);border-radius:10px;padding:16px;margin-bottom:14px}.grid{display:grid;grid-template-columns:1fr 1.3fr 1fr 1fr auto;gap:9px;align-items:end}label{font-size:12px;font-weight:700}input,select,button{width:100%;padding:9px;border:1px solid #bbc8d3;border-radius:7px;font:inherit}button{background:#e8f1fb;color:#174a8b;font-weight:700;cursor:pointer}.primary{background:var(--blue);color:#fff}.msg{padding:11px;border-radius:8px;margin-bottom:14px}.ok{background:#e9f7ed;color:#24683b}.err{background:#fff0f0;color:#8b2d2d}.account{border-top:1px solid var(--line);padding:13px 0}.account:first-of-type{border-top:0}.hint{color:#607080}.check{display:flex;align-items:center;gap:7px;height:41px}.check input{width:auto}@media(max-width:800px){.top{flex-wrap:wrap}.status{width:100%;margin:0;text-align:left}.userbar{justify-content:flex-start;flex-wrap:wrap}.grid{grid-template-columns:1fr}.check{height:auto}}
</style></head><body><header class="top"><img class="logo" src="logo.png"><div class="brand"><h1><?=tenantNameHtml()?></h1><small>Uw VvE · Instellingen</small></div><div class="status"><strong>Verbonden</strong><span>Gebruikersbeheer</span><div class="userbar"><span class="badge">👤 <?=htmlspecialchars((string)$admin['name'],ENT_QUOTES,'UTF-8')?> · admin</span><span class="badge">GUI v8.26</span><a class="topbtn" href="./">🗃 Database</a><a class="topbtn" href="documents.php">📁 Bestanden</a><a class="topbtn" href="settings.php">⚙ Instellingen</a></div></div></header><main class="wrap"><nav class="tabs"><a href="settings.php">Dropdowns</a><a class="active" href="user_management.php">👥 Gebruikers</a><a href="mfa_settings.php">🔐 MFA</a><a href="email_settings.php">✉ E-mail</a><a href="ai_settings.php">AI</a><a href="activity_log.php">Activiteiten</a></nav>
<?php if($message):?><div class="msg ok"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if($error):?><div class="msg err"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<section class="card"><h2>Bestuurslid toevoegen</h2><p class="hint">Geef een tijdelijk wachtwoord uit. De gebruiker moet dit bij de eerste aanmelding vervangen. Wachtwoorden worden uitsluitend als veilige hash bewaard.</p><form method="post" class="grid"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="create"><label>Gebruikersnaam<input name="username" required minlength="3" maxlength="40" autocomplete="off"></label><label>Volledige naam<input name="name" required maxlength="100"></label><label>Tijdelijk wachtwoord<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><label>Herhaal tijdelijk wachtwoord<input type="password" name="confirm" required minlength="12" autocomplete="new-password"></label><button class="primary" type="submit">Aanmaken</button></form></section>
<section class="card"><h2>Bestaande accounts</h2><p class="hint">Vul alleen bij “Nieuw tijdelijk wachtwoord” iets in wanneer de gebruiker het wachtwoord is vergeten. De gebruiker moet het daarna bij de eerstvolgende aanmelding wijzigen.</p><?php foreach($users as $username=>$account): if(!is_array($account))continue;?><form method="post" class="grid account"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="update"><input type="hidden" name="old_username" value="<?=htmlspecialchars((string)$username,ENT_QUOTES,'UTF-8')?>"><label>Gebruikersnaam<input name="username" required minlength="3" maxlength="40" value="<?=htmlspecialchars((string)$username,ENT_QUOTES,'UTF-8')?>"></label><label>Volledige naam<input name="name" maxlength="100" required value="<?=htmlspecialchars((string)($account['name']??$username),ENT_QUOTES,'UTF-8')?>"></label><label>Rol<select name="role"><option value="bestuur" <?=($account['role']??'')==='bestuur'?'selected':''?>>Bestuurslid</option><option value="admin" <?=($account['role']??'')==='admin'?'selected':''?>>Administrator</option></select></label><label>Nieuw tijdelijk wachtwoord<input type="password" name="password" minlength="12" placeholder="Ongewijzigd" autocomplete="new-password"><?php if(!empty($account['must_change_password'])):?><span class="hint">Wijziging verplicht bij volgende login</span><?php endif;?></label><label>Herhaal tijdelijk wachtwoord<input type="password" name="password_confirm" minlength="12" placeholder="Ongewijzigd" autocomplete="new-password"></label><div><label class="check"><input type="checkbox" name="enabled" <?=!empty($account['enabled'])?'checked':''?>> Actief</label><button type="submit">Opslaan</button></div></form><?php if(!empty($account['mfa_secret'])&&strtolower((string)$username)!==strtolower((string)$admin['username'])):?><form method="post" style="margin:0 0 10px"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="reset_mfa"><input type="hidden" name="username" value="<?=htmlspecialchars((string)$username,ENT_QUOTES,'UTF-8')?>"><button type="submit">Reset MFA voor <?=htmlspecialchars((string)$username,ENT_QUOTES,'UTF-8')?></button></form><?php endif;?><?php endforeach;?></section>
</main></body></html>
