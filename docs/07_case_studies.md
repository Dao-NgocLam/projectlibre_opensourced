# 07 — Case Studies (Tình Huống Nghiên Cứu) ProjectLibre

**Phiên bản:** 1.0 | Mỗi case gồm: bối cảnh → yêu cầu → triển khai từng bước trên ProjectLibre → kết quả/bài học. Số liệu minh họa mang tính giáo dục.

---

## CASE 1 — Dự án xây dựng nhà phố 3 tầng (Xây dựng, Waterfall)

### 1. Bối cảnh
Công ty TNHH Xây Nhà Việt nhận thi công nhà phố 120 m², giá trị hợp đồng **2,4 tỷ VND**, tiến độ cam kết **120 ngày làm việc** khởi công 09/11/2026. PM là anh Minh chỉ có laptop văn phòng, ngân sách phần mềm = 0 ⇒ chọn ProjectLibre thay MS Project.

### 2. Yêu cầu nghiệp vụ
- WBS ≥ 3 mức theo giai đoạn: Móng → Thân → Hoàn thiện → Bàn giao.
- Lịch nghỉ Tết Nguyên Đán 09/02–17/02/2027 phải tự động loại khỏi tiến độ.
- Theo dõi bê tông thương phẩm (material resource) + tổ đội (work resource).
- Báo cáo tuần cho chủ đầu tư dạng PDF có baseline so sánh.

### 3. Triển khai step-by-step
1. **Khởi tạo**: `File→New`; Save As `NHAVIET_NHAPHO120.mpp`; Project Information: start 09/11/2026, currency VND (`Tools→Options→Currency`).
2. **Calendar**: Change Working Time → Standard → Exceptions → thêm "Tết 2027" 09/02–17/02; thêm "Ngày mưa dự phòng" khi gia hạn.
3. **WBS**: nhập 28 task; Indent thành 4 summary + milestone "Bàn giao mặt bằng móng" (0d).
4. **Logic**: link FS toàn tuyến; riêng "Trát tường" SS+2d với "Xây tô"; kiểm tra Critical → đường găng qua cột+dầm+sàn đúng kỹ thuật.
5. **Resources** (Resource Sheet):
   - Work: Tổ trưởng 800k/d (max 100%), Thợ ×6 rate 400k/d (max 600%).
   - Material: Bê tông M250 – 12.500k/m³, material label "m³".
   - Cost: Máy cẩu thuê 3.000k/ca.
6. **Assign**: task "Đổ bê tông sàn 2" gán Tổ 600% + Bê tông `45 m³` (Material → work không đổi, cost = qty×rate) + Fixed cost chống thấm 40 triệu (accrual Beginning).
7. **Baseline**: sau họp duyệt 05/11 → Set Baseline Whole project (BL1); Table→Variance bật cho báo cáo.
8. **Tracking tuần**: mỗi sáng thứ Hai Update Project đến today; ghi actual ngày đổ bê tông thật; %Complete theo nghiệm thu khối lượng.

### 4. Kết quả đo được (số liệu giả lập minh họa)
| Chỉ số | Tuần 8 | Tuần 16 |
|--------|--------|---------|
| SPI | 0,96 | 1,02 |
| CPI | 0,98 | 0,99 |
| Finish forecast | trễ 6 ngày | về đích 07/03/2027 (+2 ngày so BL do mưa) |

Nhờ critical path hiển thị sớm, PM tăng ca tổ thép (units 600→700%) ở tuần 9 bù 6 ngày ⇒ bàn giao trễ 2 ngày, phạt hợp đồng giảm từ kịch bản 6 ngày xuống 2.

### 5. Bài học
- Calendar holiday là bắt buộc với dự án VN (Tết); nếu quên → forecast sai ~9 ngày.
- Material resource giúp tách chi phí bê tông khỏi nhân công, đối chiếu P&L dễ.

---

## CASE 2 — Triển khai hệ thống ERP cho chuỗi bán lẻ (CNTT, Hybrid)

### 1. Bối cảnh
Công ty IT Solutions triển khai ERP cho 12 cửa hàng: khảo sát → thiết kế → cấu hình → migrate dữ liệu → đào tạo → go-live rolling. Đội 9 người đa dự án (PM Lan quản lý 3 project cùng pool consultant).

### 2. Yêu cầu
- Resource pool dùng chung consultant giữa 3 file dự án.
- Custom field "Module" (Finance/Sales/WMS…) để group báo cáo.
- Phát hiện sớm consultant bị over-allocation > 120%.
- Báo cáo EVM hằng tháng cho Ban giám đốc.

### 3. Triển khai
1. Tạo file pool `POOL_IT.mpp`: khai báo 9 resources (rates USD/day), Max Units theo hợp đồng lao động.
2. Trong từng project: `View→Resource Pool→Share Resources→Use resources from POOL_IT.mpp`.
3. WBS 6 phase; milestone Go-live từng cụm cửa hàng constraint *Must Finish On* ngày ký SLA.
4. Custom Fields: Text1 → rename "Module", values [FIN,SAL,WMS,HRM]; thêm cột vào sheet; Group by Module cho dashboard.
5. Assign consultant; `View→Loading` thấy Dev-B đỏ 140% tuần 12 ⇒ `Tools→Level Resources→within available slack` → finish date phase UAT dịch +3d, chấp nhận vì slack đủ.
6. Baseline trước kick-off; cuối tháng: insert BCWS/BCWP/ACWP/CPI/SPI; Report Wizard template Earned Value → export PDF gửi BGĐ.

### 4. Kết quả
- Không còn double-booking consultant (trước đây xung đột lịch ~6 ngày/tháng).
- CPI tháng 3 = 1,04 (dưới budget), SPI = 0,97 do khách chậm cung cấp dữ liệu migrate → SV −3 ngày, đã điều chỉnh lag SS ở phase migration.

### 5. Bài học
- Resource pool chia sẻ là tính năng "đắt giá" nhất khi PM đa dự án dùng bản free.
- Should đặt reason-code delay (Custom Field Flag/Text) ngay từ đầu để phân tích root cause khi SPI giảm.

---

## CASE 3 — Tổ chức hội nghị khách hàng 500 người (Marketing, deadline cố định)

### 1. Bối cảnh
Sự kiện fixed-date 30/01/2027; mọi thứ phải xong trước 27/01. Ngân sách 900 triệu. Điều phối vendor ngoài (advertising, catering, sân khấu).

### 2. Triển khai chính
1. Start từ **finish date**: Project Information → *Project finish date* 30/01/2027 → scheduler tính ngược (ALAP mặc định các task ASAP chuyển hướng theo constraint FO của sự kiện).
2. Milestone chain: Chốt concept (D-60) → Ký venue (D-45) → Thư mời (D-30) → In ấn (D-14) → Tổng duyệt (D-3).
3. Vendor = Cost resources (fixed cost per use) để không lẫn nội bộ.
4. Vì lịch dồn dập: bật **Critical**; mọi task slip 1 ngày đều hiện red; PM họp daily 15 phút xem Total Slack.
5. Tracking: %Complete theo deliverable checklist; Update Project thứ Năm hằng tuần.

### 3. Kết quả & bài học
- Sự kiện diễn ra đúng ngày; tổng chi phí 872 triệu (CPI 0,97).
- Bài học: với deadline cứng, dùng finish-date-driven + milestone constraints, tránh leveling làm dịch task sau ngày D-3 (đã khóa bằng Must Finish On).

---

## CASE 4 — Di trú dữ liệu từ MS Project sang ProjectLibre (Chuyển đổi số / Cost-saving)

### 1. Bối cảnh
Ban QLDA giao thông có 40 file `.mpp` (MS Project 2016) và 25 PM; license MSP hết hạn, chi phí gia hạn lớn. Quyết định: chuyển hạ hoạch sang ProjectLibre nhưng vẫn trao đổi file với tư vấn dùng MSP.

### 2. Quy trình migration step-by-step
1. **Inventory**: liệt kê 40 file, gắn nhãn độ phức tạp (baseline nhiều bộ, custom fields, VBA macro).
2. **Pilot 5 file**: mở trong PL 1.6.2 (`File→Open .mpp`) → chạy checklist đối chiếu:
   - Số task, link, duration ±0 ngày; cost ±1%; baseline 1; critical path trùng nhau.
   - Ghi chú mất mát: một số *constraint type* lạ map về ASAP; VBA macro không chạy → script lại bằng Excel bên ngoài.
3. ** Đào tạo**: workshop 2 buổi dùng tài liệu 03+04; thi test TC-01..TC-12 (tài liệu 08).
4. **Quy chế round-trip**: file master lưu `.pod`; khi gửi tư vấn mới `Save As .mpp`; cấm sửa tiếp vòng lặp MSP↔PL quá 2 lần không review.
5. **Nâng cấp tập trung**: MSI deploy qua GPO; tắt telemetry.

### 3. Kết quả
- Tiết kiệm ~USD 25.000/năm license; 38/40 file migrate nguyên vẹn, 2 file rebuild thủ công 4 giờ.
- Bài học: luôn giữ **một nguồn sự thật** (single source of truth) và baseline đối chứng để phát hiện drift sau mỗi round-trip.

---

## Bảng tổng hợp Case ↔ Tính năng ↔ Tài liệu tham chiếu

| Case | Ngành | Tính năng nổi bật | Xem thêm |
|------|-------|-------------------|----------|
| 1 | Xây dựng | Calendar holidays, Material resources, Variance table | UC-02/07/08, manual §3/§8/§10 |
| 2 | CNTT | Resource Pool sharing, Leveling, EVM reports | UC-18/12/11, manual §8.4/§11/§15 |
| 3 | Sự kiện | Finish-date scheduling, Constraints, Critical monitoring | UC-05, manual §3.1/§6.1 |
| 4 | Chuyển đổi | .mpp interop, CM process | UC-16, manual §16, doc 08 §round-trip |
