<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/security.php';
require_once __DIR__.'/activity.php';
require_once __DIR__.'/ai_provider.php';
require_once __DIR__.'/assistant_financial.php';
header('Content-Type: application/json; charset=utf-8');

function assistantOut(array $data,int $status=200): never {
    http_response_code($status);
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function assistantTerms(string $question): array {
    $question=mb_strtolower($question);
    $parts=preg_split('/[^\p{L}\p{N}]+/u',$question,-1,PREG_SPLIT_NO_EMPTY)?:[];
    $stop=['aan','als','alles','bestand','bestanden','betreffende','betrekking','bij','chronologisch','chronologische','correspondentie','database','dat','de','den','der','die','dit','doe','doet','downloadable','donwloadable','een','en','er','geef','heeft','hebben','het','hoe','ik','in','is','kan','laatste','maak','maken','meest','met','msg','naar','niet','nummer','of','om','op','over','overzicht','pdf','project','rapport','resultaat','samenvatting','staat','te','van','voor','voorkomende','wat','waar','welk','welke','wie','woont','wonen','wordt','zijn','zoek','zoeken','eml'];
    $terms=[];
    foreach($parts as $part){
        if(mb_strlen($part)<3||in_array($part,$stop,true))continue;
        $terms[$part]=true;
        if(count($terms)>=8)break;
    }
    return array_map('strval', array_keys($terms));
}
function assistantIdentifier(string $name): string {
    if(!preg_match('/^[A-Za-z0-9_]+$/',$name))throw new RuntimeException('Onveilige database-identificatie.');
    return '`'.$name.'`';
}
function assistantActualColumn(array $columns,array $candidates): ?string {
    foreach($candidates as $candidate)foreach($columns as $column)if(mb_strtolower($column)===mb_strtolower($candidate))return $column;
    return null;
}
function assistantComparable(string $value): string {
    $value=mb_strtolower(trim($value));
    $ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);
    if($ascii!==false)$value=$ascii;
    return preg_replace('/[^a-z0-9]+/','',$value)??'';
}
function assistantDatabaseSources(PDO $pdo,array $terms): array {
    $tables=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $definitions=[
        'contacts'=>['tables'=>['Contacten','contacts','contacten'],'label'=>'Contact','title'=>['VoorNaam','Tussenvoegsel','AchterNaam','Naam'],'fields'=>['VoorNaam','Tussenvoegsel','AchterNaam','Naam','Huisnummer','Hal','Status','Actief']],
        'addresses'=>['tables'=>['Adressen','addresses','adressen'],'label'=>'Adres','title'=>['Straat','Huisnummer','Toevoegsel'],'fields'=>['Straat','Huisnummer','Toevoegsel','Postcode','Plaats','Hal','ContactID','Bewoner_ContactID','RelatieID']],
        'relations'=>['tables'=>['Relaties','relations','relaties'],'label'=>'Relatie','title'=>['Naam','Alias'],'fields'=>['Naam','Alias','Contactpersoon','Adres','Postcode','Plaats','Telefoon','Email']],
        'Activa'=>['tables'=>['Activa'],'label'=>'Activum','title'=>['Naam','Omschrijving','ActivaNaam'],'fields'=>['Naam','ActivaNaam','Omschrijving','Locatie','Status','Categorie','ActivaCategorie']],
        'maintenance_plans'=>['tables'=>['Onderhoudsplannen'],'label'=>'Onderhoudsplan','title'=>['Naam','Werkomschrijving','Omschrijving'],'fields'=>['Naam','Werkomschrijving','Omschrijving','Opmerkingen','Status','Planjaar','UitvoeringsJaar','Uitvoerder','StabuCode']],
        'meetings'=>['tables'=>['Vergaderingen'],'label'=>'Vergadering','title'=>['Datum','Soort','Locatie'],'fields'=>['Datum','Soort','Locatie','Agenda','Verslag','Aanwezig']],
        'actions'=>['tables'=>['ActieRegister'],'label'=>'Actiepunt','title'=>['Actie','Omschrijving'],'fields'=>['Actie','Omschrijving','Verantwoordelijk','Datum','DatumCompleet','Compleet']]
    ];
    $results=[];
    foreach($definitions as $entity=>$definition){
        $table=null;
        foreach($definition['tables'] as $candidate)foreach($tables as $available)if(mb_strtolower((string)$available)===mb_strtolower($candidate)){$table=(string)$available;break 2;}
        if($table===null)continue;
        try{
            $columnStmt=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
            $columnStmt->execute([$table]);$columns=$columnStmt->fetchAll(PDO::FETCH_COLUMN);
            $primaryStmt=$pdo->prepare("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME='PRIMARY' ORDER BY ORDINAL_POSITION LIMIT 1");
            $primaryStmt->execute([$table]);$primary=(string)($primaryStmt->fetchColumn()?:'');
            $searchColumns=[];
            foreach($definition['fields'] as $candidate){$actual=assistantActualColumn($columns,[$candidate]);if($actual!==null&&!in_array($actual,$searchColumns,true))$searchColumns[]=$actual;}
            if(!$searchColumns)continue;
            $conditions=[];$params=[];
            foreach($terms as $term)foreach($searchColumns as $column){$conditions[]='CAST('.assistantIdentifier($column).' AS CHAR) LIKE ?';$params[]='%'.$term.'%';}
            $sql='SELECT * FROM '.assistantIdentifier($table).' WHERE '.implode(' OR ',$conditions).' LIMIT 100';
            $stmt=$pdo->prepare($sql);$stmt->execute($params);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
                $parts=[];foreach($searchColumns as $column){$value=trim((string)($row[$column]??''));if($value!==''&&!in_array($value,$parts,true))$parts[]=$value;}
                $titleParts=[];foreach($definition['title'] as $candidate){$column=assistantActualColumn($columns,[$candidate]);$value=$column===null?'':trim((string)($row[$column]??''));if($value!==''&&!in_array($value,$titleParts,true))$titleParts[]=$value;}
                if($entity==='addresses'){
                    $contactIdColumn=assistantActualColumn($columns,['ContactID','Bewoner_ContactID','contact_id']);
                    $contactId=$contactIdColumn===null?'':trim((string)($row[$contactIdColumn]??''));
                    if($contactId!==''){
                        $contactTable=null;foreach(['Contacten','contacts','contacten'] as $candidate)foreach($tables as $available)if(mb_strtolower((string)$available)===mb_strtolower($candidate)){$contactTable=(string)$available;break 2;}
                        if($contactTable!==null){
                            $contactColumnStmt=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$contactColumnStmt->execute([$contactTable]);$contactColumns=$contactColumnStmt->fetchAll(PDO::FETCH_COLUMN);
                            $contactPrimary=assistantActualColumn($contactColumns,['ContactID','contact_id','ID','id']);
                            if($contactPrimary!==null){
                                $contactStmt=$pdo->prepare('SELECT * FROM '.assistantIdentifier($contactTable).' WHERE '.assistantIdentifier($contactPrimary).'=? LIMIT 1');$contactStmt->execute([$contactId]);$contact=$contactStmt->fetch(PDO::FETCH_ASSOC);
                                if($contact){$name=[];foreach(['VoorNaam','Tussenvoegsel','AchterNaam','Naam'] as $candidate){$column=assistantActualColumn($contactColumns,[$candidate]);$value=$column===null?'':trim((string)($contact[$column]??''));if($value!=='')$name[]=$value;}if($name){$resident=implode(' ',$name);$titleParts[]=$resident;$parts[]='Bewoner '.$resident;}}
                            }
                        }
                    }
                }
                $text=implode(' · ',$parts);$score=0;foreach($terms as $term)if(mb_stripos($text,$term)!==false)$score++;
                $id=$primary!==''?(string)($row[$primary]??''):'';
                $results[]=['kind'=>'database','id'=>$id,'title'=>implode(' ',$titleParts)?:$definition['label'].($id!==''?' '.$id:''),'filename'=>$definition['label'].($id!==''?' #'.$id:''),'folder'=>'Database','entity'=>$entity,'source'=>'database','external_url'=>'','pages'=>[],'best_page'=>null,'snippet'=>assistantSnippet($text,$terms),'score'=>$score,'matched_terms'=>assistantMatchedTerms($text,$terms)];
            }
        }catch(Throwable $ignored){continue;}
    }
    return $results;
}
function assistantResidenceSources(PDO $pdo,string $question,array $terms): array {
    $tables=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $table=null;foreach(['Contacten','contacts','contacten'] as $candidate)foreach($tables as $available)if(mb_strtolower((string)$available)===mb_strtolower($candidate)){$table=(string)$available;break 2;}
    if($table===null)return [];
    $columnStmt=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$columnStmt->execute([$table]);$columns=$columnStmt->fetchAll(PDO::FETCH_COLUMN);
    $primaryStmt=$pdo->prepare("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME='PRIMARY' ORDER BY ORDINAL_POSITION LIMIT 1");$primaryStmt->execute([$table]);$primary=(string)($primaryStmt->fetchColumn()?:'');
    $house=assistantActualColumn($columns,['Huisnummer','HuisNummer','Appartementnummer']);
    $hal=assistantActualColumn($columns,['Hal']);
    $nameColumns=[];foreach(['Aanhef','VoorNaam','Voorletters','Tussenvoegsel','AchterNaam','Naam'] as $candidate){$column=assistantActualColumn($columns,[$candidate]);if($column!==null&&!in_array($column,$nameColumns,true))$nameColumns[]=$column;}
    if($primary===''||$house===null||!$nameColumns)return [];
    $conditions=[];$params=[];$number='';
    if(preg_match('/\b(?:huisnummer|nummer|appartement)\s*(?:nr\.?\s*)?(\d{1,4}[a-z]?)\b/iu',$question,$match)){
        $number=(string)$match[1];$conditions[]='CAST('.assistantIdentifier($house).' AS CHAR) LIKE ?';$params[]=$number.'%';
    }else{
        $nameTerms=array_values(array_filter($terms,static fn($term)=>!preg_match('/^\d+$/',$term)));
        if(!$nameTerms)return [];
        $nameExpression="CONCAT_WS(' ',".implode(',',array_map('assistantIdentifier',$nameColumns)).')';
        foreach($nameTerms as $term){$conditions[]=$nameExpression.' LIKE ?';$params[]='%'.$term.'%';}
    }
    $stmt=$pdo->prepare('SELECT * FROM '.assistantIdentifier($table).' WHERE '.implode(' AND ',$conditions).' LIMIT 20');$stmt->execute($params);
    $matchedRows=$stmt->fetchAll(PDO::FETCH_ASSOC);$suggested=false;
    if(!$matchedRows&&$number===''){
        $wanted=array_values(array_filter(array_map('assistantComparable',$terms)));
        $candidates=[];
        foreach($pdo->query('SELECT * FROM '.assistantIdentifier($table))->fetchAll(PDO::FETCH_ASSOC) as $candidateRow){
            $words=[];
            foreach($nameColumns as $column)foreach(preg_split('/[^\p{L}\p{N}]+/u',(string)($candidateRow[$column]??''),-1,PREG_SPLIT_NO_EMPTY)?:[] as $word){$normal=assistantComparable($word);if($normal!=='')$words[]=$normal;}
            $distance=999;foreach($wanted as $needle)foreach($words as $word)$distance=min($distance,levenshtein($needle,$word));
            $longest=$wanted?max(array_map('strlen',$wanted)):0;$distanceLimit=$longest<=5?1:($longest<=9?2:3);
            if($distance<=$distanceLimit)$candidates[]=['distance'=>$distance,'row'=>$candidateRow];
        }
        usort($candidates,static fn($a,$b)=>$a['distance']<=>$b['distance']);
        $matchedRows=array_column(array_slice($candidates,0,3),'row');$suggested=(bool)$matchedRows;
    }
    $results=[];foreach($matchedRows as $row){
        $name=[];foreach($nameColumns as $column){$value=trim((string)($row[$column]??''));if($value!==''&&!in_array($value,$name,true))$name[]=$value;}
        $id=(string)($row[$primary]??'');$houseValue=trim((string)($row[$house]??''));$halValue=$hal===null?'':trim((string)($row[$hal]??''));
        $title=implode(' ',$name)?:'Contact '.$id;$details='Huisnummer '.$houseValue.($halValue!==''?' · Hal '.$halValue:'');
        $results[]=['kind'=>'database','id'=>$id,'title'=>$title,'filename'=>'Contact #'.$id,'folder'=>'Database','entity'=>'contacts','source'=>'database','external_url'=>'','pages'=>[],'best_page'=>null,'snippet'=>$details,'score'=>10,'suggested'=>$suggested];
    }
    return $results;
}
function assistantSnippet(string $text,array $terms,int $limit=430): string {
    $text=preg_replace('/\s+/u',' ',trim($text))??'';
    if($text==='')return '';
    $position=null;
    foreach($terms as $term){$found=mb_stripos($text,$term);if($found!==false&&($position===null||$found<$position))$position=$found;}
    $start=max(0,(int)($position??0)-120);
    $snippet=mb_substr($text,$start,$limit);
    return ($start>0?'… ':'').$snippet.(mb_strlen($text)>$start+$limit?' …':'');
}
function assistantMatchedTerms(string $text,array $terms): array {
    $matched=[];
    foreach($terms as $term)if(assistantTermOccurs($text,$term))$matched[]=$term;
    return array_values(array_unique($matched));
}
function assistantTermOccurs(string $text,string $term): bool {
    $text=mb_strtolower($text);$term=mb_strtolower(trim($term));
    if($term==='')return false;
    if(preg_match('/\s/u',$term))return mb_stripos($text,$term)!==false;
    if(mb_strlen($term)>=5)return (bool)preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term,'/').'/iu',$text);
    return (bool)preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term,'/').'(?![\p{L}\p{N}])/iu',$text);
}
function assistantRelevanceScore(string $title,string $text,array $terms): int {
    $score=0;
    foreach($terms as $term){
        if(assistantTermOccurs($title,$term))$score+=5;
        if(assistantTermOccurs($text,$term))$score+=2;
    }
    return $score;
}
function assistantDedupeSources(array $sources): array {
    $result=[];
    foreach($sources as $source){
        $kind=(string)($source['kind']??'document');
        $title=assistantComparable(preg_replace(['/\.(?:pdf|docx?|dotx|xlsx?|pptx?|msg|eml)$/iu','/^(?:(?:re|fw|fwd)\s*[:_-]\s*)+/iu','/(?:\s*__\d+|\s*\(\d+\)|\s+-?\s*(?:kopie|copy))$/iu'],['','',''],(string)($source['title']??''))??'');
        $words=assistantComparable(mb_substr((string)($source['snippet']??''),0,1400));
        $tokens=array_values(array_unique(array_filter(preg_split('/\s+/u',$words)?:[],static fn($word)=>mb_strlen($word)>=4)));
        $duplicate=false;
        foreach($result as $existing){
            $otherKind=(string)($existing['_dedupe_kind']??'document');$otherTitle=(string)($existing['_dedupe_title']??'');$otherTokens=(array)($existing['_dedupe_tokens']??[]);
            $overlap=$tokens&&$otherTokens?count(array_intersect($tokens,$otherTokens))/max(1,min(count($tokens),count($otherTokens))):0;
            $sameTitle=$title!==''&&$title===$otherTitle;
            $sameFolder=assistantComparable((string)($source['folder']??''))===assistantComparable((string)($existing['folder']??''));
            // Similar-looking messages often share boilerplate. Distinct file
            // documents must remain separate sources; only collapse a true
            // same-title duplicate in the same folder.
            if(($kind==='document'&&$otherKind==='document'&&$sameTitle&&$sameFolder)||($kind==='email'&&$otherKind==='email'&&$sameTitle&&$sameFolder&&$overlap>=.65)){$duplicate=true;break;}
        }
        if($duplicate)continue;
        $source['_dedupe_kind']=$kind;$source['_dedupe_title']=$title;$source['_dedupe_tokens']=$tokens;$result[]=$source;
    }
    foreach($result as &$source){unset($source['_dedupe_kind'],$source['_dedupe_title'],$source['_dedupe_tokens']);}unset($source);
    return $result;
}
function assistantContextualSources(array $sources,array $terms): array {
    if(!$terms){foreach($sources as &$source)unset($source['matched_terms']);unset($source);return $sources;}
    $generic=['vervanging','vervangen','renovatie','onderhoud','planning','project','overzicht','chronologisch','chronologische','correspondentie','documenten','bestanden'];
    $anchorTerms=array_values(array_filter($terms,static fn($term)=>!in_array(mb_strtolower($term),$generic,true)));
    if(!$anchorTerms)$anchorTerms=$terms;
    $frequency=array_fill_keys($anchorTerms,0);
    foreach($sources as $source)foreach(array_unique($source['matched_terms']??[]) as $term)if(isset($frequency[$term]))$frequency[$term]++;
    $frequency=array_filter($frequency,static fn($count)=>$count>0);
    if(!$frequency)return [];
    asort($frequency,SORT_NUMERIC);
    $anchor=(string)array_key_first($frequency);
    $filtered=[];
    foreach($sources as $source){
        $matched=array_unique($source['matched_terms']??[]);
        if(in_array($anchor,$matched,true)){unset($source['matched_terms']);$filtered[]=$source;}
    }
    return $filtered;
}
function assistantEmailSources(PDO $pdo,array $terms): array {
    try{
        $exists=$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='EmailBerichten'")->fetchColumn();
        if((int)$exists!==1)return [];
        $conditions=[];$params=[];
        foreach($terms as $term){$like='%'.$term.'%';$conditions[]='(e.Subject LIKE ? OR e.Sender LIKE ? OR e.Recipients LIKE ? OR e.BodyText LIKE ? OR e.AttachmentNames LIKE ?)';array_push($params,$like,$like,$like,$like,$like);}
        $sql='SELECT e.EmailBerichtID,e.Subject,e.Sender,e.Recipients,e.SentAt,e.BodyText,e.AttachmentNames,e.FolderName,a.Label FROM EmailBerichten e JOIN EmailAccounts a ON a.EmailAccountID=e.EmailAccountID WHERE a.IsActive=1 AND ('.implode(' OR ',$conditions).') ORDER BY e.SentAt DESC LIMIT 400';
        $stmt=$pdo->prepare($sql);$stmt->execute($params);$results=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){if(preg_match('/\b(automatisch antwoord|automatic reply|out of office|afwezigheidsassistent)\b/iu',(string)$row['Subject']))continue;$haystack=implode(' ',[(string)$row['Subject'],(string)$row['Sender'],(string)$row['Recipients'],(string)$row['BodyText'],(string)$row['AttachmentNames']]);$score=assistantRelevanceScore((string)$row['Subject'],$haystack,$terms);if($score===0)continue;$date=(string)($row['SentAt']??'');$results[]=['kind'=>'email','id'=>(int)$row['EmailBerichtID'],'title'=>(string)($row['Subject']?:'(geen onderwerp)'),'filename'=>(string)$row['Sender'],'folder'=>'E-mail · '.(string)$row['Label'].' · '.(string)$row['FolderName'],'entity'=>'email','source'=>'email','external_url'=>'','pages'=>[],'best_page'=>null,'snippet'=>assistantSnippet((string)$row['BodyText'],$terms,1800),'score'=>$score,'matched_terms'=>assistantMatchedTerms($haystack,$terms),'date'=>$date,'timeline_date'=>$date,'timeline_date_kind'=>'e-mail'];}
        return $results;
    }catch(Throwable $ignored){return [];}
}
function assistantBalancedSources(array $sources,int $limit=12): array {
    usort($sources,static fn($a,$b)=>$b['score']<=>$a['score']?:strcmp($a['title'],$b['title']));
    $maximum=$limit>24?['email'=>30,'document'=>25,'database'=>5]:($limit>12?['email'=>10,'document'=>10,'database'=>4]:['email'=>5,'document'=>5,'database'=>2]);$selected=[];$deferred=[];$counts=[];
    foreach($sources as $source){$kind=(string)($source['kind']??'document');$counts[$kind]=($counts[$kind]??0);if($counts[$kind]<($maximum[$kind]??$limit)){$selected[]=$source;$counts[$kind]++;}else $deferred[]=$source;if(count($selected)>=$limit)break;}
    if(count($selected)<$limit)foreach($deferred as $source){$selected[]=$source;if(count($selected)>=$limit)break;}
    usort($selected,static fn($a,$b)=>$b['score']<=>$a['score']?:strcmp($a['title'],$b['title']));return array_slice($selected,0,$limit);
}
function assistantContentDates(string $text): array {
    $dates=[];
    if(preg_match_all('/\b(20\d{2})[-\/.](0?[1-9]|1[0-2])[-\/.](0?[1-9]|[12]\d|3[01])\b/u',$text,$matches,PREG_SET_ORDER))foreach($matches as $match)if(checkdate((int)$match[2],(int)$match[3],(int)$match[1]))$dates[sprintf('%04d-%02d-%02d',(int)$match[1],(int)$match[2],(int)$match[3])]=true;
    if(preg_match_all('/\b(0?[1-9]|[12]\d|3[01])[-\/.](0?[1-9]|1[0-2])[-\/.](20\d{2})\b/u',$text,$matches,PREG_SET_ORDER))foreach($matches as $match)if(checkdate((int)$match[2],(int)$match[1],(int)$match[3]))$dates[sprintf('%04d-%02d-%02d',(int)$match[3],(int)$match[2],(int)$match[1])]=true;
    $months=['januari'=>1,'februari'=>2,'maart'=>3,'april'=>4,'mei'=>5,'juni'=>6,'juli'=>7,'augustus'=>8,'september'=>9,'oktober'=>10,'november'=>11,'december'=>12];
    if(preg_match_all('/\b(0?[1-9]|[12]\d|3[01])\s+(januari|februari|maart|april|mei|juni|juli|augustus|september|oktober|november|december)\s+(20\d{2})\b/iu',$text,$matches,PREG_SET_ORDER))foreach($matches as $match){$month=$months[mb_strtolower($match[2])]??0;if($month&&checkdate($month,(int)$match[1],(int)$match[3]))$dates[sprintf('%04d-%02d-%02d',(int)$match[3],$month,(int)$match[1])]=true;}
    $result=array_keys($dates);sort($result,SORT_STRING);return array_slice($result,0,12);
}
function assistantTitleDate(string $text): ?string {
    $dates=assistantContentDates($text);if($dates)return $dates[0];
    $months=['januari'=>1,'februari'=>2,'maart'=>3,'april'=>4,'mei'=>5,'juni'=>6,'juli'=>7,'augustus'=>8,'september'=>9,'oktober'=>10,'november'=>11,'december'=>12];
    if(preg_match('/\b(januari|februari|maart|april|mei|juni|juli|augustus|september|oktober|november|december)\s+(20\d{2})\b/iu',$text,$match))return sprintf('%04d-%02d',(int)$match[2],$months[mb_strtolower($match[1])]);
    return null;
}
function assistantGeneratedReport(string $title,string $filename): bool {
    return (bool)preg_match('/^(?:vve[-_ ]zoekresultaten|chronologisch(?:e)?[-_ ](?:overzicht|samenvatting)|ai[-_ ](?:rapport|samenvatting))/iu',trim($title.' '.$filename));
}
function assistantPruneBroadSources(array $sources,array $terms): array {
    $generic=['alles','archief','document','documenten','e-mail','email','samenvatting','chronologisch','chronologische','overzicht','rapport','project','vervanging','vervangen','renovatie','onderhoud','planning'];
    $anchors=array_values(array_filter(array_unique($terms),static fn($term)=>mb_strlen($term)>=4&&!in_array(mb_strtolower($term),$generic,true)));
    if(!$anchors)return $sources;
    $result=[];
    foreach($sources as $source){
        $label=(string)($source['title']??'').' '.(string)($source['filename']??'').' '.(string)($source['folder']??'');
        if(preg_match('/\b(?:vve\s+metea\s+lexicon|voorstel\s+taken\s+vve\s+bestuur|vve\s+bestuur\s+taken)\b/iu',$label))continue;
        $labelMatch=false;foreach($anchors as $anchor)if(assistantTermOccurs($label,$anchor)){$labelMatch=true;break;}
        $folder=(string)($source['folder']??'');
        if(!$labelMatch&&preg_match('#(?:^|/)Activa/([^/]+)#iu',$folder,$category)){
            $categoryMatch=false;foreach($anchors as $anchor)if(assistantTermOccurs((string)$category[1],$anchor)){$categoryMatch=true;break;}
            if(!$categoryMatch)continue;
        }
        $result[]=$source;
    }
    return $result?:$sources;
}

function assistantFinancialDocumentSources(PDO $pdo,string $question,array $vendorTerms): array {
    $stmt=$pdo->prepare("SELECT d.DocumentID,d.Titel,d.OrigineleNaam,d.Mimetype,d.Bron,d.ExterneUrl,d.Omschrijving,d.Documentdatum,d.AangemaaktOp,d.GewijzigdOp,m.Pad MapNaam,ti.Tekst,COALESCE(ti.Status,'niet-geindexeerd') IndexStatus FROM DocumentBibliotheek d JOIN DocumentMappen m ON m.MapID=d.MapID LEFT JOIN DocumentTekstIndex ti ON ti.DocumentID=d.DocumentID WHERE d.IsHuidig=1 AND d.VerwijderdOp IS NULL AND (m.Pad LIKE '%Financ%' OR d.OrigineleNaam REGEXP '^(Crediteuren|Inkoopfacturen|Betalingen|Grootboek|Offertes|Contracten|MJOP|Leveranciers).*\\.csv$') LIMIT 600");
    $stmt->execute();$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $invoiceIds=assistantFinancialInvoiceIds($rows,$vendorTerms);
    $supplierIds=assistantFinancialSupplierIds($rows,$vendorTerms);
    $years=[];if(preg_match_all('/\b20\d{2}\b/u',$question,$matches))$years=array_values(array_unique($matches[0]));
    $wantsPayment=(bool)preg_match('/\b(?:betaal\w*|betal\w*|voldaan)\b/iu',$question);
    $wantsLedger=(bool)preg_match('/\b(?:grootboek\w*|boekhoud\w*|kosten\w*|uitgav\w*)\b/iu',$question);
    $wantsInvoice=(bool)preg_match('/\b(?:factur\w*|crediteur\w*)\b/iu',$question);
    $wantsOpen=(bool)preg_match('/\b(?:openstaand\w*|onbetaald\w*|nog\s+open)\b/iu',$question);
    $wantsOffer=(bool)preg_match('/\boffert\w*\b/iu',$question);
    $wantsContract=(bool)preg_match('/\bcontract\w*\b/iu',$question);
    $wantsBudget=(bool)preg_match('/\b(?:begrot\w*|budget\w*|mjop)\b/iu',$question);
    $specific=$wantsPayment||$wantsLedger||$wantsInvoice||$wantsOffer||$wantsContract||$wantsBudget;
    $sources=[];
    foreach($rows as $row){
        $name=(string)$row['OrigineleNaam'];$title=(string)$row['Titel'];$text=(string)($row['Tekst']??'');
        if(assistantGeneratedReport($title,$name))continue;
        if($years&&preg_match('/_(20\d{2})(?:_|\.)/u',$name,$yearMatch)&&!in_array($yearMatch[1],$years,true))continue;
        $csv=(bool)preg_match('/\.csv$/iu',$name);
        $isInvoice=(bool)preg_match('/^(?:Crediteuren|Inkoopfacturen)/iu',$name);
        $isPayment=(bool)preg_match('/^Betalingen/iu',$name);
        $isLedger=(bool)preg_match('/^Grootboek/iu',$name);
        $isOffer=(bool)preg_match('/^Offertes/iu',$name);
        $isContract=(bool)preg_match('/^Contracten/iu',$name);
        $isBudget=(bool)preg_match('/^MJOP/iu',$name);
        if($csv&&$specific&&!($isInvoice&&($wantsPayment||$wantsLedger||$wantsInvoice))&&!($isPayment&&$wantsPayment)&&!($isLedger&&$wantsLedger)&&!($isOffer&&$wantsOffer)&&!($isContract&&$wantsContract)&&!($isBudget&&$wantsBudget))continue;
        if($csv){
            $linkedIds=($isPayment||$isLedger)?$invoiceIds:[];
            $paidThrough=$isPayment&&!preg_match('/\b(?:gepland\w*|toekomst\w*|verwacht\w*)\b/iu',$question)?date('Y-m-d'):null;
            $requiredStatus=$isInvoice&&$wantsOpen&&!$wantsPayment?'openstaand':null;
            $snippet=assistantFinancialCsvExcerpt($text,$vendorTerms,5000,$linkedIds,$supplierIds,$paidThrough,$requiredStatus);
            if($snippet==='')continue;
        }else{
            if($specific){
                $nameText=$title.' '.$name;
                $relevant=($wantsPayment||$wantsInvoice)&&preg_match('/\b(?:factuur\w*|crediteur\w*|betaling\w*|IN-\d)/iu',$nameText)
                    ||$wantsLedger&&preg_match('/\b(?:grootboek\w*|boekhoud\w*)/iu',$nameText)
                    ||$wantsOffer&&preg_match('/\boffert\w*/iu',$nameText)
                    ||$wantsContract&&preg_match('/\bcontract\w*/iu',$nameText)
                    ||$wantsBudget&&preg_match('/\b(?:mjop|begrot\w*|budget\w*)/iu',$nameText);
                if(!$relevant)continue;
            }
            if($vendorTerms){$matched=true;foreach($vendorTerms as $term)if(mb_stripos($title.' '.$name.' '.$text,$term)===false)$matched=false;if(!$matched)continue;}
            if($wantsOpen&&!$wantsPayment&&!preg_match('/\b(?:openstaand\w*|onbetaald\w*|nog\s+open)\b/iu',$text))continue;
            $snippet=assistantSnippet($text!==''?$text:(string)$row['Omschrijving'],$vendorTerms,1200);
            if($snippet==='')continue;
        }
        $score=($csv?100:10)+($isPayment&&$wantsPayment?30:0)+($isInvoice&&$wantsPayment?25:0)+($isLedger&&$wantsLedger?30:0)+($isOffer&&$wantsOffer?30:0)+($isContract&&$wantsContract?30:0)+($isBudget&&$wantsBudget?30:0);
        $sources[]=['kind'=>'document','id'=>(int)$row['DocumentID'],'title'=>$title,'filename'=>$name,'mimetype'=>(string)$row['Mimetype'],'folder'=>(string)$row['MapNaam'],'source'=>(string)$row['Bron'],'external_url'=>(string)($row['ExterneUrl']??''),'pages'=>[],'best_page'=>null,'snippet'=>$snippet,'score'=>$score,'matched_terms'=>$vendorTerms,'date'=>(string)($row['Documentdatum']?:($row['GewijzigdOp']?:$row['AangemaaktOp'])),'content_dates'=>[],'timeline_date'=>assistantTitleDate($title.' '.$name),'timeline_date_kind'=>'titel','content_quality'=>''];
    }
    usort($sources,static fn($a,$b)=>$b['score']<=>$a['score']?:strcmp($a['filename'],$b['filename']));
    return array_slice($sources,0,24);
}

try {
    $user=requireLogin();
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new SafeInputException('Gebruik een POST-verzoek.');
    verifyCsrf();
    $question=trim((string)($_POST['question']??''));
    if(mb_strlen($question)<3||mb_strlen($question)>500)throw new SafeInputException('Stel een vraag van 3 tot 500 tekens.');
    if(preg_match_all('/\bmaak\s+een\b/iu',$question)>1)throw new SafeInputException('Er lijken twee vragen aan elkaar geplakt. Wis de oude vraag en stel één vraag tegelijk.');
    $pdo=db(loadConfigCompat(tenantPrivateFile('config.php')));

    $classification=null;$classificationWarning='';
    $vendorTerms=assistantFinancialVendorTerms($question);
    $financialQuestion=assistantFinancialIsQuestion($question);
    $emailFileExtension='';if(preg_match('/\b(eml|msg)\b/iu',$question,$extensionMatch))$emailFileExtension=strtolower($extensionMatch[1]);
    $explicitEmailFileSearch=$emailFileExtension!=='';
    $complaintQuestion=(bool)preg_match('/\b(klacht\w*|storing\w*|defect\w*|meest\s+voorkomende|frequent\w*)\b/iu',$question);
    $obviousArchive=$financialQuestion||$explicitEmailFileSearch||(bool)preg_match('/\b(chronologisch|chronologische|tijdlijn|dossier|archief|documenten|correspondentie|e-?mail|notulen|offertes?|facturen?|besluiten?)\b/iu',$question);
    $obviousBroad=$explicitEmailFileSearch||(bool)preg_match('/\b(alles|chronologisch|chronologische|tijdlijn|compleet|volledig|correspondentie|dossier|onderzoek|rapport|samenvatting|meest\s+voorkomende|frequent\w*|vaakst)\b/iu',$question);
    if($obviousArchive){
        $extraTerms=[];
        if(preg_match('/\b(beveiligingscamera(?:\x{2019}s|s)?|camera(?:\x{2019}s|s)?|cameratoezicht)\b/iu',$question))$extraTerms=['beveiligingscamera','camera','cameratoezicht','reolink','nvr'];
        elseif(preg_match('/\b(appartementsdeur|appartementsdeuren|voordeur|voordeuren|daloc|mijnvoordeur|cilinder|deurdranger|faac|toegangsdeur|toegangsdeuren|entreedeur|entreedeuren)\b/iu',$question))$extraTerms=['appartementsdeur','voordeur','daloc','mijnvoordeur','cilinder','deurdranger'];
        if($complaintQuestion)$extraTerms=array_merge($extraTerms,['melding','klacht','storing','defect','overlast']);
        $classification=['archive'=>true,'scope'=>$obviousBroad?'broad':'targeted','search_query'=>$question,'search_terms'=>$extraTerms,'provider'=>aiProviderStatus()['provider'],'model'=>aiProviderStatus()['model']];
    }else try{$classification=aiProviderClassify($question);}catch(Throwable $classificationError){$classificationWarning='De AI-vraaganalyse was niet beschikbaar; de lokale herkenning is gebruikt.';}
    if($classification!==null&&!$classification['archive']){
        $direct=aiProviderDirectAnswer($question);
        recordActivity($pdo,$user,'ai-vraag-gesteld','assistent','algemene-vraag','',mb_substr($question,0,300).' · geen archiefzoekactie');
        assistantOut(['ok'=>true,'answer'=>$direct['text'],'answer_kind'=>'ai','ai_provider'=>$direct['provider'],'ai_warning'=>'','terms'=>[],'intent'=>'algemeen','archive_searched'=>false,'sources'=>[],'csrf'=>csrfToken()]);
    }
    $searchQuestion=$financialQuestion?($vendorTerms?implode(' ',$vendorTerms):$question):($classification!==null&&$classification['search_query']!==''?$classification['search_query']:$question);
    $subjectTerms=assistantTerms($question);
    $terms=assistantTerms($searchQuestion);
    if(!$financialQuestion)foreach((array)($classification['search_terms']??[]) as $suggestedTerm)foreach(assistantTerms((string)$suggestedTerm) as $term){$terms[]=$term;}
    if($complaintQuestion)$terms=array_merge($terms,['melding','klacht','storing','defect','overlast']);
    $terms=array_slice(array_values(array_unique($terms)),0,12);
    if(!$terms)throw new SafeInputException('Gebruik in de vraag minimaal één duidelijk zoekwoord.');
    $scope=(string)($classification['scope']??((bool)preg_match('/\b(alles|chronologisch|chronologische|tijdlijn|compleet|volledig|correspondentie|dossier|onderzoek|rapport)\b/iu',$question)?'broad':'targeted'));
    $residenceIntent=(bool)preg_match('/\b(woont|wonen|huisnummer|nummer|appartement)\b/iu',$question);
    $chronologicalIntent=(bool)preg_match('/\b(chronologisch|chronologische|tijdlijn|volgorde)\b/iu',$question);
    if($financialQuestion){
        $sources=assistantFinancialDocumentSources($pdo,$question,$vendorTerms);
        recordActivity($pdo,$user,'ai-vraag-gesteld','assistent','financiele-vraag','',mb_substr($question,0,300).' · '.count($sources).' bronnen');
        $answer=$sources?'Ik vond '.count($sources).' financiële bron'.(count($sources)===1?'':'nen').'. Bekijk de passages en open de bron om bedragen en betaalstatus te controleren.':'Ik vond geen passende financiële bron. Controleer of de facturen, betalingen of grootboekbestanden zijn geüpload en geïndexeerd.';
        $ai=null;$aiWarning='';
        if($sources)try{$ai=aiProviderAnswer($question,$sources,'financieel');}catch(Throwable $providerError){$aiWarning='De gekozen AI-provider was niet beschikbaar; daarom worden alleen de lokale bronnen getoond.';}
        if($ai!==null)$answer=$ai['text'];
        assistantOut(['ok'=>true,'answer'=>$answer,'answer_kind'=>$ai===null?'sources':'ai','ai_provider'=>$ai['provider']??aiProviderStatus()['provider'],'ai_warning'=>$aiWarning,'terms'=>$terms,'intent'=>'financieel','archive_searched'=>true,'sources'=>$sources,'csrf'=>csrfToken()]);
    }
    if($residenceIntent){
        $sources=assistantResidenceSources($pdo,$question,$terms);
        recordActivity($pdo,$user,'ai-vraag-gesteld','assistent','woonvraag','',mb_substr($question,0,300).' · '.count($sources).' databasebronnen');
        $hasSuggestion=(bool)array_filter($sources,static fn($source)=>!empty($source['suggested']));
        if($hasSuggestion)$answer=count($sources)===1?'Geen exacte naam gevonden. Bedoelde u misschien '.$sources[0]['title'].'? Controleer de suggestie voordat u het contact opent.':'Geen exacte naam gevonden. Hieronder staan de meest waarschijnlijke contacten; controleer de suggestie voordat u een contact opent.';
        else $answer=$sources?'Ik vond '.count($sources).' passend'.(count($sources)===1?' contact':'e contacten').' in de database.':'Ik vond geen passend contact of huisnummer in de database.';
        assistantOut(['ok'=>true,'answer'=>$answer,'terms'=>$terms,'sources'=>$sources,'csrf'=>csrfToken()]);
    }
    $conditions=[];$params=[];
    foreach($terms as $term){
        $like='%'.$term.'%';
        $conditions[]='(d.Titel LIKE ? OR d.OrigineleNaam LIKE ? OR d.Tags LIKE ? OR d.Omschrijving LIKE ? OR ti.Tekst LIKE ?)';
        array_push($params,$like,$like,$like,$like,$like);
    }
    if($explicitEmailFileSearch)$conditions[]="LOWER(d.OrigineleNaam) REGEXP '[.](eml|msg)$'";
    $sql='SELECT d.DocumentID,d.Titel,d.OrigineleNaam,d.Mimetype,d.Bron,d.ExterneUrl,d.Omschrijving,d.Documentdatum,d.AangemaaktOp,d.GewijzigdOp,m.Pad MapNaam,ti.Tekst,COALESCE(ti.Status,\'niet-geindexeerd\') IndexStatus FROM DocumentBibliotheek d JOIN DocumentMappen m ON m.MapID=d.MapID LEFT JOIN DocumentTekstIndex ti ON ti.DocumentID=d.DocumentID WHERE d.IsHuidig=1 AND d.VerwijderdOp IS NULL AND ('.implode(' OR ',$conditions).') LIMIT 600';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
    $sources=[];
    foreach($rows as $row){
        if(assistantGeneratedReport((string)$row['Titel'],(string)$row['OrigineleNaam']))continue;
        $haystack=implode(' ',[(string)$row['Titel'],(string)$row['OrigineleNaam'],(string)$row['Omschrijving'],(string)$row['Tekst']]);
        $score=assistantRelevanceScore((string)$row['Titel'].' '.(string)$row['OrigineleNaam'],$haystack,$terms);
        if($score===0)continue;
        $pages=[];$bestPage=null;
        if((string)$row['Mimetype']==='application/pdf'){
            $scoreParts=[];$pageConditions=[];$pageParams=[];
            foreach($terms as $term){$scoreParts[]='CASE WHEN Tekst LIKE ? THEN 1 ELSE 0 END';$pageParams[]='%'.$term.'%';}
            $pageParams[]=(int)$row['DocumentID'];
            foreach($terms as $term){$pageConditions[]='Tekst LIKE ?';$pageParams[]='%'.$term.'%';}
            $pageStmt=$pdo->prepare('SELECT Paginanummer,Tekst,('.implode(' + ',$scoreParts).') Zoekscore FROM DocumentTekstPagina WHERE DocumentID=? AND ('.implode(' OR ',$pageConditions).') ORDER BY Zoekscore DESC,Paginanummer ASC LIMIT 8');
            $pageStmt->execute($pageParams);$pageRows=$pageStmt->fetchAll(PDO::FETCH_ASSOC);
            $pages=array_map(static fn($page)=>(int)$page['Paginanummer'],$pageRows);
            if($pageRows){$bestPage=(int)$pageRows[0]['Paginanummer'];$row['Tekst']=(string)$pageRows[0]['Tekst'];}
        }
        $text=(string)($row['Tekst']??'');if($text==='')$text=(string)($row['Omschrijving']??'');
        $relevanceText=implode(' ',[(string)$row['Titel'],(string)$row['OrigineleNaam'],(string)$row['Omschrijving'],$text]);
        $snippet=assistantSnippet($text,$terms,$scope==='broad'?3600:600);
        $contentDates=assistantContentDates($snippet);$titleDate=assistantTitleDate((string)$row['Titel'].' '.(string)$row['OrigineleNaam']);
        $sourceDate=(string)($row['Documentdatum']?:($row['GewijzigdOp']?:$row['AangemaaktOp']));
        $indexStatus=(string)($row['IndexStatus']??'niet-geindexeerd');$contentQuality=in_array($indexStatus,['ocr-nodig','overgeslagen','mislukt','niet-geindexeerd'],true)?'inhoudsarm; controleer het brondocument handmatig':'';
        $sources[]=['kind'=>'document','id'=>(int)$row['DocumentID'],'title'=>(string)$row['Titel'],'filename'=>(string)$row['OrigineleNaam'],'mimetype'=>(string)$row['Mimetype'],'folder'=>(string)$row['MapNaam'],'source'=>(string)$row['Bron'],'external_url'=>(string)($row['ExterneUrl']??''),'pages'=>$pages,'best_page'=>$bestPage,'snippet'=>$snippet,'score'=>$score,'matched_terms'=>assistantMatchedTerms($relevanceText,$terms),'date'=>$sourceDate,'content_dates'=>$contentDates,'timeline_date'=>$titleDate??($contentDates[0]??$sourceDate),'timeline_date_kind'=>$titleDate!==null?'titel':($contentDates?'inhoud':'document'),'content_quality'=>$contentQuality];
    }
    $sources=array_merge($sources,assistantDatabaseSources($pdo,$terms),assistantEmailSources($pdo,$terms));
    if($explicitEmailFileSearch)$sources=array_values(array_filter($sources,static fn($source)=>(string)($source['kind']??'')==='document'&&preg_match('/\.'.preg_quote($emailFileExtension,'/').'$/iu',(string)($source['filename']??''))));
    elseif($scope!=='broad')$sources=assistantContextualSources($sources,$subjectTerms);
    $sources=assistantDedupeSources($sources);
    if($scope==='broad'&&!$explicitEmailFileSearch)$sources=assistantPruneBroadSources($sources,$terms);
    $sources=assistantBalancedSources($sources,$scope==='broad'?80:24);
    $emailCandidateCount=count(array_filter($sources,static fn($source)=>(string)($source['kind']??'')==='email'||preg_match('/\.(?:eml|msg)$/iu',(string)($source['filename']??''))));
    $selectionWarning='';
    if($classification!==null&&$sources&&$scope!=='broad'){
        try{$sources=aiProviderSelectRelevantSources($question,$sources,$scope==='broad'?36:12);}
        catch(Throwable $selectionError){$selectionWarning='De inhoudelijke bronselectie was tijdelijk niet beschikbaar; de resultaten zijn lokaal gefilterd.';}
    }
    $sources=assistantBalancedSources($sources,$explicitEmailFileSearch?80:($scope==='broad'?36:12));
    if($scope==='broad'&&!$explicitEmailFileSearch)$sources=aiProviderBalanceSourcePhases($sources,36);
    recordActivity($pdo,$user,'ai-vraag-gesteld','assistent','zoekvraag','',mb_substr($question,0,300).' · '.count($sources).' bronnen');
    $answer=$sources
        ? ($chronologicalIntent?'Ik heb '.count($sources).' relevante bronnen over “'.implode(', ',$terms).'” geordend op herkenbare datums in de passages. Als een passage geen datum bevat, wordt de documentdatum gebruikt. Controleer de tijdlijn aan de hand van de bron.':'Ik vond '.count($sources).' relevante bron'.(count($sources)===1?'':'nen').'. Bekijk de passages en open de bron om het antwoord te controleren.')
        : 'Ik vond geen betrouwbare bron voor deze vraag. Probeer andere of specifiekere zoekwoorden.';
    $ai=null;$aiWarning='';
    $emailCount=count(array_filter($sources,static fn($source)=>(string)($source['kind']??'')==='email'));
    $providerConfig=aiProviderConfig();
    if($emailCandidateCount>0&&empty($providerConfig['include_email']))$aiWarning='Er zijn '.$emailCandidateCount.' mogelijke e-mailbron'.($emailCandidateCount===1?'':'nen').' gevonden. De inhoud is wegens de privacy-instelling niet naar de AI-provider gestuurd. Schakel dit desgewenst bewust in via Instellingen → AI.';
    if($sources)try{$ai=aiProviderAnswer($question,$sources,$chronologicalIntent?'chronologisch':($scope==='broad'?'dossier':'zoeken'));}catch(Throwable $providerError){$aiWarning='De gekozen AI-provider was niet beschikbaar; daarom worden alleen de lokale bronnen getoond.';}
    if($ai!==null)$answer=$ai['text'];
    assistantOut(['ok'=>true,'answer'=>$answer,'answer_kind'=>$ai===null?'sources':'ai','ai_provider'=>$ai['provider']??aiProviderStatus()['provider'],'ai_warning'=>trim($classificationWarning.' '.$selectionWarning.' '.$aiWarning),'terms'=>$terms,'intent'=>$chronologicalIntent?'chronologisch':$scope,'archive_searched'=>true,'sources'=>$sources,'csrf'=>csrfToken()]);
} catch(SafeInputException $e){assistantOut(['ok'=>false,'error'=>$e->getMessage()],400);}
catch(Throwable $e){$id=securityIncident($e);assistantOut(['ok'=>false,'error'=>'De vraag kon niet veilig worden verwerkt. Foutreferentie: '.$id],500);}
