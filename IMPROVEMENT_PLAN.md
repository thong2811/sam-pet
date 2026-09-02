# Sam Pet — Kế hoạch cải thiện

> Cập nhật lần cuối: 2026-09-01 (Nhóm 1 ✅ | Nhóm 2 ✅ | Nhóm 3 ✅ | Nhóm 4 ✅ | Nhóm 5 🔄 Sprint 3 xong | Nhóm 6 ⬜ category)
> Nguyên tắc: chỉnh từng nhóm nhỏ, kiểm tra kỹ trước khi sang nhóm tiếp theo.
> Trạng thái: ⬜ Chưa làm · 🔄 Đang làm · ✅ Hoàn thành · ⏸ Tạm bỏ

---

## Nhóm 1 — Sửa lỗi nghiêm trọng ✅ HOÀN THÀNH

| # | Hạng mục | Trạng thái | Ghi chú |
|---|----------|-----------|---------|
| 1.1 | `renewWarehouse`: thêm transaction / rollback để tránh partial update khi chốt kho | ✅ | Snapshot + try/catch + rollback về state trước khi lỗi |
| 1.2 | `Product::doRepackage()`: tính lại `remainStock` phía server, không trust giá trị client gửi lên | ✅ | `calcRemainStock()` server-side + validate âm trước khi chiết |
| 1.3 | `Stocktaking::doEdit()`: chuyển sang upsert (INSERT OR REPLACE) thay vì updateRows silent no-op | ✅ | Tự tạo row mới nếu sản phẩm chưa có trong stocktaking |
| 1.4 | Fix `ExportInvoice::generatePdf()` — key JSON không khớp giữa `doAdd/doEdit` và `generatePdf` | ✅ | Map đúng `productName`/`total`, fallback an toàn cho key thiếu |
| 1.5 | `ExportStockController::doEditAction`: bỏ dependency vào `Referer` header, dùng route cố định | ✅ | Redirect về `exportStock/edit/:date` hoặc `exportStock` index |
| 1.6 | Thêm CSRF token cho các POST action quan trọng: doRepackage, renewWarehouse, doAdd/doEdit report | ✅ | `CsrfService`, meta tag layout, jQuery ajaxSetup, POST thay GET cho renewWarehouse |

---

## Nhóm 2 — Business logic ✅ HOÀN THÀNH

| # | Hạng mục | Trạng thái | Ghi chú |
|---|----------|-----------|---------|
| 2.1 | Thống nhất công thức `remaining` trong `Report`: `getDataToView()` và `getDataToViewChart()` đang tính khác nhau | ✅ | `remaining = revenue - expenses`, thêm `calcRemaining()` private, bỏ dòng comment-out |
| 2.2 | `VetCare::totalAmountByDate()`: thêm `treatmentProfit` vào kết quả trả về | ✅ | Caller không cần tự tính lại hằng số |
| 2.3 | `Expenses::doEdit()`: đổi sang replace-all strategy cho nhất quán với ImportStock / ExportStock | ✅ | Xóa hết rows theo ngày → insert lại. Fix thêm Referer header trong `ExpensesController` |
| 2.4 | Extract logic build invoice content thành method riêng, bỏ code clone giữa `doAdd` và `doEdit` trong `ExportInvoice` | ✅ | `buildInvoiceContent()` private dùng chung |
| 2.5 | Validate repackage âm: server tự tính `remainStock` trước khi cho phép chiết (liên quan 1.2) | ✅ | Đã xử lý trong task 1.2 |
| 2.6 | `Report`: thêm endpoint AJAX `GET /report/data-by-date` để auto-fill form từ data thực tế thay vì nhập tay | ✅ | `Report::getDataByDate()`, `dataByDateAction`, route, view dùng AJAX |

---

## Nhóm 3 — Tổ chức code ✅ HOÀN THÀNH

| # | Hạng mục | Trạng thái | Ghi chú |
|---|----------|-----------|---------|
| 3.1 | Tách `CommonService` thành các class nhỏ: `DataTableService`, `LoggerFactory`, `DateHelper` | ✅ | `CommonService` giữ lại làm facade backward-compatible |
| 3.2 | Xóa `getDataTablesParameters()` — dead code đọc `$_POST` trực tiếp | ✅ | |
| 3.3 | Bỏ HTML generation ra khỏi Model (`getDataToView` sinh `$row['action']`) — chuyển sang DataTable column render | ✅ | 9 Model đã sửa, DataTable `render` function trong view |
| 3.4 | Thêm Dependency Injection cho `ProductController`, `ExportStockController`, `OverviewController` | ⬜ | Chưa làm — hoãn sang sau |
| 3.5 | Gộp `indexAction` và `expensesAction` trong `OverviewController` — hai action giống hệt nhau | ✅ | Extract `buildChartViewModel()` private |
| 3.6 | Chuyển `backupDataToStocktaking()` từ `CommonService` vào `BackupService` | ✅ | `BackupService::backupForStocktaking()`, `CommonService` giữ wrapper `@deprecated` |
| 3.7 | Extract hàm `remove(id)` dùng chung vào `common.js`, bỏ copy-paste ở từng view | ✅ | `removeRow(controllerName, id, table)` trong `common.js` |

---

## Nhóm 4 — UI/UX ✅ HOÀN THÀNH

| # | Hạng mục | Trạng thái | Ghi chú |
|---|----------|-----------|---------|
| 4.1 | Thêm active state cho navbar — highlight trang hiện tại | ✅ | Dùng `str_starts_with($currentPath, $item['href'])` trong layout |
| 4.2 | Sửa tiêu đề tab: bỏ "Laminas MVC Skeleton", dùng `STORE_NAME + tên trang` | ✅ | `headTitle($storeName)`, `lang="vi"` |
| 4.3 | Fix thứ tự CSS: `style.css` phải load sau `bootstrap.min.css` | ✅ | Bootstrap → style.css đúng thứ tự cascade |
| 4.4 | Fix `position: fixed` của flash messages — thêm padding-top cho body | ✅ | JS tự tính `flash.offsetHeight` và set `body.paddingTop` |
| 4.5 | Fix duplicate HTML `id` trong product/index.phtml (2 modal dùng chung id) | ✅ | Xử lý trong Nhóm 3 — dùng `$(modalId).find('#id')` scoped |
| 4.6 | Fix duplicate HTML `id` trong report/index.phtml (2 modal dùng chung id) + sửa typo `eidtReport` | ✅ | Prefix `edit-` / `add-` cho tất cả input id, sửa typo |
| 4.7 | Thêm nút "Xóa dòng" vào `export-stock/add.phtml` (nhất quán với import-stock, expenses) | ✅ | `removeInputRow()`, không xóa nếu chỉ còn 1 dòng |
| 4.8 | Sửa overlay loading: tách text theo context (backup / loading / syncing) | ✅ | `showOverlay(text)` với map context → message |
| 4.9 | Loại bỏ `eval()` trong calculator widget — dùng math parser an toàn hoặc giới hạn phép tính | ✅ | Whitelist `[\d\s+\-*/().]+` + `Function` constructor |
| 4.10 | Xóa file `select.dataTables.min.js` bị load trùng trong layout | ✅ | Giảm từ 3 lần xuống 1 lần |
| 4.11 | Thu gọn hoặc giới hạn scope calculator widget (ẩn trên các trang không cần) | ⬜ | Hoãn — phức tạp, low priority |
| 4.12 | Thêm breadcrumb cho các trang edit/add lồng nhau (medical-record, export-invoice) | ⬜ | Hoãn — làm cùng Nhóm 5 khi refactor view |

---

## Nhóm 5 — Database 🔄 ĐANG LÀM (branch `feature/sqlite-migration`)

> Sprint 1 + 2 hoàn thành 2026-09-01. Sprint 3 tiếp theo: Repository layer thay thế từng Model.
> Tham chiếu kỹ thuật: `.kiro/specs/sam-pet-v2-optimized/requirements.md` *(lỗi thời — ưu tiên plan này)*

| # | Hạng mục | Trạng thái | Ghi chú |
|---|----------|-----------|---------|
| 5.1 | Thiết kế SQLite schema đầy đủ (15 bảng + indexes) | ✅ | `data/migrations/001_initial_schema.sql` — 15 bảng, 20+ indexes |
| 5.2 | Tạo class `Database` với PDO, WAL mode, transaction support | ✅ | `module/Application/src/Database/Database.php` + `DatabaseFactory.php` — 9/9 test passed |
| 5.3 | Viết migration script `bin/migrate-csv-to-sqlite.php` | ✅ | 9131 rows, 0 FK orphan; repackage_history parse content→ cặp from/to; NULL timestamp preserve |
| 5.4 | Chuyển `Product` sang Repository + SQLite (tính `remainStock` bằng SQL JOIN) | ✅ | `ProductRepository` — SQL JOIN, doRepackage, calcRemainStock |
| 5.5 | Chuyển `ImportStock` và `ExportStock` sang SQLite | ✅ | `ImportStockRepository`, `ExportStockRepository` — filterNewRows dùng SQL IN |
| 5.6 | Chuyển `VetCare`, `Expenses`, `Report` sang SQLite | ✅ | `VetCareRepository`, `ExpensesRepository`, `ReportRepository` |
| 5.7 | Chuyển `ExportInvoice`, `OwnerPet`, `MedicalRecord` sang SQLite | ✅ | `ExportInvoiceRepository`, `OwnerPetRepository`, `MedicalRecordRepository` — getDataToView SQL JOIN |
| 5.8 | Chuyển `Stocktaking`, `RepackageHistory` sang SQLite | ✅ | `StocktakingRepository`, `RepackageHistoryRepository` |
| 5.8a | **Redesign logic kiểm kê:** dùng `stocktaking_periods` + `stocktaking_period_items` để giữ lịch sử | ✅ | `renewWarehouse()`: VACUUM INTO backup → validate → period → update initStock → DELETE import/export trong 1 transaction |
| 5.9 | Cập nhật `BackupService`: backup file `.db` bằng `VACUUM INTO` thay vì ZIP CSV | ✅ | `BackupService` v2: asset `backup.db`, `isValidSqlite()`, `restore()` replace app.db |
| 5.10 | Thêm DateRangeFilter vào DataTable cho các bảng chính | ⬜ | Sprint 4 |
| 5.11 | Dashboard lazy load chart qua AJAX thay vì nhúng data vào HTML | ⬜ | Sprint 4 |
| 5.12 | Stocktaking: thay `prompt()` bằng Bootstrap Modal có xác nhận (StocktakingModal) | ⬜ | Sprint 4 |
| 5.13 | Thêm bảng `categories` + field `categoryId` vào `products` (FK, nullable) | ✅ | Đã có trong schema migration 001 — implement CRUD Sprint 4 (xem Nhóm 6) |
| 5.14 | Thêm bảng `customers` + field `customerId` nullable vào `export_stock` | ✅ | Đã có trong schema migration 001 |
| 5.15 | Chuẩn hoá `repackage_history`: `fromProductId`, `toProductId`, `fromQuantity`, `toQuantity` | ✅ | Đã có trong schema migration 001 (bỏ field `content` text thuần) |
| 5.16 | Thêm indexes cho các field hay query: `date`, `productId`, `pet_id`... | ✅ | 20+ indexes trong migration 001 — thiết kế ngay từ đầu |

---

## Thứ tự đề xuất

```
✅ Nhóm 1 (lỗi nghiêm trọng)       — commit 984ec86
✅ Nhóm 2 (business logic)          — commit a75ba80
✅ Nhóm 3 (tổ chức code)            — commit 040b8e1
✅ Nhóm 4 (UI/UX)                   — commit ae91790
🔄 Nhóm 5 (SQLite migration)        — branch feature/sqlite-migration
   ✅ Sprint 1: Database layer + Schema (2026-09-01)
      - 15 bảng, 20+ indexes, WAL mode, migration system
      - Files: Database.php, DatabaseFactory.php, 001_initial_schema.sql
      - Dockerfile: thêm pdo_sqlite; composer.json: ext-pdo, ext-pdo_sqlite
   ✅ Sprint 2: Migration script CSV → SQLite (2026-09-01)
      - `bin/migrate-csv-to-sqlite.php` — 9131 rows, 0 FK orphan, DB 1928KB
      - `bin/verify-migration.php` — script xác minh kết quả
      - Options: `--dry-run` (chỉ đọc báo cáo), `--force` (xóa DB cũ tạo lại)
      - Edge cases: NULL timestamps giữ nguyên, repackage_history parse content→cặp from/to
      - 2 rows report.csv duplicate id bị skip đúng (data bẩn trong CSV gốc)
   ⬜ Sprint 3: Repository layer (thay thế từng Model)
   ✅ Sprint 3: Repository layer (2026-09-01)
      - 11 Repository classes thay thế hoàn toàn Model CSV
      - 11 Controllers cập nhật dùng Repository qua DI
      - module.config.php: 11 Repository factories + 12 Controller factories
      - BackupService v2: VACUUM INTO thay ZIP CSV, asset backup.db
      - Lint: 35/35 PASS | Smoke test: 33/33 PASS | HTTP: 10/10 routes 200
   ⬜ Sprint 4: Features mới (DateRange, lazy chart, StocktakingModal)
⬜ Nhóm 6 (Category sản phẩm)      — làm cùng Sprint 4 Nhóm 5
```

---

## Nhóm 6 — Category sản phẩm ⬜ CHƯA LÀM

> **Khuyến nghị:** Làm cùng lúc với Nhóm 5 (SQLite migration) để tận dụng FK thực sự và `GROUP BY categoryId` cho báo cáo.
> Nếu cần làm trước Nhóm 5, có thể implement theo pattern CSV hiện tại (thêm `category.csv` + field `categoryId` vào `product.csv`).

**Mục tiêu:** Phân nhóm sản phẩm để lọc nhanh khi nhập/xuất hàng và báo cáo doanh thu breakdown theo danh mục.

**Các nhóm dự kiến:** Thức ăn · Phụ kiện · Thuốc & vaccine · Vệ sinh · Cát vệ sinh · Đồ chơi · Thức ăn bổ sung

| # | Hạng mục | Trạng thái | Ghi chú |
|---|----------|-----------|---------|
| 6.1 | Tạo bảng/file `categories` với các trường: `id`, `name`, `note` | ⬜ | CSV: `category.csv` / SQLite: bảng `categories` |
| 6.2 | Thêm field `categoryId` vào `product` (CSV hoặc SQLite) | ⬜ | FK → categories.id, nullable |
| 6.3 | Màn hình quản lý category — CRUD đơn giản | ⬜ | Trang mới `/category` |
| 6.4 | Dropdown lọc theo category trong trang Quản lý kho (DataTable filter) | ⬜ | |
| 6.5 | Dropdown chọn category trong form thêm/sửa sản phẩm | ⬜ | |
| 6.6 | Dropdown lọc category trong form nhập hàng / xuất hàng để tìm sản phẩm nhanh hơn | ⬜ | Select2 filter theo category |
| 6.7 | Báo cáo doanh thu breakdown theo category (thêm vào trang Overview hoặc Report) | ⬜ | Cần SQLite `GROUP BY` để hiệu quả |

---

## Ghi chú nhanh

- Nhóm 1–4 đã hoàn thành trên branch `vet_2.0.0`.
- Nhóm 5 đang làm trên branch `feature/sqlite-migration`.
- **Sprint 1 Nhóm 5 hoàn thành (2026-09-01):**
  - `data/migrations/001_initial_schema.sql` — 15 bảng, 20+ indexes
  - `module/Application/src/Database/Database.php` — PDO, WAL, migration system, VACUUM INTO
  - `module/Application/src/Database/DatabaseFactory.php` — Laminas DI
  - `module/Application/config/module.config.php` — đăng ký `Database::class`
  - `docker/php/Dockerfile` — thêm `pdo_sqlite`, `libsqlite3-dev`
  - `composer.json` — thêm `ext-pdo`, `ext-pdo_sqlite`
  - `bin/test-database.php` — 9/9 test PASSED
- **Quyết định thiết kế đã chốt:**
  - ID dùng TEXT (uniqid hex) — tương thích CSV, không cần map FK khi migrate
  - Stocktaking theo thiết kế 5.8a — giữ lịch sử qua `stocktaking_periods`
  - `repackage_history` chuẩn hoá theo 5.15 (`fromProductId`, `toProductId`, `fromQuantity`, `toQuantity`)
  - `categories` và `customers` đã có trong schema từ đầu
- **Sprint 3 Nhóm 5 hoàn thành (2026-09-01):**
  - 11 Repository classes (`module/Application/src/Repository/`) thay thế hoàn toàn Model CSV
  - `ProductRepository`: remainStock tính bằng SQL JOIN, không load toàn bộ CSV vào memory
  - `ExportStockRepository`: filterNewRows dùng SQL IN, importFromSheets filter lần 2 trong transaction
  - `ReportRepository`: getDataByDate dùng SQL aggregate trực tiếp, getDataToViewChart sort bằng SUBSTR
  - `MedicalRecordRepository`: getDataToView JOIN với owners_pets
  - `StocktakingRepository`: renewWarehouse (5.8a) — VACUUM INTO backup → period → DELETE history
  - 11 Controllers cập nhật DI (constructor injection), `module.config.php` đầy đủ factories
  - `BackupService` v2: backup/restore `.db` thay ZIP CSV, VACUUM INTO, isValidSqlite validation
  - Lint: **35/35 PASS** | Smoke test: **33/33 PASS** | HTTP: **10/10 routes 200**
- Sprint 4 tiếp theo: DateRangeFilter, lazy chart, StocktakingModal, Category CRUD (Nhóm 6)
- Nhóm 6 (Category) sẽ làm trong Sprint 4 — schema (`categories`, `products.categoryId`) đã sẵn sàng.
- Task 3.4 (DI cho controllers) hoãn lại — làm song song Sprint 3.
- Task 4.11, 4.12 hoãn lại — làm cùng lúc refactor view trong Sprint 3/4.

---

## Thiết kế lại logic kiểm kê kho (task 5.8a) — Chi tiết

### Vấn đề hiện tại

Sau khi chốt kho (`renewWarehouse`), hệ thống **xóa toàn bộ** `import_stock` và `export_stock` rồi set lại `initStock`. Điều này gây mất lịch sử nhập/xuất — không thể điều tra sai lệch, kiểm tra gian lận, hay đối soát về sau.

### Thiết kế mới — Stocktaking Periods

Thay vì xóa lịch sử, đánh dấu **mốc kiểm kê** bằng bảng `stocktaking_periods`:

```sql
stocktaking_periods (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    closedAt    TEXT,          -- ngày chốt kho dd-mm-yyyy
    note        TEXT,
    createdAt   INTEGER,
    updatedAt   INTEGER
)

stocktaking_period_items (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    periodId    INTEGER FK → stocktaking_periods.id,
    productId   INTEGER FK → products.id,
    actualStock REAL,          -- số lượng đếm thực tế khi kiểm kê
    createdAt   INTEGER,
    updatedAt   INTEGER
)
```

### Công thức tính tồn kho mới

```sql
-- remainStock tại thời điểm hiện tại:
remainStock =
    COALESCE(last_period.actualStock, p.initStock)   -- tồn kho tại mốc kiểm kê gần nhất
  + COALESCE(import_after_period, 0)                 -- nhập sau mốc đó
  - COALESCE(export_after_period, 0)                 -- xuất sau mốc đó
  + p.repackageStock                                 -- chiết hàng (cộng dồn)
```

Trong đó `last_period` là `stocktaking_period_items` có `periodId` lớn nhất (mốc gần nhất).

### Lợi ích

| | Hiện tại | Thiết kế mới |
|--|---------|-------------|
| Lịch sử nhập/xuất | Bị xóa sau chốt kho | **Giữ nguyên vĩnh viễn** |
| Điều tra sai lệch | Không thể | Có thể query theo period |
| Chốt kho nhiều lần | Mỗi lần reset từ đầu | Mỗi lần tạo 1 period mới |
| Rollback kiểm kê | Không thể | Xóa period là rollback |
| Báo cáo theo kỳ | Không có | Query giữa 2 period IDs |
