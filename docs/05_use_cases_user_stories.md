# 05 — Use Cases & User Stories (ProjectLibre)

**Phiên bản:** 1.0 | **Phương pháp:** UML Use Case (format chuẩn) + Agile User Story với Acceptance Criteria (Given/When/Then)

---

## 1. Actors (Hệ nhân)

| Actor | Mô tả | Mục tiêu chính |
|-------|-------|----------------|
| **A1 – Project Manager (PM)** | Người lập & điều hành kế hoạch | Tạo WBS, giao việc, theo dõi, báo cáo |
| **A2 – Team Member / Resource** | Thành viên thực thi | Xem task của mình, cập nhật % công việc |
| **A3 – Sponsor / Executive** | Cấp phê duyệt | Duyệt baseline, xem dashboard EVM |
| **A4 – PMO Administrator** | Quản trị chuẩn mực | Template, calendar doanh nghiệp, CM file |
| **A5 – MS Project User (ngoài)** | Đối tác dùng MSP | Trao đổi file .mpp round-trip |
| **A6 – System (Scheduler engine)** | Hệ thống con | Tự tính lịch, cost, critical path |
| **A7 – File System / OS** | Hạ tầng | Đọc/ghi file, clipboard, máy in |

## 2. Sơ đồ Use Case tổng quan (textual UML)

```
                        ┌──────────────────────────────────────┐
   A1 PM ──────────────►│ UC-01 Tạo dự án mới                  │
   A1 ─────────────────►│ UC-02 Cấu hình lịch làm việc         │
   A1 ─────────────────►│ UC-03 Nhập & cấu trúc WBS            │
   A1 ─────────────────►│ UC-04 Thiết lập phụ thuộc task       │
   A1 ─────────────────►│ UC-05 Xem/tối ưu đường găng          │
   A1 ─────────────────►│ UC-06 Quản lý resource pool          │
   A1 ─────────────────►│ UC-07 Phân công nguồn lực            │
   A1/A3 ──────────────►│ UC-08 Set & so sánh Baseline         │
   A2 ─────────────────►│ UC-09 Cập nhật tiến độ task          │
   A1 ─────────────────►│ UC-10 Update Project bulk            │
   A1/A3 ──────────────►│ UC-11 Báo cáo EVM (CPI/SPI)          │
   A1 ─────────────────►│ UC-12 San bằng nguồn lực             │
   A1 ─────────────────►│ UC-13 Đánh giá what-if thay đổi      │
   A1 ─────────────────►│ UC-14 Đóng dự án & lưu trữ           │
   A1 ─────────────────►│ UC-15 Export PDF/HTML/Report         │
   A5 ◄────────────────►│ UC-16 Mở/Lưu file .mpp (interop)     │
   A4 ─────────────────►│ UC-17 Custom Fields & Calculated     │
   A4 ─────────────────►│ UC-18 Chia sẻ resource pool đa dự án │
   A1 ─────────────────►│ UC-19 In Gantt nhiều trang           │
   A1 ─────────────────►│ UC-20 Undo/Redo & tự phục hồi        │
                        └──────────────────────────────────────┘
   «include»: UC-03 ..> UC-02 (cần calendar); UC-11 ..> UC-08; UC-12 ..> UC-07
   «extend» : UC-13 .> UC-04 (what-if khi đổi logic); UC-20 .> tất cả UC chỉnh sửa
```

## 3. Đặc tả Use Case chi tiết (10 UC trọng tâm)

### UC-01 — Tạo dự án mới
| Mục | Nội dung |
|---|---|
| **Actor** | A1 (PM) |
| **Precondition** | ProjectLibre đã cài & chạy; PM có quyền ghi thư mục dự án |
| **Postcondition** | File `.mpp/.pod` rỗng tồn tại, project info hợp lệ |
| **Trigger** | Quyết định đầu tư được duyệt |

**Main success scenario:**
1. PM chọn `[File] → New`.
2. System tạo document *Untitled* với Standard calendar & summary task gốc.
3. PM chọn `[File] → Save As`, nhập tên `PRJ2026_CauDuong.mpp`, vị trí, format `.mpp`.
4. System ghi file, update title bar, Recent list.
5. PM mở `[Project] → Project Information`, điền Title/Start date/Manager → OK.
6. System lưu metadata, recalculate ngày bắt đầu các task.

**Extensions:**
- 3a. Tên file trùng: System hỏi *Replace?* → PM chọn Replace/Cancel.
- 5x. Ngày start ở quá khứ: System cảnh báo vàng, vẫn cho phép.
**Error:** 4e. Đĩa đầy/permission denied → System hiện dialog lỗi, giữ document dirty để save nơi khác.

### UC-02 — Cấu hình lịch làm việc doanh nghiệp
**Actor:** A4. **Precondition:** dự án đang mở.
1. A4: `[Project] → Change Working Time`.
2. System hiển thị dialog calendar (Standard active).
3. A4 nhấn **Create New Calendar** → đặt "Ca hai 6h–22h", base = Standard.
4. A4 vào Work Weeks → Details → set Sat nonworking, Advanced giờ 06:00–14:00/14:00–22:00.
5. A4 thêm Holiday Tết 2027 (Exceptions).
6. System validate không overlap shift → lưu.
7. A4 gán calendar cho resource nhóm sản xuất → System recalc duration affected tasks.
**Postcondition:** lịch phản ánh đúng vận hành; duration tự đổi theo calendar.

### UC-03 — Nhập và cấu trúc hóa WBS
1. A1 click dòng task đầu → gõ từng task name + duration (Tab giữa cột).
2. A1 chọn khối task con → `[Project] → Indent` `(Alt+→)`.
3. System chuyển thành summary/child, rollup duration & cost lên summary.
4. A1 chèn milestone: thêm task, duration `0d`.
5. A1 thêm cột WBS (`[Insert] → Column → WBS`) đối chiếu phạm vi.
**Extension 2a:** indent nhầm mức → A1 Outdent `(Alt+←)`; undo khả dụng (UC-20).

### UC-04 — Thiết lập phụ thuộc
1. A1 chọn predecessor + Ctrl-click successor.
2. A1 nhấn toolbar **Link**.
3. System tạo FS link, recalc schedule, vẽ mũi tên Gantt.
4. A1 đổi loại SS trong Task Information → Predecessors → Type.
5. A1 thêm lag: sửa cột Predecessors thành `5SS+2d`.
**Error 3e:** link gây loop → System highlight đỏ, dialog liệt kê cycle, yêu cầu gỡ; hoàn tác nếu cần.

### UC-05 — Xem & phân tích đường găng
1. A1 bật `[Format] → Critical`.
2. System tô hồng task slack ≤ 0, hiển thị nhãn Slack.
3. A1 thêm cột Total Slack, Early/Late Start/Finish.
4. A1 thử rút 1 ngày task CP → System recalc finish date toàn dự án ngay.
**Include:** UC-04 (logic link phải hợp lệ trước).

### UC-07 — Phân công nguồn lực
1. A1 `[View] → Resource Sheet` khai báo resource (rate 500k/h, max 100%).
2. A1 chọn task → `Ctrl+=` (Assign Resources) → chọn Dev-A → Units 50% → Assign.
3. System sinh assignment, tính Work = Duration × Units, Cost = Work × rate; cập nhật histogram.
4. A1 double-click ô Resource Usage → chỉnh work timephased.
**Extension:** over-allocated → icon đỏ; đề nghị chạy UC-12.

### UC-08 — Set & so sánh Baseline
1. A1 `[Project] → Set Baseline → Set → Whole project`.
2. System copy Start/Finish/Work/Cost sang trường Baseline 1 cho mọi task.
3. A1 bật Table *Variance*; System tính Variance = Current − Baseline.
4. A3 xem Gantt có thanh baseline xám bên dưới bar hiện tại.
**Precondition:** plan đã duyệt. **Postcondition:** BL1 bất biến tới khi set BL khác.

### UC-09 — Team member cập nhật tiến độ
1. A2 mở file (hoặc nhận export CSV task list của mình).
2. A2 nhập `%Complete` = 40, Actual Start = 10/11.
3. System recalcs remaining work/duration, progress bar trên Gantt.
4. A2 Save (bản working copy) → gửi lại PM (quy trình P7).
**Error:** A2 nhập % nhưng thiếu actual → System cho phép nhưng report variance sai → PM kiểm tra (P4).

### UC-11 — Báo cáo EVM
1. A1 insert các cột BCWS/BCWP/ACWP/CPI/SPI (điều kiện: BL + rates + actuals).
2. System tính per-task & rollup summary.
3. A1 Report Wizard → template *Earned Value* → chart S-curve.
4. A1 Export PDF trình A3.
**Include:** UC-08 (baseline), UC-10 (actual data).

### UC-16 — Interop file .mpp với MS Project (A5)
1. A5 gửi `KeHoach.msp2016.mpp`; A1 `[File] → Open` → MPXJ parse → mapping domain.
2. System báo warning với field không hỗ trợ (ví dụ some constraint types) → log chi tiết.
3. A1 chỉnh sửa → `Save As .mpp` → A5 mở lại bằng MS Project, verify.
**Error:** file corrupt → System dialog "Cannot parse"; fallback: nhờ A5 re-save từ MSP.

*(Các UC-06, 10, 12–15, 17–20 viết cùng format — tóm tắt ở bảng Phụ lục cuối tài liệu.)*

## 4. User Stories (kèm Acceptance Criteria)

> Định dạng: **US-xx** — Là <vai trò>, tôi muốn <hành động> để <giá trị>. Priority: MoSCoW; Story point: Fibonacci.

### Epic 1 — Lập kế hoạch
| ID | User Story | AC (Given/When/Then) | P | SP |
|----|-----------|----------------------|---|----|
| US-01 | Là PM, tôi muốn tạo dự án mới với ngày bắt đầu tùy chọn để khởi tạo kế hoạch | Given app đang mở, When New + nhập start date, Then mọi task auto-schedule từ ngày đó | Must | 2 |
| US-02 | Là PM, tôi muốn nhập task bằng bàn phím liên tục (Enter/Tab) để tăng tốc soạn WBS | Khi gõ tên Enter, Then con trỏ xuống dòng mới; Tab sang Duration; Finish tự tính | Must | 3 |
| US-03 | Là PM, tôi muốn indent/outdent để xây cây WBS | Given ≥2 task, When Alt+→, Then task thành con, summary cha tự sinh, rollup đúng | Must | 3 |
| US-04 | Là PM, tôi muốn link FS/SS/FF/SF + lag | When link xong, Then lịch cập nhật tức thì; loop bị chặn kèm thông điệp | Must | 5 |
| US-05 | Là PM, tôi muốn thấy critical path | When bật Critical, Then task slack≤0 màu hồng + số slack | Must | 2 |
| US-06 | Là PM, tôi muốn milestone 0d hiển thị hình thoi | Khi duration=0, Then marker ◆ tại start, không ảnh hưởng work | Should | 1 |
| US-07 | Là PM, tôi muốn đặt constraint Must Finish On | Khi set, Then task không vượt ngày; icon ràng buộc hiển thị | Should | 2 |
| US-08 | Là PM, tôi muốn split task vì nghỉ lễ | Khi Split, Then 2 đoạn + gap; work/cost giữ nguyên | Could | 3 |

### Epic 2 — Nguồn lực & chi phí
| ID | User Story | AC | P | SP |
|----|-----------|----|---|----|
| US-10 | Là PM, tôi muốn khai báo resource Work/Material/Cost với rate | Resource Sheet lưu rate std/OT, cost/use; cost task tính đúng công thức | Must | 5 |
| US-11 | Là PM, tôi muốn gán resource 1-click (Ctrl+=) | Assignment sinh work=time×units; unassign xóa sạch | Must | 3 |
| US-12 | Là PM, tôi muốn phát hiện over-allocation | Loading view red bar; filter "Over allocated" | Must | 3 |
| US-13 | Là PM, tôi muốn san bằng tự động trong slack | Level within slack không đổi finish date CP ngoài cần thiết; leveling delay ghi nhận | Should | 5 |
| US-14 | Là PM, tôi muốn dùng chung resource pool đa dự án | Share resources N file; conflict units cộng dồn toàn cục | Could | 8 |
| US-15 | Là Accountant, tôi muốn fixed cost & accrual | Fixed cost không đổi theo work; accrual Beginning/End/Prorated đúng | Should | 3 |

### Epic 3 — Theo dõi & báo cáo
| ID | User Story | AC | P | SP |
|----|-----------|----|---|----|
| US-20 | Là PM, tôi muốn set baseline whole project | Sau set, Baseline Start/Finish filled; re-set hỏi overwrite? | Must | 2 |
| US-21 | Là Team member, tôi muốn nhập %complete/actual work | Progress bar + remaining work cập nhật; status date tôn trọng | Must | 3 |
| US-22 | Là PM, tôi muốn Update Project hàng loạt đến today | Bulk dialog; chỉ task selection/entire; actual work sinh đúng | Should | 3 |
| US-23 | Là Sponsor, tôi muốn báo cáo CPI/SPI | Cột & chart EV; value khớp hand-calc ±1% | Must | 5 |
| US-24 | Là PM, tôi muốn in/export Gantt PDF A3 | Fit to width option; font không vỡ tiếng Việt | Must | 3 |
| US-25 | Là PM, tôi muốn custom field dropdown "Reason delay" | Values list; group/filter hoạt động; export giữ field | Should | 5 |
| US-26 | Là PM, tôi muốn calculated field "RiskScore=Cost×Prob" | Formula editor validate; recalc khi input đổi | Could | 8 |

### Epic 4 — Tương thích & vận hành
| ID | User Story | AC | P | SP |
|----|-----------|----|---|----|
| US-30 | Là người dùng MSP, tôi muốn mở .mpp không mất dữ liệu | Round-trip test: tasks/links/resources/baseline ≥99% field map | Must | 8 |
| US-31 | Là admin, tôi muốn app chạy offline hoàn toàn | Không request mạng bắt buộc (tùy chọn telemetry off) | Must | 1 |
| US-32 | Là người dùng, tôi muốn undo/redo sâu | Chuỗi 50 lệnh revert đúng trạng thái | Must | 3 |
| US-33 | Là người dùng, tôi muốn tự phục hồi sau crash | Crash → lần sau mở recovery file cache gần nhất | Should | 5 |
| US-34 | Là PM Việt Nam, tôi muốn UI & font tiếng Việt | Locale vi-VN; Unicode ổn định trong sheet/chart/print | Should | 3 |

## 5. Ma trận truy vết Requirements ↔ Use Case ↔ Test

| Requirement | Use Case | User Story | Test case (tài liệu 08) |
|-------------|----------|------------|--------------------------|
| REQ-FN-01 Tạo/lưu file | UC-01 | US-01 | TC-01 |
| REQ-FN-02 Lịch làm việc | UC-02 | US-01 | TC-02 |
| REQ-FN-03 WBS | UC-03 | US-02,03 | TC-03 |
| REQ-FN-04 Phụ thuộc | UC-04 | US-04 | TC-04 |
| REQ-FN-05 CPM | UC-05 | US-05 | TC-05 |
| REQ-FN-06 Resources | UC-06/07 | US-10..12 | TC-06 |
| REQ-FN-07 Baseline | UC-08 | US-20 | TC-07 |
| REQ-FN-08 Tracking | UC-09/10 | US-21,22 | TC-08 |
| REQ-FN-09 EVM | UC-11 | US-23 | TC-09 |
| REQ-FN-10 Leveling | UC-12 | US-13 | TC-10 |
| REQ-FN-11 Reports | UC-15 | US-24 | TC-11 |
| REQ-NFR-Interop | UC-16 | US-30 | TC-12 |

## 6. Phụ lục — Tóm tắt các UC còn lại

| UC | Actor | Dòng chảy chính (rút gọn) |
|----|-------|----------------------------|
| UC-06 Resource pool | A1 | Resource Sheet → rates A–E tables → calendars riêng |
| UC-10 Bulk update | A1 | Project→Update Project→today→recalc % |
| UC-12 Leveling | A1 | Tools→Level Resources→within slack→review delay |
| UC-13 What-if | A1 | Save As copy→sửa→so sánh finish delta→merge/reject |
| UC-14 Closing | A1 | Filter incomplete→100%→final report→archive RO |
| UC-15 Export | A1 | Report Wizard→PDF/HTML/XML/PNG |
| UC-17 Custom fields | A4 | Project→Custom Fields→rename/values/formula |
| UC-18 Pool share | A4 | View→Resource Pool→Share/Open/Close |
| UC-19 Print | A1 | Print Preview→scale/fit→page ranges→print |
| UC-20 Undo/Recovery | A1/A2 | Ctrl+Z stack; crash→auto recovery prompt |
