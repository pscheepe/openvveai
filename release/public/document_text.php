<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';

function documentTextSupported(string $extension): bool {
    return in_array(strtolower($extension), ['txt','md','csv','html','htm','xml','rtf','pdf','docx','dotx','xlsx','pptx','odt','ods','odp','odg','pages','numbers','key'], true);
}

function documentPresentationXmlText(string $xml): string {
    preg_match_all('#<a:p\b[^>]*>(.*?)</a:p>#isu', $xml, $paragraphMatches);
    $sources = $paragraphMatches[1] ?? [];
    if (!$sources) $sources = [$xml];
    $paragraphs = [];
    foreach ($sources as $source) {
        preg_match_all('#<a:t\b[^>]*>(.*?)</a:t>#isu', (string)$source, $textMatches);
        $runs = [];
        foreach (($textMatches[1] ?? []) as $run) {
            $run = trim(html_entity_decode(strip_tags((string)$run), ENT_QUOTES | ENT_XML1, 'UTF-8'));
            if ($run !== '') $runs[] = $run;
        }
        if ($runs) $paragraphs[] = implode(' ', $runs);
    }
    return trim(implode("\n", $paragraphs));
}

function documentXmlText(string $xml): string {
    $xml = preg_replace('/<(?:w:tab|text:tab|a:br|w:br)\b[^>]*\/?\s*>/iu', "\t", $xml) ?? $xml;
    $xml = preg_replace('/<\/(?:w:p|a:p|text:p|text:h|row)>/iu', "\n", $xml) ?? $xml;
    $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
    return trim(preg_replace('/(?:\r\n|\r|\n){3,}/u', "\n\n", $text) ?? $text);
}

function documentArchiveText(string $path, string $extension): string {
    if (!class_exists('ZipArchive')) throw new RuntimeException('PHP ZIP-ondersteuning ontbreekt op de server.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('Documentarchief kon niet worden geopend.');
    try {
        if ($zip->numFiles > 5000) throw new RuntimeException('Documentarchief bevat te veel onderdelen.');
        $wanted = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!is_array($stat)) continue;
            $name = (string)($stat['name'] ?? '');
            $size = (int)($stat['size'] ?? 0);
            $include = false;
            if (in_array($extension, ['docx','dotx'], true)) $include = (bool)preg_match('#^word/(?:document|header\d+|footer\d+|footnotes|endnotes)\.xml$#i', $name);
            elseif ($extension === 'xlsx') $include = $name === 'xl/sharedStrings.xml' || (bool)preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $name);
            elseif ($extension === 'pptx') $include = (bool)preg_match('#^ppt/(?:slides/slide|notesSlides/notesSlide)\d+\.xml$#i', $name);
            elseif (in_array($extension, ['odt','ods','odp','odg'], true)) $include = $name === 'content.xml';
            if ($include) $wanted[$name] = $size;
        }
        uksort($wanted, 'strnatcasecmp');
        if (array_sum($wanted) > 20 * 1024 * 1024) throw new RuntimeException('Uitgepakte documenttekst is te groot voor automatische indexering.');
        $chunks = []; $total = 0;
        foreach ($wanted as $name => $size) {
            if ($size > 8 * 1024 * 1024 || $total >= 1000000) continue;
            $xml = $zip->getFromName($name, 8 * 1024 * 1024);
            if (!is_string($xml)) continue;
            $part = $extension === 'pptx' ? documentPresentationXmlText($xml) : documentXmlText($xml);
            if ($part === '') continue;
            $part = mb_substr($part, 0, 1000000 - $total);
            $total += mb_strlen($part); $chunks[] = $part;
        }
        return trim(implode("\n\n", $chunks));
    } finally { $zip->close(); }
}

function documentPdfExtract(object $pdf): array {
    $chunks = []; $pages = []; $pageNumber = 0; $totalLength = 0;
    foreach ($pdf->getPages() as $page) {
        if (++$pageNumber > 200 || $totalLength >= 1000000) break;
        $pageText = trim((string)$page->getText());
        if ($pageText === '') continue;
        $pageText = mb_substr($pageText, 0, min(100000, 1000000 - $totalLength));
        $totalLength += mb_strlen($pageText); $pages[$pageNumber] = $pageText; $chunks[] = $pageText;
    }
    return [trim(implode("\n\n", $chunks)), $pages];
}

function documentPdfCommandText(string $path): string {
    if(!function_exists('proc_open'))return '';
    $binary='';foreach(['/usr/bin/pdftotext','/usr/local/bin/pdftotext','/opt/homebrew/bin/pdftotext'] as $candidate)if(is_file($candidate)&&is_executable($candidate)){$binary=$candidate;break;}
    if($binary==='')return '';
    $target=tempnam(sys_get_temp_dir(),'vve-pdf-');if($target===false)return '';
    $descriptor=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
    $process=@proc_open([$binary,'-layout','-enc','UTF-8',$path,$target],$descriptor,$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($process)){@unlink($target);return '';}
    fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($process);
    $text=$exit===0&&is_file($target)?(string)file_get_contents($target):'';@unlink($target);
    $text=preg_replace('/[ \t]+/u',' ',$text)??$text;$text=preg_replace('/(?:\r\n|\r|\n){3,}/u',"\n\n",$text)??$text;
    return mb_substr(trim($text),0,1000000);
}

function documentIworkPreviewText(string $path): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('PHP ZIP-ondersteuning ontbreekt op de server.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('iWork-document kon niet worden geopend.');
    try {
        if ($zip->numFiles > 5000) throw new RuntimeException('iWork-document bevat te veel onderdelen.');
        $previewName = null; $previewSize = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i); if (!is_array($stat)) continue;
            $name = (string)($stat['name'] ?? '');
            if (preg_match('#(?:^|/)(?:preview|quicklook)\.pdf$#i', $name) || strcasecmp($name, 'QuickLook/Preview.pdf') === 0) {$previewName=$name;$previewSize=(int)($stat['size']??0);break;}
        }
        if ($previewName === null) return ['', [], 'Geen ingebedde PDF-preview gevonden; dit iWork-formaat kan op deze server niet op inhoud worden geïndexeerd.'];
        if ($previewSize < 1 || $previewSize > 15 * 1024 * 1024) return ['', [], 'De ingebedde PDF-preview is leeg of groter dan 15 MB.'];
        $content = $zip->getFromName($previewName, 15 * 1024 * 1024);
        if (!is_string($content)) throw new RuntimeException('Ingebedde PDF-preview kon niet worden gelezen.');
        require_once __DIR__.'/lib/pdfparser_autoload.php';
        $parser = new \Smalot\PdfParser\Parser();
        [$text,$pages] = documentPdfExtract($parser->parseContent($content));
        return [$text,$pages,$text === '' ? 'De ingebedde PDF-preview bevat geen selecteerbare tekst.' : null];
    } finally { $zip->close(); }
}

function documentPlainText(string $path, string $extension): ?string {
    $extension = strtolower($extension);
    if (!documentTextSupported($extension) || $extension === 'pdf' || in_array($extension, ['docx','dotx','xlsx','pptx','odt','ods','odp','odg','pages','numbers','key'], true)) return null;
    $raw = file_get_contents($path, false, null, 0, 2 * 1024 * 1024);
    if ($raw === false) throw new RuntimeException('Bestand kon niet voor indexering worden gelezen.');
    if ($extension === 'html' || $extension === 'htm') {
        $raw = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } elseif ($extension === 'xml') {
        $raw = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_XML1, 'UTF-8');
    } elseif ($extension === 'rtf') {
        $raw = preg_replace('/\\\\\'[0-9a-fA-F]{2}/', ' ', $raw) ?? $raw;
        $raw = preg_replace('/\\\\[a-zA-Z]+-?\\d* ?/', ' ', $raw) ?? $raw;
        $raw = str_replace(['{','}'], ' ', $raw);
    }
    if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252,ISO-8859-1');
    $raw = preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/u', ' ', $raw) ?? $raw;
    $raw = preg_replace('/[ \\t]+/u', ' ', $raw) ?? $raw;
    $raw = preg_replace('/(?:\\r\\n|\\r|\\n){3,}/u', "\n\n", $raw) ?? $raw;
    return mb_substr(trim($raw), 0, 1000000);
}

function indexDocumentText(PDO $pdo, int $documentId, string $path, string $originalName): string {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $status = 'niet-ondersteund'; $text = null; $error = null;
    $pages = [];
    if ($extension === 'pdf') {
        if (filesize($path) > 15 * 1024 * 1024) {
            $status = 'overgeslagen'; $error = 'PDF groter dan 15 MB; niet automatisch geïndexeerd.';
        } else {
            try {
                require_once __DIR__.'/lib/pdfparser_autoload.php';
                $parser = new \Smalot\PdfParser\Parser();
                [$text,$pages] = documentPdfExtract($parser->parseFile($path));
                if(mb_strlen(trim($text))<80){$fallback=documentPdfCommandText($path);if(mb_strlen($fallback)>mb_strlen($text)){$text=$fallback;$pages=[];}}
                $status = $text === '' ? 'ocr-nodig' : 'gereed';
                if ($status === 'ocr-nodig') $error = 'Geen selecteerbare tekst gevonden; OCR is nodig.';
            } catch (Throwable $e) {
                $status = 'mislukt'; $error = mb_substr($e->getMessage(), 0, 500);
            }
        }
    } elseif (in_array($extension, ['pages','numbers','key'], true)) {
        try {
            [$text,$pages,$error] = documentIworkPreviewText($path);
            $status = $text === '' ? 'niet-ondersteund' : 'gereed';
        } catch (Throwable $e) { $status = 'mislukt'; $error = mb_substr($e->getMessage(), 0, 500); }
    } elseif (in_array($extension, ['docx','dotx','xlsx','pptx','odt','ods','odp','odg'], true)) {
        try {
            $text = documentArchiveText($path, $extension);
            $status = $text === '' ? 'overgeslagen' : 'gereed';
            if ($status === 'overgeslagen') $error = 'Geen leesbare tekst in het document gevonden.';
        } catch (Throwable $e) { $status = 'mislukt'; $error = mb_substr($e->getMessage(), 0, 500); }
    } elseif (documentTextSupported($extension)) {
        try { $text = documentPlainText($path, $extension); $status = 'gereed'; }
        catch (Throwable $e) { $status = 'mislukt'; $error = mb_substr($e->getMessage(), 0, 500); }
    }
    $s = $pdo->prepare('INSERT INTO DocumentTekstIndex (DocumentID,Status,ExtractorVersie,Tekst,Foutmelding,BijgewerktOp) VALUES (?,?,6,?,?,?) ON DUPLICATE KEY UPDATE Status=VALUES(Status),ExtractorVersie=VALUES(ExtractorVersie),Tekst=VALUES(Tekst),Foutmelding=VALUES(Foutmelding),BijgewerktOp=VALUES(BijgewerktOp)');
    $s->execute([$documentId,$status,$text,$error,date('Y-m-d H:i:s')]);
    $pdo->prepare('DELETE FROM DocumentTekstPagina WHERE DocumentID=?')->execute([$documentId]);
    if ($pages) {
        $insert = $pdo->prepare('INSERT INTO DocumentTekstPagina (DocumentID,Paginanummer,Tekst) VALUES (?,?,?)');
        foreach ($pages as $number => $pageText) $insert->execute([$documentId,$number,$pageText]);
    }
    return $status;
}
