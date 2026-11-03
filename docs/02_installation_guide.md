# 02 — Hướng dẫn Cài đặt ProjectLibre (Installation Guide)

**Phiên bản:** 1.0 | **Áp dụng:** ProjectLibre 1.6.x | **Đối tượng:** Quản trị viên, người dùng cuối, developer

---

## A. Yêu cầu hệ thống (đặc tả trước cài đặt)

| Hạng mục | Tối thiểu | Khuyến nghị |
|----------|-----------|-------------|
| OS | Windows 8+, macOS 10.13+, Linux (GTK) | Windows 10/11, macOS 13+, Ubuntu 22.04+ |
| RAM | 1 GB | 4 GB+ |
| Đĩa | 500 MB | 1 GB |
| Java | JRE 8 (installer có thể bundle sẵn) | JDK/JRE 11–21 |
| Độ phân giải | 1024×768 | 1920×1080 |

## B. Cài đặt trên WINDOWS — step by step

### Cách 1: Dùng installer .exe (khuyến nghị)
1. Mở trình duyệt, truy cập **https://www.projectlibre.com/product/projectlibre-free-open-source-project-management**.
2. Nhấn nút **DOWNLOAD** chọn mục *Windows* → tải `ProjectLibre-Installer-x.x.x.exe` (~100 MB).
3. Tìm file đã tải trong `Downloads`, **nháy đúp** vào file.
4. Nếu xuất cảnh báo *Windows protected your PC / SmartScreen*: nhấn **More info → Run anyway**.
5. Welcome wizard hiện ra → nhấn **Next**.
6. Đọc EULA (CDDL/GPL) → chọn **I accept the terms** → **Next**.
7. Chọn kiểu cài: **Typical** (cho mọi người dùng) hoặc **Custom** (chọn thư mục) → **Next**.
8. Chọn đường dẫn: mặc định `C:\Program Files\ProjectLibre_v1.6\` → **Next → Install**.
9. Chờ thanh tiến trình chạy xong → tích *Launch ProjectLibre* → **Finish**.
10. Kiểm tra: Start Menu có shortcut **ProjectLibre v1.6**; màn hình khởi động hiển thị tab "Getting Started".

### Cách 2: Bản ZIP portable
1. Tải `ProjectLibre-x.x.x-win.zip` từ trang chủ hoặc SourceForge.
2. Chuột phải file → **Extract All…** → giải nén vào `D:\Apps\ProjectLibre`.
3. Vào thư mục, chạy `ProjectLibre.exe` (hoặc `projectlibre.bat`). Không cần quyền Admin.

### Gỡ cài đặt (Windows)
1. `Settings → Apps → Installed apps` (hoặc Control Panel → Programs and Features).
2. Tìm **ProjectLibre v1.6** → **Uninstall** → làm theo wizard.
3. Xóa dữ liệu người dùng (tùy chọn): `%USERPROFILE%\.ProjectLibre_v1.6`.

## C. Cài đặt trên MACOS — step by step

1. Truy cập trang chủ ProjectLibre → tải **macOS (dmg)** phù hợp chip (Intel / Apple Silicon).
2. Mở `Downloads/ProjectLibre-x.x.x.dmg` (nháy đúp).
3. Cửa sổ Finder hiện icon app và shortcut **Applications** → kéo **ProjectLibre.app** thả vào **Applications**.
4. Đóng dmg (chuột phải → Eject).
5. Lần đầu mở: `Launchpad → ProjectLibre`. Nếu báo *"cannot be opened because the developer cannot be verified"*:
   - Mở **System Settings → Privacy & Security** → mục Security → nhấn **Open Anyway** → xác nhận **Open**.
6. (Apple Silicon, nếu thiếu Java) Cài OpenJDK:
   - `brew install --cask zulu@17` rồi chạy app; hoặc dùng dmg đã bundle JRE.
7. Kiểm tra: app mở giao diện Swing với menu **File/Edit/View...** ở thanh trên cùng.

## D. Cài đặt trên LINUX — step by step

### Ubuntu/Debian (.deb)
```bash
# 1. Tải gói
wget https://downloads.sourceforge.net/projectlibre/ProjectLibre-1.6.2.deb -O /tmp/pl.deb
# 2. Cài đặt
sudo apt update && sudo dpkg -i /tmp/pl.deb || sudo apt -f install
# 3. Khởi chạy
projectlibre        # hoặc Activities → tìm "ProjectLibre"
```

### Fedora/RHEL (.rpm)
```bash
sudo rpm -ivh ProjectLibre-1.6.2.rpm
projectlibre
```

### Bản tar.gz universal (mọi distro)
```bash
tar -xzf ProjectLibre-1.6.2.tar.gz -C ~/opt/
cd ~/opt/ProjectLibre && ./ProjectLibre    # cần JRE 8+: sudo apt install default-jre
```

### Flatpak/Snap (cộng đồng)
```bash
flatpak install flathub com.projectlibre.ProjectLibre   # nếu repo cộng đồng có
```

## E. Build & chạy từ mã nguồn (Developer)

Repo tại `/workspace` là mã nguồn ProjectLibre (các module `openproj_*`, `projectlibre_*`).

### Bước 1 — Chuẩn bị công cụ
```bash
java -version    # cần JDK 8+ (khuyến nghị 8 hoặc 11 để tương thích Ant script cũ)
ant -version     # Apache Ant 1.9+
git clone <repo> projectlibre && cd projectlibre
```

### Bước 2 — Import vào IDE (Eclipse/IntelliJ)
1. `File → Import → Existing Projects into Workspace` (Eclipse) hoặc `Open` folder (IntelliJ).
2. Tích chọn tất cả module: `openproj_core`, `openproj_ui`, `openproj_exchange`, `openproj_reports`, `openproj_contrib`, `projectlibre_core`, `projectlibre_exchange`.
3. Thêm classpath: các jar trong `openproj_contrib/lib/*` và `projectlibre_exchange/lib/*`.
4. Cấu hình **Run Configuration**: Main class = `com.projectlibre.ProjectLibre` (class khởi động chính), VM args: `-Xmx1g -Dfile.encoding=UTF-8`.

### Bước 3 — Build bằng Ant
```bash
cd openproj_build
ant clean build            # biên dịch tất cả module → dist/ProjectLibre.jar
ant dist                   # tạo bản phân phối đầy đủ kèm libs
ant run                    # chạy thử từ source
```

### Bước 4 — Chạy sản phẩm build được
```bash
cd dist && java -jar ProjectLibre.jar
```

### Ghi chú xử lý lỗi build thường gặp
| Lỗi | Nguyên nhân | Cách sửa |
|-----|-------------|----------|
| `Unsupported class file major version` | JDK quá mới cho Ant cũ | Dùng JDK 11 hoặc nâng Ant ≥1.10.12 |
| Thiếu `tools.jar` | JDK ≠ 8 | Sửa `build.xml` bỏ reference tools.jar |
| Lỗi encoding properties Việt Nam | File `.properties` không UTF-8 | `-Dfile.encoding=UTF-8` khi build/run |

## F. Cấu hình sau cài đặt (First-run configuration)

1. **Chọn ngôn ngữ**: `Tools → Options → General → Language` → chọn *Tiếng Việt* (nếu có) hoặc English → Restart app.
2. **Cài ngày bắt đầu mặc định & lịch làm việc**: `Tools → Options → Calendar` (Start time 8:00 AM, End 5:00 PM, Mon–Fri).
3. **Thiết lập đơn vị tiền tệ**: `Tools → Options → Currency` (VND, symbol ₫, decimal ,).
4. **Tắt/Gửi anonymous usage statistics**: `Tools → Options → General` (bỏ tick *Send usage data* nếu muốn riêng tư).
5. **Kiểm tra phiên bản**: `Help → About ProjectLibre`.

## G. Cập nhật & rollback
1. Tải installer phiên bản mới hơn từ trang chủ.
2. Chạy installer — nó tự phát hiện bản cũ → **Upgrade** (giữ nguyên preferences).
3. File dự án `.mpp/.pod` độc lập nên an toàn khi nâng cấp.
4. Rollback: gỡ bản mới, cài lại file installer bản cũ đã lưu.

## H. Tài liệu liên quan
- Sử dụng phần mềm: [03_user_manual.md](03_user_manual.md)
- Khắc phục sự cố: [08_testing_troubleshooting_faq.md](08_testing_troubleshooting_faq.md)
