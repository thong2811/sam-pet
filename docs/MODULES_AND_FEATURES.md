# Phân Hệ Chức Năng & Nghiệp Vụ — Sam Pet 2.0

Tài liệu chi tiết về 4 phân hệ chính và các quy tắc nghiệp vụ trong hệ thống quản lý Sam Pet.

---

## 1. Phân Hệ Quản Lý Kho Hàng (Inventory Management)

### 1.1. Quản lý sản phẩm (`ProductController` & `ProductRepository`)
- **Tồn kho thực tế (Current Stock)** được tính toán tự động theo công thức:
  $$\text{Tồn hiện tại} = \text{Tồn ban đầu (initStock)} + \text{Tổng nhập (Import)} - \text{Tổng xuất (Export)} + \text{Điều chỉnh chiết (Repackage)}$$
- **Trạng thái kiểm tra hóa đơn (`invoiceCheck`):** Đánh dấu trạng thái sản phẩm đã đối soát hóa đơn với nhà cung cấp hay chưa.
- **Phân loại danh mục (`Category`):** Gán danh mục sản phẩm phục vụ thống kê phân loại.

### 1.2. Nhập & Xuất kho (`ImportStockController`, `ExportStockController`)
- **Nhập hàng (`/import-stock`):** Ghi nhận lô hàng mới, số lượng và giá vốn. Có thể chọn sản phẩm có sẵn hoặc tạo nhanh.
- **Xuất hàng (`/export-stock`):**
  - Ghi nhận bán lẻ từng dòng sản phẩm.
  - Tự động lấy giá bán niêm yết và giá vốn hiện hành.
  - Hỗ trợ xuất kèm hóa đơn tổng hợp (`ExportInvoice`).

### 1.3. Chiết hàng (`/product/repackage`)
- Dành cho nghiệp vụ tách bao lớn (VD: Bao hạt 10kg) thành nhiều túi nhỏ (VD: 10 túi 1kg).
- Khi chiết:
  - Giảm tồn kho sản phẩm nguồn (`source_product`).
  - Tăng tồn kho sản phẩm đích (`target_product`).
  - Kiểm tra điều kiện tồn kho nguồn phải $\ge$ số lượng yêu cầu chiết.
  - Ghi vết vào `repackage_history` kèm transaction an toàn.

### 1.4. Kiểm kê & Chốt kho định kỳ (`StocktakingController`)
- **Kiểm kê (`/stocktaking`):** Cho phép nhập số lượng đếm thực tế của từng mặt hàng tại cửa hàng.
- **Chốt kho (`/stocktaking/renew-warehouse`):**
  1. Tự động sao lưu snapshot CSDL trước khi chốt (`Stocktaking backup`).
  2. Cập nhật số lượng đếm thực tế thành `initStock` mới cho sản phẩm.
  3. Xóa các giao dịch nhập / xuất cũ trước thời điểm chốt kho để làm sạch dữ liệu hoạt động.
  4. Lưu vết kỳ chốt vào `stocktaking_periods` và `stocktaking_period_items`.

---

## 2. Phân Hệ Phòng Khám & Spa Thú Cưng (Clinic & Spa)

### 2.1. Hồ sơ Thú cưng & Khách hàng (`OwnerPetController`)
- Quản lý thông tin chủ nuôi (tên, số điện thoại) và thông tin thú cưng (tên, loài, giống, giới tính, tuổi).
- Tra cứu nhanh lịch sử tiêm phòng / khám bệnh theo từng thú cưng.

### 2.2. Sổ khám bệnh (`MedicalRecordController`)
- Ghi nhận triệu chứng, chẩn đoán, đơn thuốc và liệu trình điều trị (`visit_date`, `symptoms`, `diagnosis`, `prescription`, `start_date`, `end_date`).
- Liên kết trực tiếp với mã thú cưng (`pet_id`).

### 2.3. Dịch vụ Thú y & Spa (`VetCareController`)
- Ghi nhận doanh thu dịch vụ theo ngày:
  - Doanh thu Khám / Điều trị (`treatmentAmount`).
  - Doanh thu Tắm / Spa / Cắt tỉa (`spaAmount`).
- Dữ liệu này tự động được kéo sang Báo cáo tài chính ngày (`Report`).

---

## 3. Phân Hệ Quản Lý Tài Chính & Sổ Sách (Finance & Reports)

### 3.1. Sổ chi phí & Tiết kiệm (`ExpensesController`)
- Ghi nhận các khoản chi phí vận hành cửa hàng (điện, nước, mặt bằng, ăn uống, nhập vật tư).
- Phân biệt giữa **Chi phí** (`type = '0'`) và **Rút tiền / Tiết kiệm** (`type = '1'`).

### 3.2. Báo cáo doanh thu & Lợi nhuận ngày (`ReportController`)
- Hệ thống tổng hợp tự động các nguồn thu chi trong ngày:
  - **Doanh thu bán hàng Pet Shop** = Tổng tiền xuất hàng trong ngày.
  - **Lợi nhuận Pet Shop** = Doanh thu bán hàng - Giá vốn hàng bán.
  - **Doanh thu Spa & Điều trị** = Lấy từ phân hệ `VetCare`.
  - **Tổng chi phí & Tiết kiệm** = Lấy từ phân hệ `Expenses`.
- Hỗ trợ endpoint AJAX `/report/data-by-date?date=dd-mm-yyyy` tự động tính toán và điền sẵn số liệu vào biểu mẫu báo cáo.

### 3.3. Sổ doanh thu & Hóa đơn (`ExportInvoiceController`)
- Quản lý danh sách các hóa đơn bán lẻ đã phát hành.
- Cho phép xem chi tiết hóa đơn (snapshot định dạng JSON) và in hóa đơn bán lẻ.

---

## 4. Phân Hệ Tiện Ích Hệ Thống & Bảo Mật

### 4.1. Dịch vụ Sao lưu (Backup Service)
- **Tự động sao lưu hàng ngày (Daily Auto-backup):** Lưu tại `data/backups/auto/`, giữ lại tối đa 30 ngày gần nhất.
- **Sao lưu trước khi chốt kho (Stocktaking Backup):** Lưu tại `data/backups/stocktaking/`, giữ lại 10 bản gần nhất.
- **Sao lưu Cloud GitHub Releases:** Đẩy bản backup `backup.db` lên GitHub Releases tag `data-backup-prod` / `data-backup-dev`.
- **Khôi phục (Restore):** Cho phép khôi phục tức thời từ giao diện Settings (`/settings`).

### 4.2. Bảo mật & CSRF Protection (`CsrfService`)
- Mọi form submission và các thao tác thay đổi dữ liệu (POST, PUT, DELETE, Ajax Actions) đều được kiểm tra CSRF token hợp lệ.
