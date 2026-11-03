<?php
/** Auth: register / login / me */
function handle(string $route): void {
    global $CFG;

    if ($route === 'auth/register') {
        // Lớp 2 (sau Cloudflare Rate Rules): chặn đăng ký hàng loạt từ cùng IP
        if (rate_limit('register', 5, 3600) < 0) too_many_requests(3600);
        $b = body_json();
        $email = strtolower(clean_str($b['email'] ?? '', 190));
        $username = clean_str($b['username'] ?? '', 60);
        $password = (string)($b['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Email không hợp lệ.');
        if (mb_strlen($username) < 2 || mb_strlen($username) > 60) fail('Tên đăng nhập 2–60 ký tự.');
        if ($err = password_policy_error($password)) fail($err);
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
        // Lớp 2 sau Cloudflare: chống brute-force theo IP + khoá tài khoản tạm thời
        if (rate_limit('login', 10, 300) < 0) too_many_requests(300);
        $b = body_json();
        $email = strtolower(clean_str($b['email'] ?? '', 190));
        $password = (string)($b['password'] ?? '');
        $st = db()->prepare('SELECT * FROM users WHERE email=?');
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u && !empty($u['locked_until']) && strtotime((string)$u['locked_until']) > time()) {
            fail('Tài khoản đang bị khoá tạm do nhập sai nhiều lần. Thử lại sau vài phút.', 429);
        }
        if (!$u || !password_verify($password, $u['password_hash'])) {
            if ($u) { // đếm fail trên server → tự khoá 15 phút sau 8 lần sai
                $fails = (int)$u['failed_logins'] + 1;
                $lock  = $fails >= 8 ? date('Y-m-d H:i:s', time() + 900) : null;
                db()->prepare('UPDATE users SET failed_logins=?, locked_until=? WHERE id=?')
                    ->execute([$fails, $lock, $u['id']]);
            }
            fail('Sai email hoặc mật khẩu.', 401);
        }
        // Transparent upgrade của hash cũ
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT))
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        db()->prepare('UPDATE users SET last_login_at=NOW(), failed_logins=0, locked_until=NULL WHERE id=?')->execute([$u['id']]);
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
