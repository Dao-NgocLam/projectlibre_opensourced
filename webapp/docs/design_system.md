# 🎨 DeedsPlan Design System — "Deeds Slate · Teal Clarity"

## 1. Triết lý UX/CX: Minh bạch – Tường minh – Rõ ràng

| Nguyên tắc | Thể hiện trong UI |
|---|---|
| **Minh bạch (Transparent)** | Không có số "từ trên trời". Mọi chỉ số EVM/float/cost đều có tooltip ghi rõ công thức (`EV ÷ PV`…), tab Reports liệt kê full bảng công thức đang áp dụng. Audit trail cho biết *ai sửa gì lúc nào*. |
| **Tường minh (Explicit)** | Trạng thái không bao giờ chỉ dựa vào màu: Critical = đỏ **+ nhãn "CP"** + cột float = 0. Manual task có ký hiệu ✎. Over-allocation có ⚠ kèm số ngày. |
| **Rõ ràng (Clear)** | 1 accent teal duy nhất cho hành động chính; đỏ/amber reserved cho rủi ro. Grid 4-pt, khoảng trắng rộng, tabular-nums để cột số thẳng hàng. Information hierarchy: WBS → dates → money. |
| **Trust & reversibility** | Undo/Redo 50 bước mọi thao tác; autosave pill luôn hiển thị trạng thái lưu (saved/dirty/saving/error/offline); xoá dự án phải xác nhận 2 bước. |
| **Accessibility** | Contrast body ≥7:1, large ≥3:1 (WCAG AA+). Focus ring 2px teal offset 2px. Mọi icon-only button có `aria-label` + `title`. prefers-reduced-motion tôn trọng. Bảng ngữ nghĩa `<table>` + `role` đúng. |

## 2. Color tokens (CSS custom properties — xem `client/src/app.css`)

### Neutrals — nền tảng "slate" trung tính, ngả xanh rất nhẹ tạo cảm giác kỹ thuật, sạch
```
--ink        #0F172A   text chính (contrast 16:1 trên paper)
--ink-2      #1E293B   heading phụ
--line-strong#334155
--muted      #475569   text phụ (7.5:1)
--faint      #94A3B8   placeholder, tick axis
--line       #CBD5E1   border chuẩn
--soft       #E2E8F0   divider trong table
--surface    #F1F5F9   panel/table header
--bg         #F8FAFC   app background
--paper      #FFFFFF   card
```

### Brand — Teal đơn nhất (hành động, selection, progress)
```
--brand       #0D9488  primary button, focus ring, %complete bar
--brand-hover #0F766E
--brand-active#115E59
--brand-soft  #CCFBF1  row selected background
--brand-ring  rgba(13,148,136,.35)
```
*Lý do chọn teal thay vì blue ProjectLibre cổ điển: teal = "calm competence", tách thương hiệu deedsteam, và phân biệt rõ với xanh-info (#2563EB) dùng cho trạng thái.*

### Status — luôn đi kèm chữ/ký tự
```
Critical / CP : #DC2626 on #FEF2F2   (red-600)
Late / off-day: #EA580C on #FFF7ED   (orange-600)
Warning / over-alloc: #D97706 on #FFFBEB (amber-600)
Done / on-track: #059669 on #ECFDF5  (emerald-600)
Info / in-progress: #2563EB on #EFF6FF (blue-600)
```

### Data-viz
- Gantt: `--bar #3B82F6`, critical `#DC2626`, summary `#1E293B`, progress fill `--brand`, baseline `#94A3B8`, today `#EA580C` dashed.
- Categorical 8-tone colourblind-safe: teal→blue→violet→pink→amber→green→slate→red (`--c1..--c8`).
- Sequential (heatmap usage): slate-100 → teal-600.

## 3. Typography
`Inter` 400/500/600/700 (Google Fonts, preconnect). Scale: 12/13/14(body)/16(h3)/20(h2)/24(h1). Số liệu: `.num { font-variant-numeric: tabular-nums }`. Line-height 1.45. Letter-spacing -0.01em headings.

## 4. Layout & spacing
- Shell 3 tầng: Topbar 52px (dark ink — tách khỏi workspace sáng), Sidebar 232px, Content, Statusbar 26px kiểu desktop-app của ProjectLibre nhưng hiện đại hoá.
- Grid 4pt: 4/8/12/16/24/32/48. Radius 6 (control) / 10 (card) / 14 (modal). Shadow 3 mức vi tế (không glassmorphism — giữ "tường minh").
- Breakpoints: <1100px sidebar collapse (mobile-first roadmap v1.1); Gantt left pane min 460px.

## 5. Motion
Micro 120ms ease-out (hover/press), panel/modal 200ms cubic-bezier(.2,.8,.2,1). Bar drag không transition (trực tiếp). Skeleton shimmer 1.2s khi loading project.

## 6. Patterns đặc trưng "clarity"
1. **Tooltip-học-thuyết**: hover bất kỳ thanh task → tên, khoảng ngày, duration, work, float, cost, cảnh báo CP/manual — người mới học được PM qua từng hover.
2. **Statusbar suốt đời hiển thị**: count tasks, #critical, %avg, total cost — không cần mở menu.
3. **Inline editing + Enter/blur commit**, highlight cell focus bằng ring teal.
4. **Confirm-danger 2 bước** ngay tại chỗ (row project list) thay vì modal trừu tượng.
5. **Save-state pill** ở topbar: ✓ Đã lưu / ● Chưa lưu… / ⟳ Đang lưu / 📡 Ngoại tuyến — niềm tin dữ liệu an toàn (CX).
6. **Empty-state hướng dẫn** ("Chưa có Baseline → hãy bấm 📌 Baseline…") chứ không than phiền lỗi.

## 7. Do & Don't
- ✅ Màu đỏ ≤ 10% diện tích màn hình, chỉ cho rủi ro thật.
- ✅ Always label icons. ✅ Tabular numbers. ✅ Visible focus.
- ❌ Gradient cầu vồng, shadow-heavy neumorphism, color-only status, all-caps paragraphs, emoji làm icon chức năng (chỉ dùng点缀 toast/logo).
