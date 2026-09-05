# Kiến Trúc Hệ Thống — Sam Pet 2.0

Tài liệu mô tả kiến trúc tổng thể, mô hình luồng dữ liệu, các tầng xử lý và các nguyên tắc thiết kế của ứng dụng quản lý Sam Pet 2.0.

---

## 1. Tổng quan công nghệ (Tech Stack)

- **Ngôn ngữ:** PHP 8.1+
- **Framework:** Laminas MVC (tiền thân là Zend Framework)
- **Cơ sở dữ liệu:** SQLite 3 (Lưu trữ tập trung tại `data/app.db` với WAL Mode)
- **Frontend / Giao diện:**
  - Laminas PhpRenderer (View `.phtml`)
  - Bootstrap 5.2 (CSS local, JS bundle via CDN)
  - DataTables 2.1 (Phân trang, sắp xếp, lọc dữ liệu)
  - jQuery 3.7 & Select2 4.1 (Autocomplete chọn sản phẩm, ngày tháng)
- **Môi trường triển khai:** Docker (PHP-FPM + Nginx/Apache) hoặc PHP Built-in Server

---

## 2. Mô hình kiến trúc (Layered Architecture)

Hệ thống được tổ chức theo mô hình phân lớp rõ ràng (Layered / MVC + Repository):

```
                       ┌─────────────────────────┐
                       │  HTTP Request (Browser) │
                       └────────────┬────────────┘
                                    │
                                    ▼
                       ┌─────────────────────────┐
                       │  Laminas Routing &      │
                       │  CsrfService Middleware │
                       └────────────┬────────────┘
                                    │
                                    ▼
                       ┌─────────────────────────┐
                       │       Controllers       │
                       │ (Request handling, View)│
                       └──────┬───────────┬──────┘
                              │           │
                 ┌────────────┘           └────────────┐
                 ▼                                     ▼
      ┌─────────────────────┐               ┌─────────────────────┐
      │     Repositories    │               │       Services      │
      │(Data Access Layer,  │               │ (Business Logic,    │
      │ SQL queries, PDO)   │               │ Backup, Sheets...)  │
      └──────────┬──────────┘               └──────────┬──────────┘
                 │                                     │
                 └────────────┐           ┌────────────┘
                              ▼           ▼
                       ┌─────────────────────────┐
                       │  Database (SQLite PDO)  │
                       │ (Single-file: app.db)   │
                       └─────────────────────────┘
```

### 2.1. Tầng Presentation (Controllers & Views)
- **Controllers** (`module/Application/src/Controller/`):
  - Tiếp nhận request, trích xuất tham số, kiểm tra quyền & CSRF token.
  - Gọi Repository/Service tương ứng để xử lý nghiệp vụ.
  - Thiết lập flash messages (thành công/lỗi) và điều hướng (redirect) hoặc trả về JSON/ViewModel.
- **Views** (`module/Application/view/`):
  - View layout dùng chung `layout/layout.phtml` tích hợp thanh điều hướng (navbar), flash message và nạp assets.
  - Partial templates: `partial/date-range-filter.phtml` dùng lọc ngày thống kê.

### 2.2. Tầng Data Access (Repository Pattern)
- Tách biệt hoàn toàn câu lệnh SQL khỏi Controller.
- **BaseRepository** (`module/Application/src/Repository/BaseRepository.php`):
  - Cung cấp kết nối PDO từ `Database::getInstance()`.
  - Hỗ trợ các hàm tiện ích: sinh ID (uniqid hex 13 ký tự), thực thi transaction, build query lọc theo khoảng thời gian (`filterByDateRange`).
- **Domain Repositories**:
  - `ProductRepository`: Quản lý kho, cập nhật số lượng tồn kho tự động, chiết hàng (repackage).
  - `ImportStockRepository` & `ExportStockRepository`: Quản lý phiếu nhập/xuất kho và đồng bộ tồn kho.
  - `ExpensesRepository`, `ReportRepository`, `ExportInvoiceRepository`: Xử lý sổ quỹ, hóa đơn bán hàng và báo cáo doanh thu.
  - `OwnerPetRepository`, `MedicalRecordRepository`, `VetCareRepository`: Quản lý hồ sơ thú cưng, sổ khám bệnh và dịch vụ spa/thú y.
  - `StocktakingRepository`, `CategoryRepository`, `RepackageHistoryRepository`.

### 2.3. Tầng Dịch vụ (Services)
- **BackupService** (`module/Application/src/Service/BackupService.php`):
  - Quản lý 3 cơ chế sao lưu: Daily auto-backup (giữ 30 ngày), Stocktaking backup (giữ 10 bản), và Đồng bộ Cloud GitHub Releases.
- **CsrfService** (`module/Application/src/Service/CsrfService.php`):
  - Quản lý sinh và xác thực CSRF Token chống tấn công giả mạo request cho toàn bộ form và ajax action.
- **DataTableService** (`module/Application/src/Service/DataTableService.php`):
  - Hỗ trợ render cấu hình DataTables server-side / client-side.
- **GoogleSheetsService** (`module/Application/src/Service/GoogleSheetsService.php`):
  - Tích hợp đồng bộ báo cáo định kỳ với Google Sheets.
- **LoggerFactory** (`module/Application/src/Service/LoggerFactory.php`):
  - Ghi log hệ thống và lỗi truy vấn CSDL.

---

## 3. Quản lý Trạng thái & Cơ sở dữ liệu (SQLite WAL Mode)

- CSDL sử dụng SQLite nằm ở đường dẫn cấu hình `data/app.db`.
- Kích hoạt **PRAGMA journal_mode = WAL;** (Write-Ahead Logging) cho phép nhiều luồng đọc đồng thời mà không bị khóa khi đang ghi.
- Enforce **PRAGMA foreign_keys = ON;** đảm bảo tính toàn vẹn quan hệ giữa các bảng.
- Định danh khóa chính (`id`) sử dụng chuỗi 13 ký tự (`uniqid()`) đảm bảo tính tương thích và không bị trùng lặp khi phân tán.
