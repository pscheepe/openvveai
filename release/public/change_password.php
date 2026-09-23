<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/auth.php';

$user=currentUser();
if(!$user){header('Location: ./');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        verifyCsrf();
        if(($_POST['action']??'')==='logout'){
            logout();
            header('Location: ./');
            exit;
        }
        $current=(string)($_POST['current_password']??'');
        $password=(string)($_POST['password']??'');
        $confirm=(string)($_POST['confirm']??'');
        $users=loadUsers();
        $username=(string)$user['username'];
        $stored=$users[$username]??null;
        if(!is_array($stored) || !password_verify($current,(string)($stored['password_hash']??''))) throw new InvalidArgumentException('Het tijdelijke of huidige wachtwoord is niet juist.');
        validateSafePassword($password);
        if($password!==$confirm) throw new InvalidArgumentException('De twee nieuwe wachtwoorden zijn niet gelijk.');
        if(password_verify($password,(string)$stored['password_hash'])) throw new InvalidArgumentException('Kies een ander wachtwoord dan het tijdelijke of huidige wachtwoord.');
        $users[$username]['password_hash']=password_hash($password,PASSWORD_DEFAULT);
        $users[$username]['must_change_password']=false;
        saveUsers($users);
        session_regenerate_id(true);
        $_SESSION['user']['must_change_password']=false;
        $_SESSION['last_activity']=time();
        header('Location: ./');
        exit;
    }catch(Throwable $e){
        $error=$e instanceof InvalidArgumentException?$e->getMessage():'Het wachtwoord kon niet veilig worden gewijzigd.';
    }
}
$required=!empty($user['must_change_password']);
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Wachtwoord wijzigen</title><style>
:root{--accent:#2457a6;--line:#d5e0e8;--text:#17202a;--muted:#647180}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:linear-gradient(135deg,#eef4f8,#dcecf8);font:15px system-ui,-apple-system,Segoe UI,Arial,sans-serif;color:var(--text);display:flex;align-items:center;justify-content:center}.wrap{width:min(480px,92vw)}.card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 16px 50px rgba(31,55,72,.14);padding:28px}.logo{display:block;width:140px;height:100px;object-fit:contain;margin:0 auto 12px}h1{text-align:center;margin:0 0 8px}.intro{color:var(--muted);text-align:center;line-height:1.45}.field{margin:13px 0}.field label{display:block;font-size:13px;font-weight:700;margin-bottom:5px}.field input{width:100%;padding:11px;border:1px solid #cbd6df;border-radius:8px;font:inherit}.requirements{font-size:12px;color:var(--muted);background:#f6f8fa;border-radius:8px;padding:10px;line-height:1.5}.btn{width:100%;padding:11px;border:0;border-radius:8px;background:var(--accent);color:#fff;font-weight:700;cursor:pointer}.err{background:#fff0f0;border:1px solid #e3b8b8;color:#8b2d2d;padding:10px;border-radius:8px;margin:12px 0}.logout{text-align:center;margin-top:14px}.logout button{border:0;background:none;color:var(--accent);text-decoration:underline;cursor:pointer}</style></head><body><main class="wrap"><section class="card"><img class="logo" src="logo.png" alt="VvE Beheer"><h1>Wachtwoord wijzigen</h1><p class="intro"><?=$required?'U gebruikt een tijdelijk wachtwoord. Stel eerst een eigen veilig wachtwoord in voordat u verdergaat.':'Hier kunt u uw huidige wachtwoord wijzigen.'?></p><?php if($error):?><div class="err"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>"><div class="field"><label>Tijdelijk of huidig wachtwoord</label><input type="password" name="current_password" required autocomplete="current-password" autofocus></div><div class="field"><label>Nieuw wachtwoord</label><input type="password" name="password" required minlength="12" autocomplete="new-password"></div><div class="field"><label>Herhaal nieuw wachtwoord</label><input type="password" name="confirm" required minlength="12" autocomplete="new-password"></div><p class="requirements">Minimaal 12 tekens, met ten minste één kleine letter, één hoofdletter, één cijfer en één speciaal teken.</p><button class="btn" type="submit">Nieuw wachtwoord opslaan</button></form><form method="post" class="logout"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="logout"><button type="submit">Afmelden</button></form></section></main></body></html>
