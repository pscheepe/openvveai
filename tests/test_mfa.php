<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/openvveai-mfa-'.bin2hex(random_bytes(5));mkdir($dir,0700);
function tenantPrivateFile(string $name): string { global $dir; return $dir.'/'.$name; }
require_once __DIR__.'/../release/public/mfa.php';
function check(bool $ok,string $label): void { if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);} }
$secret='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // gitleaks:allow — public RFC 6238 test vector, not a credential.
check(mfaCodeValid($secret,'287082',59),'RFC 6238 SHA-1 vector');
check(!mfaCodeValid($secret,'287083',59),'reject incorrect TOTP');
$step=mfaMatchedStep($secret,'287082',59);check($step===1,'return matched time step');
check(mfaMatchedStep($secret,'287082',59,$step)===null,'reject replayed time step');
$encrypted=mfaEncrypt('secret-seed');check($encrypted!=='secret-seed'&&mfaDecrypt($encrypted)==='secret-seed','AES-GCM secret protection');
$codes=mfaIssueRecoveryCodes();check(count($codes)===10&&count(array_unique($codes))===10,'unique recovery codes');
foreach(glob($dir.'/*')?:[] as $file)unlink($file);rmdir($dir);
echo "MFA helper checks passed.\n";
