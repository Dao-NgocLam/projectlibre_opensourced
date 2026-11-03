# 🚀 Hướng dẫn triển khai DeedsPlan lên InfinityFree — domain `deedsteam.click`

> Chạy được trên gói **miễn phí** InfinityFree: PHP 8.x + MySQL + HTTPS + custom domain.
> Không cần Node.js trên server (Svelte đã build ra file tĩnh).

---

## Bước 0 — Chuẩn bị

| Cần có | Ghi chú |
|---|---|
| Tài khoản [my.infinityfree.com](https://my.infinityfree.com) | Miễn phí, không thẻ tín dụng |
| Quyền quản lý DNS của `deedsteam.click` | Mua tại Namecheap/Cloudflare/Porkbun… |
| FTP client (FileZilla) *hoặc* Online File Manager | OFM đủ dùng nếu upload < 200 file |
| Máy tính cài Node.js 18+ (chỉ để build) | Server không cần Node |

---

## Bước 1 — Build frontend

```bash
cd webapp/client
npm install --no-fund --no-audit
npm run build          # → webapp/client/dist/
```

Kiểm tra nhanh: `npx serve dist` mở `http://localhost:3000` thấy trang login là đạt.

## Bước 2 — Tạo website & gắn domain trên InfinityFree

1. **Create Account** → xác nhận email.
2. **Create Website**:
   - Domain: chọn **Enter your own domain** → `deedsteam.click` (+ `www.deedsteam.click`).
   - Chọn region server gần người dùng nhất (Asia thường là `us-` nhưng latency VN vẫn ổn; nếu có `as-` thì chọn).
   - Password FTP/control panel → **ghi lại**.
3. Hệ thống báo thiếu DNS → vào nhà cung cấp domain, thêm records:
   - `A` / `ALIAS(CNAME)` cho root theo đúng hướng dẫn mà InfinityFree hiển thị
     (thường: nameserver `ns1.epizy.com`, `ns2.epizy.com` **hoặc** A record trỏ IP server).
     *Khuyên dùng:* đổi nameservers sang InfinityFree để họ tự quản lý record.
   - Chờ DNS lan toả 5 phút – 24h (kiểm tra: `dig deedsteam.click +short`).
4. Trong **Control Panel → Domains**, bật **Free SSL Certificate** (Let's Encrypt) cho domain, bấm Issue. Nếu lỗi "not verified" → chờ DNS xong rồi retry.

## Bước 3 — Tạo MySQL database

1. Control Panel (VistaPanel) → **MySQL Databases** → Create Database.
2. Ghi lại **4 giá trị** (sẽ dùng ở bước 4):
   ```
   MySQL Hostname : sql3xx.infinityfree.com   (xem mục "Database Details")
   MySQL Username : if0_12345678
   MySQL Password : ********
   MySQL Database : if0_12345678_deedsplan
   ```
3. Vào **phpMyAdmin** → chọn DB vừa tạo → tab **Import** → upload `webapp/api/db/schema.sql` → Go.
   *(Hoặc chạy CLI nếu quen: mysql -h sql3xx... -u if0_... -p if0_... < schema.sql)*

## Bước 4 — Cấu hình API

Mở `webapp/api/config.php`, điền đúng thông tin bước 3, và sinh JWT secret mới:

```php
'host' => 'sql3xx.infinityfree.com',
'name' => 'if0_12345678_deedsplan',
'user' => 'if0_12345678',
'pass' => 'MẬT_KHẨU_DB_THẬT',
'jwt_secret' => '<dán kết quả lệnh dưới đây>',
```

```bash
php -r "echo bin2hex(random_bytes(32));"   # hoặc openssl rand -hex 32
```

> ⚠️ **Không commit config thật lên repo công khai.** Sau khi upload, đổi pass DB một lần trong phpMyAdmin rồi cập nhật config.php trên server.

## Bước 5 — Upload file

Vào **Online File Manager → htdocs/** (hoặc FTP root = `htdocs`):

1. **Xoá** `index2.html` mặc định.
2. Upload **toàn bộ nội dung bên trong** `client/dist/` vào thẳng `htdocs/`
   (`index.html`, `assets/…`, `favicon.svg`…).
3. Upload thư mục `webapp/api/` → thành `htdocs/api/`.
4. Upload `webapp/.htaccess` → thành `htdocs/.htaccess`
   *(OFM: bật "Show hidden files"; FTP FileZilla: Settings → Force dotfile transfer).*

Cấu trúc cuối phải giống:

```
htdocs/
├── .htaccess
├── index.html            ← SPA Svelte (đã build)
├── favicon.svg
├── assets/  (js/css hashed bởi Vite)
└── api/
    ├── index.php  config.php
    ├── db/schema.sql
    ├── lib/{db,jwt,auth}.php
    └── routes/{auth,projects,reports}.php
```

## Bước 6 — Kiểm tra (smoke test)

| URL kỳ vọng | Kết quả đạt |
|---|---|
| `https://deedsteam.click/api/index.php?route=health` | `{"ok":true,"service":"DeedsPlan API",...}` |
| `https://deedsteam.click/` | Trang đăng nhập DeedsPlan, logo teal |
| Đăng ký tài khoản → tạo dự án → reload | Dự án vẫn còn (DB ghi OK) |
| F12 → Network: PUT `graph` khi sửa task | 200 + autosave pill "Đã lưu" |

Lỗi thường gặp:
- **500 trắng** → xem `htdocs/api` có đúng đường dẫn không; kiểm tra config DB; bật tạm `ini_set('display_errors','1')` để đọc message, nhớ tắt sau đó.
- **404 route /api/xxx pretty-url** → `.htaccess` chưa được upload (dotfile); fallback luôn chạy được với dạng `?route=`.
- **CORS error** → đảm bảo truy cập bằng `https://deedsteam.click` trùng origin đã khai trong `cors_origins`.
- **SSL not issued** → DNS chưa propagate; chờ rồi bấm Issue lại.

## Bước 7 — Vận hành & giới hạn InfinityFree (đã thiết kế né trước)

| Giới hạn free host | Cách DeedsPlan thích ứng |
|---|---|
| Không cron job | Không cần — mọi tính năng user-triggered |
| Không WebSocket | Autosave debounce + online/offline event (store.js) |
| Max entry process ~ vài chục | Graph PUT gộp 1 request/dự án, payload cap 2MB |
| CPU seconds limit / ngày | Engine CPM chạy ở **browser**, server chỉ CRUD nhẹ |
| Free SSL hết hạn 90 ngày | Auto-renew trong control panel; kiểm tra mỗi quý |
| Backup thủ công | phpMyAdmin → Export hằng tuần (đặt lịch reminder) |

**Khuyến nghị nâng cấp khi team > 5 người:** gói Premium InfinityFree hoặc VPS nhỏ (~$4/tháng) — cùng codebase này chạy y nguyên (chỉ đổi `config.php`).

## Bước 8 — Development workflow (khi chỉnh sửa code)

```bash
# Terminal 1 — PHP local (cần PHP 8 + MySQL local, import schema.sql)
cd webapp && php -S localhost:8080 -t .
# Terminal 2 — Svelte dev (proxy /api → :8080 qua vite.config.js)
cd webapp/client && npm run dev        # http://localhost:5173
# Khi publish:
cd webapp/client && npm run build && rsync -av --delete dist/ ../deploy-site/  # rồi upload deploy-site
```

---

✅ Xong! `https://deedsteam.click` giờ là ProjectLibre-online của riêng bạn với MySQL làm nguồn sự thật.

---

## Bước 9 — ☁️ Bật Cloudflare trước khi go-live (bắt buộc)

Toàn bộ chi tiết + checklist: **`webapp/docs/cloudflare_integration.md`**. Tóm tắt thứ tự an toàn:

1. **Đổi nameservers** của `deedsteam.click` tại registrar sang Cloudflare; bật **orange cloud (Proxied)** cho `@` và `www`.
2. Dashboard CF: SSL = **Full (strict)**, Always-HTTPS ✅, TLS ≥ 1.2 ✅, HSTS ✅, Bot Fight Mode ✅, Rocket Loader ❌ (tắt để Svelte/Vite ES modules chạy đúng).
3. Tạo **Cache Rule**: `assets/*` cache 1 năm; `/api/*` bypass.
4. Nhập **5 WAF custom rules** copy-paste từ `deploy/cloudflare/waf-rules.md` (SQLi/XSS, rate-limit `/api/auth/*`, chặn scanner, region, file nhạy cảm).
5. Import lại `api/db/schema.sql` vào phpMyAdmin (bảng mới `rate_limits`, cột mới `failed_logins`/`locked_until`) — app limiter hoạt động ngay sau khi upload `api/lib/security.php`.
6. (Nâng cao, khuyến nghị) Deploy Worker ký request `deploy/cloudflare/worker-signature.js` trên subdomain gốc, điền `cf_signed_secret` vào `config.php` **cùng thời điểm** — origin sẽ 403 mọi truy cập trực tiếp không qua Cloudflare, kể cả khi IP InfinityFree bị lộ.
7. Chạy 5 lệnh xác minh §7 tài liệu integration (health-check, security headers, bypass test, SQLi test, brute-force test).

> ⚠️ Lưu ý dev local: đặt `DEEDSPLAN_ENV=development` (hoặc `'env' => 'development'` tạm trong config.php) và comment khối `CLOUDFLARE FRONT-DOOR` trong `.htaccess` khi test bằng `php -S`, vì máy local không đi qua dải IP Cloudflare.

## Bước 10 — Vận hành an toàn định kỳ

| Tần suất | Việc |
|---|---|
| Tuần | Xem CF Analytics → Events: rule nào bắn nhiều, có block nhầm không |
| Tháng | Export backup DB (phpMyAdmin), rà `audit_log` các action bất thường |
| Quý | Đổi `jwt_secret` + `cf_signed_secret` (Worker & config.php cùng lúc), kiểm tra danh sách IP CF https://www.cloudflare.com/ips-v4 |
| Khi bị tấn công | Bật Security Level "I'm Under Attack", siết Rate Rules, khoá account hàng loạt qua `UPDATE users SET locked_until=NOW()+INTERVAL 1 HOUR WHERE ...` |
