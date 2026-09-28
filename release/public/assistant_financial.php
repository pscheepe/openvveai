<?php
declare(strict_types=1);

function assistantFinancialIsQuestion(string $question): bool {
    return (bool) preg_match('/\b(?:betaal\w*|betal\w*|crediteur\w*|factur\w*|grootboek\w*|boekhoud\w*|kosten\w*|uitgav\w*|uitgegev\w*|bested\w*|begrot\w*|budget\w*|offert\w*|saldo\w*|omzet\w*|financi\w*|bedrag\w*|btw|mjop)\b/iu', $question);
}

function assistantFinancialVendorTerms(string $question): array {
    if (!assistantFinancialIsQuestion($question)) return [];
    $focus = $question;
    if (preg_match_all('/\b(?:aan|bij)\s+([^?;]+)/iu', $question, $matches) && !empty($matches[1])) {
        $focus = (string) end($matches[1]);
        $focus = (string) (preg_split('/,|\s+en\s+(?=welk|wat|hoe|waar)/iu', $focus, 2)[0] ?? $focus);
    }
    $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($focus), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $generic = ['aan','afgelopen','alle','alleen','als','andere','bestanden','bestand','betaalhistorie','bron','bronnen','btw','bv','categorie','categorieen','csv','daadwerkelijk','de','deze','dit','door','een','en','er','fictief','fictieve','geboekte','gedaan','geef','gezamenlijke','hebben','heeft','het','historie','hoe','hoeveel','in','inclusief','is','jaar','jaren','jaaroverzicht','laatste','leverancier','leveranciers','maand','maanden','met','naar','niet','nl','noem','nog','om','onder','onderhoud','onderhoudskosten','onze','op','open','over','overzicht','periode','sinds','specificatie','staan','staat','status','totaal','tot','transacties','uitgegeven','van','vergelijk','vergelijking','vijf','volgens','voor','wat','welke','werkelijk','werkelijke','wij','we','zoek','zijn'];
    $terms = [];
    foreach ($parts as $part) {
        if (mb_strlen($part) < 4 || in_array($part, $generic, true) || preg_match('/^\d+$/', $part) || preg_match('/^(?:bedrag|betaal|betal|begrot|boekhoud|budget|contract|crediteur|factur|financi|grootboek|inkoopfactur|kosten|mjop|offert|openstaand|saldo|uitgav|uitgegeven)/u', $part)) continue;
        $terms[$part] = true;
    }
    return array_slice(array_keys($terms), 0, 5);
}

function assistantFinancialCsvRows(string $text): array {
    $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
    if (!$lines) return [[], []];
    $header = str_getcsv((string) array_shift($lines), ';', '"', '');
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($header[0] ?? '')) ?? '';
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $values = str_getcsv($line, ';', '"', '');
        if (count($values) !== count($header)) continue;
        $rows[] = ['data' => array_combine($header, $values), 'line' => $line];
    }
    return [$header, $rows];
}

function assistantFinancialCsvExcerpt(string $text, array $vendorTerms, int $limit = 5000, array $invoiceIds = [], array $supplierIds = [], ?string $paidThrough = null, ?string $requiredStatus = null): string {
    [$header, $rows] = assistantFinancialCsvRows($text);
    if (!$header || !$rows) return '';
    $matches = [];
    foreach ($rows as $row) {
        $data = $row['data'];
        if ($paidThrough !== null && isset($data['Datum']) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string) $data['Datum']) && $data['Datum'] > $paidThrough) continue;
        if ($requiredStatus !== null && mb_strtolower(trim((string) ($data['Status'] ?? ''))) !== $requiredStatus) continue;
        $invoiceId = trim((string) ($data['Factuur_ID'] ?? $data['Document_ID'] ?? ''));
        $linked = $invoiceId !== '' && isset($invoiceIds[$invoiceId]);
        $supplierId = trim((string) ($data['Supplier_ID'] ?? ''));
        if ($supplierId !== '' && isset($supplierIds[$supplierId])) $linked = true;
        $vendor = trim((string) ($data['Leverancier'] ?? $data['Crediteur'] ?? $data['Naam'] ?? ''));
        $vendorMatches = $vendorTerms && $vendor !== '';
        foreach ($vendorTerms as $term) if (mb_stripos($vendor, $term) === false) $vendorMatches = false;
        if ($vendorTerms && $vendor === '') {
            $vendorMatches = true;
            foreach ($vendorTerms as $term) if (mb_stripos($row['line'], $term) === false) $vendorMatches = false;
        }
        if (!$vendorTerms && !$invoiceIds && !$supplierIds) $vendorMatches = true;
        if ($linked || $vendorMatches) $matches[] = $row['line'];
    }
    if (!$matches) return '';
    $excerpt = implode(';', $header)."\n";
    foreach ($matches as $line) {
        if (mb_strlen($excerpt.$line."\n") > $limit) break;
        $excerpt .= $line."\n";
    }
    return trim($excerpt);
}

function assistantFinancialSupplierIds(array $documents, array $vendorTerms): array {
    if (!$vendorTerms) return [];
    $ids=[];
    foreach ($documents as $document) {
        if (!preg_match('/^Leveranciers.*\.csv$/iu',(string)$document['OrigineleNaam'])) continue;
        [, $rows]=assistantFinancialCsvRows((string)($document['Tekst']??''));
        foreach ($rows as $row) {
            $name=(string)($row['data']['Leverancier']??$row['data']['Naam']??'');
            $matches=true;
            foreach ($vendorTerms as $term) if (mb_stripos($name,$term)===false) $matches=false;
            if ($matches && isset($row['data']['Supplier_ID'])) $ids[(string)$row['data']['Supplier_ID']]=true;
        }
    }
    return $ids;
}

function assistantFinancialInvoiceIds(array $documents, array $vendorTerms): array {
    $ids = [];
    foreach ($documents as $document) {
        if (!preg_match('/^(?:Crediteuren|Inkoopfacturen).*\.csv$/iu', (string) $document['OrigineleNaam'])) continue;
        [$header, $rows] = assistantFinancialCsvRows((string) ($document['Tekst'] ?? ''));
        if (!in_array('Factuur_ID', $header, true)) continue;
        foreach ($rows as $row) {
            $vendor = (string) ($row['data']['Leverancier'] ?? '');
            $matches = !$vendorTerms;
            if ($vendorTerms) {
                $matches = true;
                foreach ($vendorTerms as $term) if (mb_stripos($vendor, $term) === false) $matches = false;
            }
            if ($matches) $ids[(string) $row['data']['Factuur_ID']] = true;
        }
    }
    return $ids;
}
