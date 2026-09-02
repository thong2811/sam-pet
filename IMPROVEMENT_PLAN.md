# Sam Pet — Kế hoạch cải thiện

> Cập nhật lần cuối: 2026-09-02
> Trạng thái: ⬜ Chưa làm · 🔄 Đang làm · ✅ Hoàn thành · ⏸ Tạm bỏ

---

## Tóm tắt tiến độ

| Nhóm | Nội dung | Trạng thái |
|------|----------|-----------|
| Nhóm 1 | Sửa lỗi nghiêm trọng | ✅ commit 984ec86 |
| Nhóm 2 | Business logic | ✅ commit a75ba80 |
| Nhóm 3 | Tổ chức code | ✅ commit 040b8e1 (trừ 3.4) |
| Nhóm 4 | UI/UX | ✅ commit ae91790 (trừ 4.11, 4.12) |
| Nhóm 5 | SQLite migration | ✅ commit 2d7a042 (Sprint 1–3) + Sprint 4 done |
| Nhóm 6 | Category sản phẩm | 🔄 6.1–6.5 xong, còn 6.6, 6.7 |

---

## Nhóm 1 — Sửa lỗi nghiêm trọng ✅ HOÀN THÀNH

| # | Hạng mục | Trạng thái |
|---|----------|-----------|
| 1.1 | `renewWarehouse`: transaction / rollback tránh partial update | ✅ |
| 1.2 | `Product::doRepackage()`: tính `remainStock` server-side, validate âm | ✅ |
| 1.3 | `Stocktaking::doEdit()`: upsert (INSERT OR REPLACE) | ✅ |
| 1.4 | Fix `ExportInvoice::generatePdf()` — key JSON không khớp | ✅ |
| 1.5 | `ExportStockController::doEditAction`: bỏ dependency Referer header | ✅ |
| 1.6 | Thêm CSRF token cho doRepackage, renewWarehouse, doAdd/doEdit report | ✅ |

---

## Nhóm 2 — Business logic ✅ HOÀN THÀNH

| # | Hạng mục | Trạng thái |
|---|----------|-----------|
| 2.1 | Thống nhất công thức `remaining` trong Report | ✅ |
| 2.2 | `VetCare::totalAmountByDate()`: thêm `treatmentProfit` | ✅ |
| 2.3 | `Expenses::doEdit()`: replace-all strategy | ✅ |
| 2.4 | Extract `buildInvoiceContent()` dùng chung cho doAdd/doEdit | ✅ |
| 2.5 | Validate repackage âm server-side | ✅ (xử lý trong 1.2) |
| 2.6 | Endpoint AJAX `GET /report/data-by-date` auto-fill form | ✅ |

---

## Nhóm 3 — Tổ chức code ✅ HOÀN THÀNH (trừ 3.4)

| # | Hạng mục | Trạng thái | Ghi chú |
|---|----------|-----------|---------|
| 3.1 | Tách `CommonService` → `DataTableService`, `LoggerFactory`, `DateHelper` | ✅ | |
| 3.2 | Xóa `getDataTablesParameters()` — dead code | ✅ | |
| 3.3 | Bỏ HTML generation ra khỏi Model | ✅ | |
| 3.4 | Thêm DI cho `ProductController`, `ExportStockController`, `OverviewController` | ✅ | Đã làm trong Sprint 3 |
| 3.5 | Gộp `indexAction` và `expensesAction` trong `OverviewController` | ✅ | |
| 3.6 | Chuyển `backupDataToStocktaking()` vào `BackupService` | ✅ | |
| 3.7 | Extract `removeRow()` dùng chung vào `common.js` | ✅ | |

---

## Nhóm 4 — UI/UX ✅ HOÀN THÀNH (trừ 4.11, 4.12)

| # | Hạng mục | Trạng thái | Ghi chú |
|---|----------|-----------|---------|
| 4.1 | Active state navbar | ✅ | |
| 4.2 | Sửa tiêu đề tab | ✅ | |
| 4.3 | Fix thứ tự CSS | ✅ | |
| 4.4 | Fix `position: fixed` flash messages | ✅ | |
| 4.5 | Fix duplicate HTML `id` trong product/index.phtml | ✅ | |
| 4.6 | Fix duplicate HTML `id` trong report/index.phtml + typo | ✅ | |
| 4.7 | Nút "Xóa dòng" cho export-stock/add.phtml | ✅ | |
| 4.8 | Sửa overlay loading text theo context | ✅ | |
| 4.9 | Loại bỏ `eval()` trong calculator widget | ✅ | |
| 4.10 | Xóa `select.dataTables.min.js` bị load trùng | ✅ | |
| 4.11 | Thu gọn calculator widget trên các trang không cần | ⏸ | Low priority |
| 4.12 | Breadcrumb cho trang edit/add lồng nhau | ⬜ | medical-record, export-invoice |

---

## Nhóm 5 — Database ✅ HOÀN THÀNH

| # | Hạng mục | Trạng thái | Ghi chú |
|---|----------|-----------|---------|
| 5.1 | SQLite schema 15 bảng + 20+ indexes | ✅ | `data/migrations/001_initial_schema.sql` |
| 5.2 | Class `Database` — PDO, WAL, migration system, VACUUM INTO | ✅ | `src/Database/Database.php` |
| 5.3 | Script `bin/migrate-csv-to-sqlite.php` | ✅ | 9131 rows, 0 FK orphan; `--dry-run` / `--force` |
| 5.4 | `ProductRepository` — remainStock SQL JOIN | ✅ | |
| 5.5 | `ImportStockRepository` + `ExportStockRepository` | ✅ | filterNewRows SQL IN, race condition protection |
| 5.6 | `VetCareRepository` + `ExpensesRepository` + `ReportRepository` | ✅ | |
| 5.7 | `ExportInvoiceRepository` + `OwnerPetRepository` + `MedicalRecordRepository` | ✅ | getDataToView SQL JOIN |
| 5.8 | `StocktakingRepository` + `RepackageHistoryRepository` | ✅ | |
| 5.8a | renewWarehouse giữ lịch sử qua `stocktaking_periods` | ✅ | VACUUM INTO backup → period → DELETE history |
| 5.9 | `BackupService` v2 — VACUUM INTO, asset `backup.db` | ✅ | isValidSqlite validation |
| 5.10 | DateRangeFilter DataTable | ✅ | `DataTableService::filterDateRange()`, `initDateRangeFilter()` JS, 5 trang |
| 5.11 | Dashboard lazy load chart AJAX | ✅ | `GET /overview/chart-data?from=&to=`, filter theo khoảng ngày |
| 5.12 | StocktakingModal thay `prompt()` | ✅ | Bootstrap Modal + checkbox xác nhận + input ghi chú kỳ |
| 5.13 | Bảng `categories` + `categoryId` trong `products` | ✅ | Schema + CRUD + dropdown trong form product |
| 5.14 | Bảng `customers` + `customerId` trong `export_stock` | ✅ | Schema có sẵn (chưa có UI — xem 5.14a) |
| 5.15 | Chuẩn hoá `repackage_history` | ✅ | `fromProductId`, `toProductId`, `fromQuantity`, `toQuantity` |
| 5.16 | Indexes cho các field hay query | ✅ | 20+ indexes trong migration 001 |

**5.14a — UI quản lý Customers (chưa làm):** Schema đã có, cần thêm Controller + Repository + View nếu muốn dùng tính năng công nợ/lịch sử mua hàng.

---

## Nhóm 6 — Category sản phẩm 🔄 ĐANG LÀM

| # | Hạng mục | Trạng thái | Ghi chú |
|---|----------|-----------|---------|
| 6.1 | Bảng `categories` (id, name, note) | ✅ | Schema + `CategoryRepository` |
| 6.2 | Field `categoryId` trong `products` | ✅ | FK nullable, `ProductRepository::doAdd/doEdit` |
| 6.3 | Màn hình CRUD `/category` | ✅ | `CategoryController`, `category/index.phtml` — 2 modal Add/Edit |
| 6.4 | Dropdown lọc category trong DataTable Quản lý kho | ⬜ | Client-side filter hoặc thêm param vào server-side |
| 6.5 | Dropdown chọn category trong form thêm/sửa sản phẩm | ✅ | Đã thêm vào modal Add + Edit trong product/index.phtml |
| 6.6 | Dropdown lọc category trong form nhập/xuất hàng (Select2) | ⬜ | Filter danh sách sản phẩm theo category khi chọn |
| 6.7 | Báo cáo doanh thu breakdown theo category | ⬜ | `GROUP BY categoryId` thêm vào trang Overview hoặc Report |

---

## Công việc còn lại (theo thứ tự ưu tiên)

```
1. 6.4  Dropdown lọc category trong DataTable Quản lý kho
2. 6.6  Select2 lọc sản phẩm theo category trong form nhập/xuất hàng
3. 6.7  Báo cáo doanh thu breakdown theo category
4. 4.12 Breadcrumb cho medical-record, export-invoice
5. 5.14a UI quản lý Customers (nếu cần)
6. 4.11 Thu gọn calculator widget (low priority)
```

---

## Ghi chú kỹ thuật

**Quyết định thiết kế đã chốt:**
- ID dùng TEXT uniqid hex — tương thích CSV gốc, không cần map FK khi migrate
- Stocktaking theo 5.8a — giữ lịch sử qua `stocktaking_periods`
- `repackage_history` chuẩn hoá: `fromProductId`, `toProductId`, `fromQuantity`, `toQuantity`

**Hướng dẫn deploy lên production:**
- Xem `docs/migrate-to-sqlite.md`

**Thiết kế lại logic kiểm kê (5.8a):**

Thay vì xóa lịch sử sau chốt kho, tạo bản ghi `stocktaking_periods` + `stocktaking_period_items` để:
- Giữ toàn bộ lịch sử nhập/xuất vĩnh viễn
- Cho phép điều tra sai lệch, đối soát về sau
- Rollback kiểm kê bằng cách xóa period
- Báo cáo theo kỳ bằng query giữa 2 period IDs
