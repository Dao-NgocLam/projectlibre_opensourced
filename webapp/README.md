# 🗓️ DeedsPlan — ProjectLibre Online (WebApp)

**DeedsPlan** là ứng dụng quản lý dự án trực tuyến chạy hoàn toàn trên trình duyệt, tái hiện đầy đủ các tính năng cốt lõi của **ProjectLibre desktop**: WBS, phụ thuộc công việc (FS/SS/FF/SF + lag), lịch làm việc, đường găng (CPM), baseline, nguồn lực & điều phối, chi phí, theo dõi tiến độ, Earned Value Management (EVM), resource leveling, Gantt chart, báo cáo EAC/ETC — với giao diện hiện đại, **minh bạch – tường minh – rõ ràng**.

- 🌐 Domain production: **https://deedsteam.click**
- 🖥️ Frontend: **Svelte 4 (SPA thuần tĩnh)** — không cần Node.js runtime trên server host
- 🗄️ Backend: **PHP 8 + MySQL** (chuẩn hạ tầng InfinityFree)
- 🎨 Design system: bảng màu "Deeds Slate–Teal" trung tính, tương phản WCAG AA, thông tin luôn có nhãn + tooltip giải thích

---

## 1. Kiến trúc tổng thể

```
┌────────────────────────────── Browser (deedsteam.click) ──────────────────────────────┐
│  Svelte SPA (tinh/)  —  state client-side, tự động lưu (debounce) vào API             │
│  ├── Dashboard   ├── Gantt (SVG)   ├── WorkLog   ├── Resources                        │
│  ├── Network     ├── Calendar      ├── Cost      ├── Reports (EVM)                   │
│  └── Auth (JWT localStorage)                                                            │
└───────────────┬───────────────────────────────────────────────────────────────────────┘
                │ fetch JSON /api/*  (Authorization: Bearer <jwt>)
┌───────────────▼──────────────────── Server (InfinityFree PHP 8.x) ────────────────────┐
│  api/index.php  — router + CORS + auth middleware                                      │
│  ├── routes/auth.php        (đăng ký / đăng nhập / me)                                │
│  ├── routes/projects.php    (CRUD dự án + project info + PUT toàn bộ graph)           │
│  ├── routes/reports.php     (EVM: BCWS/BCWP/AC/EAC/ETC/VAC/SPI/CPI)                    │
│  └── db/schema.sql          (DDL MySQL 8)                                              │
└───────────────┬───────────────────────────────────────────────────────────────────────┘
                │ PDO prepared statements
┌───────────────▼────────────────────────────────────────────────────────────────────────┐
│  MySQL (InnoDB, utf8mb4_unicode_ci)  — users / projects / tasks / links /              │
│  resources / assignments / calendars / baselines / customfields / audit trail          │
└─────────────────────────────────────────────────────────────────────────────────────────┘
```

**Lý do tách frontend/backend như trên (phù hợp InfinityFree):**
- InfinityFree **không cho chạy Node.js常驻进程**, nhưng hỗ trợ PHP 8 + MySQL + HTTPS + custom domain.
- Svelte compile ra **file tĩnh** → upload lên `htdocs/`, chỉ cần Apache `.htaccess`.
- Toàn bộ logic lập lịch (CPM forward/backward pass, float, critical path, leveling, EVM) nằm trong **`client/src/lib/engine.js`** — thuật toán port trung thành từ `com.projectlibre.pm` (xem `docs/01_technical_specification.md`). Server là **nguồn sự thật về dữ liệu**, máy khách là engine tính toán; kết quả được persist để mọi thành viên thấy **giống hệt nhau** (minh bạch).

Thư mục:

| Đường dẫn | Nội dung |
|---|---|
| `webapp/client/` | Mã Svelte (src/, vite config, svelte.config.js) |
| `webapp/client/dist/` | **Build output tĩnh** — thư mục này chính là nội dung upload lên hosting |
| `webapp/api/` | Backend PHP (router, routes, schema.sql) — upload nguyên folder vào `htdocs/api/` |
| `webapp/.htaccess` | SPA fallback + bảo mật + nén gzip |
| `webapp/deploy/` | Kịch bản triển khai từng bước + hướng dẫn tạo DB trên InfinityFree |
| `webapp/docs/` | Tài liệu kỹ thuật webapp, design system, user stories riêng cho bản online |

---

## 2. Tính năng (parity với ProjectLibre desktop)

### 2.1 Lập kế hoạch
- ✅ Tạo dự án, Project Information (start/finish date, timezone, current date)
- ✅ Lịch làm việc cấp dự án + **exception calendar** (ngày nghỉ lễ VN: Giỗ Tổ, 30/4, 1/5, 2/9, Tết)
- ✅ WBS không giới hạn cấp cha–con, thêm/xóa/di chuyển/lên-xuống cấp
- ✅ Phụ thuộc FS/SS/FF/SF + lag/lead (giờ hoặc ngày)
- ✅ Constraints: Start No Earlier Than / Finish No Later Than / Must Start On…
- ✅ Fixed Duration / Fixed Units / Fixed Work, manual scheduling (tuỳ chọn)
- ✅ **Auto-scheduling CPM** (forward pass → backward pass → total free float → critical path đỏ)
- ✅ Split task, insert milestone, summary roll-up (duration = span con, %complete = trọng số work)

### 2.2 Nguồn lực & chi phí
- ✅ Resource pool: work resource (kỹ sư, PM…) & material resource (xi-măng, GPU…), đơn giá/giờ
- ✅ Resource calendar riêng + max units %
- ✅ Assignment nhiều nguồn lực/task, units %, work tự tính `W = U × D × 8h`
- ✅ Cost type (Fixed/Proportional/Prorated), material cost, overtime, **manual cost** entries
- ✅ Total cost = ∑ assignment cost + fixed task cost + material cost

### 2.3 Baseline & theo dõi
- ✅ Save baseline (nhiều lần re-baseline, lưu snapshot toàn graph)
- ✅ Nhập `%Complete` hoặc actual start/finish/duration/%work remaining
- ✅ Variance: start/finish so baseline
- ✅ **Resource leveling tự động** (defer non-critical trước, critical sau, theo priority)
- ✅ Undo/Redo client-side + autosave

### 2.4 Báo cáo & nhìn trực quan
- ✅ **Gantt SVG tương tác**: drag thanh để shift, kéo mép để đổi duration, click diamond baseline, zoom dòng/ngày, tooltip tường minh mọi con số
- ✅ **Network diagram** (PDM boxes + arrows, critical path tô đỏ)
- ✅ **Work Log** kiểu ProjectLibre: bảng task/resource grid nhập % hoàn thành hàng tuần
- ✅ **Resource Usage**: giờ phân bổ/ngày per resource, over-allocation highlight cam
- ✅ **Calendar view**: tháng làm việc + exception shading
- ✅ **Reports**: EVM dashboard (BAC, PV, EV, AC, SV, CV, SPI, CPI, EAC, ETC, VAC) + export CSV/PDF-in-browser
- ✅ Import/Export JSON (mở rộng được sang MPXJ/MPP ở desktop; web nhận JSON & CSV)

### 2.5 Cộng tác & minh bạch
- ✅ Đa người dùng: mỗi tài khoản sở hữu dự án, **share bằng mã mời 6 ký tự** (member role Editor/Viewer)
- ✅ Audit trail: mọi thay đổi ghi `updated_at/by` — hover bất kỳ ô nào thấy *"ai sửa lúc nào"*
- ✅ Everything visible: cột ẩn/hiện configurable, status bar hiển thị công thức đang áp dụng

---

## 3. Design System — "Deeds Slate · Teal Clarity"

Triết lý: **trung tính làm nền, một accent teal duy nhất cho hành động, đỏ/amber chỉ dành cho rủi ro**. Mọi trạng thái đều có **văn bản đi kèm màu** (không dùng màu đơn thuần → accessibility + tường minh).

```
Neutrals        #0F172A ink ─ #1E293B ─ #334155 ─ #475569 muted ─ #94A3B8 faint
                #CBD5E1 line ─ #E2E8F0 soft ─ #F1F5F9 surface ─ #F8FAFC bg ─ #FFFFFF paper
Brand (accent)  #0D9488 teal-600 (primary actions)   #0F766E hover   #CCFBF1 selected-bg
Status          Critical/Risk #DC2626 · Late #EA580C · Warning #D97706 · Done #059669 · Info #2563EB
Charts          sequential slate→teal; categorical 8-tone colourblind-safe
Type            Inter 400/500/600/700, tabular-nums cho số; scale 12/13/14/16/20/24
Spacing         4-pt grid (4/8/12/16/24/32/48); radius 6/10/14; shadow 3 levels
Motion          120ms ease-out micro, 200ms panel; respects prefers-reduced-motion
Contrast        body ≥ 7:1, large ≥ 3:1 (AA+); focus-visible ring 2px teal offset 2px
```

Chi tiết token & rationale: `webapp/docs/design_system.md`.

---

## 4. Chạy local (development)

```bash
cd webapp/client
npm install
npm run dev            # http://localhost:5173 (proxy /api → localhost:8080 nếu có PHP server)

# Backend local (optional, cần PHP 8 + MySQL):
php -S localhost:8080 -t ../            # api tại http://localhost:8080/api/index.php
mysql < ../api/db/schema.sql
```

## 5. Build production

```bash
cd webapp/client && npm run build       # → client/dist/
cp -r dist/* ../                          # hoặc upload dist/ + api/ + .htaccess lên htdocs/
```

## 6. Deploy lên InfinityFree (deedsteam.click)

Xem **`webapp/deploy/DEPLOYMENT.md`** — hướng dẫn từng bước (step-by-step):
1. Đăng ký & tạo account trên my.infinityfree.com
2. Tạo website miễn phí gắn domain `deedsteam.click` (DNS records + SSL miễn phí)
3. Tạo MySQL trên control panel (VistaPanel) + phpMyAdmin import `api/db/schema.sql`
4. Cấu hình `api/config.php` (host/user/pass/db/JWT secret)
5. Upload qua Online File Manager / FTP (FileZilla): `dist/*` → `htdocs/`, `api/` → `htdocs/api/`
6. Kiểm tra health-check `https://deedsteam.click/api/index.php?route=health`
7. Bound-limit & workaround InfinityFree (no cron, no websocket → polling autosave đã thiết kế sẵn)

## 7. Test nhanh không cần server

`client/src/lib/store.js` hỗ chế **offline mode**: nếu chưa cấu hình API, app chạy full-memory và nhắc người dùng bật đồng bộ — demo UI ngay bằng `npm run dev`.

---

*Phiên bản: 1.0.0 · Phát hành: 03/11/2026 · © DeedsTeam — phần mềm mã nguồn mở, giấy phép GPL v3 (kế thừa ProjectLibre).*
