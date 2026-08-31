# Tài liệu Yêu Cầu Hệ Thống: Sam Pet v2 — Optimized

## Giới thiệu

**Sam Pet v2** là phiên bản cải tiến của ứng dụng web quản lý cửa hàng thú cưng tích hợp phòng khám thú y. Hệ thống kế thừa toàn bộ nghiệp vụ của v1 (quản lý bán lẻ, nhập/xuất hàng, tồn kho, hóa đơn, phòng khám, báo cáo) đồng thời nâng cấp hạ tầng lưu trữ từ CSV sang **SQLite**, cải thiện UX trên một số luồng nghiệp vụ quan trọng và sửa các lỗi kỹ thuật tồn đọng từ v1.

Các cải tiến chính so với v1:
1. **Storage**: CSV → SQLite (PDO, transaction, locking)
2. **Report Form**: load dữ liệu bằng AJAX theo ngày thay vì nhúng vào HTML
3. **Stocktaking Modal**: thay `prompt()` + GET bằng Bootstrap Modal + POST có xác nhận
4. **Date Range Filter**: bộ lọc khoảng thời gian trên các bảng dữ liệu chính
5. **Fix duplicate HTML id**: form nhập/xuất hàng dùng `class` thay `id`
6. **Validate âm khi chiết hàng**: kiểm tra tồn kho đủ trước khi thực hiện repackage
7. **Overview Chart**: lazy load AJAX thay vì nhúng data vào HTML
8. **Tính tồn kho bằng SQL JOIN**: không load toàn bộ dữ liệu vào PHP memory

> **Quy ước đánh dấu**: Requirements mới hoàn toàn của v2 được ghi chú `[MỚI - V2]`. Requirements kế thừa từ v1 nhưng có thay đổi được ghi chú `[SỬA - V2]`. Requirements giữ nguyên từ v1 không ghi chú thêm.

---

## Bảng Thuật Ngữ (Glossary)

- **System**: Ứng dụng web Sam Pet v2 (Laminas MVC, PHP 8.x)
- **Database**: File SQLite tại `./data/app.db`, truy cập qua PHP PDO
- **Repository**: Lớp trung gian giữa Controller và Database, thay thế vai trò của Model trong v1; thực hiện tất cả câu truy vấn SQL
- **Product**: Repository quản lý danh mục sản phẩm và tính tồn kho
- **ImportStock**: Repository quản lý phiếu nhập hàng
- **ExportStock**: Repository quản lý phiếu xuất hàng
- **VetCare**: Repository quản lý doanh thu điều trị và spa theo ngày
- **Expenses**: Repository quản lý chi phí vận hành và tiết kiệm
- **Report**: Repository quản lý báo cáo thu–chi hàng ngày
- **ExportInvoice**: Repository quản lý hóa đơn xuất bán
- **OwnerPet**: Repository quản lý hồ sơ chủ sở hữu và thú cưng
- **MedicalRecord**: Repository quản lý hồ sơ khám bệnh / điều trị thú cưng
- **Stocktaking**: Repository quản lý kiểm kê tồn kho
- **RepackageHistory**: Repository lưu lịch sử chiết hàng
- **PdfGenerator**: Utility class tạo file PDF (không thay đổi so với v1)
- **CommonService**: Static utility service (DataTables, sort, compare, logger, ...)
- **BackupService**: Service backup/restore file `app.db` qua GitHub Releases
- **GoogleSheetsService**: Service đồng bộ dữ liệu xuất hàng từ Google Apps Script
- **DataTables**: Thư viện hiển thị bảng dữ liệu phía client với server-side processing
- **remainStock**: Tồn kho còn lại = `initStock + repackageStock + Σimport − Σexport`, tính bằng SQL JOIN
- **invoiceCheck**: Flag `1/0` (INTEGER) xác định sản phẩm có được in trong hóa đơn hay không
- **releaseTag**: Tag GitHub Release (`data-backup-dev` hoặc `data-backup-prod`) tương ứng `APP_ENV`
- **Repackage**: Thao tác chiết hàng từ sản phẩm nguồn sang sản phẩm đích
- **DateRangeFilter**: Bộ lọc khoảng thời gian gồm `date_from` và `date_to` (định dạng `dd-mm-yyyy`)
- **StocktakingModal**: Bootstrap Modal xác nhận trước khi chốt kho, thay thế `prompt()` của v1
- **ChartData**: Dữ liệu biểu đồ trả về dưới dạng JSON qua AJAX endpoint, không nhúng vào HTML
- **Migration**: Quá trình chuyển đổi dữ liệu từ các file CSV của v1 sang SQLite database

---

## Yêu Cầu

---

### Yêu Cầu 1: Quản Lý Sản Phẩm `[SỬA - V2]`

**User Story:** Là nhân viên cửa hàng, tôi muốn quản lý danh mục sản phẩm, để tôi có thể theo dõi thông tin và tồn kho của từng mặt hàng.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu thông tin sản phẩm gồm các trường: `id` (INTEGER PRIMARY KEY AUTOINCREMENT), `name`, `unit`, `sellingPrice` (REAL), `purchasePrice` (REAL), `initStock` (REAL), `repackageStock` (REAL), `invoiceCheck` (INTEGER, mặc định 0), `createdAt` (INTEGER), `updatedAt` (INTEGER) vào bảng `products` trong SQLite database.
2. WHEN người dùng yêu cầu danh sách sản phẩm, THE Product SHALL tính `remainStock` bằng SQL JOIN: `initStock + repackageStock + COALESCE(SUM(import.quantity), 0) − COALESCE(SUM(export.quantity), 0)` mà không load toàn bộ dữ liệu vào PHP memory. `[SỬA - V2: SQL JOIN thay vì PHP array]`
3. WHEN người dùng yêu cầu danh sách sản phẩm, THE Product SHALL tính `profit = (sellingPrice − purchasePrice) × remainStock` cho từng sản phẩm.
4. WHEN người dùng thêm một sản phẩm mới, THE System SHALL tạo bản ghi với `createdAt = updatedAt = time()` và lưu vào bảng `products`, với `id` do SQLite tự sinh (AUTOINCREMENT).
5. WHEN người dùng sửa thông tin sản phẩm, THE System SHALL cập nhật bản ghi tương ứng và đặt `updatedAt = time()`.
6. WHEN người dùng xóa một sản phẩm, THE System SHALL xóa bản ghi khỏi bảng `products` và trả về JSON `{success: true}`.
7. WHEN người dùng gửi yêu cầu DataTables server-side, THE CommonService SHALL thực hiện filter, sort, paginate và trả về JSON theo định dạng DataTables.
8. WHEN người dùng toggle `invoiceCheck` cho một hoặc nhiều sản phẩm, THE Product SHALL cập nhật field `invoiceCheck` thành `0` hoặc `1` cho các sản phẩm được chỉ định trong một transaction duy nhất.

---

### Yêu Cầu 2: Chiết Hàng (Repackage) `[SỬA - V2]`

**User Story:** Là nhân viên kho, tôi muốn chiết hàng từ sản phẩm lớn sang sản phẩm nhỏ, để tôi có thể quản lý tồn kho chính xác sau thao tác phân chia đơn vị.

#### Tiêu Chí Chấp Thuận

1. WHEN người dùng thực hiện chiết hàng với `fromProductId`, `toProductId`, `fromQuantity`, `toQuantity`, THE Product SHALL trừ `fromQuantity` khỏi `repackageStock` của sản phẩm nguồn và cộng `toQuantity` vào `repackageStock` của sản phẩm đích trong một transaction SQLite duy nhất.
2. BEFORE thực hiện chiết hàng, THE Product SHALL tính `currentRemainStock` của sản phẩm nguồn bằng SQL JOIN; IF `currentRemainStock < fromQuantity`, THEN THE System SHALL từ chối thao tác và trả về JSON `{success: false, message: "Tồn kho không đủ để chiết. Hiện còn: {currentRemainStock} {unit}"}`. `[MỚI - V2: validate âm]`
3. WHEN một thao tác chiết hàng hoàn thành, THE RepackageHistory SHALL lưu bản ghi với `date`, `content` mô tả chi tiết thao tác (tên, số lượng, đơn vị của cả hai sản phẩm), `createdAt`, `updatedAt`.
4. WHEN người dùng xem lịch sử chiết hàng, THE RepackageHistory SHALL trả về danh sách bản ghi được sắp xếp giảm dần theo `createdAt`.

---

### Yêu Cầu 3: Quản Lý Nhập Hàng `[SỬA - V2]`

**User Story:** Là nhân viên kho, tôi muốn ghi nhận phiếu nhập hàng, để tôi có thể theo dõi lịch sử nhập và cập nhật tồn kho.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu phiếu nhập với các trường: `id` (INTEGER PRIMARY KEY AUTOINCREMENT), `date` (TEXT, dd-mm-yyyy), `productId` (INTEGER, FK → products.id), `productName` (TEXT, denormalized), `quantity` (REAL), `purchasePrice` (REAL), `note` (TEXT), `createdAt` (INTEGER), `updatedAt` (INTEGER) vào bảng `import_stock` trong SQLite database.
2. THE ImportStock SHALL lưu `productName` denormalized để đảm bảo lịch sử chính xác khi tên sản phẩm thay đổi sau này.
3. WHEN người dùng thêm phiếu nhập, THE System SHALL cho phép thêm nhiều dòng cùng một lần trong một transaction SQLite duy nhất.
4. WHEN người dùng sửa phiếu nhập theo ngày, THE System SHALL xóa toàn bộ rows của ngày đó và insert lại rows mới trong một transaction SQLite duy nhất (replace-all strategy).
5. WHEN người dùng xóa một dòng nhập, THE System SHALL xóa bản ghi theo `id` và trả về JSON `{success: true/false}`.
6. WHEN người dùng yêu cầu tổng số lượng nhập, THE ImportStock SHALL trả về tổng hợp `[productId => totalQuantity]` bằng SQL `GROUP BY productId`.
7. WHEN người dùng lọc danh sách nhập hàng theo khoảng thời gian, THE CommonService SHALL áp dụng điều kiện `date >= date_from AND date <= date_to` vào truy vấn DataTables server-side. `[MỚI - V2: DateRangeFilter]`

---

### Yêu Cầu 4: Quản Lý Xuất Hàng `[SỬA - V2]`

**User Story:** Là nhân viên bán hàng, tôi muốn ghi nhận phiếu xuất hàng, để tôi có thể theo dõi doanh thu và tồn kho sau mỗi lần bán.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu phiếu xuất với các trường: `id` (INTEGER PRIMARY KEY AUTOINCREMENT), `date` (TEXT, dd-mm-yyyy), `productId` (INTEGER, FK → products.id), `productName` (TEXT, denormalized), `quantity` (REAL), `sellingPrice` (REAL), `purchasePrice` (REAL), `note` (TEXT), `createdAt` (INTEGER), `updatedAt` (INTEGER) vào bảng `export_stock` trong SQLite database.
2. THE ExportStock SHALL lưu cả `sellingPrice` và `purchasePrice` tại thời điểm xuất để tính lợi nhuận chính xác.
3. WHEN người dùng thêm phiếu xuất, THE System SHALL cho phép thêm nhiều dòng cùng một lần trong một transaction SQLite duy nhất.
4. WHEN người dùng sửa phiếu xuất theo ngày, THE System SHALL sử dụng replace-all strategy trong một transaction SQLite duy nhất (xóa hết → insert lại).
5. WHEN người dùng yêu cầu tổng xuất hàng, THE ExportStock SHALL trả về `[productId => totalQuantity]` và `[date => {revenue, profit}]` bằng SQL `GROUP BY`, trong đó `profit = SUM(quantity × (sellingPrice − purchasePrice))`.
6. WHEN người dùng cần gộp dữ liệu xuất hàng theo sản phẩm để lập hóa đơn, THE ExportStock SHALL gộp các dòng cùng `productId` bằng SQL `GROUP BY` và bỏ qua sản phẩm có `invoiceCheck = 0` nếu flag `skipInvoiceCheckFalse` được bật.
7. WHEN người dùng lọc danh sách xuất hàng theo khoảng thời gian, THE CommonService SHALL áp dụng điều kiện `date >= date_from AND date <= date_to` vào truy vấn DataTables server-side. `[MỚI - V2: DateRangeFilter]`
8. WHEN form thêm/sửa phiếu xuất hiển thị nhiều dòng sản phẩm, THE System SHALL sử dụng attribute `class` (ví dụ: `.selling-price`, `.purchase-price`) thay vì `id` cho các input trong mỗi dòng để tránh duplicate HTML id. `[MỚI - V2: fix duplicate id]`

---

### Yêu Cầu 5: Đồng Bộ Từ Google Sheets `[SỬA - V2]`

**User Story:** Là quản lý cửa hàng, tôi muốn đồng bộ dữ liệu xuất hàng từ Google Sheets vào hệ thống, để tôi có thể nhập liệu bán hàng từ nhiều nguồn mà không bị trùng dữ liệu.

#### Tiêu Chí Chấp Thuận

1. WHEN người dùng yêu cầu preview đồng bộ, THE GoogleSheetsService SHALL gọi Apps Script Web App, parse JSON response `{status: "ok", rows: [...]}` và ép kiểu từng field đúng với schema xuất hàng.
2. WHEN GoogleSheetsService nhận dữ liệu từ Google Sheets, THE ExportStock SHALL lọc và chỉ giữ lại các rows chưa tồn tại `id` trong bảng `export_stock` (filterNewRows bằng SQL `WHERE id NOT IN (...)`).
3. WHEN người dùng xác nhận đồng bộ, THE ExportStock SHALL thực hiện `filterNewRows` lần thứ hai trong cùng một transaction SQLite để chống race condition trước khi insert.
4. WHEN ExportStock import rows từ Google Sheets, THE ExportStock SHALL validate từng row gồm: đủ fields bắt buộc và `productId` tồn tại trong bảng `products`; IF validation thất bại, THEN THE System SHALL bỏ qua row đó và tiếp tục import các row hợp lệ.
5. IF Apps Script Web App trả về lỗi hoặc không thể kết nối, THEN THE System SHALL ghi log lỗi và thông báo cho người dùng.

---

### Yêu Cầu 6: Kiểm Kê Kho (Stocktaking) `[SỬA - V2]`

**User Story:** Là quản lý kho, tôi muốn thực hiện kiểm kê định kỳ và chốt kho, để số liệu tồn kho trong hệ thống khớp với thực tế.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu dữ liệu kiểm kê vào bảng `stocktaking` với `productId` (INTEGER, UNIQUE, FK → products.id) làm primary key, đảm bảo mỗi sản phẩm có đúng một bản ghi kiểm kê.
2. WHEN người dùng cập nhật kiểm kê, THE System SHALL ghi giá trị `stocktaking` thực tế cho từng sản phẩm bằng SQL `INSERT OR REPLACE`.
3. WHEN người dùng nhấn nút "Chốt kho", THE System SHALL hiển thị StocktakingModal bao gồm: danh sách sản phẩm chưa điền số kiểm kê (highlight màu đỏ), checkbox xác nhận, và nút "Chốt kho" chỉ được enable sau khi người dùng đã tick checkbox xác nhận. `[MỚI - V2: StocktakingModal thay prompt()]`
4. WHEN người dùng xác nhận trong StocktakingModal, THE System SHALL gửi POST request đến `/stocktaking/do-renew-warehouse` thay vì GET request. `[MỚI - V2: POST thay GET]`
5. WHEN `doRenewWarehouse` được kích hoạt, THE BackupService SHALL tạo bản sao file `app.db` vào `./data/backup_stocktaking/backup_YYYYMMDD_HHiiss.db` trước khi thực hiện bất kỳ thay đổi nào. `[SỬA - V2: backup .db thay ZIP CSV]`
6. WHEN `renewWarehouse` hoàn thành bước backup, THE System SHALL xóa toàn bộ dữ liệu trong bảng `export_stock` bằng SQL `DELETE FROM export_stock`.
7. WHEN `renewWarehouse` hoàn thành bước backup, THE System SHALL xóa toàn bộ dữ liệu trong bảng `import_stock` bằng SQL `DELETE FROM import_stock`.
8. WHEN `renewWarehouse` xử lý từng sản phẩm, THE Product SHALL cập nhật `initStock` bằng giá trị `stocktaking` tương ứng và đặt `repackageStock = 0` trong một transaction SQLite duy nhất.
9. WHEN `renewWarehouse` hoàn thành cập nhật sản phẩm, THE System SHALL xóa toàn bộ dữ liệu trong bảng `stocktaking` bằng SQL `DELETE FROM stocktaking`.
10. IF bất kỳ bước nào trong quy trình `renewWarehouse` thất bại, THEN THE System SHALL rollback toàn bộ transaction và thông báo lỗi chi tiết cho người dùng qua FlashMessenger.

---

### Yêu Cầu 7: Quản Lý Doanh Thu Phòng Khám (VetCare) `[SỬA - V2]`

**User Story:** Là nhân viên phòng khám, tôi muốn ghi nhận doanh thu điều trị và spa theo ngày, để tôi có thể theo dõi hiệu quả kinh doanh của phòng khám.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu bản ghi VetCare với các trường: `id` (INTEGER PRIMARY KEY AUTOINCREMENT), `date` (TEXT, dd-mm-yyyy), `treatmentAmount` (REAL), `spaAmount` (REAL), `note` (TEXT), `createdAt` (INTEGER), `updatedAt` (INTEGER) vào bảng `vet_care` trong SQLite database.
2. WHEN người dùng yêu cầu tổng hợp theo ngày, THE VetCare SHALL trả về `[date => {treatment, spa, treatmentProfit}]` bằng SQL `GROUP BY date`, trong đó `treatmentProfit = treatmentAmount × 0.4`.
3. WHEN người dùng thêm, sửa hoặc xóa bản ghi VetCare, THE System SHALL thực hiện thao tác tương ứng và redirect hoặc trả về JSON `{success: true/false}`.
4. WHEN người dùng lọc danh sách VetCare theo khoảng thời gian, THE CommonService SHALL áp dụng điều kiện `date >= date_from AND date <= date_to` vào truy vấn DataTables server-side. `[MỚI - V2: DateRangeFilter]`

---

### Yêu Cầu 8: Quản Lý Chi Phí `[SỬA - V2]`

**User Story:** Là quản lý tài chính, tôi muốn ghi nhận chi phí vận hành và tiết kiệm theo ngày, để tôi có thể theo dõi dòng tiền và phân biệt chi phí thường với tiền tiết kiệm.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu chi phí với các trường: `id` (INTEGER PRIMARY KEY AUTOINCREMENT), `date` (TEXT, dd-mm-yyyy), `type` (INTEGER, `0` = chi phí thường, `1` = tiết kiệm), `reason` (TEXT), `amount` (REAL), `person` (TEXT), `note` (TEXT), `createdAt` (INTEGER), `updatedAt` (INTEGER) vào bảng `expenses` trong SQLite database.
2. WHEN người dùng thêm chi phí trong ngày, THE System SHALL cho phép thêm nhiều dòng cùng một lần trong một transaction SQLite duy nhất.
3. WHEN người dùng sửa chi phí theo ngày, THE System SHALL sử dụng replace-all strategy trong một transaction SQLite duy nhất.
4. WHEN người dùng yêu cầu tổng hợp chi phí, THE Expenses SHALL trả về `[date => {total, totalSavings}]` bằng SQL `GROUP BY date + CASE WHEN type = 0`, trong đó `total` = tổng `type = 0` và `totalSavings` = tổng `type = 1` theo ngày.
5. WHEN người dùng lọc danh sách chi phí theo khoảng thời gian, THE CommonService SHALL áp dụng điều kiện `date >= date_from AND date <= date_to` vào truy vấn DataTables server-side. `[MỚI - V2: DateRangeFilter]`
6. WHEN form thêm/sửa chi phí hiển thị nhiều dòng, THE System SHALL sử dụng attribute `class` thay vì `id` cho các input trong mỗi dòng để tránh duplicate HTML id. `[MỚI - V2: fix duplicate id]`

---

### Yêu Cầu 9: Báo Cáo Thu–Chi (Report) `[SỬA - V2]`

**User Story:** Là quản lý cửa hàng, tôi muốn lập báo cáo thu–chi hàng ngày với form tự động điền dữ liệu theo ngày, để tôi có thể tổng hợp số liệu nhanh chóng mà không cần nhập tay.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu báo cáo với các trường: `id` (INTEGER PRIMARY KEY AUTOINCREMENT), `date` (TEXT), `petShopRevenue` (REAL), `petShopProfit` (REAL), `spaRevenue` (REAL), `treatmentRevenue` (REAL), `expenses` (REAL), `savings` (REAL), `missingAmount` (REAL), `note` (TEXT), `createdAt` (INTEGER), `updatedAt` (INTEGER) vào bảng `reports` trong SQLite database.
2. WHEN người dùng xem danh sách báo cáo, THE Report SHALL tính thêm các trường: `revenue = petShopRevenue + spaRevenue + treatmentRevenue`, `remaining = revenue − expenses`, `treatmentProfit = treatmentRevenue × 0.4`.
3. WHEN người dùng xem biểu đồ tổng quan, THE Report SHALL chuyển đổi `date` từ định dạng `dd-mm-yyyy` sang Unix timestamp milliseconds (×1000) cho trục X của Highcharts.
4. WHEN người dùng chọn ngày trên form tạo/sửa báo cáo, THE System SHALL gọi AJAX endpoint `GET /report/data-by-date?date=dd-mm-yyyy` để lấy dữ liệu tổng hợp của ngày đó từ server, thay vì tải trước toàn bộ dữ liệu vào HTML. `[MỚI - V2: AJAX by date]`
5. WHEN endpoint `GET /report/data-by-date` nhận tham số `date`, THE Report SHALL truy vấn SQLite và trả về JSON gồm: `petShopRevenue` (tổng sellingPrice từ export_stock), `spaRevenue` (tổng spaAmount từ vet_care), `treatmentRevenue` (tổng treatmentAmount từ vet_care), `expenses` (tổng amount type=0 từ expenses), `savings` (tổng amount type=1 từ expenses) của ngày đó. `[MỚI - V2: AJAX endpoint]`
6. WHEN người dùng thêm hoặc sửa báo cáo thành công, THE System SHALL kích hoạt `BackupService::backup()` chạy bất đồng bộ (non-blocking) thông qua `CommonService::executeCommand()`.
7. IF lệnh backup bất đồng bộ thất bại, THEN THE System SHALL ghi log lỗi mà không làm gián đoạn response trả về người dùng.
8. WHEN người dùng lọc danh sách báo cáo theo khoảng thời gian, THE CommonService SHALL áp dụng điều kiện `date >= date_from AND date <= date_to` vào truy vấn DataTables server-side. `[MỚI - V2: DateRangeFilter]`

---

### Yêu Cầu 10: Quản Lý Hóa Đơn Xuất Bán (ExportInvoice) `[SỬA - V2]`

**User Story:** Là nhân viên bán hàng, tôi muốn tạo và in hóa đơn xuất bán, để tôi có thể cung cấp chứng từ bán hàng chính thức cho khách.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu hóa đơn với các trường: `id` (INTEGER PRIMARY KEY AUTOINCREMENT), `date` (TEXT), `content` (TEXT, JSON blob), `total` (REAL), `createdAt` (INTEGER), `updatedAt` (INTEGER) vào bảng `export_invoices` trong SQLite database.
2. THE ExportInvoice SHALL lưu `content` là JSON có cấu trúc: `{product: [{id, name, unit, quantity, sellingPrice, total}], spa: {desc, total}, treatment: {desc, total}}`.
3. WHEN người dùng tạo hóa đơn mới cho một ngày, THE System SHALL pre-fill dữ liệu từ bảng `export_stock` và `vet_care` của ngày đó bằng SQL query.
4. WHEN người dùng yêu cầu PDF hóa đơn, THE ExportInvoice SHALL parse `content` JSON và gọi `PdfGenerator::generate()` để xuất file PDF inline theo mẫu S1a-HKD.

---

### Yêu Cầu 11: Tạo PDF (PdfGenerator)

**User Story:** Là quản lý cửa hàng, tôi muốn in báo cáo và hóa đơn dưới dạng PDF, để tôi có thể lưu trữ và cung cấp chứng từ chuẩn.

#### Tiêu Chí Chấp Thuận

1. WHEN `PdfGenerator::generate()` được gọi với dữ liệu hóa đơn, THE PdfGenerator SHALL tạo file PDF sử dụng mPDF với encoding UTF-8 và font DejaVu Sans.
2. THE PdfGenerator SHALL điền thông tin cửa hàng từ `$_ENV`: `STORE_NAME`, `ADDRESS`, `MST_CODE`, `MST_NAME` vào header PDF.
3. THE PdfGenerator SHALL xuất PDF theo mẫu S1a-HKD gồm: header thông tin hộ kinh doanh, tiêu đề "Sổ chi tiết doanh thu" + tháng/năm, bảng dữ liệu (STT, Ngày, Mã hàng, Tên hàng, ĐVT, SL, Đơn giá, Thành tiền), footer tổng cộng và ký tên.
4. WHEN PDF được tạo thành công, THE System SHALL trả về binary PDF stream để hiển thị inline trên trình duyệt.

---

### Yêu Cầu 12: Quản Lý Hồ Sơ Chủ Thú Cưng (OwnerPet) `[SỬA - V2]`

**User Story:** Là nhân viên phòng khám, tôi muốn quản lý hồ sơ chủ sở hữu và thú cưng, để tôi có thể tra cứu nhanh thông tin thú cưng khi tiếp nhận khám bệnh.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu hồ sơ với các trường: `id` (INTEGER PRIMARY KEY AUTOINCREMENT), `owner_name`, `phone`, `pet_name`, `species`, `breed`, `gender`, `age`, `note`, `createdAt` (INTEGER), `updatedAt` (INTEGER) vào bảng `owners_pets` trong SQLite database.
2. WHEN người dùng tìm kiếm thú cưng theo tên, THE OwnerPet SHALL lọc các records có `pet_name` chứa chuỗi tìm kiếm bằng SQL `LIKE '%keyword%'` (case-insensitive) và trả về JSON array `[{id, pet_name, owner_name, phone, ...}]`.
3. WHEN người dùng thêm, sửa hoặc xóa hồ sơ, THE System SHALL thực hiện thao tác CRUD tương ứng trên bảng `owners_pets`.

---

### Yêu Cầu 13: Quản Lý Hồ Sơ Khám Bệnh (MedicalRecord) `[SỬA - V2]`

**User Story:** Là bác sĩ thú y, tôi muốn ghi nhận và xem lại lịch sử khám bệnh của từng thú cưng, để tôi có thể đưa ra chẩn đoán và điều trị chính xác dựa trên tiền sử bệnh.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu hồ sơ khám với các trường: `id` (INTEGER PRIMARY KEY AUTOINCREMENT), `pet_id` (INTEGER, FK → owners_pets.id), `visit_date` (TEXT, dd-mm-yyyy), `symptoms`, `diagnosis`, `prescription`, `start_date`, `end_date`, `createdAt` (INTEGER), `updatedAt` (INTEGER) vào bảng `medical_records` trong SQLite database.
2. WHEN người dùng xem lịch sử khám của một thú cưng, THE MedicalRecord SHALL trả về tất cả records có `pet_id` khớp bằng SQL `WHERE pet_id = ? ORDER BY visit_date DESC`.
3. WHEN người dùng xem danh sách tất cả hồ sơ khám, THE MedicalRecord SHALL thực hiện SQL JOIN với bảng `owners_pets` để enrich mỗi record với `pet_name`, `owner_name`, `species`.
4. WHEN người dùng thêm hồ sơ khám, THE System SHALL liên kết bản ghi với `petId` được chỉ định trong URL thông qua foreign key `pet_id`.

---

### Yêu Cầu 14: Backup và Restore Dữ Liệu (BackupService) `[SỬA - V2]`

**User Story:** Là quản lý hệ thống, tôi muốn backup và restore toàn bộ dữ liệu qua GitHub Releases, để tôi có thể khôi phục dữ liệu khi cần thiết.

#### Tiêu Chí Chấp Thuận

1. WHEN `BackupService::backup()` được gọi, THE BackupService SHALL tạo bản sao file `./data/app.db` vào `./data/cache/backup.db` bằng SQLite `VACUUM INTO` hoặc PHP `copy()`, không dùng ZIP. `[SỬA - V2: backup file .db thay ZIP CSV]`
2. WHEN file backup được tạo xong, THE BackupService SHALL upload lên GitHub Releases: upsert release với `releaseTag`, xóa asset `backup.db` cũ nếu có, rồi upload asset mới với tên `backup.db`. `[SỬA - V2: asset backup.db thay backup.zip]`
3. WHERE `APP_ENV = dev`, THE BackupService SHALL sử dụng tag `data-backup-dev`; WHERE `APP_ENV = prod`, THE BackupService SHALL sử dụng tag `data-backup-prod`.
4. WHEN `BackupService::restore()` được gọi, THE BackupService SHALL lấy `browser_download_url` của asset `backup.db` từ release tương ứng, download về `./data/cache/backup_restore.db`, sau đó thay thế `./data/app.db` bằng file download. `[SỬA - V2: restore file .db]`
5. IF GitHub API trả về lỗi trong quá trình backup hoặc restore, THEN THE BackupService SHALL ghi log lỗi chi tiết vào file log tháng hiện tại.
6. THE BackupService SHALL đọc `GITHUB_TOKEN`, `GITHUB_REPO_OWNER`, `GITHUB_REPO_NAME` từ biến môi trường `$_ENV` (không hardcode).

---

### Yêu Cầu 15: Lưu Trữ SQLite (Database Layer) `[MỚI - V2]`

**User Story:** Là hệ thống, tôi muốn có một lớp Database thống nhất sử dụng SQLite thông qua PHP PDO, để tất cả Repository có thể truy cập dữ liệu với transaction và locking an toàn.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL khởi tạo kết nối SQLite tại `./data/app.db` thông qua PHP PDO với `PDO::ATTR_ERRMODE = PDO::ERRMODE_EXCEPTION` và `PRAGMA journal_mode = WAL` để hỗ trợ concurrent reads.
2. WHEN `Database::beginTransaction()` được gọi, THE System SHALL mở một SQLite transaction; WHEN `commit()` được gọi, THE System SHALL commit; IF bất kỳ lỗi nào xảy ra, THE System SHALL gọi `rollback()` tự động.
3. THE System SHALL tạo tất cả bảng cần thiết khi khởi động lần đầu (schema migration) nếu chưa tồn tại, bao gồm: `products`, `import_stock`, `export_stock`, `vet_care`, `expenses`, `reports`, `export_invoices`, `owners_pets`, `medical_records`, `stocktaking`, `repackage_history`.
4. WHEN `Database::migrate()` được gọi, THE System SHALL kiểm tra phiên bản schema hiện tại bằng `PRAGMA user_version`, áp dụng các migration scripts còn thiếu theo thứ tự tăng dần, và cập nhật `user_version` sau mỗi migration thành công.
5. IF file `./data/app.db` chưa tồn tại khi ứng dụng khởi động, THEN THE System SHALL tạo database mới và áp dụng toàn bộ schema migration từ đầu.

---

### Yêu Cầu 16: Migration Dữ Liệu CSV sang SQLite `[MỚI - V2]`

**User Story:** Là quản trị viên, tôi muốn có công cụ migrate dữ liệu từ các file CSV của v1 sang SQLite của v2, để tôi có thể nâng cấp hệ thống mà không mất dữ liệu hiện có.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL cung cấp script CLI (`bin/migrate-csv-to-sqlite.php`) để đọc toàn bộ các file CSV trong `./data/` và insert vào bảng tương ứng trong `app.db`.
2. WHEN script migration được chạy, THE System SHALL đọc từng file CSV theo thứ tự phụ thuộc: `products` trước, sau đó các bảng có foreign key.
3. WHEN migration insert dữ liệu, THE System SHALL preserve toàn bộ giá trị `id`, `createdAt`, `updatedAt` gốc từ CSV, không sinh lại id mới.
4. IF một row CSV có `id` trùng với row đã tồn tại trong SQLite, THEN THE System SHALL bỏ qua row đó (INSERT OR IGNORE) và ghi log cảnh báo.
5. WHEN migration hoàn thành, THE System SHALL in ra báo cáo tổng hợp: số rows đã migrate thành công và số rows bị bỏ qua cho mỗi bảng.
6. IF script migration gặp lỗi không phục hồi được, THEN THE System SHALL rollback toàn bộ transaction và giữ nguyên file CSV gốc.

---

### Yêu Cầu 17: Xử Lý DataTables Server-Side với Date Range Filter `[SỬA - V2]`

**User Story:** Là người dùng, tôi muốn các bảng dữ liệu hỗ trợ tìm kiếm, sắp xếp, phân trang và lọc theo khoảng thời gian, để tôi có thể duyệt và lọc dữ liệu lớn một cách nhanh chóng.

#### Tiêu Chí Chấp Thuận

1. WHEN `CommonService::dataTableServerSideProcessing()` nhận request, THE CommonService SHALL áp dụng tuần tự: filter theo date range (nếu có) → `filterData` → `sortData` → `paginateData` → thêm field `no` (số thứ tự) → trả về JSON gồm `draw`, `recordsTotal`, `recordsFiltered`, `data`. `[SỬA - V2: thêm bước filter date range]`
2. WHEN `filterData()` được gọi với `searchValue`, THE CommonService SHALL tìm kiếm full-text trên tất cả fields bằng SQL `LIKE` (case-insensitive).
3. WHEN DataTables request có tham số `date_from` và/hoặc `date_to`, THE CommonService SHALL áp dụng điều kiện `date >= date_from AND date <= date_to` vào SQL query trước bước filter text. `[MỚI - V2]`
4. WHEN `sortData()` được gọi trên field `date` định dạng `dd-mm-yyyy`, THE CommonService SHALL sắp xếp đúng thứ tự thời gian bằng SQL `ORDER BY substr(date,7,4)||substr(date,4,2)||substr(date,1,2)`; WHEN field là chuỗi văn bản, THE CommonService SHALL sử dụng `Collator('vi_VN')` để sort đúng tiếng Việt.
5. WHEN `paginateData()` được gọi, THE CommonService SHALL trả về slice dữ liệu từ vị trí `start` với độ dài `length` bằng SQL `LIMIT ? OFFSET ?`.
6. WHEN trang hiển thị DataTables có bộ lọc date range, THE System SHALL render 2 input datepicker "Từ ngày" và "Đến ngày" trên trang, gửi `date_from` và `date_to` theo mỗi AJAX request của DataTables. `[MỚI - V2]`

---

### Yêu Cầu 18: Tổng Quan Dashboard với Lazy Load Chart `[SỬA - V2]`

**User Story:** Là quản lý cửa hàng, tôi muốn xem biểu đồ tổng quan doanh thu và chi phí với bộ lọc khoảng thời gian, để tôi có thể nắm bắt xu hướng kinh doanh một cách trực quan mà không làm chậm thời gian tải trang.

#### Tiêu Chí Chấp Thuận

1. WHEN người dùng truy cập dashboard, THE System SHALL render trang HTML với biểu đồ rỗng và date range picker, KHÔNG nhúng data vào HTML. `[MỚI - V2: lazy load]`
2. WHEN trang dashboard tải xong hoặc người dùng thay đổi date range, THE System SHALL gọi AJAX endpoint `GET /overview/chart-data?from=dd-mm-yyyy&to=dd-mm-yyyy&type=daily|monthly` để lấy dữ liệu biểu đồ. `[MỚI - V2: AJAX endpoint]`
3. WHEN endpoint `GET /overview/chart-data` nhận tham số hợp lệ, THE Report SHALL truy vấn SQLite và trả về JSON với 4 series: `revenue`, `profit`, `expenses`, `savings` theo ngày (type=daily) hoặc theo tháng (type=monthly), với trục X là Unix timestamp milliseconds. `[MỚI - V2]`
4. WHEN endpoint `GET /overview/chart-data` nhận `type=monthly`, THE Report SHALL group dữ liệu theo tháng (MM-YYYY) bằng SQL `GROUP BY substr(date,4,7)` và tính tổng từng series.
5. WHEN người dùng truy cập trang expenses overview, THE System SHALL gọi cùng endpoint với tham số phù hợp và hiển thị chart tập trung vào `expenses` và `savings`.
6. THE System SHALL hiển thị `STORE_NAME` từ biến môi trường trong navbar layout của mọi trang.

---

### Yêu Cầu 19: Cài Đặt Hệ Thống (Settings) `[SỬA - V2]`

**User Story:** Là quản trị viên, tôi muốn xem thông tin hệ thống và thực hiện restore dữ liệu từ backup, để tôi có thể vận hành và khôi phục hệ thống khi cần.

#### Tiêu Chí Chấp Thuận

1. WHEN người dùng truy cập trang cài đặt, THE System SHALL hiển thị thông tin hệ thống (phiên bản PHP, đường dẫn DB, kích thước file `app.db`) và cung cấp chức năng backup/restore.
2. WHEN người dùng kích hoạt restore, THE BackupService SHALL thực hiện quy trình restore: download file `backup.db` từ GitHub Release → thay thế `./data/app.db`. `[SỬA - V2: restore .db]`
3. IF restore thành công, THEN THE System SHALL thông báo thành công cho người dùng qua FlashMessenger.
4. IF restore thất bại, THEN THE System SHALL thông báo lỗi chi tiết cho người dùng qua FlashMessenger.

---

### Yêu Cầu 20: Logging Hệ Thống

**User Story:** Là quản trị viên, tôi muốn hệ thống ghi log các sự kiện và lỗi, để tôi có thể chẩn đoán sự cố và theo dõi hoạt động.

#### Tiêu Chí Chấp Thuận

1. THE CommonService SHALL tạo Monolog Logger ghi vào file `logs/app_YYYY-MM.log` (xoay vòng theo tháng) cho các log ứng dụng thông thường.
2. THE CommonService SHALL tạo Monolog Logger riêng ghi vào file `logs/exception_YYYY-MM.log` cho các exception toàn cục.
3. WHEN một lỗi xảy ra trong BackupService hoặc GoogleSheetsService, THE System SHALL ghi log lỗi chi tiết với thông tin đủ để chẩn đoán nguyên nhân.
4. WHEN một SQLite transaction rollback xảy ra, THE System SHALL ghi log lỗi gồm: tên thao tác, SQL query thất bại, thông báo exception vào file `logs/app_YYYY-MM.log`. `[MỚI - V2]`

---

### Yêu Cầu 21: Môi Trường & Cấu Hình `[SỬA - V2]`

**User Story:** Là quản trị viên hệ thống, tôi muốn cấu hình ứng dụng qua biến môi trường, để tôi có thể deploy trên nhiều môi trường khác nhau mà không cần sửa code.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL đọc cấu hình từ file `.env` tại root project gồm các biến: `STORE_NAME`, `ADDRESS`, `MST_CODE`, `MST_NAME`, `APP_ENV`, `GITHUB_TOKEN`, `GITHUB_REPO_OWNER`, `GITHUB_REPO_NAME`, `SQLITE_DB_PATH` (mặc định `./data/app.db`). `[SỬA - V2: thêm SQLITE_DB_PATH]`
2. WHERE `APP_ENV = dev`, THE System SHALL sử dụng tag backup `data-backup-dev`; WHERE `APP_ENV = prod`, THE System SHALL sử dụng tag backup `data-backup-prod`.
3. THE System SHALL chạy trong container Docker với Apache HTTP Server (port 8080:80), DocumentRoot `/var/www/html/public`, mod_rewrite enabled và AllowOverride All.
4. WHEN file `public/.htaccess` xử lý request, THE System SHALL redirect tất cả request không trỏ đến file tĩnh về `index.php` thông qua mod_rewrite.
5. THE System SHALL cài đặt PHP extension `pdo_sqlite` trong Dockerfile để hỗ trợ SQLite qua PDO. `[MỚI - V2]`

---

### Yêu Cầu 22: Form Nhập Liệu Nhiều Dòng (Multi-Row Form) `[MỚI - V2]`

**User Story:** Là nhân viên, tôi muốn form nhập/xuất hàng và chi phí hoạt động đúng khi thêm nhiều dòng, để tôi có thể nhập dữ liệu mà không gặp lỗi JavaScript do trùng HTML id.

#### Tiêu Chí Chấp Thuận

1. WHEN form nhập hàng (ImportStock) thêm dòng mới qua JavaScript, THE System SHALL clone template row sử dụng `class` thay vì `id` cho tất cả input (`quantity`, `purchasePrice`, v.v.), đảm bảo không có hai element nào cùng `id` trong DOM.
2. WHEN form xuất hàng (ExportStock) thêm dòng mới qua JavaScript, THE System SHALL sử dụng `class` cho tất cả input trong mỗi dòng; WHEN người dùng thay đổi sản phẩm, THE System SHALL lấy giá trị từ `.closest('tr').find('.selling-price')` thay vì `$('#sellingPrice')`.
3. WHEN JavaScript tính tổng tiền trong form, THE System SHALL iterate qua tất cả rows bằng selector `class` và tính tổng đúng cho mọi dòng trong form.
4. WHEN form chi phí (Expenses) thêm dòng mới qua JavaScript, THE System SHALL áp dụng cùng nguyên tắc `class` thay `id` cho các input `amount`, `reason`, `person`, `type`.

