# Tài liệu Yêu Cầu Hệ Thống: Sam Pet

## Giới thiệu

**Sam Pet** là ứng dụng web quản lý cửa hàng thú cưng tích hợp phòng khám thú y. Hệ thống phục vụ hai nghiệp vụ chính: **quản lý bán lẻ** (sản phẩm, nhập/xuất hàng, tồn kho, hóa đơn) và **quản lý phòng khám thú y** (hồ sơ thú nuôi, khám bệnh, điều trị, spa). Dữ liệu được lưu trữ dưới dạng file CSV, không sử dụng cơ sở dữ liệu quan hệ. Hệ thống chạy trong môi trường Docker (Apache + PHP 8.1–8.3) và hỗ trợ backup/restore qua GitHub Releases cũng như đồng bộ dữ liệu từ Google Sheets.

---

## Bảng Thuật Ngữ (Glossary)

- **System**: Ứng dụng web Sam Pet (Laminas MVC, PHP 8.x)
- **LeagueCsv**: Lớp cơ sở cho tất cả Model, đóng gói logic đọc/ghi file CSV bằng league/csv
- **Product**: Model quản lý danh mục sản phẩm và tính tồn kho
- **ImportStock**: Model quản lý phiếu nhập hàng
- **ExportStock**: Model quản lý phiếu xuất hàng
- **VetCare**: Model quản lý doanh thu điều trị và spa theo ngày
- **Expenses**: Model quản lý chi phí vận hành và tiết kiệm
- **Report**: Model quản lý báo cáo thu–chi hàng ngày
- **ExportInvoice**: Model quản lý hóa đơn xuất bán
- **OwnerPet**: Model quản lý hồ sơ chủ sở hữu và thú cưng
- **MedicalRecord**: Model quản lý hồ sơ khám bệnh / điều trị thú cưng
- **Stocktaking**: Model quản lý kiểm kê tồn kho
- **RepackageHistory**: Model lưu lịch sử chiết hàng
- **PdfGenerator**: Utility class tạo file PDF
- **CommonService**: Static utility service (DataTables, sort, compare, logger, ...)
- **BackupService**: Service backup/restore dữ liệu qua GitHub Releases
- **GoogleSheetsService**: Service đồng bộ dữ liệu xuất hàng từ Google Apps Script
- **DataTables**: Thư viện hiển thị bảng dữ liệu phía client với server-side processing
- **LeagueCsv_Library**: Thư viện league/csv phiên bản ^9.18
- **remainStock**: Tồn kho còn lại = initStock + repackageStock + Σimport − Σexport
- **invoiceCheck**: Flag `"1"/"0"` xác định sản phẩm có được in trong hóa đơn hay không
- **releaseTag**: Tag GitHub Release (`data-backup-dev` hoặc `data-backup-prod`) tương ứng `APP_ENV`
- **Repackage**: Thao tác chiết hàng từ sản phẩm nguồn sang sản phẩm đích

---

## Yêu Cầu

---

### Yêu Cầu 1: Quản Lý Sản Phẩm

**User Story:** Là nhân viên cửa hàng, tôi muốn quản lý danh mục sản phẩm, để tôi có thể theo dõi thông tin và tồn kho của từng mặt hàng.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu thông tin sản phẩm gồm các trường: `id` (uniqid), `name`, `unit`, `sellingPrice`, `purchasePrice`, `initStock`, `repackageStock`, `invoiceCheck`, `createdAt`, `updatedAt` vào file `product.csv`.
2. WHEN người dùng yêu cầu danh sách sản phẩm, THE Product SHALL tính `remainStock = initStock + repackageStock + Σimport − Σexport` cho từng sản phẩm bằng cách join với ImportStock và ExportStock.
3. WHEN người dùng yêu cầu danh sách sản phẩm, THE Product SHALL tính `profit = (sellingPrice − purchasePrice) × remainStock` cho từng sản phẩm.
4. WHEN người dùng thêm một sản phẩm mới, THE System SHALL tạo bản ghi với `id = uniqid()`, `createdAt = updatedAt = time()` và lưu vào `product.csv`.
5. WHEN người dùng sửa thông tin sản phẩm, THE System SHALL cập nhật bản ghi tương ứng và đặt `updatedAt = time()`.
6. WHEN người dùng xóa một sản phẩm, THE System SHALL xóa bản ghi khỏi `product.csv` và trả về JSON `{success: true}`.
7. WHEN người dùng gửi yêu cầu DataTables server-side, THE CommonService SHALL thực hiện filter, sort, paginate và trả về JSON theo định dạng DataTables.
8. WHEN người dùng toggle `invoiceCheck` cho một hoặc nhiều sản phẩm, THE Product SHALL cập nhật field `invoiceCheck` thành `"0"` hoặc `"1"` cho các sản phẩm được chỉ định.

---

### Yêu Cầu 2: Chiết Hàng (Repackage)

**User Story:** Là nhân viên kho, tôi muốn chiết hàng từ sản phẩm lớn sang sản phẩm nhỏ, để tôi có thể quản lý tồn kho chính xác sau thao tác phân chia đơn vị.

#### Tiêu Chí Chấp Thuận

1. WHEN người dùng thực hiện chiết hàng với `fromProductId`, `toProductId`, `fromQuantity`, `toQuantity`, THE Product SHALL trừ `fromQuantity` khỏi `repackageStock` của sản phẩm nguồn và cộng `toQuantity` vào `repackageStock` của sản phẩm đích.
2. WHEN một thao tác chiết hàng hoàn thành, THE RepackageHistory SHALL lưu bản ghi với `date`, `content` mô tả chi tiết thao tác (tên, số lượng, đơn vị của cả hai sản phẩm), `createdAt`, `updatedAt`.
3. WHEN người dùng xem lịch sử chiết hàng, THE RepackageHistory SHALL trả về danh sách bản ghi được sắp xếp giảm dần theo `createdAt`.

---

### Yêu Cầu 3: Quản Lý Nhập Hàng

**User Story:** Là nhân viên kho, tôi muốn ghi nhận phiếu nhập hàng, để tôi có thể theo dõi lịch sử nhập và cập nhật tồn kho.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu phiếu nhập với các trường: `id`, `date` (dd-mm-yyyy), `productId`, `productName`, `quantity`, `purchasePrice`, `note`, `createdAt`, `updatedAt` vào file `import-stock.csv`.
2. THE ImportStock SHALL lưu `productName` denormalized để đảm bảo lịch sử chính xác khi tên sản phẩm thay đổi sau này.
3. WHEN người dùng thêm phiếu nhập, THE System SHALL cho phép thêm nhiều dòng cùng một lần (addRows).
4. WHEN người dùng sửa phiếu nhập theo ngày, THE System SHALL xóa toàn bộ rows của ngày đó và insert lại rows mới (replace-all strategy).
5. WHEN người dùng xóa một dòng nhập, THE System SHALL xóa bản ghi theo `id` và trả về JSON `{success: true/false}`.
6. WHEN người dùng yêu cầu tổng số lượng nhập, THE ImportStock SHALL trả về array `[productId => totalQuantity]` tổng hợp theo từng sản phẩm.

---

### Yêu Cầu 4: Quản Lý Xuất Hàng

**User Story:** Là nhân viên bán hàng, tôi muốn ghi nhận phiếu xuất hàng, để tôi có thể theo dõi doanh thu và tồn kho sau mỗi lần bán.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu phiếu xuất với các trường: `id`, `date` (dd-mm-yyyy), `productId`, `productName`, `quantity`, `sellingPrice`, `purchasePrice`, `note`, `createdAt`, `updatedAt` vào file `export-stock.csv`.
2. THE ExportStock SHALL lưu cả `sellingPrice` và `purchasePrice` tại thời điểm xuất để tính lợi nhuận chính xác.
3. WHEN người dùng thêm phiếu xuất, THE System SHALL cho phép thêm nhiều dòng cùng một lần.
4. WHEN người dùng sửa phiếu xuất theo ngày, THE System SHALL sử dụng replace-all strategy (xóa hết → insert lại).
5. WHEN người dùng yêu cầu tổng xuất hàng, THE ExportStock SHALL trả về `[productId => totalQuantity]` và `[date => {revenue, profit}]` trong đó `profit = Σ(quantity × (sellingPrice − purchasePrice))`.
6. WHEN người dùng cần gộp dữ liệu xuất hàng theo sản phẩm để lập hóa đơn, THE ExportStock SHALL gộp các dòng cùng `productId` và bỏ qua sản phẩm có `invoiceCheck = "0"` nếu flag `skipInvoiceCheckFalse` được bật.

---

### Yêu Cầu 5: Đồng Bộ Từ Google Sheets

**User Story:** Là quản lý cửa hàng, tôi muốn đồng bộ dữ liệu xuất hàng từ Google Sheets vào hệ thống, để tôi có thể nhập liệu bán hàng từ nhiều nguồn mà không bị trùng dữ liệu.

#### Tiêu Chí Chấp Thuận

1. WHEN người dùng yêu cầu preview đồng bộ, THE GoogleSheetsService SHALL gọi Apps Script Web App, parse JSON response `{status: "ok", rows: [...]}` và ép kiểu từng field đúng với schema xuất hàng.
2. WHEN GoogleSheetsService nhận dữ liệu từ Google Sheets, THE ExportStock SHALL lọc và chỉ giữ lại các rows chưa tồn tại `id` trong file `export-stock.csv` hiện tại (filterNewRows).
3. WHEN người dùng xác nhận đồng bộ, THE ExportStock SHALL thực hiện `filterNewRows` lần thứ hai để chống race condition trước khi import.
4. WHEN ExportStock import rows từ Google Sheets, THE ExportStock SHALL validate từng row gồm: đủ fields bắt buộc và `productId` tồn tại trong `product.csv`; IF validation thất bại, THEN THE System SHALL bỏ qua row đó và tiếp tục import các row hợp lệ.
5. IF Apps Script Web App trả về lỗi hoặc không thể kết nối, THEN THE System SHALL ghi log lỗi và thông báo cho người dùng.

---

### Yêu Cầu 6: Kiểm Kê Kho (Stocktaking)

**User Story:** Là quản lý kho, tôi muốn thực hiện kiểm kê định kỳ và đặt lại tồn kho, để số liệu tồn kho trong hệ thống khớp với thực tế.

#### Tiêu Chí Chấp Thuận

1. THE Stocktaking SHALL sử dụng `productId` làm primary key (không phải uniqid), đảm bảo mỗi sản phẩm có đúng một bản ghi kiểm kê.
2. WHEN người dùng cập nhật kiểm kê, THE System SHALL ghi giá trị `stocktaking` thực tế cho từng sản phẩm vào `stocktaking.csv`.
3. WHEN người dùng thực hiện `renewWarehouse`, THE CommonService SHALL tạo file ZIP chứa toàn bộ file `*.csv` trong `./data/` và lưu vào `./data/backup_stocktaking/backup_YYYYMMDD_HHiiss.zip` trước khi thực hiện bất kỳ thay đổi nào.
4. WHEN `renewWarehouse` hoàn thành bước backup, THE ExportStock SHALL xóa toàn bộ dữ liệu trong `export-stock.csv` (giữ header).
5. WHEN `renewWarehouse` hoàn thành bước backup, THE ImportStock SHALL xóa toàn bộ dữ liệu trong `import-stock.csv` (giữ header).
6. WHEN `renewWarehouse` xử lý từng sản phẩm, THE Product SHALL cập nhật `initStock` bằng giá trị `stocktaking` tương ứng và đặt `repackageStock = 0`.
7. WHEN `renewWarehouse` hoàn thành cập nhật sản phẩm, THE Stocktaking SHALL xóa toàn bộ dữ liệu trong `stocktaking.csv`.

---

### Yêu Cầu 7: Quản Lý Doanh Thu Phòng Khám (VetCare)

**User Story:** Là nhân viên phòng khám, tôi muốn ghi nhận doanh thu điều trị và spa theo ngày, để tôi có thể theo dõi hiệu quả kinh doanh của phòng khám.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu bản ghi VetCare với các trường: `id`, `date` (dd-mm-yyyy), `treatmentAmount`, `spaAmount`, `note`, `createdAt`, `updatedAt` vào file `vet-care.csv`.
2. WHEN người dùng yêu cầu tổng hợp theo ngày, THE VetCare SHALL trả về `[date => {treatment, spa, treatmentProfit}]` trong đó `treatmentProfit = treatmentAmount × 0.4`.
3. WHEN người dùng thêm, sửa hoặc xóa bản ghi VetCare, THE System SHALL thực hiện thao tác tương ứng và redirect hoặc trả về JSON `{success: true/false}`.

---

### Yêu Cầu 8: Quản Lý Chi Phí

**User Story:** Là quản lý tài chính, tôi muốn ghi nhận chi phí vận hành và tiết kiệm theo ngày, để tôi có thể theo dõi dòng tiền và phân biệt chi phí thường với tiền tiết kiệm.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu chi phí với các trường: `id`, `date` (dd-mm-yyyy), `type` (`"0"` = chi phí thường, `"1"` = tiết kiệm), `reason`, `amount`, `person`, `note`, `createdAt`, `updatedAt` vào file `expenses.csv`.
2. WHEN người dùng thêm chi phí trong ngày, THE System SHALL cho phép thêm nhiều dòng cùng một lần.
3. WHEN người dùng sửa chi phí theo ngày, THE System SHALL sử dụng replace-all strategy.
4. WHEN người dùng yêu cầu tổng hợp chi phí, THE Expenses SHALL trả về `[date => {total, totalSavings}]` trong đó `total` = tổng type=`"0"` và `totalSavings` = tổng type=`"1"` theo ngày.

---

### Yêu Cầu 9: Báo Cáo Thu–Chi (Report)

**User Story:** Là quản lý cửa hàng, tôi muốn lập báo cáo thu–chi hàng ngày và xem biểu đồ tổng quan, để tôi có thể đánh giá hiệu quả kinh doanh theo thời gian.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu báo cáo với các trường: `id`, `date`, `petShopRevenue`, `petShopProfit`, `spaRevenue`, `treatmentRevenue`, `expenses`, `savings`, `missingAmount`, `note`, `createdAt`, `updatedAt` vào file `report.csv`.
2. WHEN người dùng xem danh sách báo cáo, THE Report SHALL tính thêm các trường: `revenue = petShopRevenue + spaRevenue + treatmentRevenue`, `remaining = revenue − expenses`, `treatmentProfit = treatmentRevenue × 0.4`.
3. WHEN người dùng xem biểu đồ tổng quan, THE Report SHALL chuyển đổi `date` từ định dạng `dd-mm-yyyy` sang Unix timestamp milliseconds (×1000) cho trục X của Highcharts.
4. WHEN người dùng thêm hoặc sửa báo cáo thành công, THE System SHALL kích hoạt `BackupService::backup()` chạy bất đồng bộ (non-blocking) thông qua `CommonService::executeCommand()`.
5. IF lệnh backup bất đồng bộ thất bại, THEN THE System SHALL ghi log lỗi mà không làm gián đoạn response trả về người dùng.

---

### Yêu Cầu 10: Quản Lý Hóa Đơn Xuất Bán (ExportInvoice)

**User Story:** Là nhân viên bán hàng, tôi muốn tạo và in hóa đơn xuất bán, để tôi có thể cung cấp chứng từ bán hàng chính thức cho khách.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu hóa đơn với các trường: `id`, `date`, `content` (JSON blob), `total`, `createdAt`, `updatedAt` vào file `export-invoice.csv`.
2. THE ExportInvoice SHALL lưu `content` là JSON có cấu trúc: `{product: [{id, name, unit, quantity, sellingPrice, total}], spa: {desc, total}, treatment: {desc, total}}`.
3. WHEN người dùng tạo hóa đơn mới cho một ngày, THE System SHALL pre-fill dữ liệu từ `export-stock.csv` và `vet-care.csv` của ngày đó.
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

### Yêu Cầu 12: Quản Lý Hồ Sơ Chủ Thú Cưng (OwnerPet)

**User Story:** Là nhân viên phòng khám, tôi muốn quản lý hồ sơ chủ sở hữu và thú cưng, để tôi có thể tra cứu nhanh thông tin thú cưng khi tiếp nhận khám bệnh.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu hồ sơ với các trường: `id`, `owner_name`, `phone`, `pet_name`, `species`, `breed`, `gender`, `age`, `note`, `createdAt`, `updatedAt` vào file `owners_pets.csv`.
2. WHEN người dùng tìm kiếm thú cưng theo tên, THE OwnerPet SHALL lọc các records có `pet_name` chứa chuỗi tìm kiếm (case-insensitive) và trả về JSON array `[{id, pet_name, owner_name, phone, ...}]`.
3. WHEN người dùng thêm, sửa hoặc xóa hồ sơ, THE System SHALL thực hiện thao tác CRUD tương ứng trên `owners_pets.csv`.

---

### Yêu Cầu 13: Quản Lý Hồ Sơ Khám Bệnh (MedicalRecord)

**User Story:** Là bác sĩ thú y, tôi muốn ghi nhận và xem lại lịch sử khám bệnh của từng thú cưng, để tôi có thể đưa ra chẩn đoán và điều trị chính xác dựa trên tiền sử bệnh.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL lưu hồ sơ khám với các trường: `id`, `pet_id` (FK → owners_pets.csv), `visit_date` (dd-mm-yyyy), `symptoms`, `diagnosis`, `prescription`, `start_date`, `end_date`, `createdAt`, `updatedAt` vào file `medical_records.csv`.
2. WHEN người dùng xem lịch sử khám của một thú cưng, THE MedicalRecord SHALL trả về tất cả records có `pet_id` khớp, được sắp xếp giảm dần theo `visit_date`.
3. WHEN người dùng xem danh sách tất cả hồ sơ khám, THE MedicalRecord SHALL join với `OwnerPet` để enrich mỗi record với `pet_name`, `owner_name`, `species`.
4. WHEN người dùng thêm hồ sơ khám, THE System SHALL liên kết bản ghi với `petId` được chỉ định trong URL.

---

### Yêu Cầu 14: Backup và Restore Dữ Liệu (BackupService)

**User Story:** Là quản lý hệ thống, tôi muốn backup và restore toàn bộ dữ liệu CSV qua GitHub Releases, để tôi có thể khôi phục dữ liệu khi cần thiết.

#### Tiêu Chí Chấp Thuận

1. WHEN `BackupService::backup()` được gọi, THE BackupService SHALL tạo file ZIP chứa tất cả file `*.csv` tại root `./data/` (không bao gồm subfolder và file không phải CSV) và lưu vào `./data/cache/backup.zip`.
2. WHEN file ZIP được tạo xong, THE BackupService SHALL upload lên GitHub Releases: upsert release với `releaseTag`, xóa asset `backup.zip` cũ nếu có, rồi upload asset mới.
3. WHERE `APP_ENV = dev`, THE BackupService SHALL sử dụng tag `data-backup-dev`; WHERE `APP_ENV = prod`, THE BackupService SHALL sử dụng tag `data-backup-prod`.
4. WHEN `BackupService::restore()` được gọi, THE BackupService SHALL lấy `browser_download_url` của asset `backup.zip` từ release tương ứng, download về `./data/cache/backup_restore.zip`, giải nén và overwrite toàn bộ file CSV trong `./data/`.
5. IF GitHub API trả về lỗi trong quá trình backup hoặc restore, THEN THE BackupService SHALL ghi log lỗi chi tiết vào file log tháng hiện tại.
6. THE BackupService SHALL đọc `GITHUB_TOKEN`, `GITHUB_REPO_OWNER`, `GITHUB_REPO_NAME` từ biến môi trường `$_ENV` (không hardcode).

---

### Yêu Cầu 15: Lưu Trữ CSV (LeagueCsv)

**User Story:** Là hệ thống, tôi muốn có một lớp cơ sở thống nhất để đọc/ghi dữ liệu CSV, để tất cả Model có thể sử dụng cùng một cơ chế lưu trữ nhất quán.

#### Tiêu Chí Chấp Thuận

1. THE LeagueCsv SHALL tự động tạo file CSV với header row khi file chưa tồn tại.
2. WHEN `checkHeaders()` được gọi, THE LeagueCsv SHALL so sánh headers hiện tại của file CSV với headers được định nghĩa trong Model; IF headers thay đổi, THEN THE LeagueCsv SHALL đọc dữ liệu hiện tại, migrate sang cấu trúc mới (thêm cột trống / xóa cột cũ) và ghi lại file.
3. WHEN `addRow()` được gọi, THE LeagueCsv SHALL tự động set `id = uniqid()` nếu trống, `createdAt = updatedAt = time()`, và normalize row theo `mappingDataWithHeaders()`.
4. WHEN `updateRow()` được gọi, THE LeagueCsv SHALL giữ nguyên `id` và `createdAt` gốc, cập nhật các fields mới và set `updatedAt = time()`.
5. THE LeagueCsv SHALL merge `['createdAt', 'updatedAt']` vào cuối danh sách headers của mọi Model.
6. WHEN `getData()` được gọi, THE LeagueCsv SHALL trả về associative array keyed theo `$primaryKey` (mặc định `id`).

---

### Yêu Cầu 16: Xử Lý DataTables Server-Side (CommonService)

**User Story:** Là người dùng, tôi muốn các bảng dữ liệu hỗ trợ tìm kiếm, sắp xếp và phân trang phía server, để tôi có thể duyệt dữ liệu lớn một cách nhanh chóng.

#### Tiêu Chí Chấp Thuận

1. WHEN `CommonService::dataTableServerSideProcessing()` nhận request, THE CommonService SHALL áp dụng tuần tự: `filterData` → `sortData` → `paginateData` → thêm field `no` (số thứ tự) → trả về JSON gồm `draw`, `recordsTotal`, `recordsFiltered`, `data`.
2. WHEN `filterData()` được gọi với `searchValue`, THE CommonService SHALL tìm kiếm full-text trên tất cả fields bằng `stripos` (case-insensitive).
3. WHEN `sortData()` được gọi trên field chứa ngày định dạng `dd-mm-yyyy`, THE CommonService SHALL sử dụng `strtotime` để so sánh; WHEN field là chuỗi văn bản, THE CommonService SHALL sử dụng `Collator('vi_VN')` để sort đúng tiếng Việt.
4. WHEN `paginateData()` được gọi, THE CommonService SHALL trả về slice dữ liệu từ vị trí `start` với độ dài `length`.

---

### Yêu Cầu 17: Cài Đặt Hệ Thống (Settings)

**User Story:** Là quản trị viên, tôi muốn xem thông tin hệ thống và thực hiện restore dữ liệu từ backup, để tôi có thể vận hành và khôi phục hệ thống khi cần.

#### Tiêu Chí Chấp Thuận

1. WHEN người dùng truy cập trang cài đặt, THE System SHALL hiển thị thông tin hệ thống và cung cấp chức năng backup/restore.
2. WHEN người dùng kích hoạt restore, THE BackupService SHALL thực hiện quy trình restore: download ZIP từ GitHub Release → giải nén → overwrite CSV files vào `./data/`.
3. IF restore thành công, THEN THE System SHALL thông báo thành công cho người dùng qua FlashMessenger.
4. IF restore thất bại, THEN THE System SHALL thông báo lỗi chi tiết cho người dùng qua FlashMessenger.

---

### Yêu Cầu 18: Tổng Quan Dashboard (Overview)

**User Story:** Là quản lý cửa hàng, tôi muốn xem biểu đồ tổng quan doanh thu và chi phí, để tôi có thể nắm bắt xu hướng kinh doanh một cách trực quan.

#### Tiêu Chí Chấp Thuận

1. WHEN người dùng truy cập dashboard, THE Report SHALL cung cấp dữ liệu biểu đồ với 4 series: doanh thu, lợi nhuận, chi phí và tiết kiệm theo ngày, định dạng cho Highcharts với trục X là Unix timestamp milliseconds.
2. WHEN người dùng truy cập trang expenses overview, THE Report SHALL cung cấp dữ liệu biểu đồ tập trung vào chi phí và tiết kiệm.
3. THE System SHALL hiển thị `STORE_NAME` từ biến môi trường trong navbar layout của mọi trang.

---

### Yêu Cầu 19: Logging Hệ Thống

**User Story:** Là quản trị viên, tôi muốn hệ thống ghi log các sự kiện và lỗi, để tôi có thể chẩn đoán sự cố và theo dõi hoạt động.

#### Tiêu Chí Chấp Thuận

1. THE CommonService SHALL tạo Monolog Logger ghi vào file `logs/app_YYYY-MM.log` (xoay vòng theo tháng) cho các log ứng dụng thông thường.
2. THE CommonService SHALL tạo Monolog Logger riêng ghi vào file `logs/exception_YYYY-MM.log` cho các exception toàn cục.
3. WHEN một lỗi xảy ra trong BackupService hoặc GoogleSheetsService, THE System SHALL ghi log lỗi chi tiết với thông tin đủ để chẩn đoán nguyên nhân.

---

### Yêu Cầu 20: Môi Trường & Cấu Hình

**User Story:** Là quản trị viên hệ thống, tôi muốn cấu hình ứng dụng qua biến môi trường, để tôi có thể deploy trên nhiều môi trường khác nhau mà không cần sửa code.

#### Tiêu Chí Chấp Thuận

1. THE System SHALL đọc cấu hình từ file `.env` tại root project gồm các biến: `STORE_NAME`, `ADDRESS`, `MST_CODE`, `MST_NAME`, `APP_ENV`, `GITHUB_TOKEN`, `GITHUB_REPO_OWNER`, `GITHUB_REPO_NAME`.
2. WHERE `APP_ENV = dev`, THE System SHALL sử dụng tag backup `data-backup-dev`; WHERE `APP_ENV = prod`, THE System SHALL sử dụng tag backup `data-backup-prod`.
3. THE System SHALL chạy trong container Docker với Apache HTTP Server (port 8080:80), DocumentRoot `/var/www/html/public`, mod_rewrite enabled và AllowOverride All.
4. WHEN file `public/.htaccess` xử lý request, THE System SHALL redirect tất cả request không trỏ đến file tĩnh về `index.php` thông qua mod_rewrite.
