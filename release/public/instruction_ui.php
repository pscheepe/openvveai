<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';

function instructionVideos(): array {
    return [
        'login-contacten' => ['title'=>'Inloggen en contacten', 'file'=>'OpenVvEAI Login en Contacten.mp4'],
        'relaties' => ['title'=>'Relaties', 'file'=>'Relaties.mp4'],
        'activa-onderhoud' => ['title'=>'Activa en onderhoudsplannen', 'file'=>'Activa en Onderhoudsplannen.mp4'],
        'vergaderingen' => ['title'=>'Vergaderingen', 'file'=>'Vergaderingen.mp4'],
        'sleutels' => ['title'=>'Sleutels', 'file'=>'Sleutels.mp4'],
        'bestanden' => ['title'=>'Bestanden', 'file'=>'Bestanden.mp4'],
        'assistent' => ['title'=>'Assistent', 'file'=>'Assistent.mp4'],
        'projecten' => ['title'=>'Projecten', 'file'=>'Projecten.mp4'],
        'instellingen' => ['title'=>'Instellingen', 'file'=>'Instellingen.mp4'],
    ];
}

function instructionVideoPath(string $key): string {
    $video=instructionVideos()[$key]??null;
    if(!$video)throw new RuntimeException('Onbekende instructievideo.');
    // All VvE installations under the same hosting root share this protected
    // library; video streaming itself still passes through instruction_video.php.
    $sharedRoot=dirname(__DIR__,2).DIRECTORY_SEPARATOR.'instructies';
    if(is_link($sharedRoot))throw new RuntimeException('Gedeelde instructiemap mag geen symbolische link zijn.');
    if(!is_dir($sharedRoot)&&!mkdir($sharedRoot,0700,true)&&!is_dir($sharedRoot))throw new RuntimeException('Gedeelde instructiemap ontbreekt.');
    $root=realpath($sharedRoot);
    if($root===false)throw new RuntimeException('Gedeelde instructiemap ontbreekt.');
    return $root.DIRECTORY_SEPARATOR.$video['file'];
}

function renderInstructionMenu(): void {
    static $rendered=false;
    if($rendered)return;
    $rendered=true;
    $videos=instructionVideos();
    ?>
    <style>
    :root{--app-button-height:30px}.btn,.adminlink,.header-link,.topbtn,.logout,.instruction-menu>summary{box-sizing:border-box;min-height:var(--app-button-height)!important;display:inline-flex!important;align-items:center;justify-content:center;gap:5px;line-height:1.1!important}.ui-icon-added::before{content:attr(data-ui-icon);font-size:14px;line-height:1}.source-actions{column-gap:28px!important}.source-actions .link-assets{margin-left:10px}.doc>.actions{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;align-items:stretch!important}.doc>.actions>.btn{box-sizing:border-box!important;width:100%!important;height:34px!important;min-height:34px!important;margin:0!important;padding:4px 7px!important;text-align:center!important}
    .instruction-menu{position:relative;display:inline-block;text-align:left;font:inherit}.instruction-menu>summary{list-style:none;cursor:pointer;background:#245eae;border:1px solid #4078bb;border-radius:6px;color:#fff;padding:5px 9px;font-size:12px;font-weight:700;white-space:nowrap}.instruction-menu>summary::-webkit-details-marker{display:none}.instruction-menu>summary::after{content:' ▾';font-size:10px}.instruction-menu[open]>summary::after{content:' ▴'}.instruction-options{position:absolute;right:0;top:calc(100% + 5px);z-index:99990;width:max-content;min-width:220px;padding:6px;background:#fff;border:1px solid #c9d5df;border-radius:8px;box-shadow:0 12px 32px rgba(0,0,0,.28)}.instruction-options button{display:block;width:100%;border:0;border-radius:5px;background:#fff;color:#17202a;padding:8px 10px;text-align:left;font:13px system-ui,-apple-system,Segoe UI,Arial,sans-serif;cursor:pointer}.instruction-options button:hover,.instruction-options button:focus{background:#eaf2ff;color:#174f8e}.instruction-window{position:fixed;z-index:100000;left:max(18px,calc(50vw - 430px));top:max(18px,calc(50vh - 280px));width:min(860px,calc(100vw - 36px));background:#111923;border:1px solid #718096;border-radius:12px;box-shadow:0 22px 70px rgba(0,0,0,.48);overflow:hidden;color:#fff;text-align:left}.instruction-window[hidden]{display:none}.instruction-window-head{display:flex;align-items:center;gap:10px;padding:9px 10px 9px 14px;background:#17202a;cursor:move;user-select:none;touch-action:none}.instruction-window-title{font:700 15px system-ui,-apple-system,Segoe UI,Arial,sans-serif;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.instruction-window-spacer{flex:1}.instruction-window-close{border:1px solid #8190a0;border-radius:6px;background:#fff;color:#17202a;padding:6px 10px;font:700 12px system-ui,-apple-system,Segoe UI,Arial,sans-serif;cursor:pointer}.instruction-video{display:block;width:100%;max-height:calc(100vh - 100px);background:#000}@media(max-width:650px){.instruction-options{position:fixed;left:12px;right:12px;top:auto;width:auto}.instruction-window{left:9px!important;top:9px!important;width:calc(100vw - 18px)}.instruction-window-head{cursor:default}.btn[data-compact-icon],.adminlink[data-compact-icon],.header-link[data-compact-icon],.topbtn[data-compact-icon],.logout[data-compact-icon]{width:var(--app-button-height)!important;min-width:var(--app-button-height)!important;max-width:var(--app-button-height)!important;padding:0!important;font-size:0!important;flex:0 0 var(--app-button-height)!important}.btn[data-compact-icon]::before,.adminlink[data-compact-icon]::before,.header-link[data-compact-icon]::before,.topbtn[data-compact-icon]::before,.logout[data-compact-icon]::before{content:attr(data-compact-icon)!important;font-size:16px!important;line-height:1!important}.source-actions .link-assets{margin-left:4px}}
    @media(max-width:650px){.instruction-menu>summary{width:var(--app-button-height)!important;min-width:var(--app-button-height)!important;padding:0!important;font-size:0!important}.instruction-menu>summary::before{content:attr(data-compact-icon);font-size:16px}.instruction-menu>summary::after{display:none}.doc>.actions>.btn[data-compact-icon]{width:100%!important;max-width:none!important;height:42px!important;min-height:42px!important;flex:auto!important}}
    </style>
    <details class="instruction-menu" id="instructionMenu">
      <summary data-compact-icon="🎬" aria-label="Instructies" title="Instructies">🎬 Instructies</summary>
      <div class="instruction-options">
        <?php foreach($videos as $key=>$video): ?>
          <button type="button" data-instruction-key="<?=htmlspecialchars($key,ENT_QUOTES,'UTF-8')?>" data-instruction-title="<?=htmlspecialchars($video['title'],ENT_QUOTES,'UTF-8')?>"><?=htmlspecialchars($video['title'],ENT_QUOTES,'UTF-8')?></button>
        <?php endforeach; ?>
      </div>
    </details>
    <section class="instruction-window" id="instructionWindow" role="dialog" aria-modal="false" aria-labelledby="instructionWindowTitle" hidden>
      <div class="instruction-window-head" id="instructionWindowHandle">
        <span aria-hidden="true">🎬</span><strong class="instruction-window-title" id="instructionWindowTitle">Instructievideo</strong><span class="instruction-window-spacer"></span>
        <button class="instruction-window-close" id="instructionWindowClose" type="button">✕ Sluiten</button>
      </div>
      <video class="instruction-video" id="instructionVideo" controls preload="metadata" playsinline></video>
    </section>
    <script>
    (()=>{const menu=document.getElementById('instructionMenu'),win=document.getElementById('instructionWindow'),video=document.getElementById('instructionVideo'),title=document.getElementById('instructionWindowTitle'),close=document.getElementById('instructionWindowClose'),handle=document.getElementById('instructionWindowHandle');if(!menu||!win||!video)return;const shut=()=>{video.pause();video.removeAttribute('src');video.load();win.hidden=true};menu.querySelectorAll('[data-instruction-key]').forEach(button=>button.addEventListener('click',()=>{title.textContent=button.dataset.instructionTitle||'Instructievideo';video.src='instruction_video.php?v='+encodeURIComponent(button.dataset.instructionKey);win.hidden=false;menu.open=false;video.play().catch(()=>{})}));close.addEventListener('click',shut);document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!win.hidden)shut()});let drag=null;handle.addEventListener('pointerdown',event=>{if(event.target.closest('button')||matchMedia('(max-width:650px)').matches)return;const rect=win.getBoundingClientRect();drag={dx:event.clientX-rect.left,dy:event.clientY-rect.top};handle.setPointerCapture(event.pointerId)});handle.addEventListener('pointermove',event=>{if(!drag)return;const left=Math.max(0,Math.min(innerWidth-win.offsetWidth,event.clientX-drag.dx)),top=Math.max(0,Math.min(innerHeight-win.offsetHeight,event.clientY-drag.dy));win.style.left=left+'px';win.style.top=top+'px'});const stop=()=>drag=null;handle.addEventListener('pointerup',stop);handle.addEventListener('pointercancel',stop);
    const iconFor=label=>{const s=label.toLocaleLowerCase('nl');if(/annuleren|sluiten|wissen|prullenbak|deactiveren/.test(s))return s.includes('annuleren')?'↩':s.includes('sluiten')?'✕':'🗑';if(/zoeken|toon resultaat/.test(s))return'🔎';if(/lijst/.test(s))return'☷';if(/opslaan/.test(s))return'💾';if(/upload/.test(s))return'⬆';if(/download|csv|pdf/.test(s))return'⬇';if(/versie/.test(s))return'🕘';if(/herstellen|opnieuw/.test(s))return'↻';if(/map/.test(s))return'📁';if(/index/.test(s))return'⚙';if(/openen/.test(s))return'↗';if(/wijzigen/.test(s))return'✏️';if(/koppelen/.test(s))return'🔗';if(/inloggen|uitloggen/.test(s))return'↪';if(/nieuw|aanmaken/.test(s))return'＋';return'•'};
    const selector='.btn,.adminlink,.header-link,.topbtn,.logout',polish=root=>{const nodes=[];if(root.nodeType===1&&root.matches?.(selector))nodes.push(root);root.querySelectorAll?.(selector).forEach(node=>nodes.push(node));nodes.forEach(node=>{if(node.dataset.uiPolished)return;const label=node.textContent.trim();if(!label)return;const leading=label.match(/^(?:[\p{Extended_Pictographic}]|[＋×✕↑↩↻↪☷])/u)?.[0]||'',icon=leading||iconFor(label);node.dataset.uiPolished='1';node.dataset.compactIcon=icon;node.setAttribute('aria-label',node.getAttribute('aria-label')||label);node.setAttribute('title',node.getAttribute('title')||label);if(!leading){node.dataset.uiIcon=icon;node.classList.add('ui-icon-added')}})};addEventListener('DOMContentLoaded',()=>{polish(document);new MutationObserver(records=>records.forEach(record=>record.addedNodes.forEach(node=>polish(node)))).observe(document.body,{childList:true,subtree:true})});})();
    </script>
    <?php
}
