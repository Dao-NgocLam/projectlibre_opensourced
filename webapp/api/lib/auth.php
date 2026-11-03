<?php
/** Middleware xác thực & phân quyền theo project. */
function current_user(): ?array {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($h === '' && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $k => $v)
            if (strcasecmp($k, 'Authorization') === 0) { $h = $v; break; }
    }
    if (!preg_match('/Bearer\s+(\S+)/', $h, $m)) return null;
    $claims = jwt_decode($m[1]);
    if (!$claims || empty($claims['uid'])) return null;
    $st = db()->prepare('SELECT id,email,username,locale FROM users WHERE id=?');
    $st->execute([$claims['uid']]);
    return $st->fetch() ?: null;
}
function require_user(): array {
    $u = current_user();
    if (!$u) fail('Chưa đăng nhập hoặc phiên hết hạn.', 401);
    return $u;
}
/** Trả về role của user trong project: owner/editor/viewer — 403 nếu không có quyền. */
function require_role(int $projectId, string $min = 'viewer'): string {
    $u = require_user();
    $st = db()->prepare(
        'SELECT p.owner_id, m.role FROM projects p
         LEFT JOIN project_members m ON m.project_id=p.id AND m.user_id=?
         WHERE p.id=?'
    );
    $st->execute([$u['id'], $projectId]);
    $r = $st->fetch();
    if (!$r) fail('Không tìm thấy dự án.', 404);
    $role = ((int)$r['owner_id'] === (int)$u['id']) ? 'owner' : ($r['role'] ?? null);
    if (!$role) fail('Bạn không có quyền truy cập dự án này.', 403);
    $rank = ['viewer' => 0, 'editor' => 1, 'owner' => 2];
    if ($rank[$role] < $rank[$min]) fail('Cần quyền ' . strtoupper($min) . ' cho thao tác này.', 403);
    return $role;
}
function audit(int $projectId, ?int $userId, string $action, ?string $entity = null, ?int $entityUid = null, ?array $diff = null): void {
    $st = db()->prepare('INSERT INTO audit_log (project_id,user_id,action,entity,entity_uid,diff_json) VALUES (?,?,?,?,?,?)');
    $st->execute([$projectId, $userId, $action, $entity, $entityUid, $diff ? json_encode($diff, JSON_UNESCAPED_UNICODE) : null]);
}
