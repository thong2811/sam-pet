# Sam Pet 2.0 — Quản Lý Cửa Hàng & Phòng Khám Thú Cưng

Hệ thống quản lý bán lẻ, kho hàng, dịch vụ phòng khám thú y, spa và báo cáo tài chính cửa hàng Sam Pet.

---

## 🌟 Tính Năng Chính

- 🏪 **Quản lý kho hàng & Chiết bao:** Theo dõi tồn kho thực tế, nhập kho, xuất bán lẻ, chiết gói lớn sang gói nhỏ, kiểm kê và chốt kho định kỳ.
- 🏥 **Phòng khám & Thú y:** Quản lý thông tin chủ nuôi, hồ sơ thú cưng, sổ bệnh án & doanh thu dịch vụ điều trị, tắm spa.
- 📊 **Tài chính & Sổ quỹ:** Quản lý chi phí cửa hàng, sổ hóa đơn bán lẻ, tự động tổng hợp báo cáo doanh thu & lợi nhuận ngày.
- 🛡️ **An toàn dữ liệu:** Hệ thống CSDL SQLite đơn giản, tối ưu với WAL mode, tích hợp cơ chế tự động sao lưu hàng ngày (Daily Auto-backup), sao lưu trước khi chốt kho và đồng bộ đám mây GitHub Releases.

---

## 📚 Hệ Thống Tài Liệu Kỹ Thuật

| Tài liệu | Mô tả |
| :--- | :--- |
| 🏗️ [Kiến trúc hệ thống](docs/ARCHITECTURE.md) | Mô hình MVC + Repository, luồng dữ liệu, bảo mật CSRF và công nghệ |
| 🗄️ [Sơ đồ CSDL](docs/DATABASE_SCHEMA.md) | Chi tiết 15 bảng CSDL SQLite, khóa chính/ngoại, indexes |
| 🧩 [Phân hệ & Nghiệp vụ](docs/MODULES_AND_FEATURES.md) | Quy tắc tính tồn kho, chiết hàng, chốt kho, sổ khám và báo cáo |
| 🌐 [Danh sách API & Routes](docs/API_AND_ROUTES.md) | Toàn bộ danh sách URLs, Controllers và Actions |
| 🚀 [Vận hành & Triển khai](docs/OPERATIONS_AND_DEPLOYMENT.md) | Hướng dẫn cài đặt môi trường, Docker, cấu hình `.env` & khôi phục dữ liệu |
| 💾 [Chiến lược sao lưu](docs/backup-strategy.md) | Chi tiết 3 cơ chế sao lưu tự động & quy trình khôi phục |

---

## 🚀 Khởi Động Nhanh

### 1. Cài đặt Dependencies & Môi trường

```bash
# Cài đặt thư viện PHP qua Composer
composer install

# Tạo file cấu hình môi trường
cp .env.example .env
```

### 2. Khởi chạy ứng dụng

#### Cách 1: Chạy bằng PHP Server (Khuyến nghị cho Development)
```bash
php -S 0.0.0.0:8080 -t public
```
Truy cập: `http://localhost:8080`

#### Cách 2: Chạy bằng Docker
```bash
docker compose up -d --build
```

---

## 🛠️ Cấu Trúc Thư Mục

```
sampet_2.0/
├── bin/                    # Các script CLI bảo trì, kiểm tra DB và kiểm thử
├── config/                 # Cấu hình Laminas application và modules
├── data/
│   ├── app.db              # CSDL SQLite chính
│   ├── backups/            # Thư mục lưu trữ các bản sao lưu (auto, stocktaking)
│   └── migrations/         # Các file SQL migration
├── docs/                   # Toàn bộ tài liệu kỹ thuật của hệ thống
├── module/
│   └── Application/        # Module chính (Controllers, Repositories, Services, Views)
└── public/                 # Thư mục web root (index.php, css, js, images)
```
