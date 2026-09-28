<?php
declare(strict_types=1);

$source=file_get_contents(__DIR__.'/../release/public/document_text.php');
if(!is_string($source))throw new RuntimeException('document_text.php ontbreekt.');
$source=str_replace(['<?php','declare(strict_types=1);',"require_once __DIR__.'/tenant.php';"],'',$source);
eval($source);

$eml=tempnam(sys_get_temp_dir(),'openvve-eml-');
$singlepart=tempnam(sys_get_temp_dir(),'openvve-eml-single-');
$msg=tempnam(sys_get_temp_dir(),'openvve-msg-');
if($eml===false||$singlepart===false||$msg===false)throw new RuntimeException('Tijdelijk bestand kon niet worden gemaakt.');
try{
  file_put_contents($eml,"From: Jan Jansen <jan@example.nl>\r\nTo: Bestuur <bestuur@example.nl>\r\nSubject: =?UTF-8?Q?Lift_storing?=\r\nContent-Type: multipart/alternative; boundary=\"b1\"\r\n\r\n--b1\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nDe lift op verdieping 7 is defect.\r\n--b1\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n<b>niet nodig</b>\r\n--b1--\r\n");
  $emailText=documentEmlText($eml);
  foreach(['Lift storing','jan@example.nl','verdieping 7','defect'] as $needle)if(!str_contains($emailText,$needle))throw new RuntimeException('EML-index mist: '.$needle);
  if(str_contains($emailText,'niet nodig'))throw new RuntimeException('EML-index nam de HTML-alternatieftekst mee.');
  file_put_contents($singlepart,"From: Jeroen Mulder <jeroen@example.invalid>\r\nSubject: Melding koude radiator\r\nContent-Type: text/plain; charset=\"utf-8\"\r\nContent-Transfer-Encoding: quoted-printable\r\nMIME-Version: 1.0\r\n\r\nKoude radiator in appartement. Het probleem doet=\r\n zich regelmatig voor.\r\n");
  $singlepartText=documentEmlText($singlepart);
  foreach(['Melding koude radiator','Jeroen Mulder','Koude radiator in appartement','regelmatig voor'] as $needle)if(!str_contains($singlepartText,$needle))throw new RuntimeException('Enkelvoudige quoted-printable EML-index mist: '.$needle);
  file_put_contents($msg,"Subject text\0".iconv('UTF-8','UTF-16LE','Leverancier test factuur 123'));
  $msgText=documentMsgText($msg);
  if(!str_contains($msgText,'Leverancier test factuur'))throw new RuntimeException('MSG-index miste leesbare Unicode-berichttekst.');
  echo "EML- en MSG-tekstextractie geslaagd.\n";
}finally{@unlink($eml);@unlink($singlepart);@unlink($msg);}
