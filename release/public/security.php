<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';

final class SafeInputException extends RuntimeException {}

function uploadRoot(): string {
    return tenantDirectory('uploads');
}

function uploadDirectory(string $bucket): string {
    if (!preg_match('/^[a-z0-9_-]+$/', $bucket)) throw new RuntimeException('Ongeldige opslagcategorie.');
    $dir = uploadRoot() . DIRECTORY_SEPARATOR . $bucket;
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('De beveiligde opslagmap kan niet worden aangemaakt.');
    }
    return $dir;
}

function storedPath(string $bucket, string $filename): string {
    return $bucket . '/' . $filename;
}

function resolveStoredFile(string $storedPath): string {
    if ($storedPath === '' || str_contains($storedPath, "\0") || str_starts_with($storedPath, '/')) {
        throw new RuntimeException('Ongeldig opgeslagen bestandspad.');
    }
    $root = uploadRoot();
    $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storedPath);
    if (is_link($candidate)) throw new RuntimeException('Symbolische links zijn niet toegestaan.');
    $real = realpath($candidate);
    if ($real === false || !is_file($real) || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Opgeslagen bestand niet gevonden.');
    }
    return $real;
}

function deleteStoredFile(?string $storedPath): void {
    if ($storedPath === null || $storedPath === '') return;
    $file = resolveStoredFile($storedPath);
    if (!unlink($file)) throw new RuntimeException('Bestand kon niet veilig worden verwijderd.');
}

function allowedDocumentMime(string $extension, string $mime): bool {
    $map = [
        'pdf'=>['application/pdf'], 'doc'=>['application/msword','application/x-ole-storage'],
        'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip'],
        'xls'=>['application/vnd.ms-excel','application/x-ole-storage'],
        'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip'],
        'odt'=>['application/vnd.oasis.opendocument.text','application/zip'],
        'ods'=>['application/vnd.oasis.opendocument.spreadsheet','application/zip'],
        'csv'=>['text/plain','text/csv','application/csv'], 'txt'=>['text/plain'],
        'rtf'=>['application/rtf','text/rtf','text/plain'], 'jpg'=>['image/jpeg'], 'jpeg'=>['image/jpeg'],
        'png'=>['image/png'], 'gif'=>['image/gif'], 'webp'=>['image/webp'],
    ];
    return isset($map[$extension]) && in_array($mime, $map[$extension], true);
}

function securityIncident(Throwable $error): string {
    $id = bin2hex(random_bytes(8));
    error_log('[security-incident ' . $id . '] ' . get_class($error) . ': ' . $error->getMessage());
    return $id;
}

function loadConfigCompat(string $file): array {
    clearstatcache(true, $file);
    if ($file !== tenantPrivateFile('config.php') || !is_file($file)) {
        throw new RuntimeException('Databaseconfiguratie ontbreekt.');
    }
    if (function_exists('opcache_invalidate')) opcache_invalidate($file, true);
    $config = require $file;
    if (!is_array($config)) throw new RuntimeException('Ongeldige databaseconfiguratie.');
    return $config;
}

function db(array $c): PDO {
    $host = (string)($c['host'] ?? '');
    $name = (string)($c['database'] ?? '');
    $user = (string)($c['username'] ?? '');
    if ($host === '' || $name === '' || $user === '' || preg_match('/[;\x00-\x1f]/', $host.$name)) {
        throw new RuntimeException('Databaseconfiguratie is onvolledig.');
    }
    $dsn = 'mysql:host='.$host.';dbname='.$name.';charset=utf8mb4';
    // Socket support is only for an isolated local test database.
    if (in_array(PHP_SAPI, ['cli','cli-server'], true) && !empty($c['socket'])) $dsn = 'mysql:unix_socket='.$c['socket'].';dbname='.$name.';charset=utf8mb4';
    $pdo = new PDO($dsn, $user, (string)($c['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    try {
        $id = $pdo->query('SELECT instance_id FROM VveInstallation WHERE id=1')->fetchColumn();
        if (!is_string($id) || !hash_equals(tenantConfig()['id'], $id)) throw new RuntimeException('VvE mismatch');
    } catch (Throwable $error) {
        throw new RuntimeException('Database hoort niet bij deze VvE of is nog niet geïnstalleerd.');
    }
    return $pdo;
}
