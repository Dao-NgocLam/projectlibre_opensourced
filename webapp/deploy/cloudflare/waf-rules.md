# Cloudflare WAF — bộ quy tắc khuyến nghị cho DeedsPlan (deedsteam.click)

> Nhập tại: **dash.cloudflare.com → Security → WAF → Custom rules** (Free tier: 5 rule).
> Mỗi rule bên dưới = 1 custom rule. Thứ tự ưu tiên theo số.

## R1 — Chặn SQLi/XSS theo signature (Block)
Expression:
```
(http.request.uri.query contains "union select") or
(http.request.uri.query contains "or 1=1") or
(http.request.uri.path contains "../") or
(http.request.body.raw contains "<script>")
```
Action: **Block**, log on.

## R2 — Rate-limit trang auth (Managed Challenge, Free tier cho 1 rule)
```
(http.request.uri.path contains "/api/auth/")
```
Rate: **20 requests / 60 giây** per IP → Action: **Managed Challenge**.
*(Bổ sung cho app limiter login 10/300s trong security.php.)*

## R3 — Chặn user-agent bot/scanner
```
(http.user_agent contains "sqlmap") or (http.user_agent contains "nikto") or
(http.user_agent contains "masscan") or (http.user_agent eq "")
```
Action: **Block**.

## R4 — Giới hạn region (tuỳ chọn — team VN/Asia)
```
(ip.geo.country in {"KP","IR","RU","CN"} and http.request.uri.path contains "/api/")
```
Action: **Block**. *Cân nhắc UX nếu có thành viên quốc tế.*

## R5 — Bảo vệ file nhạy cảm (dù .htaccess đã chặn, thêm lớp edge)
```
(http.request.uri.path contains "config.php") or
(http.request.uri.path contains "schema.sql") or
(http.request.uri.path contains ".env")
```
Action: **Block**.

## Ngoài custom rules — bật ngay (miễn phí):
| Tính năng | Đường dẫn | Giá trị |
|---|---|---|
| Bot Fight Mode | Bot → Bot Fight Mode | ON |
| Security Level | Security → Settings | I'm Under Attack (khi bị DDoS) |
| SSL/TLS mode | SSL/TLS → Overview | **Full (strict)** |
| Minimum TLS | SSL/TLS → Edge Certificates | TLS 1.2 |
| HSTS | SSL/TLS → Edge Certificates → HSTS | Bật + includeSubDomains + preload |
| Always Use HTTPS | SSL/TLS → Edge Certificates | ON |
| Automatic HTTPS Rewrites | ON | ON |
| Early hints / Compression | Speed → Optimization | ON (gzip/brotli) |
| Caching assets | Caching → Cache Rules | `assets/*` cache 1 năm (Vite hash) |
| DNSSEC | DNS → Settings | Bật khi registrar hỗ trợ (.click có) |
| Lockdown | My Zone → API Tokens | giới hạn token scope |
