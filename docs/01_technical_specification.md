# 01 — Mô tả Kỹ thuật ProjectLibre (Technical Specification)

**Phiên bản:** 1.0 | **Ngày:** 03/11/2026 | **Áp dụng:** ProjectLibre 1.6.x (tag `v1.6.2`)

---

## 1. Tổng quan hệ thống

### 1.1 Định nghĩa sản phẩm
ProjectLibre là ứng dụng desktop **quản lý dự án mã nguồn mở**, viết bằng **Java**, tương thích định dạng và gần như tương đương chức năng Microsoft Project Standard/Professional. Sản phẩm kế thừa kiến trúc của OpenProj (ProjectOpen) và do Serlina / Community phát triển tiếp.

### 1.2 Mục tiêu kỹ thuật
- Đọc/ghi file **MS Project `.mpp`** (2000→2016) không cần cài MS Project.
- Engine lập lịch **CPM (Critical Path Method)** thuần Java: forward/backward pass, calendar-aware.
- Hỗ trợ **Earned Value Management (EVM)** đầy đủ (BCWS, BCWP, ACWP, CPI, SPI, CV, SV…).
- Chạy đa nền tảng: Windows, macOS, Linux — chỉ cần JRE/JDK.
- Kiến trúc module hóa, dễ mở rộng qua **Custom Fields** và script (Rhino JavaScript).

### 1.3 Bối cảnh mã nguồn (repository hiện tại)
Repo gồm các module chính (Ant build):

| Module | Vai trò | Package chủ lực |
|--------|---------|-----------------|
| `openproj_core` | Framework lõi: document, session, field, undo, preference, print, scripting, workspace | `com.projity.*` (document, session, field, undo, grouping, timescale, algorithm, command, configuration…) |
| `projectlibre_core` | Mô hình nghiệp vụ PM mới: tasks, resources, scheduler, calendar | `com.projectlibre.pm.*` (tasks, resources, scheduler, scheduling, calendar), `com.projectlibre.core.*` (fields, hierarchy, id, time, versions, nodes) |
| `openproj_ui` | Giao diện Swing, dockable windows, FlatLaf/Plastic L&F | `org.projectlibre.*`, `org.pushingpixels.*` (Trident animation), `com.projectlibre.ui` |
| `openproj_exchange` | Import/export: MPXJ, MPPParser, XML, PDF, HTML | `com.projectlibre.exchange`, `com.projity.exchange` |
| `projectlibre_exchange` | Lớp tương thích MPXJ (`net.sf.mpxj`) cho đọc/ghi .mpp | `net.sf.mpxj.*` |
| `openproj_reports` | Công cụ báo cáo JasperReports | `org.eclipse.birt`/report engine |
| `openproj_build` | Script build Ant, đóng gói installer (InstallAnywhere/Inno Setup/dmg) | — |
| `openproj_contrib` | Thư viện bên thứ ba (commons, jdom, itext, poi…) | — |

### 1.4 Sơ đồ kiến trúc tổng quát (logical view)

```
┌───────────────────────────────────────────────────────────────┐
│                    PRESENTATION LAYER (Swing)                 │
│  ProjectViewBean │ GanttView │ ResourceView │ Network │ Cal   │
│  DockingFrames (VLDocking) │ FlatLaf Look&Feel │ Menus/Toolbar│
├───────────────────────────────────────────────────────────────┤
│                     APPLICATION LAYER                         │
│  DocumentBase / ProjectDocument │ Session │ Commands │ Undo   │
│  Workspace │ Preferences │ Print/Report engine                │
├───────────────────────────────────────────────────────────────┤
│                       DOMAIN LAYER (PM Model)                 │
│  Task ── Dependency ── Resource ── Assignment ── Calendar      │
│  Scheduler (CPM): ForwardPass, BackwardPass, Leveling          │
│  FieldModel (Field, FieldType, Calculated/Custom Fields)       │
├───────────────────────────────────────────────────────────────┤
│                      PERSISTENCE / EXCHANGE                   │
│  .pod (native XML, JDOM) │ .mpp/.mpx (MPXJ/MPPParser)         │
│  Export: PDF (iText), HTML, XML, CSV, PNG                     │
└───────────────────────────────────────────────────────────────┘
```

## 2. Thành phần chi tiết (Components)

### 2.1 Domain model (`com.projectlibre.pm`)
- **Task**: WBS, duration (DurationType), start/finish, slack, critical flag, type (Fixed Units/Duration/Price), percent complete, baseline (7 bộ: Start1..Start7…), constraints (SNET, SNFT, FNLT, FNET…), calendars, notes, custom fields.
- **Dependency (Predecessor)**: FS/SS/FF/SF + lag/lead; multi-predecessor; driving/non-driving logic.
- **Resource**: Work/Material/Cost loại; units (% allocation), max units, standard & overtime rate, cost/use, accrual (Beginning/Prorated/End), resource calendar, resource pool flags.
- **Assignment (ResourceTask)**: link Resource↔Task; work, overtime, remaining work, actual cost, EVM values per assignment.
- **Calendar (ProjectCalendar)**: working week, exceptions (holidays), shift patterns; tích hợp `java.util.TimeZone`.
- **Scheduler**: thuật toán lập lịch CPM 2 pass, hỗ trợ interrupt/resume, recalculates incremental khi user sửa; leveling heuristic based on priority/slack.

### 2.2 Field framework (`com.projity.field`, `com.projectlibre.core.fields`)
- Mọi cột trong bảng (Task/Resource table) là một **Field** có `FieldType` (String, Integer, Double, Date, Money, Percent, Duration, Bit…).
- **Calculated Fields**: công thức lưu dưới dạng AST (`ScriptedFormula`), chạy trên Rhino JS engine.
- **Custom Fields**: Text1-30, Number1-20, Date1-15, Cost1-20, Flag1-15, Aspect1… — map trực tiếp vào thuộc tính `Node` qua `FieldManager`.

### 2.3 Document & Session (`com.projity.document/session`)
- `ProjectDocument extends DocumentBase`: quản lý lifecycle (new/open/save/save-as/close), dirty flag, UndoManager (Command pattern, `com.projity.undo`).
- `Session`: singleton ứng dụng, registry các DocumentFactory theo extension (.pod/.mpp/.mpx).
- `Workspace`: trạng thái cửa sổ (dock layout) lưu theo phiên.

### 2.4 Exchange layer
- **Read**: MPXJ (`net.sf.mpxj`) parse binary `.mpp` → chuyển đổi sang domain ProjectLibre; MPPParser legacy cho version cũ.
- **Write**: xuất `.mpp` (MPXJ writer), `.pod` (XML tự định nghĩa — xem tài liệu 06), `.mpx` (text legacy), Universal Process XML (`.xml`).
- **Export report**: PDF qua iText/Apache FOP pipeline; HTML/XHTML; image PNG (render Gantt).

### 2.5 UI layer
- Java Swing + **VLDocking Framework** (panel có thể kéo thả), Look & Feel FlatLaf (các bản 1.6+) hoặc Plastic/Office 2003 (bản cũ).
- Views: **Gantt Chart, Task Sheet, Resource Sheet, Resource Usage, Timeline/PERT Chart (Network), Calendars, Documents (notes), Reports**.
- `ProjectViewBean`: component trung tâm chứa bảng + chart đồng bộ selection/scroll.

## 3. Yêu cầu phi chức năng (NFR)

| Nhóm | Yêu cầu |
|------|---------|
| Hiệu năng | Dự án ≤ 10.000 tasks, ≤ 1.000 resources; re-calculate < 2 s với 1.000 tasks (máy i5/8GB) |
| Bộ nhớ | JVM heap mặc định `-Xmx512m`; khuyến nghị `-Xmx1g` cho dự án lớn |
| Tương thích | Windows 8/10/11 (x86/x64), macOS 10.13+ (Intel & Apple Silicon qua Rosetta/JDK ARM), Linux GTK; JDK 8–21 |
| Đa ngôn ngữ | 30+ locale qua resource bundle (`FolderLocalization.properties`) |
| Bảo mật | Không server; quyền file OS; bản Cloud (thương mại) dùng HTTPS/OAuth |
| Khả năng truy cập | Keyboard shortcut toàn menu, zoom font, high-contrast theme |
| Tính sẵn dụng | Offline 100%; auto-save recovery file vào thư mục cache |

## 4. Công nghệ & thư viện

| Layer | Công nghệ |
|-------|-----------|
| Ngôn ngữ | Java 8+ (Swing, AWT, java.time compat shim) |
| Build | Apache Ant (`openproj_build/build.xml`), output: `ProjectLibre.jar` + libs |
| UI | Swing, VLDocking, FlatLaf, Trident animations |
| XML | JDOM / Xerces |
| Office interop | MPXJ (net.sf.mpxj), Apache POI (Excel export) |
| PDF | iText / Apache FOP |
| Scripting | Mozilla Rhino (JS cho calculated fields) |
| Reporting | JasperReports/BIRT-based report engine (`openproj_reports`) |
| Logging | Log4j-family internal logger, log folder per-user |

## 5. Mô hình triển khai (Deployment View)

```
[Người dùng] --installer--> Máy tính cá nhân (Fat client)
    ├── JRE bundled (Windows .exe / macOS .dmg / Linux .deb-rpm-tar.gz)
    ├── ProjectLibre.jar + lib/*.jar
    └── User data: ~/.ProjectLibre_v1.6/ (preferences, recent files, logs)

[Optional] ProjectLibre Cloud / Server (thương mại): Web app + REST, sync .pod
```

## 6. Giới hạn kỹ thuật đã biết
1. Không hỗ trợ **.mpp của MS Project 2019+/365** một số trường hợp (struct storage thay đổi); fallback: mở bằng MPXJ mới nhất hoặc convert qua MS Project 2016.
2. Resource leveling chưa tối ưu tổ hợp (heuristic, không LP/MIP).
3. Multi-user realtime không có ở bản Desktop (cần bản Cloud).
4. Macro VBA của MS Project không thực thi được; thay bằng Rhino scripting.

## 7. Thuật toán lập lịch (tóm tắt đặc tả)
1. **Forward pass**: với mỗi task theo thứ tự WBS/topological: `ES = max(ngày bắt đầu calendar-aware từ từng predecessor + lag, project start, constraint)`.
2. **Backward pass**: `LF = min(ngày kết thúc từ successor − lag, project finish)`.
3. **Slack** = LS − ES; **Critical** khi Slack ≤ 0 (ngưỡng configurable).
4. Lịch làm việc: bước nhảy theo `Calendar.getWorkingMinutes()` — ngày nghỉ/exception tự động dồn thời lượng.
5. **Effort-driven recalc**: `Duration = Work / (Units × Efficiency)` khi Fixed Units; giữWork khi Fixed Duration re-distribute.

## 8. Tài liệu liên quan
- Cài đặt: [02_installation_guide.md](02_installation_guide.md)
- Hướng dẫn sử dụng: [03_user_manual.md](03_user_manual.md)
- Từ điển dữ liệu/DTD: [06_data_dictionary_dtd.md](06_data_dictionary_dtd.md)
