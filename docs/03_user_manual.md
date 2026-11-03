# 03 — Hướng Dẫn Sử Dụng ProjectLibre (User Manual — Step by Step)

**Phiên bản:** 1.0 | **Áp dụng:** ProjectLibre 1.6.x | **Người đọc:** Quản lý dự án, PMO, thành viên đội dự án

> Ký hiệu: **[File]** = menu File; phím tắt ghi trong ngoặc `(Ctrl+S)`. Mọi thao tác được mô tả **từng bước một**, có ảnh minh họa mô tả bằng lời.

---

## MỤC LỤC
1. Giao diện tổng quan
2. Tạo & quản lý file dự án
3. Thiết lập thông tin dự án & lịch làm việc
4. Lập WBS và nhập công việc (Tasks)
5. Liên kết công việc (Dependencies) & đường găng
6. Ràng buộc (Constraints) & chia cắt (Split)
7. Baseline
8. Nguồn lực (Resources) & phân công (Assignments)
9. Chi phí (Cost) & ngân sách
10. Theo dõi tiến độ (Progress Tracking)
11. Earned Value Management (EVM)
12. Resource Leveling (San bằng nguồn lực)
13. Views: Gantt, Network, Timeline, Resource Usage
14. Custom Fields & Calculated Fields
15. Báo cáo & in ấn
16. Import/Export & tương thích MS Project
17. Phím tắt tham khảo nhanh

---

## 1. Giao diện tổng quan

**Bước 1.** Khởi động ProjectLibre → tab **Getting Started** hiện 4 lựa chọn: *Create a new blank project*, *Open an existing project*, *Open recent project*, *Take the tour*.
**Bước 2.** Nhìn cấu trúc cửa sổ chính:
- **Menu bar**: File, Edit, View, Insert, Format, Project, Tools, Window, Help.
- **Toolbar**: New, Open, Save, Print, Cut/Copy/Paste, Undo/Redo, Link/Unlink, Critical, Details, Rollup, Calendar, Zoom In/Out.
- **Khu vực trái**: bảng sheet (Task Sheet/Resource Sheet…) — có thể đổi tab dưới cùng.
- **Khu vực phải**: chart (Gantt/Network/Timeline).
- **Dock panels có thể kéo thả**: kéo tiêu đề panel ra giữa để nổi, thả vào mép để neo.
- **Status bar**: hiển thị *Project Start Date*, số tasks, chế độ chọn.

**Bước 3.** Di chuyển/thu phóng: thanh trượt dưới chart hoặc `View → Zoom`; `Ctrl+cuộn chuột` zoom timeline.

## 2. Tạo & quản lý file dự án

### 2.1 Tạo dự án mới
1. `[File] → New` `(Ctrl+N)`.
2. Hộp thoại không hiện (tạo thẳng template blank) — tiêu đề thành *Untitled Project*.
3. Lưu ngay: `[File] → Save As` `(Ctrl+Shift+S)` → chọn thư mục → đặt tên `DuAn_X.mpp` (hoặc `.pod`) → **Save**.
   - **Khuyến nghị:** dùng `.mpp` nếu cần trao đổi với người dùng MS Project; `.pod` cho tính năng đầy đủ của ProjectLibre.

### 2.2 Mở / lưu / đóng
| Thao tác | Các bước | Phím |
|---|---|---|
| Mở file | `[File] → Open` → duyệt tới `.mpp/.pod/.mpx` → **Open**; hoặc click tên trong *Recent* | Ctrl+O |
| Lưu đè | `[File] → Save` | Ctrl+S |
| Lưu bản khác | `[File] → Save As…` → đổi tên/format → Save | Ctrl+Shift+S |
| Đóng | `[File] → Close` (xác nhận lưu nếu dirty) | Ctrl+W |
| Nhiều file | `[Window]` liệt kê các project đang mở để chuyển | — |

### 2.3 Template
1. `[File] → New from Template…` (bản Cloud) hoặc mở file `.pod` mẫu đã lưu → `Save As` tên mới — giữ nguyên cấu trúc WBS/resource.

## 3. Thông tin dự án & lịch làm việc

### 3.1 Khai báo Project Information
1. `[Project] → Project Information`.
2. Tab **General**: nhập *Title*, *Author*, *Company*, *Manager*; chọn **Project start date** (hoặc *Finish date*).
3. Tab **Statistics**: xem tính toán tự động (duration, work, cost) — chỉ đọc.
4. Tab **Custom Fields**: thêm metadata (xem mục 14).
5. **OK**.

### 3.2 Cấu hình lịch làm việc (Calendars)
1. `[Project] → Change Working Time…` (icon Calendar trên toolbar).
2. **Calendar name**: chọn *Standard* (project default) hoặc nhấn **Create New Calendar** (vd: "Ca đêm").
3. Tab **Work Weeks** → *Details*:
   - Chọn block ngày (Mon–Sun) → **Set Days To Nonworking Time** cho T7-CN → **Advanced** nhập giờ: Start 08:00 AM, End 12:00, Resume 01:00 PM, Complete 05:00 PM → **OK**.
4. Tab **Exceptions / Holidays**: Add holiday → *Name: Tết 2027*, *Start/End date* → OK. Ngày nghỉ tự động loại khỏi duration.
5. Gán calendar cho task/resource: click task → `[Format] → Details → Calendar` hoặc trong Resource Info tab **General**.

## 4. WBS & nhập công việc (Step by step)

### 4.1 Nhập task đầu tiên
1. Ở **Task Sheet**, dòng đầu là *Project Name* (summary gốc). Click dòng **Task Name** thứ nhất.
2. Gõ tên → Enter. Nhập **Duration** (đơn vị `d` = day, `ed` = elapsed): `5d`.
3. Cột **Start/Finish** tự tính từ Project Start + calendar.

### 4.2 Tạo cấu trúc WBS (hierarchy)
1. Nhập danh sách flat các task lá theo nhóm: *Thiết kế → [mô-đun A, mô-đun B]*.
2. Bôi đen các task con (Shift-click).
3. `[Project] → Indent` `(Alt+Right)` → chúng trở thành con, task phía trên tự thành **Summary Task** (in đậm, có thanh rollup).
4. Muốn đổi mức: `Outdent` `(Alt+Left)`.
5. Xem mã WBS: `[Insert] → Column → WBS` (dạng 1, 1.1, 1.2…). Có thể sửa tay trong Project Information → tab WBS? (bản 1.6: click phải cột WBS → *WBS Code options*).

### 4.3 Thuộc tính task (Task Information)
Chọn task → double-click (hoặc `[Project] → Task Information`) — các tab:
- **General**: Name, Type (Fixed Units/Fixed Duration/Fixed Price), Start, Finish, Duration, %Complete, Priority, Notes.
- **Predecessors/Successors**: thêm/xóa link, loại FS/SS/FF/SF, Lag.
- **Constraints**: Constraint type + date (xem 6).
- **Calendar**: override lịch cho task.
- **Advanced**: Multi-critical path, Leveling delay, Baseline (view per baseline).
- **Custom Fields**: giá trị field tùy biến.

### 4.4 Loại task đặc biệt
- **Milestone**: Duration = `0d` → hiển thị hình thoi trên Gantt.
- **Recurring task**: `[Insert] → Recurring Task` → nhập pattern hàng tuần/tháng → OK.
- **Manual vs Auto Scheduled**: click phải task → *Scheduling Mode → Manually Scheduled* (ProjectLibre mặc định auto; manual cho phép nhập ngày trực tiếp).

## 5. Liên kết công việc & đường găng

### 5.1 Tạo dependency
1. Chọn task **predecessor** rồi Ctrl-click task **successor** (hoặc chọn nhiều liên tiếp).
2. Nhấn icon **Link** (chuỗi xích) trên toolbar hoặc `[Edit] → Links → Link (Predecessor)` `(Ctrl+F)`.
3. Mặc định tạo quan hệ **FS**. Đổi loại: mở Task Information → tab Predecessors → sửa *Type*; hoặc kéo mũi tên link trực tiếp trên Gantt.
4. Thêm độ trễ: cột **Predecessors** gõ `3FS+2d` (lag 2 ngày), `-1d` cho lead.

### 5.2 Kiểm tra logic
- Vòng lặp (loop): phần mềm highlight đỏ + dialog cảnh báo khi link gây cycle → gỡ link lỗi.
- Delete link: chọn successor → `[Edit] → Links → Remove All Predecessors` `(Ctrl+Shift+F)`; hoặc click mũi tên trên Gantt + Del.

### 5.3 Đường găng (Critical Path)
1. `[Format] → Critical` (toggle) → task slack ≤ 0 tô hồng + label Slack.
2. Xem giá trị: thêm cột **Total Slack**, **Early Start/Finish**, **Late Start/Finish**.
3. Ngưỡng critical: `[Tools] → Options → Calculation → Critical tasks: slack less than X days`.

## 6. Constraints & Split tasks

### 6.1 Đặt ràng buộc
1. Task Information → tab **Constraints**.
2. **Constraint type** (dropdown): *As Soon As Possible, As Late As Possible, Start On, Finish On, Must Start On, Must Finish On, No Earlier Than, No Later Than…*
3. Nhập **Constraint date** → OK. Task bị khóa lịch; icon tam giác xuất hiện ở cuối thanh Gantt.

### 6.2 Chia cắt task (Split)
1. Chọn task → `[Edit] → Tasks → Split Task` (biểu tượng tia chớp trên toolbar Gantt).
2. Click trên thanh Gantt tại vị trí muốn ngắt (vd nghỉ 3 ngày) → thanh tách đôi.
3. Gỡ split: Unlink? Không — chọn đoạn split → Delete, hoặc `Format → Schedule → ...`; cách an toàn: Task Information chỉnh lại duration/link.

## 7. Baseline (Đường cơ sở)

1. Sau khi kế hoạch hoàn tất: `[Project] → Set Baseline → Set → Whole project` → OK (lưu Baseline 1).
2. Trong Task Information tab **Advanced** có thể chọn *Baseline 2..7* (Plan 1–7 trong UI 1.6).
3. Hiển thị so sánh: `[Format] → Table → Variance` hoặc thêm cột **Baseline Start/Finish, Start Variance, Finish Variance**.
4. Trên Gantt: bật *Baselines* (right-click chart → Gridlines/Baselines?) — bản 1.6: `[Format] → Details → Baselines` tick show; thanh phụ xám hiển thị underneath.
5. Khi cập nhật thực tế, variance = Actual/Current − Baseline giúp phát hiện trễ.

## 8. Resources & Assignments

### 8.1 Tạo resource pool
1. Chuyển sang tab **Resource Sheet** (dưới cùng khu bảng) hoặc `[View] → Resources → Resource Sheet`.
2. Nhập từng dòng:
   - **Resource Name**: *Dev-A*
   - **Type**: *Work / Material / Cost*
   - **Initials, Material Label** (cho material: "kg", "giờ ca máy")
   - **Standard Rate**: `500000/d` hoặc `8/h` (VND); **Overtime Rate**
   - **Cost/Use**: chi phí cố định mỗi lần dùng
   - **Max Units**: `100%` (1 người full-time), `300%` (3 đơn vị)
   - **Base Calendar, Base Holiday**: override lịch riêng
3. Resource Information (double-click): tab General/Assignment/Rates — khai báo **rate table theo thời gian** (A→E, From date).

### 8.2 Gán resource cho task
Cách 1 – Assignment window: chọn task → `[Project] → Assignments → Assign Resources` `(Ctrl+=)` → chọn resource → **Assign** → đơn vị `%` hoặc số lượng → Close.
Cách 2 – Entry trực tiếp: thêm cột **Resource Names** vào Task Sheet → gõ tên, phân cách dấu `,`.
Cách 3 – Resource Usage view kéo thả.

### 8.3 Điều chỉnh assignment
- Double-click ô trong **Resource Usage** → *Assignment Information*: Units %, Work, Overtime, Cost, Remaining.
- Over-allocated (vượt tải) hiển thị màu đỏ ở Resource Sheet → san bằng (mục 12).
- Ngưng gán: Assign Resources → chọn → **Replace/Unassign**.

### 8.4 Resource pool dùng chung nhiều dự án
1. Mở dự án A → `[View] → Resource Pool → Share Resources` → *Use resources from:* trỏ tới file B (hoặc current) → OK.
2. Đổi thành *Pool-only file*: `[View] → Resource Pool → Open Pool File` (file rỗng chứa resource master).
3. Khi mọi project share xong: `[View] → Resource Pool → Close Pool File`.

## 9. Chi phí & ngân sách

1. Tự động tính: Cost = f(rate, work, cost/use, accrual). Thêm cột **Cost, Fixed Cost, Total Cost, Baseline Cost, Actual Cost, Remaining Cost** qua `[Insert] → Column`.
2. **Fixed cost**: Task Information → *Fixed Cost* + accrual (Beginning/End/Prorated).
3. **Cost table A–E**: Task Information → tab Advanced? (UI: cột *Cost Table*) — áp rate khác theo giai đoạn.
4. Kiểm tra tổng: `[Project] → Project Information → Statistics → Cost` và **Budget cost** sau khi set baseline.
5. Biểu đồ chi phí tích lũy: `[View] → Reports → Budget/Cost curves` (report wizard).

## 10. Theo dõi tiến độ (Tracking)

### 10.1 Cập nhật % hoàn thành
1. Chọn task → gõ **% Complete** (0–100) trong sheet, hoặc kéo slider trên thanh Gantt.
2. Chi tiết hơn: Task Information → tab General → *Actual Start* (ngày bắt đầu thật).
3. **Update Project**: `[Project] → Update Project` → *Update tasks: Entire project / Selection* → *Update work as complete through: Today* → OK → tự tính % dựa work thực.

### 10.2 Nhập actuals cấp assignment
1. `[View] → Resource Usage` → bảng dưới: chọn row *Actuals* (Add row: right-click → Rows → Actual).
2. Click ô timephased → nhập **Actual Work / Actual Overtime / Remaining Work** theo tuần.
3. **Mark on Top** (toolbar tracking): đánh dấu task đang chạy; **Mark as Presented** tạo progress line.
4. **Status Date**: `[Project] → Status Date` — mọi actual tính đến ngày này.

### 10.3 Progress line & re-plan
- Bật đường tiến độ: right-click Gantt → *Show Progress Line* (vertical at status date).
- Re-plan: giữ Baseline cũ, sửa remaining duration → variance tự cập nhật.

## 11. EVM (Earned Value Management)

1. Đảm bảo: Baseline đã set + resources rates + actuals nhập.
2. Thêm cột EVM: `[Insert] → Column` → chọn:
   - **BCWS** (Budgeted Cost of Work Scheduled)
   - **BCWP** (Budgeted Cost of Work Performed / Earned Value)
   - **ACWP** (Actual Cost of Work Performed)
   - **CPI**, **SPI**, **CV** (Cost Variance), **SV** (Schedule Variance)
   - **TCPI**, **EAC/IEAC**, **VAC** (Budget At Completion − EAC).
3. Đọc kết quả: CPI < 1 ⇒ vượt chi; SPI < 1 ⇒ trễ tiến độ.
4. Đồ thị: `[View] → Reports → Earned Value Analysis` (wizard tạo curve S BCWS/BCWP/ACWP).

## 12. Resource Leveling

1. Xác định over-allocation: `[View] → Resource Sheet` (icon đỏ) hoặc filter *Involving leveling*.
2. Chạy: `[Tools] → Level Resources`:
   - **Level within available slack** first (an toàn nhất).
   - **Level across assignments / entire project**.
   - Order: *Priority then standard*; *ID then priority*.
   - Range: *From/To dates*; check *Auto level during editing* nếu muốn real-time.
3. **Leveling** → OK → ProjectLibre dịch finish task (tạo *Leveling Delay*), Gantt cập nhật.
4. Undo leveling: `[Tools] → Level Resources → Accept all leveling suggestions`? — thực tế dùng **Undo** `(Ctrl+Z)` hoặc clear leveling delay trong Task Information → Advanced (*Leveling Delay = none*).
5. Review kết quả: thêm cột **Leveled Start/Finish, Leveling Delay**.

## 13. Các View chính

| View | Cách mở | Dùng khi nào |
|------|---------|--------------|
| Gantt Chart | `[View] → Gantt Chart` | Tiến độ tổng thể, link, baseline |
| Task Sheet | tab dưới bảng | Nhập liệu nhanh dạng bảng |
| Network Diagram (PERT) | `[View] → Network` | Logic mạng, node box tùy biến |
| Timeline | `[View] → Timeline` | Hình khối milestone cho báo cáo |
| Resource Sheet | `[View] → Resources → Resource Sheet` | Định nghĩa resource |
| Resource Usage | `[View] → Resources → Resource Usage` | Work/timephased, actuals |
| Loading (Histogram) | `[View] → Resources → Loading` | Over-allocation bars |
| Calendars | `[View] → Calendars` | Ngày làm/nghỉ |
| Documents | `[View] → Documents` | Đính kèm tài liệu vào task |

**Tùy biến Gantt:** right-click chart → *Bar styles* (milestone/summary/split), *Text styles* (label left/right), *TimeScale* (day/week/month/quarter), *Gridlines*, *Sort & Group* (`[Project] → Sort/Group`).

## 14. Custom Fields & Calculated Fields

### 14.1 Tạo custom field
1. `[Project] → Custom Fields…` → *Field name*: chọn `Text1` (Task) hoặc `Number1` (Resource).
2. **Rename** → "Phòng ban phụ trách".
3. **Type**: Text/Number/Date/Cost/Flag/Percent…
4. **Values for selection**: Add các giá trị dropdown (KT, KD, HC…).
5. **Calculation** (nếu Number/Date/Cost): *Rollup* cho summary (Sum/Average/Count…).
6. Tick **Use formula** → *Formula…* (calculated field): vd `IF([Percent Complete]>=100,"XONG","CHẠY")` — trình soạn thảo công thức giống Excel, kéo field từ nút *Field…*.
7. **OK** → thêm cột: `[Insert] → Column → Custom fields → Phòng ban phụ trách`.

### 14.2 Group & Sort theo custom field
`[Project] → Group → Define Groups` → group by *Phòng ban* → Apply; `[Project] → Sort` ascending/descending.

## 15. Báo cáo & in

### 15.1 Report Wizard
1. `[File] → Print Preview` hoặc `[View] → Reports → Create Report…` (Report Wizard).
2. Bước 1: chọn **Template** (Blank, Overview, Current Activities, Upcoming Milestones, Project Summary, Earned Value, Budget…).
3. Bước 2: chọn **Elements**: Table / Chart / Image / Text; sắp xếp thứ tự.
4. Bước 3: cấu hình Table (columns, grouping, filters) / Chart (type: Bar, Pie, Line, XY; series data).
5. Bước 4: **Page Setup** (orientation, header/footer) → **Finish** → preview.
6. Lưu report template: Save trong wizard → tái sử dụng.

### 15.2 Xuất báo cáo
- `[File] → Export Report` (trong preview) → **PDF / HTML / XML / Image (PNG)**.
- In trực tiếp: `[File] → Print` `(Ctrl+P)` → Page setup: *fit to 1 page wide*, scale %, paper size.

### 15.3 Copy chart
Click phải Gantt → `[Edit] → Copy Picture` → paste vào Word/PowerPoint (chọn metafile/bitmap).

## 16. Import/Export & MS Project interop

| Đích | Các bước |
|------|----------|
| Mở .mpp | `[File] → Open` → filter *Microsoft Project Files (*.mpp)* → Open. Cảnh báo mất thuộc tính hiếm → OK. |
| Lưu .mpp | `[File] → Save As` → format *MS Project 2000-2016 (*.mpp)* |
| Universal Process XML | `[File] → Save As` → *.xml (UPXML)* |
| CSV task list | `[File] → Save As` → *.csv* (chỉ bảng hiện hành) |
| PDF | `[File] → Print` → printer *Microsoft Print to PDF*/export report PDF |
| HTML | Report Wizard → Export HTML |

**Nguyên tắc round-trip an toàn:** tránh constraint lạ, giữ tên resource ASCII, kiểm tra lại baseline sau khi mở đi mở lại giữa 2 phần mềm.

## 17. Phụ lục phím tắt

| Chức năng | Phím | Chức năng | Phím |
|-----------|------|-----------|------|
| New/Open/Save | Ctrl+N/O/S | Link tasks | Ctrl+F |
| Save As | Ctrl+Shift+S | Unlink all preds | Ctrl+Shift+F |
| Undo/Redo | Ctrl+Z / Ctrl+Y | Indent/Outdent | Alt+→ / Alt+← |
| Copy/Paste | Ctrl+C/V | Assign resources | Ctrl+= |
| Find | Ctrl+F (Edit→Find) | Toggle Critical | Format menu |
| Print | Ctrl+P | New task | Insert key |

---
Xem quy trình nghiệp vụ chuẩn: [04_processes_workflows.md](04_processes_workflows.md) • Tình huống mẫu: [07_case_studies.md](07_case_studies.md)
