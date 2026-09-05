# Vận Hành & Triển Khai (Operations & Deployment) — Sam Pet 2.0

Tài liệu hướng dẫn cài đặt, cấu hình môi trường, chạy ứng dụng trên Docker/Local, bảo trì CSDL SQLite và xử lý sự cố.

---

## 1. Yêu cầu hệ thống

- **PHP:** Phiên bản $\ge$ 8.1
- **PHP Extensions:** `pdo`, `pdo_sqlite`, `sqlite3`, `curl`, `json`, `mbstring`, `intl`, `zip`
- **Composer:** Phiên bản 2.x
- **Docker & Docker Compose** *(nếu chạy qua container)*

---

## 2. Cấu hình môi trường (`.env`)

Sao chép `.env.example` thành `.env` và thiết lập các tham số:

```ini
APP_ENV=production          # development | production
PORT=8080                   # Cổng chạy ứng dụng

STORE_NAME="Sam Pet"        # Tên cửa hàng hiển thị trên thanh tiêu đề & hóa đơn
STORE_ADDRESS="123 Đường ABC, Quận XYZ"
STORE_PHONE="0901234567"

DB_PATH="data/app.db"       # Đường dẫn file CSDL SQLite

# GitHub Cloud Backup (tùy chọn)
GITHUB_TOKEN="ghp_xxxxxxxxxxxx"
GITHUB_REPO_OWNER="username"
GITHUB_REPO_NAME="repository"

# Google Apps Script Web App (Đồng bộ Xuất hàng & Chiết hàng)
GOOGLE_APPS_SCRIPT_URL="https://script.google.com/macros/s/AKfycb.../exec"
```

---

## 3. Khởi chạy ứng dụng

### 3.1. Chạy nhanh bằng PHP Built-in Server (Local Development)

```bash
# 1. Cài đặt dependencies
composer install

# 2. Khởi chạy server
php -S 0.0.0.0:8080 -t public
```
Truy cập ứng dụng tại: `http://localhost:8080`

### 3.2. Chạy với Docker & Docker Compose (Production / Staging)

```bash
# Khởi động container nền
docker compose up -d --build

# Xem log hoạt động
docker compose logs -f
```

---

## 4. Quản lý Cơ sở dữ liệu & Sao lưu (Backup & Recovery)

### 4.1. File CSDL chính
- File CSDL SQLite duy nhất: `data/app.db`.
- Kèm file tạm thời của WAL Mode: `data/app.db-wal` và `data/app.db-shm`.

### 4.2. Khôi phục từ bản sao lưu nội bộ (Local Restore)

```bash
# 1. Xem danh sách các bản backup tự động
ls -lh data/backups/auto/
ls -lh data/backups/stocktaking/

# 2. Dừng ứng dụng
docker compose stop app

# 3. Thay thế app.db bằng bản backup mong muốn
cp data/backups/auto/YYYY-MM-DD_HHiiss.db data/app.db

# 4. Cấp quyền truy cập nếu chạy Docker
docker compose exec app chown www-data:www-data /var/www/html/data/app.db

# 5. Khởi động lại ứng dụng
docker compose start app
```

### 4.3. Khôi phục từ GitHub Cloud
Vào giao diện Quản trị tại menu **Cài đặt (`/settings`)** $\rightarrow$ bấm nút **Khôi phục từ GitHub**.

---

## 5. Các công cụ CLI bảo trì trong `bin/`

- `php bin/check-db.php`: Kiểm tra tính toàn vẹn và số lượng bản ghi các bảng SQLite.
- `php bin/smoke-test.php`: Chạy kiểm thử tự động luồng hoạt động chính.
- `php bin/clear-config-cache.php`: Xóa cache cấu hình Laminas khi thay đổi config.
- `bash bin/lint-all.sh`: Kiểm tra cú pháp PHP toàn bộ repository.
