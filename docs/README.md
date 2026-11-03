# Tài liệu Kỹ thuật & Hướng dẫn Sử dụng ProjectLibre

**Phiên bản tài liệu:** 1.0 — **Ngày biên soạn:** 03/11/2026
**Phần mềm tham chiếu:** ProjectLibre 1.6.x (mã nguồn mở, kế thừa từ OpenProj)

Bộ tài liệu này được viết cho **ProjectLibre** — phần mềm quản lý dự án mã nguồn mở tương thích Microsoft Project, phát triển bằng Java theo giấy phép GPL/CDDL, hỗ trợ Windows / macOS / Linux.

## Cấu trúc bộ tài liệu (Table of Contents)

| # | Tài liệu | Mô tả | Dành cho |
|---|----------|-------|----------|
| 1 | [01_technical_specification.md](01_technical_specification.md) | Mô tả kỹ thuật tổng thể: kiến trúc, module, công nghệ, định dạng file | Developer, Kiến trúc sư, Quản trị |
| 2 | [02_installation_guide.md](02_installation_guide.md) | Hướng dẫn cài đặt từng bước trên Windows/macOS/Linux, build từ mã nguồn | Quản trị hệ thống, Developer |
| 3 | [03_user_manual.md](03_user_manual.md) | Hướng dẫn sử dụng chi tiết từng bước (step-by-step) toàn bộ tính năng | PMO, Quản lý dự án, Người dùng |
| 4 | [04_processes_workflows.md](04_processes_workflows.md) | Quy trình nghiệp vụ chuẩn (SOP): lập kế hoạch, theo dõi, báo cáo, đóng dự án | PMO, Quản lý dự án |
| 5 | [05_use_cases_user_stories.md](05_use_cases_user_stories.md) | Use Cases (định dạng chuẩn UML) và User Stories kèm tiêu chí chấp nhận | BA, Tester, Developer |
| 6 | [06_data_dictionary_dtd.md](06_data_dictionary_dtd.md) | Từ điển dữ liệu (DTD/Data Dictionary) + cấu trúc XML Schema của file .mpp/.pod | Developer, Integrator |
| 7 | [07_case_studies.md](07_case_studies.md) | Tình huống nghiên cứu (Case Studies) đầy đủ theo ngành nghề | Người dùng, Đào tạo |
| 8 | [08_testing_troubleshooting_faq.md](08_testing_troubleshooting_faq.md) | Kiểm thử, sự cố thường gặp, FAQ, so sánh với MS Project | Support, QA, Người dùng |

## Giới thiệu nhanh về ProjectLibre

- **Website chính thức:** https://www.projectlibre.com
- **Loại phần mềm:** Quản lý dự án (Project Management) — thay thế miễn phí cho Microsoft Project
- **Phương pháp hỗ trợ:** Waterfall (biểu đồ Gantt, mạng PERT, đường găng CPM), Earned Value Management (EVM), Agile (thông qua ProjectLibre Cloud/plugin Scrum)
- **Định dạng file:** `.mpp` (MS Project 2000–2016), `.pod` (native XML), `.mpx`, export PDF/HTML/XML/CSV/PNG
- **Kỹ thuật lõi:** Java Swing UI, mô hình lịch trình (Scheduler) tự phát triển, MPPParser để đọc/ghi file MS Project
- **Mã nguồn:** GitHub (cấu trúc repo gồm các module `openproj_*` và `projectlibre_*` — đúng như repository hiện tại tại `/workspace`)

## Phiên bản tài liệu & Lịch sử cập nhật

| Phiên bản | Ngày | Tác giả | Mô tả thay đổi |
|-----------|------|---------|----------------|
| 0.1 | 20/10/2026 | Nhóm Tài liệu | Khung sườn ban đầu |
| 1.0 | 03/11/2026 | Nhóm Tài liệu | Phát hành đầy đủ 8 tài liệu |
