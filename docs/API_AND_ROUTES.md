# Danh Sách Đường Dẫn & Điểm Cuối (API & Routes) — Sam Pet 2.0

Tài liệu đặc tả toàn bộ các tuyến đường dẫn (Routes), HTTP methods, Controllers và Actions tương ứng trong hệ thống.

---

## 1. Phân Hệ Tổng Quan & Dashboard

| Phương thức | Đường dẫn (URL) | Controller & Action | Mô tả chức năng |
|:---|:---|:---|:---|
| `GET` | `/` | `OverviewController::indexAction` | Trang chủ / Màn hình tổng quan |
| `GET` | `/overview` | `OverviewController::indexAction` | Bảng điều khiển tài chính |
| `GET` | `/overview/chart-data` | `OverviewController::chartDataAction` | API dữ liệu biểu đồ doanh thu theo khoảng ngày |

---

## 2. Phân Hệ Quản Lý Kho & Hàng Hóa

| Phương thức | Đường dẫn (URL) | Controller & Action | Mô tả chức năng |
|:---|:---|:---|:---|
| `GET` | `/product` | `ProductController::indexAction` | Danh sách sản phẩm & tồn kho |
| `GET` / `POST` | `/product/add` | `ProductController::addAction` / `doAddAction` | Thêm mới sản phẩm |
| `GET` / `POST` | `/product/edit/:id` | `ProductController::editAction` / `doEditAction` | Chỉnh sửa thông tin sản phẩm |
| `POST` | `/product/delete/:id` | `ProductController::deleteAction` | Xóa sản phẩm |
| `GET` / `POST` | `/product/repackage` | `ProductController::repackageAction` / `doRepackageAction` | Chiết hàng (tách gói to sang gói nhỏ) |
| `GET` / `POST` | `/product/add-invoice-check` | `ProductController::addInvoiceCheckAction` | Cập nhật trạng thái đối soát hóa đơn |
| `GET` | `/category` | `CategoryController::indexAction` | Quản lý danh mục sản phẩm |
| `POST` | `/category/add` | `CategoryController::doAddAction` | Thêm danh mục |
| `POST` | `/category/edit/:id` | `CategoryController::doEditAction` | Sửa danh mục |
| `POST` | `/category/delete/:id` | `CategoryController::deleteAction` | Xóa danh mục |

---

## 3. Nhập Kho & Xuất Hàng

| Phương thức | Đường dẫn (URL) | Controller & Action | Mô tả chức năng |
|:---|:---|:---|:---|
| `GET` | `/import-stock` | `ImportStockController::indexAction` | Danh sách các phiếu nhập hàng |
| `GET` / `POST` | `/import-stock/add` | `ImportStockController::addAction` / `doAddAction` | Tạo phiếu nhập kho mới |
| `GET` / `POST` | `/import-stock/edit/:date` | `ImportStockController::editAction` / `doEditAction` | Sửa các phiếu nhập trong ngày |
| `POST` | `/import-stock/delete/:id` | `ImportStockController::deleteAction` | Xóa 1 dòng nhập kho |
| `GET` | `/export-stock` | `ExportStockController::indexAction` | Danh sách các dòng xuất bán lẻ |
| `GET` / `POST` | `/export-stock/add` | `ExportStockController::addAction` / `doAddAction` | Xuất bán hàng |
| `GET` / `POST` | `/export-stock/edit/:date` | `ExportStockController::editAction` / `doEditAction` | Sửa xuất hàng theo ngày |
| `POST` | `/export-stock/delete/:id` | `ExportStockController::deleteAction` | Xóa dòng xuất hàng |
| `GET` | `/export-stock/sync-preview` | `ExportStockController::syncPreviewAction` | Xem trước dữ liệu đồng bộ Google Sheets |
| `POST` | `/export-stock/do-sync` | `ExportStockController::doSyncAction` | Thực hiện đồng bộ Google Sheets |

---

## 4. Kiểm Kê Kho Hàng

| Phương thức | Đường dẫn (URL) | Controller & Action | Mô tả chức năng |
|:---|:---|:---|:---|
| `GET` | `/stocktaking` | `StocktakingController::indexAction` | Bảng kiểm kê kho thực tế |
| `POST` | `/stocktaking/edit` | `StocktakingController::doEditAction` | Lưu số lượng đếm kiểm kê |
| `POST` | `/stocktaking/renew-warehouse` | `StocktakingController::renewWarehouseAction` | Chốt kho & khởi tạo kỳ tồn kho mới |

---

## 5. Thú Y, Sổ Khám & Spa

| Phương thức | Đường dẫn (URL) | Controller & Action | Mô tả chức năng |
|:---|:---|:---|:---|
| `GET` | `/owner-pet` | `OwnerPetController::indexAction` | Danh sách hồ sơ chủ & thú cưng |
| `GET` / `POST` | `/owner-pet/add` | `OwnerPetController::addAction` / `doAddAction` | Tạo hồ sơ thú cưng |
| `GET` / `POST` | `/owner-pet/edit/:id` | `OwnerPetController::editAction` / `doEditAction` | Sửa hồ sơ thú cưng |
| `POST` | `/owner-pet/delete/:id` | `OwnerPetController::deleteAction` | Xóa hồ sơ thú cưng |
| `GET` | `/medical-record` | `MedicalRecordController::indexAction` | Danh sách sổ khám bệnh |
| `GET` / `POST` | `/medical-record/add/:petId` | `MedicalRecordController::addAction` / `doAddAction` | Thêm phiếu khám bệnh |
| `GET` / `POST` | `/medical-record/edit/:id` | `MedicalRecordController::editAction` / `doEditAction` | Cập nhật thông tin phiếu khám |
| `GET` | `/medical-record/history/:petId`| `MedicalRecordController::historyAction` | Lịch sử khám theo thú cưng |
| `GET` | `/vet-care` | `VetCareController::indexAction` | Doanh thu dịch vụ khám & spa |
| `GET` / `POST` | `/vet-care/add` | `VetCareController::addAction` / `doAddAction` | Ghi nhận doanh thu dịch vụ ngày |
| `GET` / `POST` | `/vet-care/edit/:id` | `VetCareController::editAction` / `doEditAction` | Sửa phiếu dịch vụ |
| `POST` | `/vet-care/delete/:id` | `VetCareController::deleteAction` | Xóa phiếu dịch vụ |

---

## 6. Tài Chính & Báo Cáo

| Phương thức | Đường dẫn (URL) | Controller & Action | Mô tả chức năng |
|:---|:---|:---|:---|
| `GET` | `/expenses` | `ExpensesController::indexAction` | Danh sách chi phí & rút tiết kiệm |
| `GET` / `POST` | `/expenses/add` | `ExpensesController::addAction` / `doAddAction` | Ghi nhận khoản chi mới |
| `GET` / `POST` | `/expenses/edit/:date` | `ExpensesController::editAction` / `doEditAction` | Sửa chi phí trong ngày |
| `POST` | `/expenses/delete/:id` | `ExpensesController::deleteAction` | Xóa khoản chi |
| `GET` | `/report` | `ReportController::indexAction` | Báo cáo doanh thu & lợi nhuận |
| `GET` / `POST` | `/report/add` | `ReportController::addAction` / `doAddAction` | Thêm báo cáo ngày |
| `GET` / `POST` | `/report/edit/:id` | `ReportController::editAction` / `doEditAction` | Chỉnh sửa báo cáo ngày |
| `GET` | `/report/data-by-date` | `ReportController::dataByDateAction` | AJAX: Tự động gom số liệu bán hàng, dịch vụ, chi phí của ngày |
| `GET` | `/export-invoice` | `ExportInvoiceController::indexAction` | Sổ hóa đơn bán lẻ |
| `GET` / `POST` | `/export-invoice/add/:date` | `ExportInvoiceController::addAction` / `doAddAction` | Lập hóa đơn từ xuất kho |
| `GET` | `/export-invoice/pdf/:id` | `ExportInvoiceController::pdfAction` | Xuất/In PDF hóa đơn bán lẻ |

---

## 7. Cài Đặt & Quản Trị Hệ Thống

| Phương thức | Đường dẫn (URL) | Controller & Action | Mô tả chức năng |
|:---|:---|:---|:---|
| `GET` | `/settings` | `SettingsController::indexAction` | Màn hình cấu hình & trạng thái sao lưu |
| `POST` | `/settings/do-restore` | `SettingsController::doRestoreAction` | Khôi phục CSDL từ Cloud GitHub Releases |
| `POST` | `/settings/clear-cache` | `SettingsController::clearCacheAction` | Xóa config cache của Laminas |
