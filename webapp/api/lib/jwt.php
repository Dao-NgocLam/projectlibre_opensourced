<?php
/** JWT HS256 tự chứa (không dependency — shared hosting không có composer). */
function b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function b64url_dec(string $s): string|false {
    return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
}
function jwt_encode(array $payload): string {
    global $CFG;
    $hdr = b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $now = time();
    $payload = array_merge(['iat' => $now, 'exp' => $now + $CFG['jwt_ttl']], $payload);
    $pl = b64url(json_encode($payload));
    $sig = b64url(hash_hmac('sha256', "$hdr.$pl", $CFG['jwt_secret'], true));
    return "$hdr.$pl.$sig";
}
function jwt_decode(string $jwt): ?array {
    global $CFG;
    $p = explode('.', $jwt);
    if (count($p) !== 3) return null;
    [$hdr, $pl, $sig] = $p;
    $check = b64url(hash_hmac('sha256', "$hdr.$pl", $CFG['jwt_secret'], true));
    if (!hash_equals($check, $sig)) return null;
    $data = json_decode((string)b64url_dec($pl), true);
    if (!is_array($data) || ($data['exp'] ?? 0) < time()) return null;
    return $data;
}
