<?php
// vvegui authentication helper

declare(strict_types=1);
require_once __DIR__.'/tenant.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/instruction_ui.php';
require_once __DIR__ . '/mfa.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('__Host-VVE'.tenantConfig()['id']);
    session_save_path(tenantDirectory('sessions'));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (isset($_SESSION['vve_id']) && $_SESSION['vve_id'] !== tenantConfig()['id']) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['vve_id'] = tenantConfig()['id'];
}

// Beëindig een aangemelde sessie na 30 minuten zonder verzoeken.
if (!empty($_SESSION['user'])) {
    $now = time();
    $lastActivity = (int)($_SESSION['last_activity'] ?? $now);
    if (($now - $lastActivity) > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['login_notice'] = 'Uw sessie is na 30 minuten inactiviteit automatisch beëindigd.';
    } else {
        $_SESSION['last_activity'] = $now;
    }
}

function usersFile(): string { return tenantPrivateFile('users.php'); }
function csrfToken(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verifyCsrf(): void {
    $given = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '');
    $known = (string)($_SESSION['csrf'] ?? '');
    if ($known === '' || $given === '' || !hash_equals($known, $given)) {
        http_response_code(403);
        throw new RuntimeException('Ongeldige beveiligingstoken.');
    }
}
function loadUsers(): array {
    $file = usersFile();
    if (!is_file($file)) return [];
    if (function_exists('opcache_invalidate')) @opcache_invalidate($file, true);
    clearstatcache(true, $file);
    $x = include $file;
    return is_array($x) ? $x : [];
}
function currentUser(): ?array {
    $u = $_SESSION['user'] ?? null;
    if (!is_array($u)) return null;
    $username = (string)($u['username'] ?? '');
    $stored = loadUsers()[$username] ?? null;
    if (!is_array($stored) || empty($stored['enabled'])) {
        unset($_SESSION['user'], $_SESSION['last_activity']);
        return null;
    }
    $u['role'] = (string)($stored['role'] ?? 'bestuur');
    $u['name'] = (string)($stored['name'] ?? $username);
    $u['must_change_password'] = !empty($stored['must_change_password']);
    $_SESSION['user'] = $u;
    return $u;
}
function requireLogin(): array {
    $u = currentUser();
    if (!$u) {
        http_response_code(401);
        throw new RuntimeException('Inloggen vereist.');
    }
    if (mfaPolicy() === 'required' && empty(loadUsers()[(string)$u['username']]['mfa_secret'])) {
        if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'mfa_settings.php') { header('Location: mfa_settings.php'); exit; }
    }
    if (!empty($u['must_change_password']) && basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'change_password.php') {
        header('Location: change_password.php');
        exit;
    }
    return $u;
}
function requireAdmin(): array {
    $u = requireLogin();
    if (($u['role'] ?? '') !== 'admin') {
        http_response_code(403);
        throw new RuntimeException('Alleen de gebruiker admin mag wijzigingen uitvoeren.');
    }
    return $u;
}
function attemptLogin(string $username, string $password): bool {
    $username = trim($username);
    unset($_SESSION['mfa_pending_user'], $_SESSION['mfa_pending_at']);
    if ($username === '' || $password === '') return false;
    enforceLoginRateLimit($username);
    $users = loadUsers();
    $u = $users[$username] ?? null;
    if (!is_array($u) || empty($u['enabled']) || empty($u['password_hash']) || !password_verify($password, (string)$u['password_hash'])) {
        recordFailedLogin($username);
        return false;
    }
    if (mfaPolicy() !== 'off' && !empty($u['mfa_secret'])) {
        session_regenerate_id(true);
        $_SESSION['mfa_pending_user'] = $username;
        $_SESSION['mfa_pending_at'] = time();
        return false;
    }
    completeLogin($username, $u);
    return true;
}
function completeLogin(string $username, array $u): void {
    clearFailedLogins($username);
    session_regenerate_id(true);
    $_SESSION['user'] = ['username'=>$username,'role'=>$u['role'] ?? 'bestuur','name'=>$u['name'] ?? $username,'must_change_password'=>!empty($u['must_change_password'])];
    unset($_SESSION['mfa_pending_user'], $_SESSION['mfa_pending_at']);
    $_SESSION['last_activity'] = time();
    csrfToken();
}
function validateSafePassword(string $password): void {
    if (strlen($password) < 12) throw new InvalidArgumentException('Het wachtwoord moet minimaal 12 tekens bevatten.');
    if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^a-zA-Z0-9]/', $password)) {
        throw new InvalidArgumentException('Gebruik minimaal één kleine letter, hoofdletter, cijfer en speciaal teken.');
    }
}
function loginRateKey(string $username): string {
    $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
    return hash('sha256', strtolower(trim($username))."\0".$ip);
}
function loginRateFile(string $username): string { return tenantDirectory('rate-limits').'/vve-login-'.loginRateKey($username).'.json'; }
function enforceLoginRateLimit(string $username): void {
    $file=loginRateFile($username); if(!is_file($file))return;
    $data=json_decode((string)file_get_contents($file),true); if(!is_array($data))return;
    $since=time()-900; $attempts=array_values(array_filter((array)($data['attempts']??[]),fn($t)=>(int)$t>=$since));
    if(count($attempts)>=5){http_response_code(429);throw new SafeInputException('Te veel mislukte inlogpogingen. Probeer het over 15 minuten opnieuw.');}
}
function recordFailedLogin(string $username): void {
    $file=loginRateFile($username);$since=time()-900;$attempts=[];
    if(is_file($file)){$data=json_decode((string)file_get_contents($file),true);$attempts=is_array($data)?(array)($data['attempts']??[]):[];}
    $attempts=array_values(array_filter($attempts,fn($t)=>(int)$t>=$since));$attempts[]=time();
    file_put_contents($file,json_encode(['attempts'=>$attempts]),LOCK_EX);@chmod($file,0600);
}
function clearFailedLogins(string $username): void { $file=loginRateFile($username);if(is_file($file))@unlink($file); }
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}
function saveUsers(array $users): void {
    $php = "<?php\n// Beheerd door setup.php / admin. Wachtwoorden staan uitsluitend als password_hash.\nreturn " . var_export($users, true) . ";\n";
    if (file_put_contents(usersFile(), $php, LOCK_EX) === false) throw new RuntimeException('users.php kon niet worden opgeslagen.');
    @chmod(usersFile(), 0600);
    if (function_exists('opcache_invalidate')) @opcache_invalidate(usersFile(), true);
    clearstatcache(true, usersFile());
}
