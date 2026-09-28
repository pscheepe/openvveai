<?php
declare(strict_types=1);

function sourceFunction(string $path,string $name): string {
    $tokens=token_get_all((string)file_get_contents($path));$start=null;$foundName=false;
    foreach($tokens as $index=>$token){
        if(is_array($token)&&$token[0]===T_FUNCTION){$candidate=null;for($j=$index+1;$j<count($tokens);$j++){if(is_array($tokens[$j])&&$tokens[$j][0]===T_STRING){$candidate=$tokens[$j][1];break;}if($tokens[$j]==='(')break;}if($candidate===$name){$start=$index;$foundName=true;break;}}
    }
    if(!$foundName||$start===null)throw new RuntimeException('Functie niet gevonden: '.$name);
    $depth=0;$opened=false;$code='';
    for($i=$start;$i<count($tokens);$i++){
        $token=$tokens[$i];$code.=is_array($token)?$token[1]:$token;
        if($token==='{'){$depth++;$opened=true;}elseif($token==='}'){$depth--;if($opened&&$depth===0)break;}
    }
    if(!$opened||$depth!==0)throw new RuntimeException('Functie kon niet worden uitgelezen: '.$name);
    return $code;
}

$api=__DIR__.'/../release/public/assistant_api.php';
$provider=__DIR__.'/../release/public/ai_provider.php';
eval(sourceFunction($api,'assistantComparable').sourceFunction($api,'assistantTermOccurs').sourceFunction($api,'assistantDedupeSources'));
eval(sourceFunction($provider,'aiProviderIsEmailSource'));
$boilerplate='Bericht aan het bestuur. Wij ontvangen graag een reactie en horen wanneer dit wordt opgepakt. ';$sources=[];
foreach([['email-1.eml','Koude radiator'],['email-2.eml','Defecte verlichting']] as [$filename,$unique])$sources[]=['kind'=>'document','id'=>1,'title'=>$filename,'filename'=>$filename,'folder'=>'Correspondentie','snippet'=>$boilerplate.$unique,'score'=>10];
$sources[]=['kind'=>'document','id'=>3,'title'=>'Index.xlsx','filename'=>'Index.xlsx','folder'=>'Correspondentie','snippet'=>$boilerplate.'Koude radiator; defecte verlichting','score'=>9];
if(count(assistantDedupeSources($sources))!==3)throw new RuntimeException('Verschillende e-mailbestanden zijn onterecht samengevoegd.');
if(!aiProviderIsEmailSource(['kind'=>'document','filename'=>'email-1.eml']))throw new RuntimeException('EML-document werd niet als e-mailbron herkend voor de privacy-instelling.');
if(!aiProviderIsEmailSource(['kind'=>'document','filename'=>'email-1.msg']))throw new RuntimeException('MSG-document werd niet als e-mailbron herkend voor de privacy-instelling.');
if(aiProviderIsEmailSource(['kind'=>'document','filename'=>'reglement.docx']))throw new RuntimeException('Gewoon document werd onterecht als e-mailbron herkend.');
echo "E-mailbronnen blijven afzonderlijk en volgen de privacy-instelling.\n";
