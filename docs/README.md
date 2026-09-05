# Hệ Thống Tài Liệu Kỹ Thuật (Technical Documentation) — Sam Pet 2.0

Chào mừng bạn đến với trung tâm tài liệu kỹ thuật của dự án **Sam Pet 2.0**. Hệ thống tài liệu được phân chia thành 3 phân khu chính: **Kiến trúc hệ thống**, **Phân hệ & Tính năng**, và **Vận hành & Triển khai**.

---

## 🗂️ Cấu Trúc Thư Mục Tài Liệu

```
docs/
├── README.md                      # Mục lục và tổng quan hệ thống tài liệu
│
├── architecture/                  # Kiến trúc hệ thống, CSDL & API
│   ├── ARCHITECTURE.md            # Mô hình kiến trúc MVC + Repository, Layering & CSRF
│   ├── DATABASE_SCHEMA.md         # Sơ đồ CSDL SQLite 15 bảng & Indexes
│   └── API_AND_ROUTES.md          # Danh sách toàn bộ Routes, Endpoints & Controller Actions
│
├── features/                      # Chi tiết phân hệ nghiệp vụ & tính năng
│   ├── MODULES_AND_FEATURES.md    # Tổng quan 4 phân hệ chính (Kho, Thú y, Tài chính, Hệ thống)
│   └── REPACKAGE.md               # Đặc tả chi tiết Chức năng Chiết hàng (Repackage)
│
└── operations/                    # Vận hành, Triển khai & Bảo trì
    ├── DEPLOYMENT.md              # Cài đặt môi trường, Docker, Biến môi trường .env
    ├── BACKUP_STRATEGY.md         # 3 cơ chế sao lưu tự động & Quy trình khôi phục
    └── MIGRATION_GUIDE.md         # Hướng dẫn di chuyển dữ liệu từ CSV sang SQLite
```

---

## 📑 Danh Mục Tài Liệu Chi Tiết

### 1. 🏗️ Kiến Trúc & Thiết Kế Hệ Thống (`docs/architecture/`)

| Tài liệu | Mô tả nội dung | Đối tượng độc giả |
| :--- | :--- | :--- |
| [ARCHITECTURE.md](architecture/ARCHITECTURE.md) | Tổng quan kiến trúc MVC, Laminas Framework, Repository Pattern, SQLite WAL mode, CSRF Service | Backend Developer, Solution Architect |
| [DATABASE_SCHEMA.md](architecture/DATABASE_SCHEMA.md) | Đặc tả chi tiết 15 bảng CSDL SQLite `data/app.db`, kiểu dữ liệu, khóa chính, khóa ngoại, Indexes | Developer, DBA |
| [API_AND_ROUTES.md](architecture/API_AND_ROUTES.md) | Danh mục đường dẫn (Routes), HTTP methods, Controller Actions và chức năng tương ứng | Fullstack Developer, QA / Tester |

---

### 2. 🧩 Phân Hệ & Tính Năng Nghiệp Vụ (`docs/features/`)

| Tài liệu | Mô tả nội dung | Đối tượng độc giả |
| :--- | :--- | :--- |
| [MODULES_AND_FEATURES.md](features/MODULES_AND_FEATURES.md) | Tổng quan 4 phân hệ cốt lõi: Quản lý kho, Phòng khám thú y & Spa, Quản lý tài chính & Báo cáo, Tiện ích hệ thống | Tất cả thành viên |
| [REPACKAGE.md](features/REPACKAGE.md) | Nghiệp vụ chiết tách bao lớn thành gói nhỏ, công thức tính tồn kho, luồng xử lý Transaction & Lịch sử chiết | Developer, Quản lý kho |

---

### 3. 🚀 Vận Hành & Triển Khai (`docs/operations/`)

| Tài liệu | Mô tả nội dung | Đối tượng độc giả |
| :--- | :--- | :--- |
| [DEPLOYMENT.md](operations/DEPLOYMENT.md) | Hướng dẫn cài đặt môi trường, cấu hình Docker Compose, file cấu hình `.env` & kiểm tra sức khỏe hệ thống | DevOps, SysAdmin, Developer |
| [BACKUP_STRATEGY.md](operations/BACKUP_STRATEGY.md) | Chi tiết 3 tầng sao lưu (Daily local, Stocktaking local, GitHub Releases cloud) & Hướng dẫn restore | DevOps, SysAdmin, Quản lý |
| [MIGRATION_GUIDE.md](operations/MIGRATION_GUIDE.md) | Hướng dẫn từng bước migrate dữ liệu lịch sử từ tệp CSV sang SQLite trên môi trường Production | DevOps, Database Engineer |

---

## 🧭 Hướng Dẫn Dành Cho Thành Viên Mới (Quick Onboarding)

1. **Khởi động dự án:** Đọc [DEPLOYMENT.md](operations/DEPLOYMENT.md) để cài đặt Docker hoặc PHP server cục bộ.
2. **Hiểu kiến trúc ứng dụng:** Đọc [ARCHITECTURE.md](architecture/ARCHITECTURE.md) và [DATABASE_SCHEMA.md](architecture/DATABASE_SCHEMA.md).
3. **Nắm rõ nghiệp vụ:** Đọc [MODULES_AND_FEATURES.md](features/MODULES_AND_FEATURES.md) và các đặc tả tính năng trong `docs/features/`.
4. **Tham khảo API & Điều hướng:** Tra cứu [API_AND_ROUTES.md](architecture/API_AND_ROUTES.md) khi thêm mới hoặc sửa đổi Controllers / Views.
