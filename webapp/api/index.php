<?php
/**
 * DeedsPlan API Router — chạy trên shared hosting PHP (InfinityFree).
 * Endpoint gốc: /api/index.php?route=<path>  (hoặc rewrite /api/<path>)
 * Mọi response là JSON. Auth: Authorization: Bearer <JWT>.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // production: không lộ stack trace

header('Content-Type: application/json; charset=utf-8');

$CFG = require __DIR__ . '/config.php';

// ---------- CORS ----------
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $CFG['cors_origins'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 600');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

// ---------- Helpers chung ----------
function body_json(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}
function out($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail(string $msg, int $code = 400): never { out(['error' => $msg], $code); }

require __DIR__ . '/lib/db.php';       // db() -> PDO singleton
require __DIR__ . '/lib/jwt.php';      // jwt_encode / jwt_decode
require __DIR__ . '/lib/security.php'; // Cloudflare verify, CSP headers, rate-limit, input sanitizers
require __DIR__ . '/lib/auth.php';     // current_user(), require_role()

// ---------- Cloudflare front-door enforcement (production only) ----------
enforce_cloudflare_only($CFG);

// ---------- Security headers (defense-in-depth song song với CF dashboard) ----------
emit_security_headers();

// ---------- Route ----------
$route = $_GET['route'] ?? parse_route();
$route = trim((string)$route, '/');

switch ($route) {
    case '':
    case 'health':
        out(['ok' => true, 'service' => 'DeedsPlan API', 'v' => '1.0.0', 'time' => gmdate('c')]);
        break;

    case 'auth/register':
    case 'auth/login':
    case 'auth/me':
        require __DIR__ . '/routes/auth.php';
        handle($route);
        break;

    case 'projects':                 // GET list | POST create
    case 'project':                  // GET ?id= full graph | DELETE ?id=
    case 'graph':                    // PUT whole graph (tasks/links/resources/assignments)
    case 'baseline':                 // POST save snapshot | GET list
    case 'invite':                   // POST gen code | POST join by code
    case 'members':                  // GET list | DELETE kick
        require __DIR__ . '/routes/projects.php';
        handle($route);
        break;

    case 'reports/evm':              // GET ?id=project → PV/EV/AC/EAC/ETC...
        require __DIR__ . '/routes/reports.php';
        handle($route);
        break;

    default:
        fail('Không tìm thấy route: ' . $route, 404);
}

/** Hỗ trợ pretty URL /api/xxx nếu có .htaccess rewrite */
function parse_route(): string {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (preg_match('#/api/(.+)$#', $uri, $m)) return $m[1];
    return '';
}
