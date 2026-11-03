/**
 * Cloudflare Worker — "Signed Requests" cho DeedsPlan (deedsteam.click)
 * ---------------------------------------------------------------------
 * Mục đích: mỗi request đi qua CF được ký HMAC-SHA256 (header X-Deeds-Signature:
 * "<unix_ts>.<hex_mac>") trước khi gửi về origin InfinityFree. Origin
 * (api/lib/security.php::enforce_cloudflare_only) verify chữ ký → kể cả kẻ tấn
 * công biết IP thật của host cũng KHÔNG thể bypass Cloudflare để gọi thẳng API.
 *
 * Cài đặt (tier Free của CF hỗ trợ Workers):
 *   1. dash.cloudflare.com → Workers & Pages → Create → "Hello World" → Deploy.
 *   2. Dán nội dung file này, thay SECRET bằng giá trị giống `cf_signed_secret`
 *      trong webapp/api/config.php (sinh: openssl rand -hex 32).
 *   3. Settings → Triggers → Custom Domains → thêm `deedsteam.click/*`
 *      (chỉ route vùng `/api/*` nếu muốn CF chỉ đứng trước API; còn static
 *       cứ để CF CDN proxy thường).
 *   4. Bật "Proxy status = Orange cloud" cho record A/CNAME trong DNS.
 *
 * Lưu ý thứ tự xử lý: Worker chạy SAU các lớp WAF/Bot/Rate-Limit của CF,
 * nên mọi request bị CF chặn sẽ không bao giờ tới đây → origin chỉ nhận
 * traffic đã qua toàn bộ pipeline bảo vệ.
 */

const SECRET = 'THAY_BANG_cf_signed_SECRET_TRONG_CONFIG_PHP'; // hex 32 bytes
const HEADER = 'X-Deeds-Signature';

async function sign(secretHex, message) {
  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey(
    'raw',
    Uint8Array.from(secretHex.match(/../g).map((h) => parseInt(h, 16))),
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign']
  );
  const mac = await crypto.subtle.sign('HMAC', key, enc.encode(message));
  return [...new Uint8Array(mac)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

export default {
  async fetch(request) {
    const url = new URL(request.url);
    const ts = Math.floor(Date.now() / 1000).toString();
    const mac = await sign(SECRET, `${ts}|${url.pathname}`);

    const headers = new Headers(request.headers);
    headers.set(HEADER, `${ts}.${mac}`);
    // Xoá header client có thể giả mạo gửi lên
    headers.delete('X-Forwarded-For-Origin-Bypass');

    return fetch(new Request(url, { method: request.method, headers, body: request.body }), {
      cf: { cacheTtl: 0 }, // API không cache ở edge; GET /health có thể bật nếu cần
    });
  },
};
