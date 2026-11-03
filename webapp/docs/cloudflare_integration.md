# ☁️ Tích hợp Cloudflare — Bảo mật & An toàn cho DeedsPlan

> Domain: **deedsteam.click** · Origin: **InfinityFree** (shared hosting)
> Mô hình: **Defense-in-depth** — Cloudflare đứng trước, ứng dụng tự bảo vệ lớp trong.

```
User ──HTTPS/TLS1.3──▶ [Cloudflare Edge]                [InfinityFree origin]
                        │ DNS (orange cloud)             Apache .htaccess  ◄─ R0: chỉ nhận IP dải CF
                        │ CDN + Always Use HTTPS         PHP API           ◄─ R1: enforce_cloudflare_only()
                        │ WAF custom rules (R1–R5)       JWT + RBAC        ◄─ R2: rate-limit MySQL + lock account
                        │ Bot Fight Mode / Managed Ch.   CSP/headers      ◄─ R3: emit_security_headers()
                        │ Rate Rules /auth/*             Signed requests   ◄─ R4: verify HMAC X-Deeds-Signature
                        │ Authenticated Origin Pulls*    audit_log         ◄─ R5: mọi mutation ghi vết
                        └─ Worker ký signature ─────────▶
   * AOP cần mTLS client-cert — InfinityFree không hỗ trợ → thay bằng Worker-signed header (R4).
```

---

## 1. Vì sao Cloudflare là bắt buộc với InfinityFree?

| Rủi ro shared hosting free | Cloudflare giải quyết |
|---|---|
| IP origin công khai → bị scan/DDoS trực tiếp | Orange-cloud proxy ẩn IP; `enforce_cloudflare_only` chặn bypass |
| Không có WAF/tường lửa application | 5 custom rules + managed rules miễn phí |
| Không giới hạn request (login brute-force thoải mái) | Rate Rules `/api/auth/*` 20 req/60s |
| SSL tự ký/renew thủ công dễ hở | Universal SSL tự renew + HSTS + TLS 1.2 minimum |
| Bot/scraper tiêu quota CPU/hosting suspend | Bot Fight Mode + Security Level |
| Downtime khi host quá tải | Cache static assets ở edge gần người dùng |

---

## 2. Bước 1 — Chuyển DNS sang Cloudflare (5 phút)

1. Đăng ký/đăng nhập [dash.cloudflare.com](https://dash.cloudflare.com) → **Add a Site** → nhập `deedsteam.click` → chọn **Free plan**.
2. Cloudflare quét record hiện có → xác nhận đủ `A/@`, `CNAME/www`.
3. **Bật orange cloud (Proxied)** cho `@` và `www`. *Không bao giờ để record API grey-cloud.*
4. Cloudflare trả về 2 nameservers (vd: `ana.ns.cloudflare.com`, `brad.ns.cloudflare.com`) → vào nơi mua domain (Namecheap/Porkbun/…) đổi **nameservers** sang 2 giá trị đó.
5. Chờ lan toả: `dig NS deedsteam.click +short` thấy nameserver CF là xong.
6. Trong InfinityFree → Control Panel → Domains: domain vẫn giữ nguyên (CF trỏ về qua CNAME/A mà CF quản lý); nếu IF báo "domain pending", bật **SSL = Full (strict)** ở CF rồi chờ verify lại.

## 3. Bước 2 — Bật các cấu hình nền tảng (miễn phí)

Vào dashboard theo bảng trong [`deploy/cloudflare/waf-rules.md`](./cloudflare/waf-rules.md):

- **SSL/TLS → Overview**: chế độ **Full (strict)**. *(IF có chứng thư Let's Encrypt cho sub gốc; nếu full-strict lỗi, tạm để Full và renew cert trên IF.)*
- **SSL/TLS → Edge Certificates**: *Always Use HTTPS* ✅ · *Minimum TLS 1.2* ✅ · *HSTS* ✅ (max-age 1 năm, include subdomains, **chưa bật preload** cho tới khi chắc chắn không quay về HTTP).
- **Speed → Optimization**: Auto-minify JS/CSS/HTML ✅ · Brotli ✅.
- **Caching → Cache Rules**: tạo rule `assets/*` → cache eligibility **Cache everything**, Edge TTL **1 year** (an toàn vì Vite phát hành file theo content-hash). API `/api/*` → **Bypass cache**.
- **Networking**: *Rocket Loader* ❌ (tắt — gây lỗi ES modules của Svelte/Vite), *HTTP/2 + HTTP/3* ✅.
- **Bot → Bot Fight Mode** ✅.

## 4. Bước 3 — WAF Custom Rules

Copy 5 rule R1→R5 trong [`waf-rules.md`](./cloudflare/waf-rules.md) vào **Security → WAF → Create rule**. Đây là lớp chặn SQLi/XSS/scanner **trước khi** request chạm PHP; app vẫn có sanitizer riêng (`clean_str/clean_int/clean_date`).

## 5. Bước 4 — Chống bypass origin (phần cứng nhất)

Kẻ tấn công có thể tra lịch sử DNS cũ để tìm IP thật của InfinityFree và gọi thẳng, bỏ qua CF. Ba lớp phòng thủ đã cài sẵn trong repo:

1. **Apache** (`.htaccess`): khối `CLOUDFLARE FRONT-DOOR` trả 403 cho mọi `REMOTE_ADDR` ngoài dải IP CF.
2. **PHP** (`api/lib/security.php::enforce_cloudflare_only()`): kiểm tra lại bằng `CF_IP_RANGES` khi `env=production`.
3. **Worker ký request** (`deploy/cloudflare/worker-signature.js`): mỗi request đi qua CF được gắn header
   `X-Deeds-Signature: <timestamp>.<hmac_sha256(timestamp|path, secret)>`;
   origin verify với `cf_signed_secret` trong `api/config.php` (dung sai 300 s).
   → Kể cả giả mạo từ đúng dải IP CF cũng không lọt nếu thiếu chữ ký.

### Cài Worker (10 phút)
```text
1. dash.cloudflare.com → Workers & Pages → Create worker → tên deeds-sign → Deploy mẫu.
2. Paste toàn bộ worker-signature.js; thay SECRET = output của `openssl rand -hex 32`.
3. Cùng chuỗi đó điền vào api/config.php:
     'cf_signed_header' => 'X-Deeds-Signature',
     'cf_signed_secret' => '<chuỗi hex>',
4. Workers & Pages → deeds-sign → Settings → Triggers → Custom Domains →
   thêm deedsteam.click với route *  (Worker sẽ đứng trước toàn bộ zone).
5. Upload lại api/config.php + api/lib/security.php lên htdocs.
```
> **Thứ tự quan trọng:** triển khai Worker **cùng lúc** bật secret ở origin. Nếu bật `cf_signed_secret` mà chưa chạy Worker → toàn bộ API trả 403. Kiểm thử bằng `curl https://deedsteam.click/api/health` phải thấy JSON `{"ok":true,...}`.

## 6. Bước 5 — Lớp ứng dụng đã tích hợp sẵn (defense-in-depth)

| Cơ chế | File | Ý nghĩa minh bạch |
|---|---|---|
| Rate-limit login 10/300s, register 5/h, join-code 10/h, graph PUT 120/ph | `api/lib/security.php::rate_limit()` + routes | Trả **429 + Retry-After** kèm hint tiếng Việt rõ nguyên nhân |
| Khoá tài khoản 15 phút sau 8 lần sai | `users.failed_logins/locked_until` (schema.sql) | Thông báo tường minh, không im lặng fail |
| Password policy báo từng vi phạm | `password_policy_error()` | UX rõ ràng ("cần ít nhất 1 chữ số") |
| CSP nghiêm ngặt + COOP/CORP/Permissions-Policy | `emit_security_headers()` + `.htaccess` | SPA không cần unsafe-eval; chống clickjacking tuyệt đối (`frame-ancestors 'none'`) |
| Input sanitization whitelist mọi field | `clean_*()` trong routes | Chuỗi điểu khiển bị loại, ngày bắt buộc phải là `YYYY-MM-DD`, số kẹp min/max |
| Collection caps (tasks ≤5000 …) | `routes/projects.php` graph PUT | Chặn payload khủng làm nghẽn DB |
| Audit log mọi mutation | `audit()` | Người dùng xem được lịch sử thay đổi — trụ cột "minh bạch" |

## 7. Bước 6 — Xác minh sau triển khai

```bash
# 1. Đi qua CF bình thường → 200 JSON
curl -si https://deedsteam.click/api/health | grep -E "HTTP|server: cloudflare|cf-ray"

# 2. Header bảo mật đủ bộ
curl -sI https://deedsteam.click/ | grep -iE "content-security|x-frame|strict-transport|x-content-type|permissions-policy"

# 3. Thử bypass origin (thay IP_IF_TRUE từ `dig +short deedsteam.click` trước khi bật CF,
#    hoặc history tại securitytrails.com) → phải 403
curl -sk --resolve deedsteam.click:443:<IP_IF_TRUE> https://deedsteam.click/api/health

# 4. Thử SQLi bị WAF chặn → trang 1020 của CF
curl -s "https://deedsteam.click/api/project?id=1%20union%20select%201" | head -c 200

# 5. Brute-force login → 429 sau ~10 lần
for i in $(seq 1 12); do curl -s -o /dev/null -w "%{http_code} " \
  -X POST https://deedsteam.click/api/auth/login \
  -H 'Content-Type: application/json' -d '{"email":"a@a.a","password":"wrongpass1"}'; done
# kỳ vọng: 401×n rồi 429
```

Điểm nâng cao (tuỳ chọn): gửi URL site tới [SSL Labs](https://www.ssllabs.com/ssltest/) đạt A; chạy [securityheaders.com](https://securityheaders.com) đạt A.

## 8. Vận hành & sự cố

- **Website trắng sau khi bật front-door**: bạn đang truy cập từ IP không qua CF (VD internal network route vòng) → kiểm tra record đã orange-cloud, xoá cache DNS.
- **Lỗi 521/522 origin unreachable**: IF sleep account free (idle > 1h) — vào VistaPanel re-enable; cân nhắc UptimeRobot ping `/api/health` 5 phút/lần (được phép với IF).
- **CF block nhầm người dùng thật**: Security → Events → xem rule nào bắn → thu hẹp expression hoặc thêm Bypass cho path tĩnh.
- **Refresh danh sách IP CF** (CF đôi khi thêm dải): cập nhật `CF_IP_RANGES` trong `security.php` + khối rewrite trong `.htaccess` theo https://www.cloudflare.com/ips-v4.
- **Đổi secret định kỳ** (khuyến nghị 90 ngày): sinh hex mới → dán vào Worker **và** config.php → deploy đồng thời.
- Nhật ký tập trung: bật **Logs → Analytics** (miễn phí) theo dõi top paths bị WAF chặn; app đã log `[deedsplan]` qua error_log phía IF.

## 9. Checklist hoàn tất

- [ ] Nameservers trỏ Cloudflare, @ + www proxied (orange)
- [ ] SSL Full (strict), Always-HTTPS, TLS ≥1.2, HSTS
- [ ] 5 WAF custom rules R1–R5
- [ ] Rate rule `/api/auth/*`
- [ ] Bot Fight Mode ON
- [ ] Cache rule `assets/*` 1 năm / bypass `/api/*`
- [ ] Worker signed-header deployed + `cf_signed_secret` khớp config.php
- [ ] `env=production` trên server, `DEEDSPLAN_ENV=development` local
- [ ] 5 lệnh xác minh §7 đều đạt
- [ ] Schema mới (bảng `rate_limits`, cột `failed_logins/locked_until`) đã import lại qua phpMyAdmin
