<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/public/security.php';
require_once dirname(__DIR__).'/public/maintenance_mjop.php';

try {
    foreach (['pdo_mysql','mbstring','fileinfo','openssl','zip','zlib'] as $extension) {
        if (!extension_loaded($extension)) throw new RuntimeException('PHP-extensie ontbreekt: '.$extension);
    }
    if (is_file(tenantPrivateFile('users.php'))) throw new RuntimeException('Deze installatie heeft al gebruikers; gestopt.');
    $adminFile = tenantPrivateFile('initial-admin.php');
    if (!is_file($adminFile)) throw new RuntimeException('Maak eerst een nieuw beheerdersaccount met create_admin.php.');
    $admin = require $adminFile;
    if (!is_array($admin) || !preg_match('/^[a-zA-Z0-9_.-]{1,64}$/D', (string)($admin['username'] ?? ''))
        || empty(password_get_info((string)($admin['password_hash'] ?? ''))['algo'])) {
        throw new RuntimeException('Een geldig nieuw beheerdersaccount ontbreekt.');
    }
    $c = loadConfigCompat(tenantPrivateFile('config.php'));
    foreach (['host','database','username'] as $key) {
        if (empty($c[$key]) || preg_match('/[;\x00-\x1f]/', (string)$c[$key])) throw new RuntimeException('Databaseconfiguratie ontbreekt.');
    }
    $dsn = 'mysql:host='.$c['host'].';dbname='.$c['database'].';charset=utf8mb4';
    if (!empty($c['socket'])) $dsn = 'mysql:unix_socket='.$c['socket'].';dbname='.$c['database'].';charset=utf8mb4';
    $pdo = new PDO($dsn, (string)$c['username'], (string)($c['password'] ?? ''), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($pdo->query('SHOW FULL TABLES')->fetch() !== false) throw new RuntimeException('Database is niet leeg. Installatie weigert bestaande gegevens te wijzigen.');
    $schema = json_decode((string)file_get_contents(__DIR__.'/schema.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($schema as $sql) {
        if (!is_string($sql) || !preg_match('/^(SET NAMES|SET FOREIGN_KEY_CHECKS|CREATE TABLE|ALTER TABLE|CREATE ALGORITHM=)/', $sql)) {
            throw new RuntimeException('Onverwachte inhoud in het installatieschema.');
        }
    }
    foreach ($schema as $sql) $pdo->exec($sql);
    // General building element categories only, without old document/page links.
    $stmt = $pdo->prepare('INSERT INTO MJOPHoofdgroepen (Code,Naam,Pagina,DocumentID,vvegui_modified_at) VALUES (?,?,0,NULL,NOW())');
    foreach (maintenanceMjopDefinitions() as $item) $stmt->execute([$item['code'],$item['name']]);
    $stmt = $pdo->prepare('INSERT INTO VveInstallation (id,instance_id,schema_version) VALUES (1,?,1)');
    $stmt->execute([tenantConfig()['id']]);
    $users = [$admin['username']=>['name'=>(string)($admin['name'] ?? 'Beheerder'),'role'=>'admin','enabled'=>true,'password_hash'=>$admin['password_hash'],'must_change_password'=>false]];
    $file = tenantPrivateFile('users.php');
    if (file_put_contents($file, "<?php\nreturn ".var_export($users, true).";\n", LOCK_EX) === false) throw new RuntimeException('Gebruikersbestand niet geschreven.');
    chmod($file, 0600);
    unlink($adminFile);
    db($c); // Verify the database-to-instance binding.
    fwrite(STDOUT, "Lege VvE-installatie voltooid. E-mail, AI-provider en automatische back-ups staan uit.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Installatie gestopt: ".$e->getMessage()."\nEen gedeeltelijke schema-import wordt niet automatisch teruggedraaid.\n");
    exit(1);
}
