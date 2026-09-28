<?php
declare(strict_types=1);
require_once __DIR__.'/../release/public/assistant_financial.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$withFolder = assistantFinancialVendorTerms('Zoek in crediteuren wat er de laatste 5 jaar betaald is aan Liftzorg.nl');
$withoutFolder = assistantFinancialVendorTerms('Wat hebben we de laatste 5 jaar betaald aan Liftzorg.nl?');
check($withFolder === ['liftzorg'], 'Crediteurenvraag moet op leverancier zoeken.');
check($withoutFolder === ['liftzorg'], 'Betaalvraag zonder woord crediteuren moet op leverancier zoeken.');
check(assistantFinancialVendorTerms('Waar staat het onderhoudscontract?') === [], 'Niet-financiële vraag mag niet worden overgenomen.');
check(assistantFinancialIsQuestion('Wat hebben we betaald aan LiftService Noord BV?'), 'Betalingsvraag moet financieel worden herkend.');
check(assistantFinancialVendorTerms('Volgens Crediteuren_2026.csv: welke LiftService Noord BV facturen zijn betaald en welke is openstaand?') === ['liftservice','noord'], 'Alleen leverancierswoorden mogen de financiële zoekroute sturen.');
check(assistantFinancialVendorTerms('Welke betalingen zijn volgens de fictieve betaalhistorie in 2026 aan LiftService Noord BV gedaan, en welke factuur staat nog open?') === ['liftservice','noord'], 'Bij een samengestelde vraag moet de naam na aan worden herkend.');
check(assistantFinancialVendorTerms('Welke facturen staan in 2026 nog open?') === [], 'Een openstaande-facturenvraag heeft geen leveranciersfilter.');

$csv = "Crediteur;Te betalen;Betaald;Saldo\nAndere Leverancier;€ 100,00;€ 90,00;€ 10,00\nLiftzorg B.V.;€ 200,00;€ 175,00;€ 25,00\n";
$excerpt = assistantFinancialCsvExcerpt($csv, $withoutFolder);
check(str_contains($excerpt, 'Crediteur;Te betalen;Betaald;Saldo'), 'Kolomkoppen moeten bewaard blijven.');
check(str_contains($excerpt, 'Liftzorg B.V.;€ 200,00;€ 175,00;€ 25,00'), 'Volledige leveranciersregel moet bewaard blijven.');
check(!str_contains($excerpt, 'Andere Leverancier'), 'Andere leveranciers mogen niet in de passage staan.');
check(assistantFinancialCsvExcerpt($csv, ['onbekend']) === '', 'Zonder passende rij mag geen betaalbedrag worden gesuggereerd.');

$credit="Factuur_ID;Supplier_ID;Leverancier;Factuurdatum;Omschrijving;Incl_btw;Betaaldatum;Status\n"
    ."IN-2026-10037;SUP-001;LiftService Noord BV;2026-01-10;Lift onderhoud;1512.5;2026-01-29;Betaald\n"
    ."IN-2026-10038;SUP-001;LiftService Noord BV;2026-02-05;Lift storingsdienst;387.2;2026-02-12;Betaald\n"
    ."IN-2026-2002;SUP-001;LiftService Noord BV;2026-03-01;Modernisering lift;13552;;Openstaand\n";
$payments="Betaling_ID;Factuur_ID;Datum;Bedrag;Methode\n"
    ."PAY-2026-10037;IN-2026-10037;2026-01-29;1512.5;Bank\n"
    ."PAY-2026-10038;IN-2026-10038;2026-02-12;387.2;Bank\n"
    ."PAY-2026-2002;IN-2026-2002;2026-12-01;13552;Bank\n";
$ledger="Datum;Factuur_ID;Leverancier;Grootboekrekening;Bedrag\n"
    ."2026-01-10;IN-2026-10037;LiftService Noord BV;Onderhoud;1250\n";
$suppliers="Supplier_ID;Leverancier\nSUP-001;LiftService Noord BV\n";
$offers="Offerte_ID;Supplier_ID;Bedrag;Status\nOFF-2026-044;SUP-001;27104;Geaccepteerd\n";
{
    $terms=['liftservice','noord'];
    $supplierIds=assistantFinancialSupplierIds([['OrigineleNaam'=>'Leveranciers.csv','Tekst'=>$suppliers]],$terms);
    check(isset($supplierIds['SUP-001']), 'Een offerte moet via Supplier_ID aan een leverancier gekoppeld kunnen worden.');
    check(str_contains(assistantFinancialCsvExcerpt($offers,$terms,5000,[],$supplierIds),'OFF-2026-044'), 'Offertebedragen moeten vindbaar zijn via de leverancier.');
    $invoiceIds=assistantFinancialInvoiceIds([['OrigineleNaam'=>'Crediteuren_2026.csv','Tekst'=>$credit]],$terms);
    check(isset($invoiceIds['IN-2026-10037'],$invoiceIds['IN-2026-10038'],$invoiceIds['IN-2026-2002']), 'Ook de openstaande factuur moet aan de leverancier worden gekoppeld.');
    $creditExcerpt=assistantFinancialCsvExcerpt($credit,$terms);
    $openExcerpt=assistantFinancialCsvExcerpt($credit,[],5000,[],[],null,'openstaand');
    $paymentExcerpt=assistantFinancialCsvExcerpt($payments,$terms,5000,$invoiceIds,[],'2026-09-26');
    $ledgerExcerpt=assistantFinancialCsvExcerpt($ledger,$terms,5000,$invoiceIds);
    check(str_contains($creditExcerpt,'IN-2026-2002')&&str_contains($creditExcerpt,'Openstaand'), 'Openstaande factuur moet zichtbaar blijven.');
    check(str_contains($openExcerpt,'IN-2026-2002')&&!str_contains($openExcerpt,'IN-2026-10037'), 'Openstaande facturen moeten afzonderlijk gefilterd worden.');
    check(str_contains($paymentExcerpt,'PAY-2026-10037')&&str_contains($paymentExcerpt,'PAY-2026-10038'), 'Betalingsregels moeten via Factuur_ID gevonden worden.');
    check(!str_contains($paymentExcerpt,'IN-2026-2002'), 'Openstaande factuur mag niet als betaling worden gepresenteerd.');
    check(str_contains($ledgerExcerpt,'IN-2026-10037'), 'Grootboekregels moeten als aparte bron vindbaar zijn.');
    [$paymentHeader,$paymentRows]=assistantFinancialCsvRows($paymentExcerpt);
    check(in_array('Bedrag',$paymentHeader,true),'De betalingskolom moet behouden blijven.');
    $paidTotal=array_sum(array_map(static fn($row)=>(float)$row['data']['Bedrag'],$paymentRows));
    check(abs($paidTotal-1899.70)<0.001,'De twee echte betalingen aan LiftService Noord BV moeten optellen tot € 1.899,70.');
}
echo "OK: gerichte financiële bronextractie\n";
