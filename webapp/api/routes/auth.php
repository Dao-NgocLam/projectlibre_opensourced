<?php
/** Auth: register / login / me */
function handle(string $route): void {
    global $CFG;

    if ($route === 'auth/register') {
        $b = body_json();
        $email = strtolower(trim((string)($b['email'] ?? '')));
        $username = trim((string)($b['username'] ?? ''));
        $password = (string)($b['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Email không hợp lệ.');
        if (mb_strlen($username) < 2 || mb_strlen($username) > 60) fail('Tên đăng nhập 2–60 ký tự.');
        if (strlen($password) < 8) fail('Mật khẩu tối thiểu 8 ký tự.');
        $st = db()->prepare('SELECT id FROM users WHERE email=?');
        $st->execute([$email]);
        if ($st->fetch()) fail('Email đã được đăng ký.', 409);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db()->prepare('INSERT INTO users (email,username,password_hash) VALUES (?,?,?)')
            ->execute([$email, $username, $hash]);
        $uid = (int)db()->lastInsertId();
        auditGlobal($uid, 'user.register');
        out(['token' => jwt_encode(['uid' => $uid]),
             'user' => ['id' => $uid, 'email' => $email, 'username' => $username]], 201);
    }

    if ($route === 'auth/login') {
        $b = body_json();
        $email = strtolower(trim((string)($b['email'] ?? '')));
        $password = (string)($b['password'] ?? '');
        $st = db()->prepare('SELECT * FROM users WHERE email=?');
        $st->execute([$email]);
        $u = $st->fetch();
        if (!$u || !password_verify($password, $u['password_hash'])) fail('Sai email hoặc mật khẩu.', 401);
        // Transparent upgrade của hash cũ
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT))
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$u['id']]);
        out(['token' => jwt_encode(['uid' => (int)$u['id']]),
             'user' => ['id' => (int)$u['id'], 'email' => $u['email'], 'username' => $u['username']]]);
    }

    if ($route === 'auth/me') {
        $u = require_user();
        out(['user' => $u]);
    }

    fail('Route auth chưa xử lý: ' . $route, 404);
}
function auditGlobal(int $uid, string $action): void {
    try { error_log("[deedsplan] user#$uid $action " . gmdate('c')); } catch (Throwable) {}
}
