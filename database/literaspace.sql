-- =============================================
-- Database: literaspace
-- =============================================

CREATE DATABASE IF NOT EXISTS literaspace
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE literaspace;

-- Tabel users
CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nama_depan      VARCHAR(100)  NOT NULL,
  nama_belakang   VARCHAR(100)  NOT NULL,
  email           VARCHAR(255)  NOT NULL UNIQUE,
  password        VARCHAR(255)  NOT NULL,
  reset_token     VARCHAR(255)  DEFAULT NULL,
  reset_expires   DATETIME      DEFAULT NULL,
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;