# 06 — Từ Điển Dữ Liệu (Data Dictionary / DTD) & Cấu Trúc File ProjectLibre

**Phiên bản:** 1.0 | Phạm vi: lược đồ dữ liệu logic, đặc tả file `.pod` (XML), ánh xạ `.mpp`, định nghĩa **DTD/XML Schema** cho tích hợp hệ thống.

---

## 1. Mô hình dữ liệu logic (Entity-Relationship)

```
Project (1) ──< Task (N)          : WBS tree qua parentId
Project (1) ──< Resource (N)
Task (M) >──< Resource            : thông qua Assignment (ResourceTask)
Task (1) ──< Dependency (N)       : predecessor/successor + type + lag
Project (1) ──< Calendar (N)      : Standard/Shift2/Baseline...; Task/Resource → Calendar (N:1)
Task (1) ──< BaselineSet (7)      : Start1..Finish7, Work1..Cost7
Node (abstract) ── CustomFields   : Text1..30, Number1..20, Date1..15, Cost1..20, Flag1..15
```

## 2. Từ điển dữ liệu — thực thể PROJECT

| Trường | Kiểu | Bắt buộc | Mặc định | Miêu tả / Ràng buộc |
|--------|------|----------|----------|---------------------|
| id | UUID(long) | ✔ | auto | Khóa nội bộ `Node.id` |
| name | String(255) | ✔ | "Untitled Project" | Tên dự án |
| startDate | DateTime | ✔ | today | Ngày bắt đầu; nếu nhập finishDate thì lịch tính ngược |
| finishDate | DateTime | – | calc | Do scheduler sinh |
| statusDate | DateTime | – | today | Mốc tính actual/EVM |
| currency/currencyDigits | Enum/int | – | USD/2 | Tools→Options |
| minutesPerDay | int | – | 480 | Quy đổi work↔duration |
| calendar | FK→Calendar | ✔ | Standard | Lịch mặc định task/resource mới |
| defaultStartTime/defaultEndTime | Time | – | 08:00/17:00 | Giờ làm chuẩn |

## 3. Từ điển dữ liệu — thực thể TASK (trường quan trọng)

| Field (UI/mã) | Kiểu | Range/Enum | Ghi chú nghiệp vụ |
|---------------|------|------------|-------------------|
| id | long | unique | Node ID |
| parentId | long | FK Task | null = summary gốc; tạo cây WBS |
| name | String | ≤255 | Không rỗng |
| duration | DurationType | ≥0, đơn vị m/h/d/w/mo/ed | `0d` ⇒ milestone |
| start / finish | DateTime | start≤finish | Auto-scheduled: do engine tính |
| manualStart/manualFinish | bool | false | Chế độ Manually Scheduled |
| type | Enum | FixedUnits(0)/FixedDuration(1)/FixedPrice(2)/… | Công thức TAIR: Work=Duration×Units |
| isFixedCalc | bool | true | Bật effort-driven |
| percentComplete | int% | 0–100, step 10 configurable | Rollup từ children theo work |
| actualStart/actualFinish | DateTime | – | Tracking |
| remainingWork/remainingDuration | – | ≥0 | Sau update |
| constraintType | Enum | ASAP, ALAP, SNET, SNFT, FNLT, FNET, MSO, MFO, SO, FO | Kèm constraintDate |
| constraintDate | DateTime | – | Bắt buộc nếu type ≠ ASAP/ALAP |
| priority | int | 0–999, mặc định 500 | Ordering cho leveling |
| critical | bool | auto | slack ≤ threshold |
| totalSlack | Duration | auto | LS−ES |
| earlyStart/earlyFinish/lateStart/lateFinish | DateTime | auto | 2-pass CPM |
| baselineStart1..7 / baselineFinish1..7 | DateTime | set-by-baseline | 7 bộ baseline |
| baselineWork/Cost1..7 | float | – | EVM BCWS nguồn |
| cost, fixedCost, overtimeCost, variance | Money | ≥0 | Total=∑assignment+fixed |
| accrual | Enum | Beginning/Prorated/End | Cho fixed cost |
| calendar | FK | Standard | Override lịch task |
| notes | RichText | – | Tab General dialog |
| predecessors | List<LinkSpec> | `id[FS\|SS\|FF\|SF][±lag]` | Chuỗi nhập nhanh dạng `3FS+2d` |
| wbsLevel / wbsCode | int/String | auto/manual | 1.2.3 |
| outlineLevel | int | 0–? | Indent level |
| showRollup | bool | false | Tam giác rollup |
| splitParts | List<Intervals> | – | Split task |
| custom fields | xem §6 | – | Text1…, Number1… |

## 4. Từ điển dữ liệu — RESOURCE & ASSIGNMENT

**RESOURCE** (`com.projectlibre.pm.resources.Resource`)

| Field | Kiểu | Enum/Range | Ý nghĩa |
|-------|------|------------|---------|
| resourceType | Enum | WORK/MATERIAL/COST | Quyết định công thức cost |
| units (maxUnits) | %/qty | 0–∞ | Khả dụng tối đa (100%=1 fulltime) |
| standardRate | Rate | tiền/giờ-ngày-tháng | Bảng rate A–E with From date |
| overtimeRate | Rate | – | Chỉ áp khi OT entered |
| costPerUse | Money | ≥0 | Phí phát sinh mỗi assignment |
| materialLabel | String | "kg","ca" | Đơn vị material |
| calendar | FK | – | Resource calendar riêng |
| bookingMask | Days | – | Ngày không book được |
| isPool/isLinked | bool | – | Resource pool sharing |
| userLimit | % | ≤ maxUnits | Giới hạn assignment |

**ASSIGNMENT (ResourceTask)**

| Field | Kiểu | Ghi chú |
|-------|------|---------|
| taskId, resourceId | FK | Quan hệ n-n |
| units | % | Ví dụ 50% |
| work, overtime, actualWork, remainingWork | Duration | Timephased stored per bucket |
| cost, actualCost, remainingCost | Money | |
| delay | Duration | Leveling delay |
| stop/continue | Date | Phần assignment bị cắt |

## 5. Định dạng file

### 5.1 `.pod` — ProjectLibre/OpenProj native XML
Bản chất: **XML UTF-8**, root `<Workspace>` chứa các `<node class="...">`; serialization bằng JDOM trong `openproj_exchange`.

**Ví dụ cấu trúc rút gọn:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<Workspace version="1.0">
  <DocumentFactory/>
  <node class="ProjectDocumentImpl">
    <id value="1"/>
    <name value="Xay Nha Pho"/>
    <startDate value="2026-11-09T08:00:00"/>
    <minutesPerDay value="480"/>
    <currency value="VND"/>
    <childNodes>
      <node class="TaskImpl">
        <id value="2"/> <parentId value="1"/>
        <name value="Thi cong mong"/>
        <duration value="PT120H"/>          <!-- ISO-8601 duration -->
        <start value="2026-11-09T08:00:00"/>
        <finish value="2026-11-13T17:00:00"/>
        <type value="0"/>                    <!-- Fixed Units -->
        <percentComplete value="0"/>
        <predecessors value=""/>
        <attribute name="baselineStart1" value="2026-11-09T08:00:00"/>
        <attribute name="Text1" value="KT"/>
      </node>
      <node class="DependencyImpl">
        <id value="9"/>
        <fromID value="2"/> <toID value="3"/>
        <dependencyType value="1"/>          <!-- 1=FS,2=SS,3=FF,4=SF -->
        <lag value="PT16H"/>                 <!-- +2d -->
      </node>
      <node class="ResourceImpl"> ... </node>
      <node class="ResourceAssignmentImpl"> ... </node>
      <node class="ProjectCalendarImpl"> ... </node>
    </childNodes>
  </node>
</Workspace>
```

### 5.2 DTD chính thức hóa (cho `.pod` subset — dùng validate tích hợp ERP)
> ProjectLibre không ship XSD trong repo; DTD dưới đây là **đặc tả tài liệu hóa** (documentation DTD) phản chiếu serializer hiện tại, đủ để validator bên ngoài kiểm tra pipeline ETL.

```dtd
<!ELEMENT Workspace (DocumentFactory?, node+)>
<!ATTLIST Workspace version CDATA #REQUIRED>

<!ELEMENT DocumentFactory EMPTY>

<!ELEMENT node (id, parentId?, name?, duration?, start?, finish?, type?,
                percentComplete?, predecessors?, attribute*, childNodes?)>
<!ATTLIST node class CDATA #REQUIRED>   <!-- ProjectDocumentImpl|TaskImpl|
        ResourceImpl|ResourceAssignmentImpl|DependencyImpl|ProjectCalendarImpl -->

<!ELEMENT id EMPTY>        <!ATTLIST id value CDATA #REQUIRED>
<!ELEMENT parentId EMPTY>  <!ATTLIST parentId value CDATA #IMPLIED>
<!ELEMENT name EMPTY>      <!ATTLIST name value CDATA #IMPLIED>
<!ELEMENT duration EMPTY>  <!ATTLIST duration value CDATA #IMPLIED> <!-- ISO-8601 PTnH -->
<!ELEMENT start EMPTY>     <!ATTLIST start value CDATA #IMPLIED>    <!-- xs:dateTime -->
<!ELEMENT finish EMPTY>    <!ATTLIST finish value CDATA #IMPLIED>
<!ELEMENT type EMPTY>      <!ATTLIST type (0|1|2|3|4|5) #IMPLIED>
<!ELEMENT percentComplete EMPTY> <!ATTLIST percentComplete value CDATA #IMPLIED>
<!ELEMENT predecessors EMPTY>    <!ATTLIST predecessors value CDATA #IMPLIED>

<!ELEMENT attribute EMPTY>
<!ATTLIST attribute name CDATA #REQUIRED value CDATA #IMPLIED>

<!ELEMENT childNodes (node*)>

<!-- Dependency node -->
<!ELEMENT fromID EMPTY> <!ATTLIST fromID value CDATA #REQUIRED>
<!ELEMENT toID EMPTY>   <!ATTLIST toID value CDATA #REQUIRED>
<!ELEMENT dependencyType EMPTY> <!ATTLIST dependencyType (1|2|3|4) #REQUIRED>
<!ELEMENT lag EMPTY>    <!ATTLIST lag value CDATA #IMPLIED>
```

### 5.3 XML Schema (XSD) fragment tương đương — trường TaskImpl
```xml
<xs:complexType name="TaskType">
  <xs:sequence>
    <xs:element name="id" type="xs:long"/>
    <xs:element name="parentId" type="xs:long" minOccurs="0"/>
    <xs:element name="name" type="xs:string" maxlength="255"/>
    <xs:element name="duration" type="xs:duration"/>
    <xs:element name="start" type="xs:dateTime" minOccurs="0"/>
    <xs:element name="finish" type="xs:dateTime" minOccurs="0"/>
    <xs:element name="type" minOccurs="0">
      <xs:simpleType><xs:restriction base="xs:int">
        <xs:enumeration value="0"/><xs:enumeration value="1"/>
        <xs:enumeration value="2"/><xs:enumeration value="3"/>
      </xs:restriction></xs:simpleType>
    </xs:element>
    <xs:element name="percentComplete" type="xs:int" minOccurs="0">
      <xs:annotation><xs:documentation>0..100</xs:documentation></xs:annotation>
    </xs:element>
    <xs:any minOccurs="0" maxOccurs="unbounded" processContents="lax"/>
  </xs:sequence>
  <xs:attribute name="class" type="xs:string" use="required"/>
</xs:complexType>
```

### 5.4 `.mpp` — binary Structured Storage (OLE compound file)
- Root stream `ProjectProperties`, `Prop`, `Tasks`, `Resources`, `Assignments` (fixed-record binary).
- ProjectLibre đọc/ghi qua **MPXJ** (`projectlibre_exchange/src/net/sf/mpxj`); mapping table MP↔PL:

| MSP field (MPXJ) | PL field | Ghi chú transform |
|------------------|----------|-------------------|
| `Task.getUID()` | `task.id` | giữ UID gốc khi round-trip |
| `TaskField.START/FINISH` | start/finish | phút ↔ ms |
| `TaskField.DURATION` (tenths of minutes) | DurationType | chia 600 |
| `TaskField.TYPE` (0–5) | type enum | map trực tiếp |
| `TaskField.CONSTRAINT_TYPE` (0–10) | constraintType | bảng 11 giá trị |
| `TaskLinkType` FS/SS/FF/SF | dependencyType 1–4 | reorder enum |
| `TaskField.LINK_LAG_TIME` | lag | tenths→ISO |
| `ResourceField.COST_PER_USE` | costPerUse | |
| `ResourceField.ASSIGNMENT_UNITS_DEFAULT` | units | ×100 (%) |
| `Baseline*` (fields 152+) | baselineStart1..7 | MSP chỉ 11 baseline; PL 7 |

### 5.5 Các format khác
| Ext | Direction | Nội dung |
|-----|-----------|----------|
| .mpx | R/W | text tab-delimited legacy MSP 98 |
| .xml (UPXML) | R/W | Universal Process Modeling XML (interchange chuẩn mở) |
| .csv/.txt | Export | current table view |
| .pdf/.html/.png | Export | report/print pipeline |

## 6. Custom Fields — quy ước vật lý

| Nhóm | Key lưu trữ | Kiểu DB | Số lượng (Task/Resource) |
|------|-------------|---------|---------------------------|
| Text | `Text1`…`Text30` | String | 30/10 |
| Note | `Note1`… | RichText | 30/– |
| Number | `Number1`…`Number20` | double | 20/10 |
| Percent | `Percent1`…`5` | int 0–100 | 5/– |
| Date | `Date1`…`Date15` | dateTime | 15/15 |
| Cost | `Cost1`…`Cost20` | money | 20/– |
| Flag | `Flag1`…`Flag15` | boolean | 15/15 |
| Duration | `Duration1`…`10` | ISO dur | 10/– |
| Aspect | `Aspect1`…`10` | metadata | 10/10 |

Quy tắc đặt tên khi tích hợp: `CF_<MODULE>_<MEANING>` (vd `CF_TASK_REASON_DELAY`) ghi vào `attribute name="Text3"`.

## 7. Khoá ngoại & toàn vẹn tham chiếu (validation rules cho ETL)
1. `Task.parentId` phải trỏ Task có outlineLevel = parent+1 hoặc Project.
2. `Dependency.fromID/toID` tồn tại; đồ thị **không chu trình** (kiểm tra topo trước khi import).
3. `Assignment.resourceId` ∈ Resource; `taskId` ∈ Task lá (summary tự rollup).
4. `baselineStartN ≤ baselineFinishN`; `start+calendar ≥ project.startDate`.
5. `percentComplete=100 ⇒ actualFinish != null` (soft rule, cảnh báo).
6. Currency nhất quán trong một document; rates lưu kèm `From` date để xử lý thay đổi lương.

## 8. Endpoint tích hợp gợi ý (khi dùng ProjectLibre Server/Cloud)
- REST: `GET /projects/{id}/tasks`, `POST /tasks`, `PATCH /tasks/{uid}` payload JSON mirror §3.
- Batch import: upload `.mpp/.pod` → parser cùng engine desktop → trả về validation report (§7).

## 9. Tài liệu liên quan
- Kiến trúc module sinh ra các cấu trúc trên: [01_technical_specification.md](01_technical_specification.md)
- Kiểm thử round-trip: [08_testing_troubleshooting_faq.md](08_testing_troubleshooting_faq.md)
