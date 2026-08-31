# Sam Pet — Kế hoạch cải thiện

> Cập nhật lần cuối: 2026-08-31 (Nhóm 1 ✅ | Nhóm 2 🔄 đang làm, chưa commit)
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

## Nhóm 2 — Business logic 🔄 ĐANG LÀM (chưa commit)

| # | Hạng mục | Trạng thái | Ghi chú |
|---|----------|-----------|---------|
| 2.1 | Thống nhất công thức `remaining` trong `Report`: `getDataToView()` và `getDataToViewChart()` đang tính khác nhau | ✅ | `remaining = revenue - expenses`, thêm `calcRemaining()` private, bỏ dòng comment-out |
| 2.2 | `VetCare::totalAmountByDate()`: thêm `treatmentProfit` vào kết quả trả về | ✅ | Caller không cần tự tính lại hằng số |
| 2.3 | `Expenses::doEdit()`: đổi sang replace-all strategy cho nhất quán với ImportStock / ExportStock | ✅ | Xóa hết rows theo ngày → insert lại. Fix thêm Referer header trong `ExpensesController` |
| 2.4 | Extract logic build invoice content thành method riêng, bỏ code clone giữa `doAdd` và `doEdit` trong `ExportInvoice` | ✅ | `buildInvoiceContent()` private dùng chung |
| 2.5 | Validate repackage âm: server tự tính `remainStock` trước khi cho phép chiết (liên quan 1.2) | ✅ | Đã xử lý trong task 1.2 |
| 2.6 | `Report`: thêm endpoint AJAX `GET /report/data-by-date` để auto-fill form từ data thực tế thay vì nhập tay | 🔄 | `Report::getDataByDate()` đã viết, còn thiếu: route + `dataByDateAction` trong controller + cập nhật view form |

---

## Nhóm 3 — Tổ chức code (refactor, không thay đổi behavior)

| # | Hạng mục | Trạng thái | Ghi chú |
|---|----------|-----------|---------|
| 3.1 | Tách `CommonService` thành các class nhỏ: `DataTableService`, `LoggerFactory`, `DateHelper` | ⬜ | Hiện là God Class toàn static |
| 3.2 | Xóa `getDataTablesParameters()` — dead code đọc `$_POST` trực tiếp | ⬜ | |
| 3.3 | Bỏ HTML generation ra khỏi Model (`getDataToView` sinh `$row['action']`) — chuyển sang DataTable column render | ⬜ | Vi phạm SRP |
| 3.4 | Thêm Dependency Injection cho `ProductController`, `ExportStockController`, `OverviewController` | ⬜ | Hiện `new Model()` trong action |
| 3.5 | Gộp `indexAction` và `expensesAction` trong `OverviewController` — hai action giống hệt nhau | ⬜ | |
| 3.6 | Chuyển `backupDataToStocktaking()` từ `CommonService` vào `BackupService` | ⬜ | Logic backup đang tồn tại ở 2 nơi |
| 3.7 | Extract hàm `remove(id)` dùng chung vào `common.js`, bỏ copy-paste ở từng view | ⬜ | Hiện mỗi trang tự viết lại |

---

## Nhóm 4 — UI/UX (cải thiện trải nghiệm người dùng)

| # | Hạng mục | Trạng thái | Ghi chú |
|---|----------|-----------|---------|
| 4.1 | Thêm active state cho navbar — highlight trang hiện tại | ⬜ | Hiện không có active nav item nào |
| 4.2 | Sửa tiêu đề tab: bỏ "Laminas MVC Skeleton", dùng `STORE_NAME + tên trang` | ⬜ | |
| 4.3 | Fix thứ tự CSS: `style.css` phải load sau `bootstrap.min.css` | ⬜ | Hiện bootstrap override style.css |
| 4.4 | Fix `position: fixed` của flash messages — thêm padding-top cho body | ⬜ | Message che nội dung trang |
| 4.5 | Fix duplicate HTML `id` trong product/index.phtml (2 modal dùng chung id) | ⬜ | Vi phạm HTML spec |
| 4.6 | Fix duplicate HTML `id` trong report/index.phtml (2 modal dùng chung id) + sửa typo `eidtReport` | ⬜ | |
| 4.7 | Thêm nút "Xóa dòng" vào `export-stock/add.phtml` (nhất quán với import-stock, expenses) | ⬜ | |
| 4.8 | Sửa overlay loading: tách text theo context (backup / loading / syncing) | ⬜ | Hiện luôn hiện "Đang backup dữ liệu..." |
| 4.9 | Loại bỏ `eval()` trong calculator widget — dùng math parser an toàn hoặc giới hạn phép tính | ⬜ | Security risk |
| 4.10 | Xóa file `select.dataTables.min.js` bị load trùng trong layout | ⬜ | Load 3 lần thay vì 1 |
| 4.11 | Thu gọn hoặc giới hạn scope calculator widget (ẩn trên các trang không cần) | ⬜ | Hiện hiển thị ở mọi trang |
| 4.12 | Thêm breadcrumb cho các trang edit/add lồng nhau (medical-record, export-invoice) | ⬜ | |

---

## Nhóm 5 — Database (nâng cấp lớn, làm sau cùng)

| # | Hạng mục | Trạng thái | Ghi chú |
|---|----------|-----------|---------|
| 5.1 | Thiết kế SQLite schema đầy đủ (11 bảng theo spec v2) | ⬜ | Nền tảng cho toàn bộ nhóm 5 |
| 5.2 | Tạo class `Database` với PDO, WAL mode, transaction support | ⬜ | |
| 5.3 | Viết migration script `bin/migrate-csv-to-sqlite.php` | ⬜ | Giữ lại dữ liệu hiện có |
| 5.4 | Chuyển `Product` sang Repository + SQLite (tính `remainStock` bằng SQL JOIN) | ⬜ | |
| 5.5 | Chuyển `ImportStock` và `ExportStock` sang SQLite | ⬜ | |
| 5.6 | Chuyển `VetCare`, `Expenses`, `Report` sang SQLite | ⬜ | |
| 5.7 | Chuyển `ExportInvoice`, `OwnerPet`, `MedicalRecord` sang SQLite | ⬜ | |
| 5.8 | Chuyển `Stocktaking`, `RepackageHistory` sang SQLite | ⬜ | |
| 5.9 | Cập nhật `BackupService`: backup file `.db` thay vì ZIP CSV | ⬜ | |
| 5.10 | Thêm DateRangeFilter vào DataTable cho các bảng chính | ⬜ | Yêu cầu v2 spec |
| 5.11 | Dashboard lazy load chart qua AJAX thay vì nhúng data vào HTML | ⬜ | Yêu cầu v2 spec |
| 5.12 | Stocktaking: thay `prompt()` bằng Bootstrap Modal có xác nhận (StocktakingModal) | ⬜ | Yêu cầu v2 spec |

---

## Thứ tự đề xuất

```
Nhóm 1 (lỗi nghiêm trọng)
  → Nhóm 4.5, 4.6 (fix duplicate id - nhanh, ít rủi ro)
    → Nhóm 2 (business logic)
      → Nhóm 3 (refactor)
        → Nhóm 4 còn lại
          → Nhóm 5 (SQLite migration - làm cuối, impact lớn nhất)
```

---

## Ghi chú nhanh

- Trước khi làm bất kỳ nhóm nào, commit code hiện tại lên branch riêng.
- Nhóm 5 nên làm trên branch `feature/sqlite-migration` riêng biệt.
- Các task 1.x có thể làm độc lập, không phụ thuộc nhau.
