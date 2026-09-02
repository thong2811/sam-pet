-- ============================================================
-- Migration 001 — Initial Schema
-- Sam Pet v2 — SQLite
-- Tạo toàn bộ 15 bảng + indexes
--
-- Quy ước:
--   - id: TEXT PRIMARY KEY (uniqid hex 13 ký tự, tương thích CSV)
--   - date: TEXT 'dd-mm-yyyy' (giữ nguyên định dạng hiện tại)
--   - createdAt / updatedAt: INTEGER (Unix timestamp), nullable
--     (một số rows cũ migrate từ CSV có thể null)
-- ============================================================

-- ----------------------------------------------------------------
-- 1. categories (Nhóm 6 — làm cùng để tránh migrate 2 lần)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id          TEXT NOT NULL PRIMARY KEY,  -- uniqid
    name        TEXT NOT NULL DEFAULT '',
    note        TEXT NOT NULL DEFAULT '',
    createdAt   INTEGER,
    updatedAt   INTEGER
);

-- ----------------------------------------------------------------
-- 2. products
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id              TEXT NOT NULL PRIMARY KEY,  -- uniqid
    name            TEXT NOT NULL DEFAULT '',
    unit            TEXT NOT NULL DEFAULT '',
    sellingPrice    REAL NOT NULL DEFAULT 0,
    purchasePrice   REAL NOT NULL DEFAULT 0,
    initStock       REAL NOT NULL DEFAULT 0,
    repackageStock  REAL NOT NULL DEFAULT 0,
    invoiceCheck    TEXT NOT NULL DEFAULT '0',  -- '0' | '1'
    categoryId      TEXT,                       -- FK nullable (Nhóm 6)
    createdAt       INTEGER,
    updatedAt       INTEGER,
    FOREIGN KEY (categoryId) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_products_categoryId ON products (categoryId);
CREATE INDEX IF NOT EXISTS idx_products_name       ON products (name);

-- ----------------------------------------------------------------
-- 3. import_stock
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS import_stock (
    id              TEXT NOT NULL PRIMARY KEY,
    date            TEXT NOT NULL DEFAULT '',   -- dd-mm-yyyy
    productId       TEXT NOT NULL DEFAULT '',
    productName     TEXT NOT NULL DEFAULT '',   -- denormalized snapshot
    quantity        REAL NOT NULL DEFAULT 1,
    purchasePrice   REAL NOT NULL DEFAULT 0,
    note            TEXT NOT NULL DEFAULT '',
    createdAt       INTEGER,
    updatedAt       INTEGER,
    FOREIGN KEY (productId) REFERENCES products(id)
);

CREATE INDEX IF NOT EXISTS idx_import_stock_date      ON import_stock (date);
CREATE INDEX IF NOT EXISTS idx_import_stock_productId ON import_stock (productId);

-- ----------------------------------------------------------------
-- 4. export_stock
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS export_stock (
    id              TEXT NOT NULL PRIMARY KEY,
    date            TEXT NOT NULL DEFAULT '',
    productId       TEXT NOT NULL DEFAULT '',
    productName     TEXT NOT NULL DEFAULT '',
    quantity        REAL NOT NULL DEFAULT 1,
    sellingPrice    REAL NOT NULL DEFAULT 0,
    purchasePrice   REAL NOT NULL DEFAULT 0,
    note            TEXT NOT NULL DEFAULT '',
    customerId      TEXT,                       -- FK nullable (task 5.14)
    createdAt       INTEGER,
    updatedAt       INTEGER,
    FOREIGN KEY (productId)   REFERENCES products(id),
    FOREIGN KEY (customerId)  REFERENCES customers(id) ON DELETE SET NULL
);

-- NOTE: customers table phải tạo trước export_stock nếu enforce FK
-- SQLite không enforce FK declaration order — vẫn OK khi tạo trước
CREATE INDEX IF NOT EXISTS idx_export_stock_date       ON export_stock (date);
CREATE INDEX IF NOT EXISTS idx_export_stock_productId  ON export_stock (productId);
CREATE INDEX IF NOT EXISTS idx_export_stock_customerId ON export_stock (customerId);

-- ----------------------------------------------------------------
-- 5. customers (task 5.14)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id          TEXT NOT NULL PRIMARY KEY,
    name        TEXT NOT NULL DEFAULT '',
    phone       TEXT NOT NULL DEFAULT '',
    address     TEXT NOT NULL DEFAULT '',
    note        TEXT NOT NULL DEFAULT '',
    createdAt   INTEGER,
    updatedAt   INTEGER
);

CREATE INDEX IF NOT EXISTS idx_customers_name  ON customers (name);
CREATE INDEX IF NOT EXISTS idx_customers_phone ON customers (phone);

-- ----------------------------------------------------------------
-- 6. vet_care
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vet_care (
    id              TEXT NOT NULL PRIMARY KEY,
    date            TEXT NOT NULL DEFAULT '',
    treatmentAmount REAL NOT NULL DEFAULT 0,
    spaAmount       REAL NOT NULL DEFAULT 0,
    note            TEXT NOT NULL DEFAULT '',
    createdAt       INTEGER,
    updatedAt       INTEGER
);

CREATE INDEX IF NOT EXISTS idx_vet_care_date ON vet_care (date);

-- ----------------------------------------------------------------
-- 7. expenses
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS expenses (
    id          TEXT NOT NULL PRIMARY KEY,
    date        TEXT NOT NULL DEFAULT '',
    type        TEXT NOT NULL DEFAULT '0',  -- '0'=chi phí, '1'=tiết kiệm
    reason      TEXT NOT NULL DEFAULT '',
    amount      REAL NOT NULL DEFAULT 0,
    person      TEXT NOT NULL DEFAULT '',
    note        TEXT NOT NULL DEFAULT '',
    createdAt   INTEGER,
    updatedAt   INTEGER
);

CREATE INDEX IF NOT EXISTS idx_expenses_date ON expenses (date);
CREATE INDEX IF NOT EXISTS idx_expenses_type ON expenses (type);

-- ----------------------------------------------------------------
-- 8. reports
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reports (
    id                TEXT NOT NULL PRIMARY KEY,
    date              TEXT NOT NULL DEFAULT '',
    petShopRevenue    REAL NOT NULL DEFAULT 0,
    petShopProfit     REAL NOT NULL DEFAULT 0,
    spaRevenue        REAL NOT NULL DEFAULT 0,
    treatmentRevenue  REAL NOT NULL DEFAULT 0,
    expenses          REAL NOT NULL DEFAULT 0,
    savings           REAL NOT NULL DEFAULT 0,
    missingAmount     REAL NOT NULL DEFAULT 0,
    note              TEXT NOT NULL DEFAULT '',
    createdAt         INTEGER,
    updatedAt         INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS uidx_reports_date ON reports (date);
CREATE INDEX IF NOT EXISTS idx_reports_date ON reports (date);

-- ----------------------------------------------------------------
-- 9. export_invoices
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS export_invoices (
    id          TEXT NOT NULL PRIMARY KEY,
    date        TEXT NOT NULL DEFAULT '',
    content     TEXT NOT NULL DEFAULT '{}',  -- JSON blob
    total       REAL NOT NULL DEFAULT 0,
    createdAt   INTEGER,
    updatedAt   INTEGER
);

CREATE INDEX IF NOT EXISTS idx_export_invoices_date ON export_invoices (date);

-- ----------------------------------------------------------------
-- 10. owners_pets
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS owners_pets (
    id          TEXT NOT NULL PRIMARY KEY,
    owner_name  TEXT NOT NULL DEFAULT '',
    phone       TEXT NOT NULL DEFAULT '',
    pet_name    TEXT NOT NULL DEFAULT '',
    species     TEXT NOT NULL DEFAULT '',
    breed       TEXT NOT NULL DEFAULT '',
    gender      TEXT NOT NULL DEFAULT '',
    age         TEXT NOT NULL DEFAULT '',   -- TEXT: có thể "5" hoặc "6 tháng"
    note        TEXT NOT NULL DEFAULT '',
    createdAt   INTEGER,
    updatedAt   INTEGER
);

CREATE INDEX IF NOT EXISTS idx_owners_pets_pet_name   ON owners_pets (pet_name);
CREATE INDEX IF NOT EXISTS idx_owners_pets_owner_name ON owners_pets (owner_name);
CREATE INDEX IF NOT EXISTS idx_owners_pets_phone      ON owners_pets (phone);

-- ----------------------------------------------------------------
-- 11. medical_records
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS medical_records (
    id              TEXT NOT NULL PRIMARY KEY,
    pet_id          TEXT NOT NULL DEFAULT '',
    visit_date      TEXT NOT NULL DEFAULT '',  -- dd-mm-yyyy
    symptoms        TEXT NOT NULL DEFAULT '',
    diagnosis       TEXT NOT NULL DEFAULT '',
    prescription    TEXT NOT NULL DEFAULT '',
    start_date      TEXT NOT NULL DEFAULT '',
    end_date        TEXT NOT NULL DEFAULT '',
    createdAt       INTEGER,
    updatedAt       INTEGER,
    FOREIGN KEY (pet_id) REFERENCES owners_pets(id)
);

CREATE INDEX IF NOT EXISTS idx_medical_records_pet_id     ON medical_records (pet_id);
CREATE INDEX IF NOT EXISTS idx_medical_records_visit_date ON medical_records (visit_date);

-- ----------------------------------------------------------------
-- 12. stocktaking
-- Giữ nguyên semantics: id = productId (upsert per product)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stocktaking (
    id          TEXT NOT NULL PRIMARY KEY,   -- = productId
    stocktaking REAL,                        -- NULL = chưa nhập; REAL để tính toán
    createdAt   INTEGER,
    updatedAt   INTEGER,
    FOREIGN KEY (id) REFERENCES products(id)
);

-- ----------------------------------------------------------------
-- 13. stocktaking_periods (task 5.8a — giữ lịch sử chốt kho)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stocktaking_periods (
    id          TEXT NOT NULL PRIMARY KEY,  -- uniqid
    closedAt    TEXT NOT NULL DEFAULT '',   -- dd-mm-yyyy ngày chốt
    note        TEXT NOT NULL DEFAULT '',
    createdAt   INTEGER,
    updatedAt   INTEGER
);

CREATE INDEX IF NOT EXISTS idx_stocktaking_periods_closedAt ON stocktaking_periods (closedAt);

-- ----------------------------------------------------------------
-- 14. stocktaking_period_items (task 5.8a)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stocktaking_period_items (
    id          TEXT NOT NULL PRIMARY KEY,
    periodId    TEXT NOT NULL,
    productId   TEXT NOT NULL,
    actualStock REAL NOT NULL DEFAULT 0,    -- số lượng đếm thực tế khi chốt
    createdAt   INTEGER,
    updatedAt   INTEGER,
    FOREIGN KEY (periodId)  REFERENCES stocktaking_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (productId) REFERENCES products(id)
);

CREATE INDEX IF NOT EXISTS idx_sp_items_periodId  ON stocktaking_period_items (periodId);
CREATE INDEX IF NOT EXISTS idx_sp_items_productId ON stocktaking_period_items (productId);

-- ----------------------------------------------------------------
-- 15. repackage_history (chuẩn hoá theo task 5.15)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS repackage_history (
    id              TEXT NOT NULL PRIMARY KEY,
    date            TEXT NOT NULL DEFAULT '',      -- dd-mm-yyyy
    fromProductId   TEXT,                          -- NULL khi migrate từ CSV (không có ID trong content)
    fromProductName TEXT NOT NULL DEFAULT '',      -- snapshot tên lúc chiết
    toProductId     TEXT,                          -- NULL khi migrate từ CSV
    toProductName   TEXT NOT NULL DEFAULT '',      -- snapshot tên lúc chiết
    fromQuantity    REAL NOT NULL DEFAULT 0,
    toQuantity      REAL NOT NULL DEFAULT 0,
    note            TEXT NOT NULL DEFAULT '',
    createdAt       INTEGER,
    updatedAt       INTEGER,
    FOREIGN KEY (fromProductId) REFERENCES products(id),
    FOREIGN KEY (toProductId)   REFERENCES products(id)
);

CREATE INDEX IF NOT EXISTS idx_repackage_history_date          ON repackage_history (date);
CREATE INDEX IF NOT EXISTS idx_repackage_history_fromProductId ON repackage_history (fromProductId);
CREATE INDEX IF NOT EXISTS idx_repackage_history_toProductId   ON repackage_history (toProductId);
