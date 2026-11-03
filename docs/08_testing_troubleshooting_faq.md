# 08 — Kiểm thử, Sự cố thường gặp, FAQ & So sánh

**Phiên bản:** 1.0 | Dành cho: QA/Support/User Helpdesk

---

## PHẦN A — DANH MỤC KIỂM THỬ CHỨC NĂNG (Functional Test Suite)

Mỗi test case: **TC-xx** — Tiền đề → Các bước → Kết quả mong đợi (ER). Áp dụng regression trước khi nâng cấp.

| ID | Tên test | Bước thực hiện | Expected Result |
|----|----------|----------------|-----------------|
| TC-01 | Tạo & lưu dự án | File→New; Save As .pod và .mpp; đóng; mở lại | File mở lại đúng tên, start date, không lỗi parse |
| TC-02 | Calendar holiday | Thêm holiday 5 ngày T7-CN kế tiếp; task 5d bắt đầu trước đó | Finish dịch đúng số ngày làm việc; non-working không tính |
| TC-03 | WBS indent/outdent | 5 task flat; indent 4; outdent 1 | Summary rollup duration = max(children); %Complete theo work |
| TC-04 | Dependency & loop | Link A→B→C→A | System cảnh báo circular; từ chối hoặc highlight đỏ |
| TC-05 | Critical path | Chuỗi FS 3 task + nhánh phụ slack 2d | Bật Critical chỉ tô 3 task chuỗi chính; Total Slack = 0 / 2d |
| TC-06 | Resource cost | Rate 10/h, units 100%, duration 8h | Cost=80; đổi units 50% ⇒ Work giữ (Fixed Units) Duration×2 |
| TC-07 | Baseline | Set BL; sửa finish +2d | Cột Finish Variance = 2d; Gantt baseline bar lệch |
| TC-08 | Tracking | Actual Start + 50% complete | Remaining work halved; progress line trên bar |
| TC-09 | EVM | BCWS=100, BCWP=80, ACWP=90 | CPI=0.89, SPI=0.8, CV=−10, SV=−20 hiển thị đúng |
| TC-10 | Leveling | 1 resource 200% cùng ngày | Sau level within slack: Leveled Start dịch, không còn red |
| TC-11 | Report PDF | Report Wizard Overview → Export | PDF đủ tiêu đề, font Unicode tiếng Việt không lỗi |
| TC-12 | Round-trip .mpp | Mở mpp MSP → Save As mpp → mở lại PL & MSP | Số task/link/baseline khớp ≥99%; không corrupt header |
| TC-13 | Undo depth | 50 thao tác liên tiếp rồi Ctrl+Z ×50 | Trạng thái quay về sau mỗi lệnh; Redo hoạt động |
| TC-14 | Crash recovery | Kill process khi dirty; khởi động lại | Prompt recovery file cache; restore gần nhất <2 phút |
| TC-15 | Custom field formula | Number1 = Cost×0.1; đổi Cost | Recalc tức thì; group/filter dùng được |
| TC-16 | Multi-language | Locale vi-VN; nhập "Quản trị" | UI/sheet/print hiển thị dấu chuẩn UTF-8 |
| TC-17 | Large project | Import 5.000 tasks | Mở <60s; scroll mượt; recalc <5s |
| TC-18 | Print fit | Gantt 200 dòng, fit 1 page wide | Preview đúng scale, không cắt cột |

### Kiểm thử phi chức năng
- **Hiệu năng**: benchmark TC-17 với JMeter-style script (dùng MPXJ sinh file lớn).
- **Tương thích OS**: ma trận Win10/11 × macOS(Intel/ARM) × Ubuntu LTS; JDK 8/11/17/21.
- **Bảo mật**: xác nhận offline-only; tắt telemetry trong Options; không port lắng nghe.

## PHẦN B — TROUBLESHOOTING (Sự cố thường gặp)

### Bảng triệu chứng → nguyên nhân → khắc phục
| # | Triệu chứng | Nguyên nhân khả dĩ | Cách xử lý step-by-step |
|---|-------------|--------------------|--------------------------|
| 1 | App không mở, chỉ splash rồi mất | JRE thiếu/corrupt; heap thấp | ① `java -version` ≥8; ② chạy `ProjectLibre.exe -Xmx1g`; ③ cài lại JDK Temurin 17; ④ xem log `~/.ProjectLibre_v1.6/logs` |
| 2 | Mở .mpp báo lỗi/khung trống | File MSP 2019+/365; macro; save nén | ① nhờ bên gửi Save As MSP 2016/`.xml`; ② nâng ProjectLibre mới nhất; ③ mở bằng MSP→Export XML→import UPXML |
| 3 | Tiếng Việt bị □□□ khi in/PDF | Font máy in không Unicode | ① Page setup chọn DejaVu Sans/Noto; ② xuất PDF qua report wizard thay vì printer |
| 4 | Task không tự dịch ngày khi link | Task ở Manual mode hoặc constraint khóa | ① click phải → Auto Scheduled; ② Constraints → ASAP |
| 5 | Date sai sau khi mở lại giữa MSP↔PL | Lag đơn vị khác nhau (elapsed vs working) | Chuẩn hóa lag về `d` working; kiểm tra calendar hai bên giống nhau |
| 6 | Over-allocation đỏ nhưng Level không đổi gì | Không còn slack; leveling range hẹp | ① tăng Max Units/thêm resource; ② nới range From/To; ③ chấp nhận kéo dài finish (what-if UC-13) |
| 7 | %Complete summary ≠ trung bình con | Rollup theo work vs duration | Task Information→Advanced? chỉnh *rollup* hoặc thống nhất method khai báo work |
| 8 | Chậm khi cuộn Gantt dự án lớn | Heap nhỏ, nhiều custom chart lines | ① `-Xmx2g`; ② ẩn Baselines/Gridlines; ③ lọc view theo phase |
| 9 | Undo không hoạt động sau Save As | Document reload reset stack | Thiết kế lại workflow: lưu sớm đặt tên ngay từ đầu |
| 10 | Crash khi paste ảnh vào Notes | Clipboard image >heap PermGen(old JDK) | Dùng Documents view đính file thay vì nhúng ảnh lớn |

### Quy trình escalation cho Helpdesk nội bộ
1. Thu thập: version (`Help→About`), OS, JDK, file `.pod` lỗi (ẩn danh hóa), steps tái hiện, log folder.
2. Tái hiện trên môi trường sạch (portable zip).
3. Tìm trên issue tracker GitHub/SourceForge + forum projectlibre.com.
4. Nếu bug mới: tối thiểu repro file → gắn nhãn `bug`, gửi cộng đồng; workaround song song.
5. Regression: thêm TC tương ứng vào bộ test Phần A.

## PHẦN C — FAQ (Hỏi đáp nhanh)

1. **ProjectLibre có miễn phí hoàn toàn?** Desktop community là mã nguồn mở (CDDL/GPL) miễn phí vĩnh viễn; bản Cloud/Server trả phí.
2. **Có thay thế được 100% MS Project?** ~90% usage phổ thông; thiếu: VBA macros, Timesheets server, Enterprise Project types, một số reporting Power BI.
3. **Chạy được file MS Project 365?** Hỗ trợ tốt nhất tới MSP 2016; 2019/365 nên export XML trước.
4. **Có bản portable Linux không cần root?** Có — tar.gz chạy `./ProjectLibre` với JRE hệ thống.
5. **Lưu baseline được mấy bộ?** 7 (Plan 1–7 trong UI 1.6).
6. **Quản lý multiple projects/resource pool?** Có qua Share Resources; dashboard portfolio gộp nằm ở bản Cloud.
7. **Có API/script tự động?** Không REST public ở desktop; scripting Rhino cho calculated fields; tích hợp ngoài qua file `.pod/.mpp` + MPXJ (Java library).
8. **In Gantt A0?** Print dialog hỗ trợ scale %/fit pages; cách khác: xuất PNG độ phân giải cao.
9. **Dữ liệu có gửi về hãng?** Chỉ anonymous usage stats tùy chọn, tắt được trong Tools→Options.
10. **Khác biệt OpenProj vs ProjectLibre?** Cùng gốc; ProjectLibre tiếp tục phát triển scheduler/calendar engine mới (`com.projectlibre.pm.*`) — dùng bản này.
11. **Làm sao tính EV khi chưa gán resource rate?** EVM cost-based yêu cầu rates; nếu rỗng → BCWP=0; dùng %duration thay thế (khai báo giới hạn).
12. **Đổi giờ công/ngày mặc định?** Tools→Options→General "Hours per day" (mặc định 8).

## PHẦN D — SO SÁNH PROJECTLIBRE ↔ MS PROJECT ↔ OPENPROJ

| Tiêu chí | ProjectLibre 1.6 | MS Project Std/Pro | OpenProj 1.4 (legacy) |
|----------|------------------|--------------------|------------------------|
| Giá | 0đ (desktop OSS) | ~USD 400+ license / subscription | 0đ (ngừng phát triển) |
| Nền tảng | Win/mac/Linux (Java) | Windows (+Web/iOS) | Win/Linux cũ |
| Đọc .mpp | ✔ (≤2016 tốt) | native | một phần |
| CPM/Slack/Critical | ✔ | ✔ | ✔ |
| Baseline | 7 bộ | 11 bộ | 1 bộ |
| EVM | ✔ đủ các chỉ số | ✔ + Project Server | cơ bản |
| Leveling | heuristic | heuristic + LP cải tiến | heuristic |
| Resource pool đa file | ✔ | ✔ (Enterprise) | ✔ |
| Custom fields/formula | ✔ Text/Num/Date/Cost/Flag + JS | ✔ mạnh hơn + lookup enterprise | hạn chế |
| Reporting | Wizard PDF/HTML | Power BI/Portfolio | Wizard cũ |
| Collaboration realtime | ✘ (Cloud trả phí) | Teams/Project for Web | ✘ |
| Macro/Automation | Rhino script nội bộ | VBA/PowerShell full | ✘ |
| Localize tiếng Việt | một phần (cộng đồng dịch) | official | ✘ |

**Khi nào chọn ProjectLibre:** ngân sách 0, team nhỏ vừa, cần .mpp interchange, OS đa dạng, muốn dữ liệu nằm local. **Khi cần MSP:** enterprise PMO, Project Server/Online, macros phức tạp, portfolio PPM sâu.

## PHẦN E — CHECKLIST VẬN HÀNH ĐỊNH KỲ (cho quản trị)
- [ ] Weekly: backup file master lên snapshot; verify mở lại được.
- [ ] Monthly: đối chiếu chi phí actual vs accounting (export CSV).
- [ ] Quarterly: nâng cấp ProjectLibre trên 1 máy pilot + chạy TC-01..TC-12 trước khi đại trà.
- [ ] Per-project close: archive `.pod+.mpp+PDF` vào hồ sơ; khóa quyền sửa.

## Tài liệu liên quan
- Bộ test ánh xạ use case: [05_use_cases_user_stories.md](05_use_cases_user_stories.md) §5
- Hướng dẫn interop chi tiết: [03_user_manual.md](03_user_manual.md) §16
