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
];
