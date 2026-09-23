<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/public/security.php';
$file = tenantPrivateFile('initial-admin.php');
if (is_file($file) || is_file(tenantPrivateFile('users.php'))) {
    fwrite(STDERR, "Beheerdersbestand bestaat al; er is niets gewijzigd.\n"); exit(1);
}
if (!function_exists('stream_isatty') || !stream_isatty(STDIN) || !function_exists('shell_exec')) {
    fwrite(STDERR, "Voer dit script interactief uit via SSH.\n"); exit(1);
}
function secretPrompt(string $prompt): string {
    $mode = trim((string)shell_exec('stty -g'));
    if ($mode === '') throw new RuntimeException('Verborgen invoer is niet beschikbaar.');
    fwrite(STDOUT, $prompt);
    shell_exec('stty -echo');
    try { $value = rtrim((string)fgets(STDIN), "\r\n"); }
    finally { shell_exec('stty '.escapeshellarg($mode)); fwrite(STDOUT, "\n"); }
    return $value;
}
try {
    $password = secretPrompt('Nieuw wachtwoord voor admin: ');
    $repeat = secretPrompt('Herhaal het wachtwoord: ');
    if ($password !== $repeat || strlen($password) < 12 || !preg_match('/[a-z]/', $password)
        || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^a-zA-Z0-9]/', $password)) {
        throw new RuntimeException('Gebruik hetzelfde wachtwoord, minimaal 12 tekens met hoofdletter, kleine letter, cijfer en speciaal teken.');
    }
    $data = ['username'=>'admin','name'=>'Beheerder','password_hash'=>password_hash($password, PASSWORD_DEFAULT)];
    $handle = fopen($file, 'x');
    if ($handle === false) throw new RuntimeException('Bestand kon niet worden aangemaakt.');
    chmod($file, 0600);
    fwrite($handle, "<?php\nreturn ".var_export($data, true).";\n"); fclose($handle);
    fwrite(STDOUT, "Nieuw beheerdersaccount voorbereid. Voer nu install.php uit.\n");
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }
