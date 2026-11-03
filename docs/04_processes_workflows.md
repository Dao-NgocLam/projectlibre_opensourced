# 04 — Quy Trình Nghiệp Vụ Chuẩn (SOP) Trên ProjectLibre

**Phiên bản:** 1.0 | **Mô hình tham chiếu:** PMBOK 7 / PRINCE2 lite | **Công cụ:** ProjectLibre 1.6.x

Tài liệu định nghĩa **7 quy trình chuẩn** áp dụng khi dùng ProjectLibre trong tổ chức. Mỗi quy trình gồm: mục tiêu, đầu vào/đầu ra, trách nhiệm (RACI), các bước thực hiện trên phần mềm, tiêu chí hoàn thành (DoD), biểu mẫu kèm theo.

---

## Tổng quan vòng đời dự án trên ProjectLibre

```
[P0 Khởi tạo] → [P1 Lập kế hoạch] → [P2 Baseline & Phê duyệt]
      → [P3 Thực thi & Cập nhật] ⇄ [P4 Kiểm soát & Báo cáo]
      → [P5 Quản lý thay đổi]     → [P6 Đóng dự án & Hậu cần]
```

## P0 — Khởi tạo dự án (Initiation)

| Thuộc tính | Nội dung |
|---|---|
| **Mục tiêu** | Tạo "vỏ" dự án, khai báo thông nền tảng, lịch làm việc tổ chức |
| **Đầu vào** | Project Charter, quyết định đầu tư |
| **Đầu ra** | File `DA_<Ma>_init.mpp`, calendar chuẩn công ty |
| **RACI** | R: PM; A: Sponsor; C: PMO; I: Team |

**Các bước trên ProjectLibre:**
1. `[File] → New` → `Save As` theo quy ước tên file `MA_DON_VI_NAM.mpp`.
2. `[Project] → Project Information` → điền Title/Author/Company/Manager, **Project start date**.
3. Xây dựng **Enterprise Calendar**: `[Project] → Change Working Time`:
   - Work Weeks: mặc định Mon–Fri 8:00–12:00, 13:00–17:00.
   - Holidays: thêm nghỉ lễ Việt Nam (Tết, Giỗ Tổ 10/3 AL, 30/4, 1/5, 2/9…).
   - Tạo calendar riêng: "Ca sản xuất 2 ca", "Consultant part-time".
4. Cấu hình mặc định doanh nghiệp: `[Tools] → Options` (thang đo work 8h/ngày, currency VND, week starts Monday).
5. Ghi chú charter vào `[View] → Documents` (đính kèm file Charter PDF).
6. **DoD:** project info đủ trường bắt buộc; Standard calendar phản ánh đúng lịch công ty.

## P1 — Lập kế hoạch (Planning)

**Mục tiêu:** WBS đầy đủ ≥ mức 3, logic mạng chuẩn, nguồn lực & chi phí ước lượng.

### B1. Phân rã WBS
1. Họp team brainstorm → liệt kê deliverable → đặt thành Summary Task theo nhóm chức năng (`Insert → New Task`, Indent `(Alt+→)`).
2. Quy tắc: mỗi task lá ≤ 10 ngày công (rule of thumb 8/80); danh từ hóa tên ("Thiết kế UI" không phải "UI").
3. Thêm cột **WBS**, **Notes** để mô tả phạm vi từng task.

### B2. Ước lượng thời lượng
1. Nhập Duration cho task lá (dùng 3-point nếu cần: ghi Note optimistic/pessimistic rồi lấy PERT).
2. Milestone: duration `0d` tại cuối mỗi phase.

### B3. Thiết lập phụ thuộc
1. Chọn cặp task trước–sau → Link `(Ctrl+F)`; chọn loại FS/SS/FF/SF hợp logic.
2. Lag kỹ thuật: gõ vào cột Predecessors dạng `12FS+3d`.
3. Chạy `[Format] → Critical` kiểm tra đường găng; gỡ mọi link gây loop (cảnh báo đỏ).
4. Hạn chế constraint tuyệt đối; chỉ dùng *Must Finish On* cho milestone pháp lý.

### B4. Nguồn lực & chi phí
1. `Resource Sheet`: khai báo resource (Work/Material/Cost), rate theo bảng lương thật.
2. Gán qua `Assign Resources (Ctrl+=)`; kiểm tra histogram `[View] → Loading`.
3. Fixed cost (thuê ngoài, license): Task Information → Fixed Cost.
4. Review tổng cost tại Project Information → Statistics.

### B5. Lịch chất lượng/rủi ro
1. Thêm task "Kiểm thử UAT", "Review an toàn" vào WBS (không giấu ngoài kế hoạch).
2. Buffer dự phòng: task "Management Reserve" ở cuối phase hoặc dùng lag ẩn.

**DoD:** Không task lá nào thiếu predecessor (trừ task khởi đầu); Critical path được sponsor xác nhận; over-allocation < ngưỡng chấp nhận.

## P2 — Thiết lập Baseline & phê duyệt

1. Đóng băng phạm vi: khóa file gốc sang chế độ chỉ-đọc trên server (backup `..._v0.docx`).
2. `[Project] → Set Baseline → Set → Whole project` (Baseline 1 = kế hoạch được duyệt).
3. Hiển thị baseline trên Gantt (`Format → Details → Baselines`) để in tài liệu họp.
4. Xuất báo cáo phê duyệt: Report Wizard → template *Project Summary* → Export PDF → ký số/lưu.
5. **DoD:** mọi task có Baseline Start/Finish khác blank; PDF phê duyệt lưu thư mục hồ sơ.

## P3 — Thực thi & cập nhật tiến độ (weekly cycle)

**Chu kỳ thứ Hai hàng tuần (30–45 phút):**
1. `[Project] → Status Date` = thứ Sáu tuần trước.
2. Nhận báo cáo từ lead → cập nhật:
   - Actual Start (task đã mở), % Complete hoặc Actual Work trong Resource Usage.
   - `[Project] → Update Project` cho bulk update đến today.
3. Điều chỉnh duration còn lại (remaining) cho task trễ; nhập reason code vào Custom Field `Ly do tre`.
4. Kiểm tra critical path mới; nếu finish date dịch > 3 ngày → cảnh báo sponsor (chuyển P5).
5. Lưu file phiên bản tuần: Save As `..._W<tuần>.mpp` (archive tự động).

**DoD:** không task nào %Complete thay đổi mà thiếu actual data; variance report sinh được.

## P4 — Kiểm soát & báo cáo (Monitoring)

1. **Variance review**: Table → *Variance*: Start/Finish Variance, Cost Variance.
2. **EVM**: thêm cột BCWS/BCWP/ACWP/CPI/SPI; báo cáo tháng với S-curve (Report Wizard → Earned Value).
3. **Over-allocation**: Loading view; chạy Leveling nếu vượt tải kéo dài (xem P5 nếu ảnh hưởng deadline).
4. **Dashboard họp giao ban** (1 slide): Timeline view copy picture + Top 10 risk table (Custom field Flag + filter).
5. Phát hành báo cáo tuần/tháng PDF cho stakeholder theo ma trận truyền thông.

| Chỉ số | Công thức hiển thị trong PL | Ngưỡng hành động |
|--------|------------------------------|------------------|
| SPI | cột SPI | < 0.9 → escalation |
| CPI | cột CPI | < 0.95 → review chi phí |
| Total Slack CP | Total Slack | giảm 5 ngày/tuần → red flag |

## P5 — Quản lý thay đổi (Change Control)

1. Yêu cầu CR nhập form `CR-<số>` (lưu trong Documents view).
2. Đánh giá tác động **trên bản sao**: `Save As ..._CR<n>_whatif.mpp`; sửa plan; so sánh finish date/cost delta.
3. Nếu chấp thuận:
   - Áp thay đổi vào file chính.
   - `[Project] → Set Baseline → Set → **Baseline 2**` (giữ BL1 để so lịch sử).
   - Ghi log thay đổi vào Custom Field `Lich su doi`.
4. Từ chối/lùi: cập nhật trạng thái CR, thông báo.
5. **DoD:** mọi thay đổi lịch đều có CR được phê duyệt + baseline mới được set.

## P6 — Đóng dự án (Closing)

1. Xác nhận 100% task lá (%Complete = 100, Remaining Work = 0) — dùng Filter *Incomplete tasks* để quét.
2. `[Project] → Update Project` lần cuối với status date = ngày đóng.
3. Khóa actuals: không cho sửa (save read-only, lưu archive).
4. Báo cáo quyết toán: Report Wizard template *Budget/Cost* + EVM final (CPI/BAC/EAC/VAC).
5. Lessons learned: tạo Document "LL.txt" đính kèm; tổng kết vào PMO repository.
6. Release resources: xóa assignment hoặc set Max Units về 0; close resource pool share.

## P7 — Quy trình vận hành file & phân quyền (Configuration Management)

1. Một file master duy nhất trên shared drive/SVN/Git LFS; nhân viên chỉ làm việc trên **working copy**.
2. Đặt tên: `PROJ_WBS_v<N>_<YYYYMMDD>.mpp`; cấm đổi extension tùy tiện.
3. Đồng bộ MS Project ↔ ProjectLibre: round-trip test mỗi lần nâng cấp phần mềm (xem tài liệu 08).
4. Backup: snapshot tự động hằng đêm; recovery file cache `~/.ProjectLibre_v1.6`.

---

## Ma trận truy vết Quy trình ↔ Tính năng ↔ Use Case

| Quy trình | Tính năng ProjectLibre chủ chốt | Use Case (tài liệu 05) |
|-----------|--------------------------------|------------------------|
| P0 | Project Information, Calendars, Options | UC-01, UC-02 |
| P1 | Task entry, Indent, Links, Resource Sheet, Assign | UC-03…UC-07 |
| P2 | Set Baseline, Report Wizard | UC-08 |
| P3 | Update Project, %Complete, Actual Work | UC-09, UC-10 |
| P4 | Variance table, EVM fields, Loading | UC-11, UC-12 |
| P5 | What-if copy, Baseline 2+, Custom Fields | UC-13 |
| P6 | Filters, Final reports, Documents | UC-14 |
