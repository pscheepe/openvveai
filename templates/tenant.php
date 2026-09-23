<?php
declare(strict_types=1);

/** One deployment, one fixed VvE. Never derive identity from a URL parameter. */
function tenantConfig(): array {
    static $config = null;
    if ($config !== null) return $config;
    $path = dirname(__DIR__).'/private/tenant.php';
    if (!is_file($path) || is_link($path)) throw new RuntimeException('VvE-configuratie ontbreekt.');
    $value = require $path;
    if (!is_array($value) || !preg_match('/^[a-f0-9]{32}$/D', (string)($value['id'] ?? ''))
        || !preg_match('/^[a-z0-9][a-z0-9.-]+$/D', (string)($value['host'] ?? ''))
        || trim((string)($value['name'] ?? '')) === '') {
        throw new RuntimeException('Ongeldige VvE-configuratie.');
    }
    return $config = $value;
}

function tenantPrivateFile(string $name): string {
    if (!preg_match('/^[a-z][a-z0-9_.-]*$/D', $name)) throw new RuntimeException('Ongeldige configuratienaam.');
    $root = realpath(dirname(__DIR__).'/private');
    if ($root === false || is_link(dirname(__DIR__).'/private')) throw new RuntimeException('Privémap ontbreekt.');
    $file = $root.'/'.$name;
    if (is_link($file)) throw new RuntimeException('Symbolische configuratielink geweigerd.');
    return $file;
}

function tenantDirectory(string $name): string {
    if (!preg_match('/^[a-z][a-z0-9-]*$/D', $name)) throw new RuntimeException('Ongeldige opslagnaam.');
    $root = dirname(tenantPrivateFile('tenant.php')).'/'.$name;
    if (is_link($root)) throw new RuntimeException('Symbolische opslaglink geweigerd.');
    if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) throw new RuntimeException('Privéopslag niet beschikbaar.');
    $real = realpath($root);
    if ($real === false) throw new RuntimeException('Privéopslag niet beschikbaar.');
    return $real;
}

function tenantNameHtml(): string {
    return htmlspecialchars((string)tenantConfig()['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Web access requires the exact host and HTTPS. Forwarded headers are not trusted.
try {
    $tenant = tenantConfig();
    if (PHP_SAPI !== 'cli') {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== $tenant['host'] && $host !== $tenant['host'].':443') {
            http_response_code(421);
            exit('Dit adres hoort niet bij deze VvE.');
        }
        if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
            http_response_code(400);
            exit('Gebruik de beveiligde HTTPS-verbinding.');
        }
        $private = realpath(dirname(__DIR__).'/private');
        $webroot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($private === false || $webroot === false || $private === $webroot || str_starts_with($private, $webroot.'/')) {
            http_response_code(503);
            exit('Stel de document-root in op de public-map.');
        }
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
    }
} catch (Throwable $error) {
    if (PHP_SAPI === 'cli') throw $error;
    http_response_code(503);
    exit('Deze VvE-omgeving is nog niet ingericht.');
}
