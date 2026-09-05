# Tài Liệu Chức Năng Chiết Hàng (Product Repackage) — Sam Pet 2.0

Tài liệu đặc tả chi tiết về nghiệp vụ, kiến trúc kỹ thuật, luồng xử lý, cơ sở dữ liệu và hướng dẫn sử dụng cho **Chức năng Chiết hàng (Repackage)** trong hệ thống quản lý Sam Pet 2.0.

---

## 1. Tổng Quan Nghiệp Vụ (Business Overview)

### 1.1. Mục đích chức năng
Trong mô hình kinh doanh Pet Shop và Thú y, cửa hàng thường xuyên nhập các sản phẩm đóng gói quy cách lớn từ nhà phân phối (ví dụ: *Bao thức ăn hạt 10kg, 20kg*, *Can sữa tắm 5 lít*, *Bao cát vệ sinh 25kg*) nhằm tối ưu giá vốn. Sau đó, cửa hàng thực hiện **chiết / tách bao** thành các đơn vị quy cách nhỏ hơn (ví dụ: *Gói hạt 1kg*, *Chai sữa tắm 500ml*) để bán lẻ cho khách hàng.

Chức năng **Chiết hàng (`/product/repackage`)** giúp:
- Tự động điều chỉnh số lượng tồn kho giữa sản phẩm nguồn (gói lớn) và sản phẩm đích (gói nhỏ).
- Đảm bảo tính toàn vẹn và chính xác của kho hàng mà không cần thực hiện các giao dịch Xuất kho / Nhập kho ảo thủ công.
- Lưu trữ lịch sử vết chiết hàng phục vụ tra cứu, đối soát và kiểm kê.

---

### 1.2. Công thức tính toán Tồn kho liên quan

Tồn kho khả dụng thực tế (`remainStock`) của mọi sản phẩm trong hệ thống được tính toán theo công thức:

$$\text{remainStock} = \text{initStock} + \text{repackageStock} + \sum(\text{importQuantity}) - \sum(\text{exportQuantity})$$

Trong đó:
- `initStock`: Số lượng tồn kho ban đầu (hoặc số lượng thực tế sau kỳ chốt kho gần nhất).
- `repackageStock`: Số lượng tồn kho biến động do các lần chiết hàng (có thể âm với sản phẩm nguồn, dương với sản phẩm đích).
- `importQuantity`: Tổng số lượng nhập kho từ các phiếu nhập.
- `exportQuantity`: Tổng số lượng xuất kho bán lẻ.

---

## 2. Quy Tắc Nghiệp Vụ & Ràng Buộc (Business Rules)

1. **Điều kiện tồn kho nguồn:**
   - Số lượng tồn kho thực tế của sản phẩm nguồn tại thời điểm chiết phải $\ge$ Số lượng yêu cầu chiết (`remainStock >= quantityBig`).
   - Nếu không đủ tồn kho, hệ thống từ chối thực hiện và thông báo lỗi rõ ràng cho người dùng.
2. **Hỗ trợ 1 nguồn $\rightarrow$ Nhiều đích (1-to-N):**
   - Một lần chiết có thể phân bổ 1 sản phẩm nguồn thành 1 hoặc nhiều sản phẩm đích với tỷ lệ và số lượng khác nhau (ví dụ: 1 bao 20kg chiết thành 15 gói 1kg và 10 gói 500g).
3. **Ràng buộc số lượng:**
   - Số lượng sản phẩm nguồn chiết (`quantityBig`) phải là số dương $> 0$.
   - Số lượng từng sản phẩm đích nhận (`quantitySmall`) phải là số dương $> 0$.
4. **Tính nguyên vẹn giao dịch (ACID):**
   - Toàn bộ thao tác trừ kho nguồn, cộng kho các sản phẩm đích và ghi lịch sử chiết được thực thi trong một **Database Transaction** duy nhất. Nếu có bất kỳ lỗi nào xảy ra, toàn bộ thay đổi sẽ được rollback về trạng thái ban đầu.
5. **Bảo mật:**
   - Form chiết hàng được bảo vệ bởi **CSRF Token** xác thực qua `CsrfService`.

---

## 3. Kiến Trúc Kỹ Thuật (Technical Architecture)

```
[ Giao diện Web (repackage.phtml) ]
                │
         POST /product/do-repackage
                │
                ▼
[ ProductController::doRepackageAction ]
   │
   ├─► 1. Validate CSRF Token (CsrfService::validateOrFail)
   │
   ├─► 2. ProductRepository::doRepackage($postData, $historyRepo)
   │      │
   │      ├─► Validate tồn kho nguồn (calcRemainStock)
   │      ├─► Validate danh sách sản phẩm đích
   │      └─► Database Transaction:
   │            ├─► UPDATE products SET repackageStock = repackageStock - ? WHERE id = sourceId
   │            ├─► UPDATE products SET repackageStock = repackageStock + ? WHERE id = targetId
   │            └─► RepackageHistoryRepository::addRow(...)
   │
   └─► 3. FlashMessenger & Redirect về /product/repackage
```

---

## 4. Đặc Tả Điểm Cuối (API & Route Specification)

### 4.1. Màn hình Chiết hàng & Lịch sử
- **Đường dẫn:** `GET /product/repackage`
- **Controller Action:** `Application\Controller\ProductController::repackageAction`
- **Dữ liệu truyền vào View:**
  - `productList`: Danh sách toàn bộ sản phẩm cùng tồn kho hiện tại (`remainStock`).
  - `repackageHistoryList`: 20 phiên chiết hàng gần nhất (được nhóm theo phiên chiết).

---

### 4.2. Xử lý thực hiện Chiết hàng
- **Đường dẫn:** `POST /product/do-repackage`
- **Controller Action:** `Application\Controller\ProductController::doRepackageAction`
- **Headers / Security:** Yêu cầu `_csrf` token hợp lệ.
- **Tham số Form (POST Body):**

| Tham số | Kiểu dữ liệu | Bắt buộc | Mô tả |
|:---|:---|:---|:---|
| `_csrf` | `string` | Có | CSRF Token chống tấn công giả mạo |
| `date` | `string` | Có | Ngày thực hiện chiết hàng (`dd-mm-yyyy`) |
| `productId_big` | `string` | Có | ID sản phẩm nguồn (gói lớn cần chiết) |
| `quantity_big` | `float` | Có | Số lượng sản phẩm nguồn xuất chiết |
| `productId_small[]` | `array<string>` | Có | Mảng ID các sản phẩm đích nhận |
| `quantity_small[]` | `array<float>` | Có | Mảng số lượng các sản phẩm đích nhận |

- **Phản hồi:**
  - Thành công: Gửi Flash message `Chiết hàng thành công.` và redirect về `/product/repackage`.
  - Thất bại: Gửi Flash error message (ví dụ: `Tồn kho không đủ để chiết. Hiện còn: X Gói.`) và redirect về `/product/repackage`.

---

## 5. Cấu Trúc Cơ Sở Dữ Liệu (Database Schema)

Chức năng chiết hàng tương tác trực tiếp với 2 bảng trong SQLite (`data/app.db`):

### 5.1. Bảng `products`
| Cột | Kiểu | Mô tả |
|:---|:---|:---|
| `id` | `TEXT (PK)` | Mã định danh sản phẩm (uniqid hex 13 ký tự) |
| `name` | `TEXT` | Tên sản phẩm |
| `unit` | `TEXT` | Đơn vị tính (Bao, Túi, Chai, Lon...) |
| `initStock` | `REAL` | Tồn kho ban đầu / sau chốt kho |
| `repackageStock` | `REAL` | **Lượng điều chỉnh tồn kho do chiết hàng** |
| `sellingPrice` | `REAL` | Đơn giá bán lẻ |
| `purchasePrice` | `REAL` | Giá nhập gốc |
| `updatedAt` | `INTEGER` | Unix timestamp cập nhật gần nhất |

---

### 5.2. Bảng `repackage_history`
Lưu trữ lịch sử từng dòng chiết xuất giữa sản phẩm nguồn và sản phẩm đích.

```sql
CREATE TABLE IF NOT EXISTS repackage_history (
    id              TEXT NOT NULL PRIMARY KEY,     -- uniqid hex 13 ký tự
    date            TEXT NOT NULL DEFAULT '',      -- Ngày chiết (dd-mm-yyyy)
    fromProductId   TEXT,                          -- FK -> products.id (Sản phẩm nguồn)
    fromProductName TEXT NOT NULL DEFAULT '',      -- Snapshot tên nguồn tại thời điểm chiết
    toProductId     TEXT,                          -- FK -> products.id (Sản phẩm đích)
    toProductName   TEXT NOT NULL DEFAULT '',      -- Snapshot tên đích tại thời điểm chiết
    fromQuantity    REAL NOT NULL DEFAULT 0,       -- Số lượng nguồn giảm
    toQuantity      REAL NOT NULL DEFAULT 0,       -- Số lượng đích tăng
    note            TEXT NOT NULL DEFAULT '',      -- Ghi chú bổ sung
    createdAt       INTEGER,                       -- Unix timestamp tạo
    updatedAt       INTEGER,                       -- Unix timestamp sửa
    FOREIGN KEY (fromProductId) REFERENCES products(id),
    FOREIGN KEY (toProductId)   REFERENCES products(id)
);

CREATE INDEX IF NOT EXISTS idx_repackage_history_date          ON repackage_history (date);
CREATE INDEX IF NOT EXISTS idx_repackage_history_fromProductId ON repackage_history (fromProductId);
CREATE INDEX IF NOT EXISTS idx_repackage_history_toProductId   ON repackage_history (toProductId);
```

---

## 6. Luồng Xử Lý Chi Tiết (Step-by-Step Flow)

### 6.1. Tại Giao Diện Người Dùng (Client-side)
1. Người dùng truy cập menu **Sản phẩm $\rightarrow$ Chiết hàng**.
2. Chọn **Ngày chiết hàng** (sử dụng bootstrap Datepicker, định dạng `dd-mm-yyyy`).
3. Tại khối **"Chọn hàng chiết" (Nguồn):**
   - Tìm kiếm và chọn sản phẩm nguồn từ dropdown Select2.
   - Hệ thống tự động đọc dữ liệu từ `productList` và hiển thị **Tồn kho hiện tại** tương ứng.
   - Nhập **Số lượng chiết** (mặc định là `1`).
4. Tại khối **"Chiết thành" (Đích):**
   - Tìm kiếm và chọn sản phẩm đích. Tồn kho hiện tại của sản phẩm đích tự động hiển thị.
   - Nhập **Số lượng chiết ra**.
   - Khi chọn xong sản phẩm ở dòng cuối, bảng tự động sinh thêm dòng nhập mới để hỗ trợ chiết ra nhiều mặt hàng cùng lúc.
5. Nhấn **Lưu** để gửi yêu cầu.

---

### 6.2. Tại Máy Chủ (Server-side Execution)

```php
// Trích đoạn logic trong ProductRepository::doRepackage
$remainStockBig = $this->calcRemainStock($productIdBig);
if ($remainStockBig < $quantityBig) {
    throw new \RuntimeException(
        "Tồn kho không đủ để chiết. Hiện còn: $remainStockBig {$productBig['unit']}."
    );
}

$this->db->transactional(function () use (...): void {
    // 1. Trừ repackageStock của sản phẩm nguồn
    $this->execute(
        "UPDATE products SET repackageStock = repackageStock - ?, updatedAt = ? WHERE id = ?",
        [$quantityBig, $now, $productIdBig]
    );

    // 2. Cộng repackageStock cho từng sản phẩm đích & ghi lịch sử
    foreach ($smallItems as $item) {
        $this->execute(
            "UPDATE products SET repackageStock = repackageStock + ?, updatedAt = ? WHERE id = ?",
            [$item['quantity'], $now, $item['id']]
        );

        $historyRepo->addRow([
            'date'            => $date,
            'fromProductId'   => $productIdBig,
            'fromProductName' => $productBig['name'],
            'toProductId'     => $item['id'],
            'toProductName'   => $item['name'],
            'fromQuantity'    => $quantityBig,
            'toQuantity'      => $item['quantity'],
            'note'            => '',
        ]);
    }
});
```

---

### 6.3. Hiển Thị Lịch Sử Chiết Hàng
Các bản ghi trong `repackage_history` được nhóm theo phiên chiết (`date | fromProductName | createdAt`) qua `RepackageHistoryRepository::getDataToView(20)`:
- Hiển thị ngày giờ thực hiện phiên chiết.
- Định dạng trực quan số lượng thay đổi:
  ```text
  -1 Bao Thức Ăn Chó Classic 20kg
  +15 Gói Hạt Classic 1kg
  +10 Gói Hạt Classic 500g
  ```

---

## 7. Các Tệp Mã Nguồn Liên Quan

| Tệp mã nguồn | Vai trò |
|:---|:---|
| [`ProductController.php`](file:///d:/Developer/Project/sampet_2.0/module/Application/src/Controller/ProductController.php) | Xử lý request `repackageAction` và `doRepackageAction` |
| [`ProductRepository.php`](file:///d:/Developer/Project/sampet_2.0/module/Application/src/Repository/ProductRepository.php) | Nghiệp vụ `doRepackage`, kiểm tra tồn kho `calcRemainStock` |
| [`RepackageHistoryRepository.php`](file:///d:/Developer/Project/sampet_2.0/module/Application/src/Repository/RepackageHistoryRepository.php) | Thêm bản ghi `addRow`, truy vấn nhóm dữ liệu `getDataToView` |
| [`repackage.phtml`](file:///d:/Developer/Project/sampet_2.0/module/Application/view/application/product/repackage.phtml) | Giao diện form chiết hàng, Select2, tự động thêm dòng và hiển thị lịch sử |
| [`001_initial_schema.sql`](file:///d:/Developer/Project/sampet_2.0/data/migrations/001_initial_schema.sql) | Khởi tạo bảng `products` (cột `repackageStock`) & bảng `repackage_history` |

---

## 8. Kiểm Tra & Xác Minh (Testing & Verification)

1. **Kiểm tra luồng chuẩn (Happy Path):**
   - Chọn sản phẩm nguồn có tồn kho $> 0$, nhập số lượng chiết hợp lệ.
   - Chọn 1 hoặc nhiều sản phẩm đích với số lượng $> 0$.
   - Bấm **Lưu** $\rightarrow$ Hệ thống báo thành công, tồn kho nguồn giảm, tồn kho đích tăng, lịch sử xuất hiện phiên chiết mới.
2. **Kiểm tra khi tồn kho không đủ (Insufficient Stock):**
   - Chọn sản phẩm nguồn có tồn kho $= 2$, nhập số lượng chiết $= 5$.
   - Bấm **Lưu** $\rightarrow$ Hệ thống báo lỗi `Tồn kho không đủ để chiết. Hiện còn: 2...`, dữ liệu tồn kho không bị thay đổi.
3. **Kiểm tra bảo mật CSRF:**
   - Thử gửi request POST trực tiếp không có hoặc sai CSRF token $\rightarrow$ Bị chặn ngay lập tức.
