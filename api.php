<?php
/*
 * Easy Arcade — online library
 *
 * Keeps your games, artwork, settings and saved progress on your own web hosting,
 * behind a password you choose the first time you open it. Works on PHP 7.4 or newer.
 *
 * Everything is stored in the "data" folder next to this file, inside a sub-folder
 * with a random name, so it can't be fetched directly even on servers that ignore
 * .htaccess files. Only this script, after you sign in, can read it.
 *
 * Forgot your password? Delete data/config.php with your hosting file manager and
 * open Easy Arcade again to choose a new one. Your games stay where they are.
 */

const CHUNK_MAX  = 8 * 1024 * 1024;     // largest piece of a file accepted in one request
const MAX_FILE   = 1024 * 1024 * 1024;  // largest single file (1 GB)
const KINDS      = ['rom' => '.zip', 'art' => '.jpg', 'state' => '.state'];
const META_KEYS  = ['id','title','file','size','helper','engine','added','lastPlayed','plays','artFit','artV','artAt','hasArt',
                    'coreOpts','hasState','stateAt','stateSize'];

$DATA = __DIR__ . '/data';
$CONFIG = $DATA . '/config.php';

/* ---------- helpers ---------- */
function out($a, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($a);
    exit;
}
function fail($msg, $code = 400) { out(['ok' => false, 'error' => $msg], $code); }
function body_json() {
    $j = json_decode(file_get_contents('php://input'), true);
    return is_array($j) ? $j : [];
}
function valid_id($id) { return is_string($id) && preg_match('/^[a-z0-9_-]{1,64}$/', $id); }
function load_config($path) { return is_file($path) ? (include $path) : null; }
function save_config($path, $cfg) {
    $tmp = $path . '.tmp';
    file_put_contents($tmp, "<?php\n// Easy Arcade settings — do not share this file.\nreturn " . var_export($cfg, true) . ";\n", LOCK_EX);
    rename($tmp, $path);
    if (function_exists('opcache_invalidate')) @opcache_invalidate($path, true);
}
function protect_dir($dir) {
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    if (!is_file("$dir/.htaccess"))
        file_put_contents("$dir/.htaccess", "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    if (!is_file("$dir/index.html")) file_put_contents("$dir/index.html", '');
}

/* Library metadata lives in one JSON file, updated under a lock */
function lib_path($store) { return "$store/library.json"; }
function lib_read($store) {
    $p = lib_path($store);
    if (!is_file($p)) return ['games' => new stdClass()];
    $j = json_decode(file_get_contents($p), true);
    return is_array($j) && isset($j['games']) ? $j : ['games' => []];
}
function lib_update($store, callable $fn) {
    $p = lib_path($store);
    $fh = fopen($p, 'c+');
    if (!$fh) fail('Could not open the library file', 500);
    flock($fh, LOCK_EX);
    $raw = stream_get_contents($fh);
    $lib = json_decode($raw ?: '', true);
    if (!is_array($lib) || !isset($lib['games']) || !is_array($lib['games'])) $lib = ['games' => []];
    $lib = $fn($lib);
    ftruncate($fh, 0); rewind($fh);
    fwrite($fh, json_encode($lib));
    fflush($fh); flock($fh, LOCK_UN); fclose($fh);
    return $lib;
}
function clean_meta($m) {
    $o = [];
    foreach (META_KEYS as $k) if (array_key_exists($k, $m)) $o[$k] = $m[$k];
    if (isset($o['title'])) $o['title'] = mb_substr((string)$o['title'], 0, 80);
    if (isset($o['file']))  $o['file']  = preg_replace('/[^a-z0-9_.-]/', '', strtolower((string)$o['file']));
    if (isset($o['coreOpts']) && !is_array($o['coreOpts'])) unset($o['coreOpts']);
    return $o;
}

/* ---------- session ---------- */
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$cookiePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/';
session_name('easyarcade');
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 60, 'path' => $cookiePath, 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
} else {
    session_set_cookie_params(60 * 60 * 24 * 60, $cookiePath, '', $https, true);
}
ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 60));
session_start();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Requests that change anything must come from Easy Arcade itself (blocks cross-site form tricks)
if ($method === 'POST' && ($_SERVER['HTTP_X_EASY_ARCADE'] ?? '') !== '1') fail('Not allowed', 403);

protect_dir($DATA);
$cfg = load_config($CONFIG);
$authed = $cfg && !empty($_SESSION['ea_ok']) && ($_SESSION['ea_key'] ?? '') === $cfg['secret'];

/* ---------- open actions ---------- */
if ($action === 'status') {
    $info = ['ok' => true, 'easyArcade' => 1, 'setup' => !$cfg, 'authed' => (bool)$authed];
    if ($authed) {
        $info['maxUpload'] = CHUNK_MAX;
        $free = @disk_free_space($DATA);
        if ($free !== false) $info['free'] = $free;
    }
    out($info);
}

if ($action === 'setup') {
    if ($method !== 'POST') fail('Use POST', 405);
    if ($cfg) fail('A password has already been set', 409);
    $pw = (string)(body_json()['password'] ?? '');
    if (strlen($pw) < 6) fail('Please choose a password of at least 6 characters');
    $secret = bin2hex(random_bytes(16));
    $cfg = ['hash' => password_hash($pw, PASSWORD_DEFAULT), 'secret' => $secret, 'store' => 'lib-' . bin2hex(random_bytes(12)),
            'fails' => 0, 'lockUntil' => 0, 'created' => time()];
    $store = "$DATA/{$cfg['store']}";
    protect_dir($store);
    foreach (array_keys(KINDS) as $k) protect_dir("$store/$k");
    save_config($CONFIG, $cfg);
    session_regenerate_id(true);
    $_SESSION['ea_ok'] = true; $_SESSION['ea_key'] = $secret;
    out(['ok' => true]);
}

if ($action === 'login') {
    if ($method !== 'POST') fail('Use POST', 405);
    if (!$cfg) fail('No password has been set yet', 409);
    if (($cfg['lockUntil'] ?? 0) > time()) fail('Too many tries — please wait a few minutes and try again', 429);
    $pw = (string)(body_json()['password'] ?? '');
    if (!password_verify($pw, $cfg['hash'])) {
        $cfg['fails'] = ($cfg['fails'] ?? 0) + 1;
        if ($cfg['fails'] >= 5) { $cfg['fails'] = 0; $cfg['lockUntil'] = time() + 300; }
        save_config($CONFIG, $cfg);
        sleep(1);
        fail('That password isn\'t right', 401);
    }
    if (($cfg['fails'] ?? 0) !== 0) { $cfg['fails'] = 0; save_config($CONFIG, $cfg); }
    session_regenerate_id(true);
    $_SESSION['ea_ok'] = true; $_SESSION['ea_key'] = $cfg['secret'];
    out(['ok' => true]);
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    out(['ok' => true]);
}

/* ---------- everything else needs you to be signed in ---------- */
if (!$authed) fail('Please sign in', 401);
$store = "$DATA/{$cfg['store']}";
foreach (array_keys(KINDS) as $k) protect_dir("$store/$k");
session_write_close();   // let parallel requests run

switch ($action) {

case 'library':
    out(['ok' => true, 'library' => lib_read($store)]);

case 'meta':            // save one game's details (title, engine, settings...)
    if ($method !== 'POST') fail('Use POST', 405);
    $g = body_json()['game'] ?? null;
    if (!is_array($g) || !valid_id($g['id'] ?? null)) fail('Bad game');
    $m = clean_meta($g);
    lib_update($store, function ($lib) use ($m) {
        $cur = $lib['games'][$m['id']] ?? [];
        $lib['games'][$m['id']] = array_merge($cur, $m);
        return $lib;
    });
    out(['ok' => true]);

case 'delete':          // remove a game and its files
    if ($method !== 'POST') fail('Use POST', 405);
    $id = body_json()['id'] ?? null;
    if (!valid_id($id)) fail('Bad game');
    foreach (KINDS as $k => $ext) @unlink("$store/$k/$id$ext");
    lib_update($store, function ($lib) use ($id) { unset($lib['games'][$id]); return $lib; });
    out(['ok' => true]);

case 'delstate':
    if ($method !== 'POST') fail('Use POST', 405);
    $id = body_json()['id'] ?? null;
    if (!valid_id($id)) fail('Bad game');
    @unlink("$store/state/$id.state");
    lib_update($store, function ($lib) use ($id) {
        if (isset($lib['games'][$id])) { $lib['games'][$id]['hasState'] = false; unset($lib['games'][$id]['stateAt']); }
        return $lib;
    });
    out(['ok' => true]);

case 'upload':          // a file arrives in pieces: kind, id, part (0-based), parts
    if ($method !== 'POST') fail('Use POST', 405);
    $kind = $_GET['kind'] ?? ''; $id = $_GET['id'] ?? '';
    $part = (int)($_GET['part'] ?? -1); $parts = (int)($_GET['parts'] ?? 0);
    if (!isset(KINDS[$kind]) || !valid_id($id) || $parts < 1 || $part < 0 || $part >= $parts) fail('Bad upload');
    $final = "$store/$kind/$id" . KINDS[$kind];
    $tmp = "$final.part";
    $in = fopen('php://input', 'rb');
    $outf = fopen($tmp, $part === 0 ? 'wb' : 'ab');
    if (!$in || !$outf) fail('Could not save the file', 500);
    $n = stream_copy_to_stream($in, $outf, CHUNK_MAX + 1);
    fclose($in); fclose($outf);
    if ($n > CHUNK_MAX) { @unlink($tmp); fail('Piece too large', 413); }
    clearstatcache(true, $tmp);
    $size = filesize($tmp);
    if ($size > MAX_FILE) { @unlink($tmp); fail('File too large', 413); }
    if ($part < $parts - 1) out(['ok' => true, 'received' => $size]);
    if ($kind === 'rom') {   // a game must be a real zip
        $fh = fopen($tmp, 'rb'); $sig = fread($fh, 2); fclose($fh);
        if ($sig !== "PK") { @unlink($tmp); fail('That isn\'t a zip file'); }
    }
    rename($tmp, $final);
    $now = round(microtime(true) * 1000);
    lib_update($store, function ($lib) use ($id, $kind, $size, $now) {
        $g = $lib['games'][$id] ?? ['id' => $id];
        if ($kind === 'rom')   { $g['size'] = $size; $g['hasRom'] = true; }
        if ($kind === 'art')   { $g['hasArt'] = true; $g['artAt'] = $g['artAt'] ?? $now; }
        if ($kind === 'state') { $g['hasState'] = true; $g['stateAt'] = $now; $g['stateSize'] = $size; }
        $lib['games'][$id] = $g;
        return $lib;
    });
    out(['ok' => true, 'size' => $size]);

case 'file':            // download a game, picture or save
    $kind = $_GET['kind'] ?? ''; $id = $_GET['id'] ?? '';
    if (!isset(KINDS[$kind]) || !valid_id($id)) fail('Bad request');
    $path = "$store/$kind/$id" . KINDS[$kind];
    if (!is_file($path)) fail('Not found', 404);
    $types = ['rom' => 'application/zip', 'art' => 'image/jpeg', 'state' => 'application/octet-stream'];
    header('Content-Type: ' . $types[$kind]);
    header('Content-Length: ' . filesize($path));
    if ($kind === 'art') header('Cache-Control: private, max-age=31536000');
    while (ob_get_level()) ob_end_clean();
    readfile($path);
    exit;

default:
    fail('Unknown action', 404);
}
