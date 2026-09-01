# Sam Pet — Kế hoạch cải thiện

> Cập nhật lần cuối: 2026-09-01 (Nhóm 1 ✅ | Nhóm 2 ✅ | Nhóm 3 ✅ | Nhóm 4 ✅ | Nhóm 5 ⬜ tạm dừng | Nhóm 6 ⬜ category)
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

## Nhóm 5 — Database ⏸ TẠM DỪNG (làm sau)

> Nhóm này là thay đổi lớn nhất — nên làm trên branch riêng `feature/sqlite-migration`.
> Tham chiếu kỹ thuật: `.kiro/specs/sam-pet-v2-optimized/requirements.md`

| # | Hạng mục | Trạng thái | Tham chiếu spec v2 |
|---|----------|-----------|-------------------|
| 5.1 | Thiết kế SQLite schema đầy đủ (11 bảng theo spec v2) | ⬜ | Yêu cầu 15, Section 6 design.md |
| 5.2 | Tạo class `Database` với PDO, WAL mode, transaction support | ⬜ | Yêu cầu 15 |
| 5.3 | Viết migration script `bin/migrate-csv-to-sqlite.php` | ⬜ | Yêu cầu 16 |
| 5.4 | Chuyển `Product` sang Repository + SQLite (tính `remainStock` bằng SQL JOIN) | ⬜ | Yêu cầu 1, 2 |
| 5.5 | Chuyển `ImportStock` và `ExportStock` sang SQLite | ⬜ | Yêu cầu 3, 4 |
| 5.6 | Chuyển `VetCare`, `Expenses`, `Report` sang SQLite | ⬜ | Yêu cầu 7, 8, 9 |
| 5.7 | Chuyển `ExportInvoice`, `OwnerPet`, `MedicalRecord` sang SQLite | ⬜ | Yêu cầu 10, 12, 13 |
| 5.8 | Chuyển `Stocktaking`, `RepackageHistory` sang SQLite | ⬜ | Yêu cầu 6 |
| 5.9 | Cập nhật `BackupService`: backup file `.db` thay vì ZIP CSV | ⬜ | Yêu cầu 14 |
| 5.10 | Thêm DateRangeFilter vào DataTable cho các bảng chính | ⬜ | Yêu cầu 17 |
| 5.11 | Dashboard lazy load chart qua AJAX thay vì nhúng data vào HTML | ⬜ | Yêu cầu 18 |
| 5.12 | Stocktaking: thay `prompt()` bằng Bootstrap Modal có xác nhận (StocktakingModal) | ⬜ | Yêu cầu 6 |

---

## Thứ tự đề xuất

```
✅ Nhóm 1 (lỗi nghiêm trọng)       — commit 984ec86
✅ Nhóm 2 (business logic)          — commit a75ba80
✅ Nhóm 3 (tổ chức code)            — commit 040b8e1
✅ Nhóm 4 (UI/UX)                   — commit ae91790
⏸ Nhóm 5 (SQLite migration)        — tạm dừng, làm trên branch riêng
⬜ Nhóm 6 (Category sản phẩm)      — làm cùng Nhóm 5 để tránh làm 2 lần
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
- Nhóm 5 nên làm trên branch `feature/sqlite-migration` tách từ `vet_2.0.0`.
- Nhóm 6 (Category) nên làm **cùng Nhóm 5** — khi có SQLite thì có FK thực sự và dùng `GROUP BY categoryId` cho báo cáo breakdown hiệu quả. Nếu cần ngay trước Nhóm 5 thì implement theo pattern CSV.
- Trước khi bắt đầu Nhóm 5, đọc kỹ `.kiro/specs/sam-pet-v2-optimized/requirements.md`.
- Task 3.4 (DI cho controllers) hoãn lại — có thể làm song song với Nhóm 5.
- Task 4.11, 4.12 hoãn lại — làm cùng lúc refactor view trong Nhóm 5.
