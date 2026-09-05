# Sơ Đồ Cơ Sở Dữ Liệu (Database Schema) — Sam Pet 2.0

Toàn bộ CSDL của hệ thống được lưu trữ trong tệp SQLite `data/app.db`. Cấu trúc bao gồm 15 bảng liên kết với các chỉ mục (Indexes) tối ưu hóa truy vấn.

---

## 1. Bảng tổng quan các thực thể (15 Bảng)

| STT | Tên bảng | Mô tả chức năng | Khóa chính |
|:---|:---|:---|:---|
| 1 | `categories` | Danh mục phân loại sản phẩm | `id` (TEXT) |
| 2 | `products` | Danh sách hàng hóa / sản phẩm & tồn kho | `id` (TEXT) |
| 3 | `import_stock` | Phiếu nhập kho | `id` (TEXT) |
| 4 | `export_stock` | Chi tiết xuất bán lẻ hàng hóa | `id` (TEXT) |
| 5 | `customers` | Khách hàng mua sỉ / thân thiết | `id` (TEXT) |
| 6 | `vet_care` | Doanh thu dịch vụ điều trị và Spa thú cưng | `id` (TEXT) |
| 7 | `expenses` | Sổ chi phí và các khoản tiết kiệm | `id` (TEXT) |
| 8 | `reports` | Báo cáo doanh thu & tài chính theo ngày | `id` (TEXT) |
| 9 | `export_invoices` | Hóa đơn bán lẻ (JSON snapshot các mặt hàng) | `id` (TEXT) |
| 10 | `owners_pets` | Hồ sơ thông tin chủ và thú cưng | `id` (TEXT) |
| 11 | `medical_records` | Sổ khám bệnh / điều trị theo thú cưng | `id` (TEXT) |
| 12 | `stocktaking` | Dữ liệu kiểm kê hàng hóa hiện tại | `id` (TEXT = productId) |
| 13 | `stocktaking_periods` | Lịch sử các kỳ chốt sổ kho | `id` (TEXT) |
| 14 | `stocktaking_period_items`| Chi tiết số lượng thực tế trong kỳ chốt sổ | `id` (TEXT) |
| 15 | `repackage_history` | Lịch sử chiết hàng (tách gói lớn thành gói nhỏ)| `id` (TEXT) |

---

## 2. Chi tiết cấu trúc từng bảng

### 2.1. `categories` (Danh mục)
- `id` (TEXT, PK): Mã danh mục.
- `name` (TEXT): Tên danh mục sản phẩm (VD: Thức ăn, Phụ kiện, Thuốc thú y).
- `note` (TEXT): Ghi chú danh mục.
- `createdAt` / `updatedAt` (INTEGER): Thời gian tạo / cập nhật (Unix timestamp).

### 2.2. `products` (Sản phẩm & Tồn kho)
- `id` (TEXT, PK): Mã sản phẩm (13 ký tự hex).
- `name` (TEXT): Tên sản phẩm.
- `unit` (TEXT): Đơn vị tính (Gói, Lon, Chai, Hộp...).
- `sellingPrice` (REAL): Giá bán lẻ niêm yết.
- `purchasePrice` (REAL): Giá nhập gốc.
- `initStock` (REAL): Số lượng tồn kho ban đầu (hoặc sau chốt kho).
- `repackageStock` (REAL): Số lượng điều chỉnh sau chiết bao.
- `invoiceCheck` (TEXT): Trạng thái kiểm tra hóa đơn (`'0'` hoặc `'1'`).
- `categoryId` (TEXT, FK -> `categories.id`): Phân loại danh mục.
- `createdAt` / `updatedAt` (INTEGER).
- **Chỉ mục:** `idx_products_categoryId`, `idx_products_name`.

### 2.3. `import_stock` (Nhập kho)
- `id` (TEXT, PK): Mã phiếu nhập.
- `date` (TEXT): Ngày nhập (`dd-mm-yyyy`).
- `productId` (TEXT, FK -> `products.id`): Mã sản phẩm nhập.
- `productName` (TEXT): Tên sản phẩm tại thời điểm nhập (denormalized).
- `quantity` (REAL): Số lượng nhập.
- `purchasePrice` (REAL): Giá nhập đơn vị.
- `note` (TEXT): Ghi chú nhập hàng (nhà cung cấp, lô...).
- **Chỉ mục:** `idx_import_stock_date`, `idx_import_stock_productId`.

### 2.4. `export_stock` (Xuất bán hàng)
- `id` (TEXT, PK): Mã dòng xuất hàng.
- `date` (TEXT): Ngày xuất (`dd-mm-yyyy`).
- `productId` (TEXT, FK -> `products.id`): Mã sản phẩm bán.
- `productName` (TEXT): Tên sản phẩm tại thời điểm bán.
- `quantity` (REAL): Số lượng bán.
- `sellingPrice` (REAL): Đơn giá bán thực tế.
- `purchasePrice` (REAL): Giá vốn tương ứng để tính lợi nhuận.
- `note` (TEXT): Ghi chú xuất hàng.
- `customerId` (TEXT, FK -> `customers.id`, Nullable): Khách hàng (nếu có).
- **Chỉ mục:** `idx_export_stock_date`, `idx_export_stock_productId`, `idx_export_stock_customerId`.

### 2.5. `customers` (Khách hàng)
- `id` (TEXT, PK): Mã khách hàng.
- `name` (TEXT): Họ tên khách hàng.
- `phone` (TEXT): Số điện thoại liên hệ.
- `address` (TEXT): Địa chỉ.
- `note` (TEXT): Ghi chú sở thích/thông tin thêm.

### 2.6. `vet_care` (Dịch vụ Điều trị & Spa)
- `id` (TEXT, PK): Mã dòng dịch vụ.
- `date` (TEXT): Ngày thực hiện (`dd-mm-yyyy`).
- `treatmentAmount` (REAL): Doanh thu khám / điều trị thú y.
- `spaAmount` (REAL): Doanh thu dịch vụ tắm / cắt tỉa / spa.
- `note` (TEXT): Ghi chú chi tiết dịch vụ.

### 2.7. `expenses` (Chi phí & Tiết kiệm)
- `id` (TEXT, PK): Mã khoản chi.
- `date` (TEXT): Ngày phát sinh (`dd-mm-yyyy`).
- `type` (TEXT): Loại giao dịch (`'0'` = Chi phí cửa hàng, `'1'` = Tiết kiệm/Rút vốn).
- `reason` (TEXT): Lý do chi / khoản mục.
- `amount` (REAL): Số tiền.
- `person` (TEXT): Người chi hoặc người nhận.
- `note` (TEXT): Ghi chú.

### 2.8. `reports` (Báo cáo doanh thu theo ngày)
- `id` (TEXT, PK): Mã báo cáo.
- `date` (TEXT, UNIQUE): Ngày báo cáo (`dd-mm-yyyy`).
- `petShopRevenue` (REAL): Doanh thu bán hàng Pet Shop.
- `petShopProfit` (REAL): Lợi nhuận gộp Pet Shop.
- `spaRevenue` (REAL): Doanh thu Spa.
- `treatmentRevenue` (REAL): Doanh thu Khám / Điều trị.
- `expenses` (REAL): Tổng chi phí trong ngày.
- `savings` (REAL): Tổng tiết kiệm trong ngày.
- `missingAmount` (REAL): Tiền thâm hụt / thiếu hụt thực tế.
- `note` (TEXT): Ghi chú tổng kết ngày.

### 2.9. `export_invoices` (Hóa đơn xuất)
- `id` (TEXT, PK): Mã hóa đơn.
- `date` (TEXT): Ngày lập hóa đơn.
- `content` (TEXT): Dữ liệu JSON danh sách mặt hàng, số lượng, đơn giá.
- `total` (REAL): Tổng tiền thanh toán hóa đơn.

### 2.10. `owners_pets` & `medical_records` (Hồ sơ khám thú y)
- **`owners_pets`**: Lưu thông tin chủ nuôi (`owner_name`, `phone`) và thông tin thú cưng (`pet_name`, `species`, `breed`, `gender`, `age`).
- **`medical_records`**: Sổ theo dõi bệnh án (`pet_id`, `visit_date`, `symptoms`, `diagnosis`, `prescription`, `start_date`, `end_date`).

### 2.11. `stocktaking`, `stocktaking_periods` & `stocktaking_period_items` (Kiểm kê kho)
- **`stocktaking`**: Bảng kiểm kê tức thời (`id` = `productId`, `stocktaking` = số lượng đếm thực tế).
- **`stocktaking_periods`**: Lưu lịch sử các đợt chốt kho theo ngày (`closedAt`, `note`).
- **`stocktaking_period_items`**: Chi tiết số lượng kiểm đếm tương ứng với từng kỳ chốt (`periodId`, `productId`, `actualStock`).

### 2.12. `repackage_history` (Lịch sử chiết hàng)
- `id` (TEXT, PK): Mã đợt chiết.
- `source_product_id` (TEXT, FK): Sản phẩm nguồn (gói to).
- `target_product_id` (TEXT, FK): Sản phẩm đích (gói nhỏ).
- `source_qty` (REAL): Số lượng nguồn giảm đi.
- `target_qty` (REAL): Số lượng đích tăng lên.
- `created_at` (INTEGER): Timestamp thực hiện.
