<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';

function aiProviderConfig(): array {
    $defaults=['provider'=>'none','model'=>'','api_key'=>'','endpoint'=>'','include_database'=>false,'include_email'=>false,'timeout'=>25];
    $path=tenantPrivateFile('ai_config.php');
    if(!is_file($path))return $defaults;
    $loaded=require $path;
    return is_array($loaded)?array_replace($defaults,$loaded):$defaults;
}

function aiProviderStatus(): array {
    $config=aiProviderConfig();
    $provider=mb_strtolower(trim((string)$config['provider']));
    $configured=match($provider){
        'openai'=>(string)$config['api_key']!==''&&(string)$config['model']!=='',
        'ollama'=>(string)$config['endpoint']!==''&&(string)$config['model']!=='',
        default=>false
    };
    return ['provider'=>$provider,'configured'=>$configured,'model'=>(string)$config['model']];
}

function aiProviderRequest(string $url,array $headers,array $payload,int $timeout): array {
    if(!function_exists('curl_init'))throw new RuntimeException('De PHP cURL-extensie ontbreekt.');
    $handle=curl_init($url);
    curl_setopt_array($handle,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),CURLOPT_TIMEOUT=>max(5,min(60,$timeout)),CURLOPT_CONNECTTIMEOUT=>8]);
    $body=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$error=curl_error($handle);curl_close($handle);
    if($body===false||$error!=='')throw new RuntimeException('De AI-provider is tijdelijk niet bereikbaar.');
    $decoded=json_decode((string)$body,true);
    if($status<200||$status>=300||!is_array($decoded))throw new RuntimeException('De AI-provider gaf geen bruikbaar antwoord.');
    return $decoded;
}

function aiProviderSourceText(array $sources,bool $includeDatabase,bool $includeEmail,bool $broad=false): string {
    $blocks=[];$included=0;
    $limit=$broad?36:12;$snippetLimit=$broad?3600:1600;
    foreach($sources as $index=>$source){
        if(($source['kind']??'')==='email'&&!$includeEmail)continue;
        if(($source['kind']??'')==='database'&&!$includeDatabase)continue;
        $included++;if($included>$limit)break;$number=(int)$index+1;
        $date=trim((string)($source['timeline_date']??$source['date']??''));
        $kind=match((string)($source['kind']??'')){'database'=>'database','email'=>'e-mail',default=>'document'};
        $blocks[]='[Bron '.$number.']'."\n".'Titel: '.mb_substr((string)($source['title']??''),0,240)."\n".'Datum: '.($date!==''?$date:'onbekend')."\n".'Type: '.$kind."\n".'Locatie: '.mb_substr((string)($source['folder']??''),0,300)."\n".'Passage: '.mb_substr((string)($source['snippet']??''),0,$snippetLimit);
    }
    return implode("\n\n",$blocks);
}

function aiProviderText(array $config,string $instructions,string $input,int $maxTokens=700): array {
    $status=aiProviderStatus();
    if(!$status['configured'])throw new RuntimeException('Er is geen AI-provider ingesteld.');
    $provider=$status['provider'];$timeout=(int)$config['timeout'];
    if($provider==='openai'){
        $response=aiProviderRequest('https://api.openai.com/v1/responses',['Content-Type: application/json','Authorization: Bearer '.(string)$config['api_key']],['model'=>(string)$config['model'],'store'=>false,'instructions'=>$instructions,'input'=>$input,'max_output_tokens'=>$maxTokens],$timeout);
        $text=trim((string)($response['output_text']??''));
        if($text==='')foreach(($response['output']??[]) as $item)foreach(($item['content']??[]) as $content)if(($content['type']??'')==='output_text')$text.=($text!==''?"\n":'').trim((string)($content['text']??''));
    }elseif($provider==='ollama'){
        $response=aiProviderRequest(rtrim((string)$config['endpoint'],'/').'/api/generate',['Content-Type: application/json'],['model'=>(string)$config['model'],'system'=>$instructions,'prompt'=>$input,'stream'=>false],$timeout);
        $text=trim((string)($response['response']??''));
    }else throw new RuntimeException('Onbekende AI-provider.');
    if($text==='')throw new RuntimeException('De AI-provider leverde geen tekst terug.');
    return ['text'=>$text,'provider'=>$provider,'model'=>$status['model']];
}

function aiProviderClassify(string $question): ?array {
    $config=aiProviderConfig();if(!aiProviderStatus()['configured'])return null;
    $instructions='Classificeer uitsluitend de gebruikersvraag. Antwoord exact met één JSON-object zonder markdown: {"archive":true|false,"scope":"none|targeted|broad","search_query":"...","search_terms":["..."]}. archive is alleen true als informatie uit het VvE-archief, documenten, database of e-mail nodig is. Gebruik broad voor alles, chronologie, geschiedenis, correspondentie, onderzoek, rapport of een compleet overzicht; anders targeted. Maak search_query kort. Geef in search_terms maximaal twaalf onderscheidende namen, projectnummers, leveranciers, synoniemen en onderwerpvarianten die daadwerkelijk kunnen helpen bij het terugvinden van het dossier. Voor algemene kennis of rekenen: archive false, scope none.';
    $result=aiProviderText($config,$instructions,$question,180);$raw=trim($result['text']);
    $raw=preg_replace('/^```(?:json)?\s*|\s*```$/iu','',$raw)??$raw;$data=json_decode($raw,true);
    if(!is_array($data)||!array_key_exists('archive',$data))throw new RuntimeException('De vraaganalyse gaf geen bruikbare classificatie.');
    $scope=in_array(($data['scope']??''),['none','targeted','broad'],true)?$data['scope']:(!empty($data['archive'])?'targeted':'none');
    $searchTerms=[];foreach((array)($data['search_terms']??[]) as $term){$term=trim((string)$term);if($term!==''&&mb_strlen($term)<=80)$searchTerms[]=$term;}
    return ['archive'=>(bool)$data['archive'],'scope'=>$scope,'search_query'=>trim((string)($data['search_query']??$question)),'search_terms'=>array_slice(array_values(array_unique($searchTerms)),0,12),'provider'=>$result['provider'],'model'=>$result['model']];
}

function aiProviderSourcePhase(array $source,?string $wanted=null): ?string {
    $text=mb_strtolower(implode(' ',[(string)($source['title']??''),(string)($source['filename']??''),(string)($source['snippet']??'')]));
    $patterns=[
        'besluit'=>'/\b(?:alv|balv|notulen|vergadering|stemming|stemformulier|besluit|mandaat|protocol)\b/iu',
        'opdracht'=>'/\b(?:offerte|prijsopgave|opdracht|opdrachtbon|overeenkomst|contract|factuur|bestelling|aanschaf|financiering|bouwdepot)\b/iu',
        'uitvoering'=>'/\b(?:planning|aanvang|start|montage|installatie|werkzaamheden|uitvoering|oplevering|afgerond|afronding)\b/iu',
        'nazorg'=>'/\b(?:storing|defect|reparatie|herstel|restpunt|nazorg|onderhoud|inspectie|controle|melding|klacht)\b/iu',
        'techniek'=>'/\b(?:kiwa|quickscan|mjop|meerjarenonderhoud|technisch|rapport|bekabeling|schema|keuring|certificaat)\b/iu'
    ];
    foreach($patterns as $phase=>$pattern)if(($wanted===null||$wanted===$phase)&&preg_match($pattern,$text))return $phase;
    return null;
}

function aiProviderBalanceSourcePhases(array $sources,int $limit=36): array {
    if(count($sources)<2||$limit<1)return array_slice($sources,0,max(0,$limit));
    $selected=[];$seen=[];
    $key=static fn(array $source):string=>(string)($source['kind']??'document').':'.(string)($source['id']??sha1(serialize($source)));
    foreach(['besluit','opdracht','uitvoering','nazorg','techniek'] as $phase){
        $phaseCount=0;
        foreach($sources as $source){
            $sourceKey=$key($source);
            if(isset($seen[$sourceKey])||aiProviderSourcePhase($source,$phase)!==$phase)continue;
            $selected[]=$source;$seen[$sourceKey]=true;$phaseCount++;
            if($phaseCount>=2||count($selected)>=$limit)break;
        }
        if(count($selected)>=$limit)break;
    }
    foreach($sources as $source){
        if(count($selected)>=$limit)break;
        $sourceKey=$key($source);if(isset($seen[$sourceKey]))continue;
        $selected[]=$source;$seen[$sourceKey]=true;
    }
    return $selected;
}

function aiProviderSelectRelevantSources(string $question,array $sources,int $limit=36): array {
    $config=aiProviderConfig();
    if(!aiProviderStatus()['configured']||!$sources)return $sources;
    $candidates=[];$sourceMap=[];
    foreach($sources as $source){
        if(($source['kind']??'')==='email'&&empty($config['include_email']))continue;
        if(($source['kind']??'')==='database'&&empty($config['include_database']))continue;
        $number=count($sourceMap)+1;$sourceMap[$number]=$source;
        $phase=aiProviderSourcePhase($source)??'overig';$quality=trim((string)($source['content_quality']??''));
        $candidates[]='[Kandidaat '.$number.']' . "\nTitel: ".mb_substr((string)($source['title']??''),0,220)."\nBestandsnaam: ".mb_substr((string)($source['filename']??''),0,220)."\nLocatie: ".mb_substr((string)($source['folder']??''),0,240)."\nDossierfase: {$phase}".($quality!==''?"\nExtractiestatus: {$quality}":'')."\nHerkenbare inhoudsdatums: ".implode(', ',(array)($source['content_dates']??[]))."\nInhoud: ".mb_substr((string)($source['snippet']??''),0,700);
        if($number>=80)break;
    }
    if(!$sourceMap)return $sources;
    $instructions='U selecteert bronnen voor een inhoudelijk VvE-dossier. Beoordeel de betekenis en het projectverband, niet alleen overeenkomstige woorden. Een bron is relevant als zij rechtstreeks over het gevraagde onderwerp gaat, of aantoonbaar een fase, besluit, offerte, opdracht, financiering, planning, uitvoering, correspondentie of conflict binnen hetzelfde dossier beschrijft. Zorg bij een chronologisch of volledig dossier waar mogelijk voor dekking van besluitvorming, offerte/opdracht, uitvoering, oplevering/nazorg, actuele toestand en primaire technische bronnen. Geef voorrang aan formele besluiten, notulen, opdrachten, overeenkomsten, facturen, planningen en inhoudelijke correspondentie. Een inhoudsarme factuur, opdracht of technisch rapport mag op grond van titel en dossierlocatie worden behouden, maar trek daaruit geen details die niet in de passage staan. Verwerp automatische antwoorden en stukken die alleen algemene woorden delen, bijvoorbeeld een ander vervangingsproject, andere deuren, ventilatoren, gevelinstallaties of algemeen onderhoud. Geef 90-100 voor rechtstreeks beslissende stukken, 80-89 voor duidelijke dossiercontext, 72-79 voor nuttige maar indirecte context en lager dan 72 voor onvoldoende verband. Neem uitsluitend bronnen met minimaal 72 punten op. date is alleen een expliciet in titel of passage aangetroffen gebeurtenis- of brondatum in formaat YYYY-MM-DD; gebruik null als die niet betrouwbaar is vast te stellen en gebruik nooit de upload- of registratiedatum als gebeurtenisdatum. Antwoord uitsluitend met geldig JSON zonder markdown: {"selected":[{"source":1,"score":100,"date":"2025-08-25","reason":"kort"}]}. Gebruik alleen bestaande kandidaatnummers, maximaal '.$limit.', aflopend op relevantie. Selecteer niets wanneer geen kandidaat inhoudelijk past.';
    $input="Gebruikersvraag: {$question}\n\n".implode("\n\n",$candidates);
    $result=aiProviderText($config,$instructions,$input,2200);
    $raw=preg_replace('/^```(?:json)?\s*|\s*```$/iu','',trim($result['text']))??trim($result['text']);
    $decoded=json_decode($raw,true);
    if(!is_array($decoded)||!isset($decoded['selected'])||!is_array($decoded['selected']))throw new RuntimeException('De inhoudelijke bronselectie gaf geen bruikbaar resultaat.');
    $selected=[];$seen=[];
    foreach($decoded['selected'] as $choice){
        $number=(int)($choice['source']??0);
        if($number<1||!isset($sourceMap[$number])||isset($seen[$number]))continue;
        $semanticScore=max(0,min(100,(int)($choice['score']??0)));if($semanticScore<72)continue;
        $source=$sourceMap[$number];$source['semantic_score']=$semanticScore;$source['score']=$semanticScore*1000+(int)($source['score']??0);$source['selection_reason']=mb_substr(trim((string)($choice['reason']??'')),0,240);
        $eventDate=trim((string)($choice['date']??''));if(preg_match('/^20\d{2}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/',$eventDate)){$source['timeline_date']=$eventDate;$source['timeline_date_kind']='bron';}
        $selected[]=$source;$seen[$number]=true;
        if(count($selected)>=$limit)break;
    }
    $phaseCounts=[];foreach($selected as $source)foreach(['besluit','opdracht','uitvoering','nazorg','techniek'] as $phase)if(aiProviderSourcePhase($source,$phase)!==null)$phaseCounts[$phase]=($phaseCounts[$phase]??0)+1;
    foreach(['besluit','opdracht','uitvoering','nazorg','techniek'] as $wantedPhase){
        $needed=2-(int)($phaseCounts[$wantedPhase]??0);if($needed<=0)continue;
        foreach($sourceMap as $number=>$source){
            if($needed<=0)break;if(isset($seen[$number])||aiProviderSourcePhase($source,$wantedPhase)!==$wantedPhase)continue;
            $source['semantic_score']=74;$source['score']=74000+(int)($source['score']??0);$source['selection_reason']='Aanvullende bron voor dossierfase '.$wantedPhase;$selected[]=$source;$seen[$number]=true;$needed--;
        }
    }
    foreach($sourceMap as $number=>$source){
        if(isset($seen[$number])||!preg_match('/\b(kiwa|mjop|meerjarenonderhoud|technisch(?:e)?\s+(?:inspectie|rapport))\b/iu',(string)($source['title']??'')))continue;
        $source['semantic_score']=72;$source['score']=72000+(int)($source['score']??0);$source['selection_reason']='Aanvullende primaire technische of meerjarenbron';$selected[]=$source;$seen[$number]=true;
        if(count($selected)>=$limit)break;
    }
    usort($selected,static fn($a,$b)=>(int)($b['score']??0)<=>(int)($a['score']??0));
    if(count($selected)>$limit){$kept=[];$keptPhases=[];foreach(['besluit','opdracht','uitvoering','nazorg','techniek'] as $phase)foreach($selected as $source){if(($keptPhases[$phase]??0)>=2)break;if(aiProviderSourcePhase($source,$phase)!==null&&!in_array($source,$kept,true)){$kept[]=$source;$keptPhases[$phase]=($keptPhases[$phase]??0)+1;}}foreach($selected as $source){if(count($kept)>=$limit)break;if(!in_array($source,$kept,true))$kept[]=$source;}$selected=$kept;}
    usort($selected,static fn($a,$b)=>(int)($b['score']??0)<=>(int)($a['score']??0));
    return array_slice($selected,0,$limit);
}

function aiProviderDirectAnswer(string $question): ?array {
    $config=aiProviderConfig();if(!aiProviderStatus()['configured'])return null;
    return aiProviderText($config,'Beantwoord de algemene vraag kort en duidelijk in het Nederlands. Gebruik geen VvE-archiefbronnen en beweer niet dat u die hebt geraadpleegd. Geef geen juridisch, financieel of technisch eindoordeel.',$question,500);
}

function aiProviderAnswer(string $question,array $sources,string $intent='zoeken'): ?array {
    $config=aiProviderConfig();$status=aiProviderStatus();
    if(!$status['configured'])return null;
    $broad=in_array($intent,['chronologisch','dossier'],true);
    if($broad)$config['timeout']=max(35,(int)$config['timeout']);
    $sourceText=aiProviderSourceText($sources,(bool)$config['include_database'],(bool)$config['include_email'],$broad);
    if($sourceText==='')return null;
    $instructions=$broad
        ? 'U bent een uitsluitend lezende onderzoeksassistent voor een VvE-archief. Maak op basis van uitsluitend de aangeleverde bronnen een inhoudelijk dossier, niet een opsomming van zoektreffers. Selecteer alleen werkelijk relevante passages; een toevallige woordovereenkomst is onvoldoende. Combineer documenten en e-mail tot gebeurtenissen en voeg kennelijke duplicaten samen. Zet gedateerde gebeurtenissen strikt van oud naar nieuw; controleer ook binnen iedere alinea de datumvolgorde. Geef een expliciete datum in titel of bestandsnaam voorrang boven een oudere datum die toevallig in de passage voorkomt. Onderscheid strikt gebeurtenisdatum, documentdatum en registratiedatum: plaats een bron met uitsluitend een registratiedatum niet als gebeurtenis op die datum. Zet een gebeurtenis zonder betrouwbare datum onder "Datum onbekend" bij de vroegst aantoonbare bespreking. Benoem voorgeschiedenis, besluitvorming, offertes en opdrachten, uitvoering, oplevering of nazorg en huidige stand voor zover aanwezig. Controleer expliciet of latere storings-, herstel-, afrondings- en opleveringsbronnen de eerdere planning wijzigen. Een inhoudsarme bron bewijst alleen dat het genoemde document bestaat: beweer niet dat een opdracht, factuur, rapport of oplevering ontbreekt wanneer een passende inhoudsarme bron is aangeleverd; vermeld dan dat de inhoud niet kon worden uitgelezen en handmatig moet worden gecontroleerd. Signaleer bronconflicten, verschillende stemuitslagen of aantallen en onzekerheden beknopt. Onderbouw iedere gebeurtenis of conclusie met één of meer verwijzingen zoals [Bron 3] en gebruik exact de bestaande bronnummers. Schrijf maximaal 1.700 woorden, vermijd herhaling en reserveer altijd ruimte voor een volledige sectie "Huidige stand en ontbrekende stukken". Rond elke zin en het gehele antwoord volledig af. Gebruik Markdownkoppen met ## voor hoofddelen en ### voor subdelen en zet afzonderlijke gebeurtenisdatums tussen **dubbele sterretjes**. Verzin niets en geef geen juridisch, financieel of technisch eindoordeel. De applicatie maakt van uw antwoord met de knop PDF exporteren een downloadbaar rapport; beweer daarom nooit dat u geen PDF kunt maken.'
        : 'U bent een uitsluitend lezende VvE-archiefassistent. Beantwoord de vraag alleen met de aangeleverde bronnen. Behandel uitsluitend informatie die inhoudelijk over het onderwerp van de vraag gaat; negeer passages waarin alleen een algemeen woord overeenkomt. Onderbouw beweringen met verwijzingen zoals [Bron 2]. Zeg het duidelijk als bronnen onvoldoende of tegenstrijdig zijn. Verzin niets. De applicatie kan het antwoord met de knop PDF exporteren downloaden; beweer niet dat dit onmogelijk is. Geef geen juridisch, financieel of technisch eindoordeel.';
    $input="Vraag: {$question}\nIntentie: {$intent}\n\n{$sourceText}";
    return aiProviderText($config,$instructions,$input,$broad?3000:900);
}
