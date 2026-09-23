<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/public/security.php';
try {
    $pdo = db(loadConfigCompat(tenantPrivateFile('config.php')));
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $nonempty = [];
    foreach ($tables as $table) {
        if (in_array($table, ['VveInstallation','MJOPHoofdgroepen'], true)) continue;
        if ((int)$pdo->query('SELECT COUNT(*) FROM `'.str_replace('`','``',$table).'`')->fetchColumn() > 0) $nonempty[] = $table;
    }
    echo json_encode(['database_binding'=>'ok','business_tables_empty'=>!$nonempty,'nonempty_tables'=>$nonempty,'imap_available'=>function_exists('imap_open'),'gd_available'=>extension_loaded('gd')], JSON_PRETTY_PRINT)."\n";
    exit($nonempty ? 2 : 0);
} catch (Throwable $e) { fwrite(STDERR, "Controle mislukt: ".$e->getMessage()."\n"); exit(1); }
