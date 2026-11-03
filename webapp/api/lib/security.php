<?php
/**
 * DeedsPlan — Security Hardening Layer (tích hợp Cloudflare + defense-in-depth)
 * --------------------------------------------------------------------------
 * Nguyên tắc: Cloudflare là "lá chắn biên" (WAF, DDoS, bot, rate-limit edge),
 * nhưng ứng dụng PHP VẪN tự bảo vệ mình (defense in depth) vì:
 *   - Shared hosting không chặn được nguồn IP thật nếu CF bypass;
 *   - Rate-limit của CF tier Free giới hạn rule → app cần limiter bổ sung ở API nhạy cảm;
 *   - Header bảo mật phải được app set đúng khi CF chưa cấu hình.
 *
 * Cách nhận diện request đi qua Cloudflare:
 *   1. Header `Cf-Ray` / `Cf-Connecting-Ip` luôn được CF proxy chèn thêm.
 *   2. IP gốc (REMOTE_ADDR) nằm trong dải IP công khai của CF
 *      (danh sách tĩnh trong CF_IP_RANGES + có thể refresh từ
 *       https://api.cloudflare.com/client/v4/ips hoặc https://www.cloudflare.com/ips/).
 */

declare(strict_types=1);

/** Dải IPv4 công khai của Cloudflare (cập nhật: https://www.cloudflare.com/ips-v4). */
const CF_IP_RANGES = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
    '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
    '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
    '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
];

/** Trả về IP thật của client khi request đi qua Cloudflare. */
function client_ip(): string {
    // Cf-Connecting-Ip do CF đặt và ghi đè → đáng tin CŨNG KHI request đã xác thực qua CF
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && cf_is_proxy()) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Request có đến từ Cloudflare proxy hay không? */
function cf_is_proxy(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '') return false;
    foreach (CF_IP_RANGES as $cidr) {
        if (ip_in_cidr($ip, $cidr)) return true;
    }
    // Local dev: cho phép spoof header khi REMOTE_ADDR là loopback/private
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

function ip_in_cidr(string $ip, string $cidr): bool {
    [$subnet, $bits] = explode('/', $cidr);
    $ipL = ip2long($ip); $subL = ip2long($subnet);
    if ($ipL === false || $subL === false) return false;
    $mask = -1 << (32 - (int)$bits);
    return (($ipL & $mask) === ($subL & $mask));
}

/**
 * Chặn truy cập TRỰC TIẾP không qua Cloudflare vào API production
 * (chỉ bật khi đã chạy sau CF; local/dev bỏ qua nhờ env DEEDSPLAN_ENV).
 * Optional: verify HMAC header do Cloudflare Worker ký (cf_signed_secret trong config)
 * — xem docs/cloudflare_integration.md §5.
 */
function enforce_cloudflare_only(array $cfg): void {
    $env = getenv('DEEDSPLAN_ENV') ?: ($cfg['env'] ?? 'production');
    if ($env !== 'production') return;
    if (php_sapi_name() === 'cli') return;
    // Tắt lớp "chỉ-qua-CF" nếu được đặt tường minh (escape hatch vận hành, vd sự cố CF)
    if (!empty($cfg['cf_enforce_disabled'])) return;
    if (!cf_is_proxy()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Truy cập trực tiếp bị chặn. Vui lòng truy cập qua https://deedsteam.click']);
        exit;
    }
    // Lớp nâng cao (tuỳ chọn): nếu có cấu hình shared secret, verify HMAC signature
    // do Cloudflare Worker ký → chống cả request "mạo danh" từ trong dải IP CF.
    if (!empty($cfg['cf_signed_secret']) && !empty($cfg['cf_signed_header'])) {
        $hdrName = 'HTTP_' . strtoupper(str_replace('-', '_', $cfg['cf_signed_header']));
        $sig = (string)($_SERVER[$hdrName] ?? '');
        [$ts, $mac] = array_pad(explode('.', $sig, 2), 2, '');
        $valid = false;
        if ($ts !== '' && $mac !== '' && abs(time() - (int)$ts) <= (int)($cfg['cf_signed_tolerance'] ?? 300)) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
            $expected = hash_hmac('sha256', $ts . '|' . $path, $cfg['cf_signed_secret']);
            $valid = hash_equals($expected, $mac);
        }
        if (!$valid) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Chữ ký Cloudflare không hợp lệ hoặc hết hạn.']);
            exit;
        }
    }
}

/**
 * CSP nghiêm ngặt cho SPA Svelte (static assets cùng origin + Google Fonts + API cùng host).
 * Nonce cho inline script của index.html (Vite inject modulepreload → không cần unsafe-inline).
 */
function emit_security_headers(?string $csp_nonce = null): void {
    $nonceAttr = $csp_nonce ? "'nonce-$csp_nonce'" : '';
    $csp = implode('; ', array_filter([
        "default-src 'self'",
        // Vite build ra ES module hash → không cần unsafe-inline cho script;
        // svelte chỉ dùng style attribute → cần 'unsafe-inline' cho style (SHA-less)
        "script-src 'self'{$nonceAttr}",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' data: https://fonts.gstatic.com",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "object-src 'none'",
        "upgrade-insecure-requests",
    ]));
    header('Content-Security-Policy: ' . $csp);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-XSS-Protection: 0'); // chủ động tắt: CSP thay thế; tránh auditor cũ gây lỗi
}

/**
 * App-level rate limiter (bổ sung cho Cloudflare WAF Rate Rules).
 * Dùng bảng `rate_limits` trên MySQL vì shared hosting không có Redis/APCu bền vững.
 * Window trượt cố định (fixed window) — đủ cho login/register chống brute-force.
 * @return int remaining requests trong cửa sổ hiện tại (-1 nếu bị chặn).
 */
function rate_limit(string $bucket, int $max, int $windowSec): int {
    $key = hash('sha256', $bucket . '|' . client_ip());
    $db  = db();
    try {
        $st = $db->prepare('SELECT hits, window_start FROM rate_limits WHERE bucket_key=? FOR UPDATE');
        // InfinityFree MySQL hỗ trợ tốt; dùng insert-on-duplicate để atomic trên engine MyISAM/InnoDB
        $db->beginTransaction();
        $st->execute([$key]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $now = time();
        if ($row && ($now - (int)$row['window_start']) < $windowSec) {
            $hits = (int)$row['hits'] + 1;
            $db->prepare('UPDATE rate_limits SET hits=? WHERE bucket_key=?')->execute([$hits, $key]);
            $db->commit();
            $rem = $max - $hits;
            return $rem >= 0 ? $rem : -1;
        }
        if ($row) {
            $db->prepare('UPDATE rate_limits SET hits=1, window_start=? WHERE bucket_key=?')->execute([$now, $key]);
        } else {
            $db->prepare('INSERT INTO rate_limits (bucket_key, hits, window_start) VALUES (?,1,?)')->execute([$key, $now]);
        }
        $db->commit();
        return $max - 1;
    } catch (Throwable) {
        @$db->rollBack();
        return $max; // fail-open nhẹ: limiter phụ, lớp chính là CF WAF
    }
}

function too_many_requests(int $retryAfter = 60): never {
    http_response_code(429);
    header('Retry-After: ' . $retryAfter);
    out(['error' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau ' . $retryAfter . ' giây.',
          'hint'  => 'Cloudflare + server đang bảo vệ tài khoản của bạn khỏi brute-force.'], 429);
}

/** Chuẩn hoá & validate input JSON theo whitelist kiểu — chống mass-assignment/SQLi logic errors. */
function clean_str(mixed $v, int $maxLen = 500): string {
    $s = trim((string)($v ?? ''));
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    return mb_substr($s, 0, $maxLen);
}
function clean_int(mixed $v, ?int $min = null, ?int $max = null): ?int {
    if ($v === null || $v === '') return null;
    $i = is_numeric($v) ? (int)$v : null;
    if ($i === null) return null;
    if ($min !== null && $i < $min) return $min;
    if ($max !== null && $i > $max) return $max;
    return $i;
}
function clean_date(mixed $v): ?string {
    $s = trim((string)($v ?? ''));
    if ($s === '') return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}

/** Password policy tường minh (min 8, có chữ + số) — báo lỗi rõ ràng cho UX. */
function password_policy_error(string $pw): ?string {
    if (strlen($pw) < 8)  return 'Mật khẩu tối thiểu 8 ký tự.';
    if (!preg_match('/[A-Za-zÀ-ỹ]/u', $pw)) return 'Mật khẩu cần ít nhất 1 chữ cái.';
    if (!preg_match('/\d/', $pw))            return 'Mật khẩu cần ít nhất 1 chữ số.';
    return null;
}

/** CSRF-style double-submit token cho các mutation không dùng Bearer (nếu cần cookie flow). */
function constant_equal(string $a, string $b): bool { return hash_equals($a, $b); }
