<?php
// Local test harness only; NEVER deploy this router. It simulates HTTPS metadata.
declare(strict_types=1);
$instances = json_decode((string)getenv('VVE_TEST_INSTANCES'), true, 512, JSON_THROW_ON_ERROR);
$host = (string)($_SERVER['HTTP_HOST'] ?? '');
$root = $instances[$host] ?? reset($instances);
$_SERVER['DOCUMENT_ROOT'] = $root.'/public';
$_SERVER['HTTPS'] = isset($_SERVER['HTTP_X_TEST_PLAIN_HTTP']) ? 'off' : 'on';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/') $path = '/index.php';
if (!preg_match('~^/[a-z_]+\.php$~D', $path)) { http_response_code(404); exit; }
$file = $root.'/public'.$path;
if (!is_file($file)) { http_response_code(404); exit; }
require $file;
