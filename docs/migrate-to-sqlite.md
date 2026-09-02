# Hướng dẫn migrate CSV → SQLite trên Production

> Áp dụng khi nâng cấp từ phiên bản CSV (v1) lên SQLite (v2).  
> Script chỉ **đọc** CSV, không xóa hay sửa file gốc.

---

## Yêu cầu

- Docker + Docker Compose đang chạy trên server
- Code đã được pull về branch `vet_2.0.0` (hoặc branch tương đương)

---

## Các bước thực hiện

### 1. Backup dữ liệu CSV gốc

```bash
cp -r data/ data_backup_$(date +%Y%m%d)/
```

---

### 2. Pull code mới

```bash
git pull origin vet_2.0.0
```

---

### 3. Rebuild Docker image

Cần thiết vì Dockerfile đã thêm `pdo_sqlite`:

```bash
docker compose down
docker compose up --build -d
```

---

### 4. Fix permission thư mục data

```bash
docker compose exec app chown -R www-data:www-data /var/www/html/data
```

---

### 5. Chạy dry-run để kiểm tra trước

```bash
docker compose exec app php /var/www/html/bin/migrate-csv-to-sqlite.php --dry-run
```

Kiểm tra output: số rows mỗi bảng, không có lỗi nào. Nếu ổn, tiến hành bước 6.

---

### 6. Chạy migration thật

```bash
docker compose exec app php /var/www/html/bin/migrate-csv-to-sqlite.php
```

Output mẫu khi thành công:

```
→ Migrating products ...       inserted=491  skipped=0
→ Migrating import_stock ...   inserted=232  skipped=0
→ Migrating export_stock ...   inserted=3838 skipped=0
...
✅ Migration hoàn thành thành công!
```

---

### 7. Xác minh kết quả

```bash
docker compose exec app php /var/www/html/bin/verify-migration.php
```

Kiểm tra:
- Row count mỗi bảng khớp với CSV gốc
- FK integrity: 0 orphan rows
- Sample data hiển thị đúng

---

### 8. Fix permission file app.db

```bash
docker compose exec app chown www-data:www-data /var/www/html/data/app.db
docker compose exec app chmod 664 /var/www/html/data/app.db
```

---

### 9. Kiểm tra app chạy bình thường

Mở trình duyệt và kiểm tra các trang:
- `/product` — danh sách sản phẩm, tồn kho hiển thị đúng
- `/import-stock` — lịch sử nhập hàng
- `/export-stock` — lịch sử xuất hàng
- `/report` — báo cáo thu chi
- `/overview` — biểu đồ doanh thu

---

## Rollback nếu có sự cố

Xóa file `app.db` — app sẽ báo lỗi thay vì đọc dữ liệu sai. CSV gốc vẫn còn nguyên:

```bash
rm /var/www/html/data/app.db
# Khôi phục code cũ rồi restart
git checkout <commit-cũ>
docker compose restart
```

---

## Lưu ý

| | Dev | Prod |
|---|---|---|
| GitHub backup tag | `data-backup-dev` | `data-backup-prod` |
| Flag nguy hiểm | `--force` xóa DB hiện có | **Không dùng `--force` trên prod** |
| Migration lần 2 | Dùng `INSERT OR IGNORE` — row trùng id bị skip, không bị lỗi | An toàn để chạy lại |
