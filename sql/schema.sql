-- =====================================================
-- Sunflower Dental Lab  --  MySQL 8 schema
-- =====================================================
-- Run this once on a fresh database. The bin/install.php
-- script will execute it for you, but you can also run
-- it manually:  mysql -u root -p sunflower < sql/schema.sql
-- =====================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------- USERS ----------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(60)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(120) NOT NULL,
  role          ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- CATEGORIES ----------
CREATE TABLE IF NOT EXISTS categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code        VARCHAR(40)  NOT NULL,           -- e.g. "Ceramic"
  label       VARCHAR(80)  NOT NULL,           -- e.g. "Ceramic"
  sort_order  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_cat_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- PRODUCTS ----------
CREATE TABLE IF NOT EXISTS products (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code          VARCHAR(40)  NOT NULL,
  name          VARCHAR(180) NOT NULL,
  category_id   INT UNSIGNED NOT NULL,
  price         DECIMAL(10,2) NOT NULL DEFAULT 0,
  note          VARCHAR(180) NOT NULL DEFAULT '',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_prod_code (code),
  KEY idx_prod_cat (category_id),
  CONSTRAINT fk_prod_cat
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- CLINICS ----------
CREATE TABLE IF NOT EXISTS clinics (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(180) NOT NULL,
  contact_person  VARCHAR(120) NOT NULL DEFAULT '',
  phone           VARCHAR(40)  NOT NULL DEFAULT '',
  email           VARCHAR(180) NOT NULL DEFAULT '',
  address         VARCHAR(255) NOT NULL DEFAULT '',
  notes           TEXT         NULL,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clinic_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- INVOICES ----------
-- One row per document (invoice OR receipt). Both share the same numbering.
CREATE TABLE IF NOT EXISTS invoices (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_no          VARCHAR(30)  NOT NULL,        -- e.g. "SF-000576"
  doc_type        ENUM('invoice','receipt') NOT NULL DEFAULT 'invoice',
  clinic_id       INT UNSIGNED NULL,
  -- Snapshot the clinic info so historical docs aren't broken if clinic changes.
  clinic_name     VARCHAR(180) NOT NULL DEFAULT '',
  clinic_address  VARCHAR(255) NOT NULL DEFAULT '',
  clinic_phone    VARCHAR(40)  NOT NULL DEFAULT '',
  doc_date        DATE         NOT NULL,
  subtotal        DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount        DECIMAL(10,2) NOT NULL DEFAULT 0,
  total           DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_method  VARCHAR(40)  NOT NULL DEFAULT '',
  paid_at         DATE         NULL,
  notes           TEXT         NULL,
  created_by      INT UNSIGNED NULL,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_doc_no (doc_no),
  KEY idx_inv_clinic (clinic_id),
  KEY idx_inv_date (doc_date),
  KEY idx_inv_type (doc_type),
  CONSTRAINT fk_inv_clinic
    FOREIGN KEY (clinic_id) REFERENCES clinics(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_inv_user
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- INVOICE ITEMS ----------
CREATE TABLE IF NOT EXISTS invoice_items (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id    INT UNSIGNED NOT NULL,
  product_id    INT UNSIGNED NULL,             -- nullable so deleting product doesn't lose history
  product_code  VARCHAR(40)  NOT NULL DEFAULT '',
  product_name  VARCHAR(180) NOT NULL,
  unit_price    DECIMAL(10,2) NOT NULL,
  qty           INT          NOT NULL DEFAULT 1,
  patient_name  VARCHAR(120) NOT NULL DEFAULT '',
  line_total    DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_item_inv (invoice_id),
  CONSTRAINT fk_item_inv
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_item_prod
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- SETTINGS ----------
CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(60)  NOT NULL,
  v VARCHAR(500) NOT NULL,
  PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
