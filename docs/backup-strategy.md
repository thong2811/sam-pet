# Chiến lược Backup — Sam Pet v2

---

## Tổng quan

Toàn bộ dữ liệu nằm trong 1 file: `data/app.db`.  
Backup = copy file này ra chỗ khác bằng SQLite **VACUUM INTO** (tạo bản sao nhất quán, an toàn dù đang có writes).

---

## 3 loại backup

### 1. Daily backup (local)

**Trigger:** Tự động mỗi lần mở app lần đầu trong ngày (khi `onBootstrap` chạy).  
**Lưu tại:** `data/backups/auto/YYYY-MM-DD_HHiiss.db`  
**Giữ:** 30 ngày gần nhất, tự xóa cũ.  
**Không cần làm gì** — app tự lo.

### 2. Stocktaking backup (local)

**Trigger:** Tự động ngay trước mỗi lần **chốt kho**.  
**Lưu tại:** `data/backups/stocktaking/YYYY-MM-DD_HHiiss.db`  
**Giữ:** 10 bản gần nhất, tự xóa cũ.  
**Mục đích:** Safety net — nếu chốt kho có lỗi thì có thể restore về trước khi chốt.

### 3. GitHub Releases backup (cloud)

**Trigger:** Mỗi khi thêm hoặc sửa **báo cáo thu chi**.  
**Lưu tại:** GitHub Releases, asset tên `backup.db`.  
**Tag:** `data-backup-dev` (dev) hoặc `data-backup-prod` (prod).  
**Giữ:** 1 bản duy nhất (overwrite mỗi lần) — đây là bản cloud để phục hồi khi mất máy.

---

## Cấu trúc thư mục

```
data/backups/
├── auto/
│   ├── 2026-09-01_020000.db
│   ├── 2026-09-02_093015.db
│   └── ...  (tối đa 30 file)
├── stocktaking/
│   ├── 2026-09-01_140000.db
│   └── ...  (tối đa 10 file)
└── github/
    └── backup.db  (file tạm, tự xóa sau khi upload)
```

---

## Restore thủ công

### Restore từ local backup

```bash
# Xem danh sách backup có sẵn
ls -lh data/backups/auto/
ls -lh data/backups/stocktaking/

# Stop app
docker compose stop app

# Replace app.db bằng bản backup muốn khôi phục
cp data/backups/auto/2026-09-01_020000.db data/app.db

# Fix permission
docker compose exec app chown www-data:www-data /var/www/html/data/app.db

# Start lại
docker compose start app
```

### Restore từ GitHub

Vào trang `/settings` → nhấn nút **Restore từ GitHub** — app tự download và replace `app.db`.

---

## Lưu ý

- `data/backups/` đã được thêm vào `.gitignore` — file backup không bị commit lên git.
- File `data/app.db` cũng không được commit lên git.
- Trước khi restore, nên backup bản hiện tại trước: `cp data/app.db data/app.db.bak`
