<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
ob_start(static fn(string $html):string=>str_replace(['<a class="active" href="ai_settings.php">AI</a><a href="activity_log.php">','GUI v8.50','</nav>'],['<a class="active" href="ai_settings.php">AI</a><a href="backup_settings.php">Back-ups</a><a href="activity_log.php">','<a href="version_log.php" style="color:#fff" title="Bekijk wijzigingen per versie">GUI v8.50</a>','<a href="version_log.php">Versies</a></nav>'],$html));
require_once __DIR__.'/activity.php';
require_once __DIR__.'/ai_provider.php';
$admin=requireAdmin();$message='';$error='';$path=tenantPrivateFile('ai_config.php');$config=aiProviderConfig();
function aih(string $v):string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function saveAiConfig(string $path,array $data):void{
    $php="<?php\ndeclare(strict_types=1);\n// Server-lokale AI-configuratie; nooit opnemen in Git of installatie-ZIP.\nreturn ".var_export($data,true).";\n";
    $temporary=$path.'.tmp-'.bin2hex(random_bytes(5));
    if(file_put_contents($temporary,$php,LOCK_EX)===false)throw new RuntimeException('AI-instellingen konden niet worden opgeslagen.');
    @chmod($temporary,0600);
    if(!rename($temporary,$path)){@unlink($temporary);throw new RuntimeException('AI-instellingen konden niet veilig worden geplaatst.');}
    if(function_exists('opcache_invalidate'))@opcache_invalidate($path,true);
}
if($_SERVER['REQUEST_METHOD']==='POST')try{
    verifyCsrf();$provider=(string)($_POST['provider']??'none');
    if(!in_array($provider,['none','openai','ollama'],true))throw new RuntimeException('Onbekende AI-provider.');
    $model=trim((string)($_POST['model']??''));$endpoint=trim((string)($_POST['endpoint']??''));$key=trim((string)($_POST['api_key']??''));
    if($key==='')$key=(string)($config['api_key']??'');
    if($provider==='openai'&&($model===''||$key===''))throw new RuntimeException('Vul voor OpenAI een model en API-sleutel in.');
    if($provider==='ollama'&&($model===''||!filter_var($endpoint,FILTER_VALIDATE_URL)))throw new RuntimeException('Vul voor Ollama een model en geldige HTTPS-endpoint in.');
    $config=['provider'=>$provider,'model'=>$model,'api_key'=>$provider==='openai'?$key:'','endpoint'=>$provider==='ollama'?rtrim($endpoint,'/'):'','include_database'=>isset($_POST['include_database']),'include_email'=>isset($_POST['include_email']),'timeout'=>max(5,min(60,(int)($_POST['timeout']??25)))];
    saveAiConfig($path,$config);recordActivity(activityDb(),$admin,'ai-provider-ingesteld','instellingen','ai-provider','',$provider==='none'?'AI-provider uitgeschakeld.':'AI-provider ingesteld: '.$provider.' / '.$model.'.');
    $message=$provider==='none'?'De assistent blijft volledig lokaal werken.':'De AI-provider is server-lokaal ingesteld. De lokale zoekfunctie blijft als terugval beschikbaar.';
}catch(Throwable $e){securityIncident($e);$error=$e->getMessage();}
$status=aiProviderStatus();
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AI-instellingen</title><style>
.top{min-height:84px;padding:10px 22px}
:root{--dark:#17202a;--blue:#2457a6;--line:#d7e0e8}*{box-sizing:border-box}body{margin:0;background:#f3f6f9;color:#17202a;font:14px system-ui,-apple-system,Segoe UI,Arial}.top{background:var(--dark);color:#fff;padding:14px 22px;display:flex;align-items:center}.top h1{margin:0}.top .right{margin-left:auto;text-align:right}.wrap{max-width:920px;margin:20px auto;padding:0 14px}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.tabs a,.btn{padding:9px 12px;border:1px solid #bfc8d2;border-radius:7px;color:#17202a;text-decoration:none;background:#fff}.tabs .active,.primary{background:var(--blue);color:#fff}.card{background:#fff;border:1px solid var(--line);border-radius:10px;overflow:hidden}.head{background:#dcecf8;padding:12px 15px;font-weight:800}.body{padding:16px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field label{display:block;font-weight:700;margin-bottom:5px}.field input:not([type=checkbox]),.field select{width:100%;padding:9px;border:1px solid #cbd3dc;border-radius:7px;font:inherit}.full{grid-column:1/-1}.notice,.ok,.err{padding:11px;border-radius:7px;margin-bottom:13px}.notice{background:#fff8e4;border-left:4px solid #e5a51d}.ok{background:#e9f7ed}.err{background:#fff0f0}.actions{text-align:right;margin-top:16px}.muted{display:block;color:#667482;margin-top:4px}@media(max-width:680px){.grid{grid-template-columns:1fr}.full{grid-column:auto}}
</style></head><body><header class="top"><h1><?=tenantNameHtml()?></h1><div class="right"><strong>AI-instellingen</strong><br>GUI v8.50</div></header><main class="wrap"><nav class="tabs"><a href="settings.php">Dropdowns</a><a href="user_management.php">Gebruikers</a><a href="email_settings.php">E-mail</a><a class="active" href="ai_settings.php">AI</a><a href="activity_log.php">Activiteiten</a><a href="./">Database</a></nav>
<?php if($message):?><div class="ok">✓ <?=aih($message)?></div><?php endif;?><?php if($error):?><div class="err">⚠ <?=aih($error)?></div><?php endif;?>
<div class="notice"><strong>Veilige standaard:</strong> provider uit. E-mail- en database-inhoud worden alleen extern verwerkt na een aparte bewuste keuze.</div>
<section class="card"><div class="head">AI-provider kiezen</div><div class="body"><p>Status: <strong><?=$status['configured']?'actief: '.aih($status['provider'].' / '.$status['model']):'uitgeschakeld'?></strong></p><form method="post"><input type="hidden" name="csrf" value="<?=aih(csrfToken())?>"><div class="grid">
<div class="field"><label>Provider</label><select name="provider"><option value="none"<?=$config['provider']==='none'?' selected':''?>>Uit — alleen lokale bronnen</option><option value="openai"<?=$config['provider']==='openai'?' selected':''?>>OpenAI</option><option value="ollama"<?=$config['provider']==='ollama'?' selected':''?>>Eigen Ollama-server</option></select></div>
<div class="field"><label>Model</label><input name="model" value="<?=aih((string)$config['model'])?>" placeholder="Bijv. gpt-5.6-luna"></div><div class="field full"><label>OpenAI API-sleutel</label><input type="password" name="api_key" autocomplete="new-password" placeholder="Leeg laten om de opgeslagen sleutel te behouden"><small class="muted"><?=($config['api_key']??'')!==''?'Er is server-lokaal een sleutel opgeslagen.':'Er is nog geen sleutel opgeslagen.'?></small></div>
<div class="field full"><label>Ollama-endpoint</label><input name="endpoint" value="<?=aih((string)$config['endpoint'])?>" placeholder="https://ai.voorbeeld.nl"></div><div class="field"><label>Timeout in seconden</label><input type="number" name="timeout" min="5" max="60" value="<?=(int)$config['timeout']?>"></div>
<div class="field"><label><input type="checkbox" name="include_database" value="1"<?=!empty($config['include_database'])?' checked':''?>> Databasepassages extern laten verwerken</label><small class="muted">Kan persoonsgegevens bevatten.</small></div><div class="field full"><label><input type="checkbox" name="include_email" value="1"<?=!empty($config['include_email'])?' checked':''?>> E-mailinhoud gebruiken voor AI-dossieranalyse</label><small class="muted">Geselecteerde e-mailpassages gaan naar de ingestelde provider. Schakel dit alleen bewust in.</small></div>
</div><div class="actions"><button class="btn primary" type="submit">Instellingen opslaan</button></div></form></div></section></main></body></html>
