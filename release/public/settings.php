<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/activity.php';
require_once __DIR__.'/lookup_catalogs.php';

$user=requireAdmin();
$path=tenantPrivateFile('lookups.php');
$db=activityDb();
$message=''; $error='';
function lookupEsc(string $value): string { return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function lookupRead(string $path): array { $data=is_file($path)?include $path:[]; return is_array($data)?$data:[]; }
function lookupWrite(string $path,array $data): void {
    $source="<?php\nreturn ".var_export($data,true).";\n";
    $temp=tempnam(dirname($path),'lookup-');
    if($temp===false)throw new RuntimeException('Opslaan mislukt.');
    try {
        if(file_put_contents($temp,$source,LOCK_EX)===false)throw new RuntimeException('Opslaan mislukt.');
        chmod($temp,0600);
        if(!rename($temp,$path))throw new RuntimeException('Opslaan mislukt.');
        if(function_exists('opcache_invalidate'))opcache_invalidate($path,true);
    } finally { if(is_file($temp))unlink($temp); }
}
function lookupMapped(string $group,string $field): bool {
    return ($group==='Activa'&&$field==='ActivaCatID')||($group==='maintenance_plans'&&in_array($field,['OnderhoudsCategorie','StabuCode'],true));
}
$saved=lookupRead($path);
$lookups=catalogLookups($db,$saved);
if($_SERVER['REQUEST_METHOD']==='POST'){
    try {
        verifyCsrf();
        $updated=$saved;
        foreach($lookups as $group=>$fields){
            if(!is_array($fields))continue;
            foreach($fields as $field=>$values){
                if(lookupMapped((string)$group,(string)$field)){
                    $posted=(array)($_POST['catalogs'][$group][$field]??[]);
                    if(array_diff(array_keys((array)$values),array_keys($posted))||array_diff(array_keys($posted),array_keys((array)$values)))
                        throw new RuntimeException('De lijst is gewijzigd. Laad de pagina opnieuw.');
                    $updated[$group][$field]=catalogEditedRows((string)$field,$posted,(array)($_POST['new_codes'][$group][$field]??[]),(array)($_POST['new_labels'][$group][$field]??[]));
                }else{
                    $text=(string)($_POST['groups'][$group][$field]??'');
                    $updated[$group][$field]=array_values(array_filter(array_map('trim',preg_split('/\R/u',$text)),static fn($x)=>$x!==''));
                }
            }
        }
        lookupWrite($path,$updated);
        $lookups=catalogLookups($db,$updated);
        recordActivity($db,$user,'instellingen-gewijzigd','instellingen','','','Dropdownwaarden opgeslagen.');
        $message='Dropdownwaarden zijn opgeslagen.';
    } catch(Throwable $exception){
        securityIncident($exception);
        $error='Opslaan is mislukt: '.($exception instanceof RuntimeException?$exception->getMessage():'controleer de invoer.');
    }
}
$catalogTitles=['ActivaCatID'=>'Activa-categorie','OnderhoudsCategorie'=>'Onderhoudscategorie','StabuCode'=>'STABU-code','TypeMelding'=>'Wat wilt u doorgeven?','OnderwerpMelding'=>'Onderwerp','LocatieMelding'=>'Locatie','UrgentieMelding'=>'Urgentie'];$catalogGroupTitles=['resident_portal'=>'Bewonersportaal'];
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instellingen · Dropdowns</title><style>
:root{--dark:#17202a;--blue:#2457a6;--line:#d7e0e8}*{box-sizing:border-box}body{margin:0;background:#f3f6f9;color:#17202a;font:14px system-ui,-apple-system,Segoe UI,Arial,sans-serif}.top{min-height:84px;background:var(--dark);color:#fff;padding:10px 22px;display:flex;align-items:center;gap:18px}.logo{width:86px;height:64px;object-fit:contain;background:#fff;border-radius:8px}.brand h1{margin:0;font-size:25px}.brand small{opacity:.82}.status{margin-left:auto;text-align:right}.status>strong,.status>span{display:block}.status>span{font-size:12px;opacity:.82}.userbar{display:flex;justify-content:flex-end;gap:8px;margin-top:4px}.badge,.topbtn{color:#fff;border:1px solid rgba(255,255,255,.35);padding:3px 7px;border-radius:5px;font-size:11px;text-decoration:none}.topbtn{background:var(--blue);font-weight:700}.wrap{max-width:1100px;margin:18px auto;padding:0 14px}.tabs{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap}.tabs a,.btn{padding:9px 12px;border:1px solid #bfc8d2;border-radius:7px;color:#17202a;text-decoration:none;background:#fff}.tabs .active,.primary{background:var(--blue);color:#fff}.card{background:#fff;border:1px solid var(--line);border-radius:10px;margin-bottom:14px;overflow:hidden}.head{background:#dcecf8;padding:10px 14px;font-weight:800}.body{padding:14px}.field{margin-bottom:18px}.field label{display:block;font-weight:700;margin-bottom:5px}.field small{display:block;color:#667482;margin:4px 0}textarea,select,input{border:1px solid #cbd3dc;border-radius:7px;padding:8px;font:inherit}textarea{width:100%;min-height:90px}select{width:100%;max-width:600px;background:#fff}.catalog-list{display:grid;gap:7px;margin:9px 0}.catalog-row{display:grid;grid-template-columns:130px 1fr;gap:8px;align-items:center}.catalog-row input{width:100%}.catalog-code{font-weight:700;color:#536170}.catalog-add{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px}.catalog-add input:first-child{width:130px}.catalog-add input:nth-child(2){flex:1;min-width:180px}.actions{display:flex;justify-content:flex-end}.ok,.err{padding:9px;border-radius:7px;margin-bottom:12px}.ok{background:#e9f7ed}.err{background:#fff0f0}@media(max-width:760px){.top{flex-wrap:wrap}.status{width:100%;margin:0;text-align:left}.userbar{justify-content:flex-start;flex-wrap:wrap}.catalog-row{grid-template-columns:90px 1fr}}
</style></head><body><header class="top"><img class="logo" src="logo.png" alt="OpenVvE AI"><div class="brand"><h1><?=tenantNameHtml()?></h1><small>Uw VvE · Instellingen</small></div><div class="status"><strong>Verbonden</strong><span>Instellingen</span><div class="userbar"><span class="badge">👤 <?=lookupEsc((string)$user['name'])?> · admin</span><a class="badge" href="version_log.php">GUI V8.60</a><a class="topbtn" href="./">🗃 Database</a><a class="topbtn" href="documents.php">📁 Bestanden</a></div></div></header><main class="wrap"><nav class="tabs"><a class="active" href="settings.php">Dropdowns</a><a href="resident_portal_admin.php">Bewonersportaal</a><a href="user_management.php">👥 Gebruikers</a><a href="email_settings.php">✉ E-mail</a><a href="ai_settings.php">AI</a><a href="backup_settings.php">Back-ups</a><a href="activity_log.php">Activiteiten</a><a href="version_log.php">Versies</a></nav><?php if($message):?><div class="ok">✓ <?=lookupEsc($message)?></div><?php endif;?><?php if($error):?><div class="err">⚠ <?=lookupEsc($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=lookupEsc(csrfToken())?>"><?php foreach($lookups as $group=>$fields):if(!is_array($fields))continue;?><section class="card"><div class="head">Groep: <?=lookupEsc($catalogGroupTitles[$group]??(string)$group)?></div><div class="body"><?php foreach($fields as $field=>$values):$mapped=lookupMapped((string)$group,(string)$field);?><div class="field"><label><?=lookupEsc($catalogTitles[$field]??(string)$field)?></label><?php if($mapped):$key=(string)$group.'-'.(string)$field;?><small>Kies een waarde om de keuzelijst te bekijken. Wijzig de tekst hieronder; de code blijft gelijk.</small><select aria-label="Voorbeeld <?=lookupEsc($catalogTitles[$field]??(string)$field)?>" data-catalog="<?=lookupEsc($key)?>"><option value="">— Kies een <?=lookupEsc($catalogTitles[$field]??(string)$field)?> —</option><?php foreach((array)$values as $code=>$label):?><option value="<?=lookupEsc((string)$code)?>"><?=lookupEsc((string)$code.' — '.(string)$label)?></option><?php endforeach;?></select><?php if(!$values):?><small>Er zijn nog geen waarden in de bijbehorende databasetabel. Voeg hieronder een keuze toe.</small><?php endif;?><div class="catalog-list" data-list="<?=lookupEsc($key)?>"><?php foreach((array)$values as $code=>$label):?><div class="catalog-row"><span class="catalog-code"><?=lookupEsc((string)$code)?></span><input type="text" maxlength="255" required aria-label="Tekst voor code <?=lookupEsc((string)$code)?>" data-code="<?=lookupEsc((string)$code)?>" data-catalog="<?=lookupEsc($key)?>" name="catalogs[<?=lookupEsc((string)$group)?>][<?=lookupEsc((string)$field)?>][<?=lookupEsc((string)$code)?>]" value="<?=lookupEsc((string)$label)?>"></div><?php endforeach;?></div><?php if($field!=='StabuCode'):?><div class="catalog-add"><input type="text" maxlength="32" id="new-code-<?=lookupEsc($key)?>" placeholder="Nieuwe code / ID" aria-label="Nieuwe code voor <?=lookupEsc((string)$field)?>"><input type="text" maxlength="255" id="new-label-<?=lookupEsc($key)?>" placeholder="Nieuwe omschrijving" aria-label="Nieuwe omschrijving voor <?=lookupEsc((string)$field)?>"><button class="btn" type="button" data-add="<?=lookupEsc($key)?>" data-group="<?=lookupEsc((string)$group)?>" data-field="<?=lookupEsc((string)$field)?>">+ Toevoegen</button></div><?php endif;?><?php else:?><small>Één keuze per regel.</small><textarea name="groups[<?=lookupEsc((string)$group)?>][<?=lookupEsc((string)$field)?>]"><?=lookupEsc(implode("\n",(array)$values))?></textarea><?php endif;?></div><?php endforeach;?></div></section><?php endforeach;?><div class="actions"><button class="btn primary" type="submit">💾 Dropdowns opslaan</button></div></form></main><script>
document.querySelectorAll('select[data-catalog]').forEach(select=>select.addEventListener('change',()=>{if(!select.value)return;const input=[...document.querySelectorAll('input[data-code][data-catalog]')].find(x=>x.dataset.catalog===select.dataset.catalog&&x.dataset.code===select.value);if(input){input.scrollIntoView({block:'center'});input.focus()}}));
document.querySelectorAll('[data-catalog][data-code]').forEach(input=>input.addEventListener('input',()=>{const select=document.querySelector('select[data-catalog="'+CSS.escape(input.dataset.catalog)+'"]');const option=[...select.options].find(x=>x.value===input.dataset.code);if(option)option.textContent=input.dataset.code+' — '+input.value;}));
document.querySelectorAll('[data-add]').forEach(button=>button.addEventListener('click',()=>{const key=button.dataset.add,codeInput=document.getElementById('new-code-'+key),labelInput=document.getElementById('new-label-'+key),code=codeInput.value.trim(),label=labelInput.value.trim(),select=document.querySelector('select[data-catalog="'+CSS.escape(key)+'"]');if(!code||!label){alert('Vul een code en een omschrijving in.');return}if([...select.options].some(x=>x.value===code)){alert('Deze code bestaat al.');return}const row=document.createElement('div');row.className='catalog-row';const caption=document.createElement('span');caption.className='catalog-code';caption.textContent=code;const input=document.createElement('input');input.type='text';input.required=true;input.maxLength=255;input.value=label;input.name='new_labels['+button.dataset.group+']['+button.dataset.field+'][]';const hidden=document.createElement('input');hidden.type='hidden';hidden.name='new_codes['+button.dataset.group+']['+button.dataset.field+'][]';hidden.value=code;row.append(caption,input,hidden);document.querySelector('[data-list="'+CSS.escape(key)+'"]').append(row);select.add(new Option(code+' — '+label,code));select.value=code;input.addEventListener('input',()=>{const option=[...select.options].find(x=>x.value===code);if(option)option.textContent=code+' — '+input.value});codeInput.value='';labelInput.value='';}));
</script></body></html>
