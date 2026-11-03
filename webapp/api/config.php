<?php
/**
 * DeedsPlan API — cấu hình (điền thông tin từ VistaPanel/MySQL InfinityFree)
 * File này KHÔNG commit mật khẩu thật. Sau khi upload, đổi lại secret.
 */
return [
    'db' => [
        // InfinityFree: vào MySQL Manager → "Database Details"
        'host' => 'sql300.infinityfree.com',   // ví dụ — thay bằng host của bạn (sqlXXX.epizy.com)
        'name' => 'if0_12345678_deedsplan',    // thay bằng DB name
        'user' => 'if0_12345678',              // thay bằng DB user
        'pass' => 'THAY_MAT_KHAU_DB',          // thay bằng DB pass
        'charset' => 'utf8mb4',
    ],
    // JWT secret: chuỗi ngẫu nhiên ≥ 32 ký tự. Sinh lại bằng: php -r "echo bin2hex(random_bytes(32));"
    'jwt_secret' => 'THAY_BANG_CHUOI_NGAU_NHIEN_32BYTES_HEX',
    'jwt_ttl'    => 60 * 60 * 24 * 7,          // 7 ngày
    'cors_origins' => ['https://deedsteam.click', 'http://deedsteam.click', 'http://localhost:5173'],
    'max_graph_bytes' => 2_000_000,             // ~2MB payload PUT graph

    // ---------- Cloudflare integration ----------
    // 'production' → bật enforce_cloudflare_only (chặn truy cập trực tiếp không qua CF).
    // Đặt 'development' khi test local; hoặc export DEEDSPLAN_ENV=development.
    'env' => 'production',

    // Shared secret giữa Cloudflare Worker/Signature và origin (Authenticated Origin Pulls
    // dạng HMAC header). Để trống = tắt kiểm tra signature (vẫn verify IP dải CF).
    // Sinh: openssl rand -hex 32  → dán cả vào Worker lẫn đây. Xem docs/cloudflare_integration.md §6.
    'cf_signed_header' => '',        // tên header, ví dụ 'X-Deeds-Signature'
    'cf_signed_secret' => '',        // chuỗi hex 32 bytes dùng chung với Worker
    'cf_signed_tolerance' => 300,    // giây dung sai timestamp của signature
];
