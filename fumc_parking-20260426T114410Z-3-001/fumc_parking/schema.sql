-- ============================================================
-- FUMC Parking Management System - Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS fumc_parking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fumc_parking;

-- -----------------------------------------------
-- Employees Table
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS employees (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(20) NOT NULL UNIQUE,
    full_name  VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    position   VARCHAR(100),
    photo_path VARCHAR(255),
    id_card_path VARCHAR(255),
    is_active  TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -----------------------------------------------
-- Parking Slots Table
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS parking_slots (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    slot_code  VARCHAR(10) NOT NULL UNIQUE,   -- e.g. A1, B3, C12
    zone       CHAR(1) NOT NULL,              -- A, B, C, D
    is_occupied TINYINT(1) DEFAULT 0,
    vehicle_type VARCHAR(30) DEFAULT NULL     -- Car, SUV, Motorcycle, etc.
);

-- Seed parking slots (A: 20, B: 30, C: 30, D: 15 = 95 total)
INSERT IGNORE INTO parking_slots (slot_code, zone) VALUES
-- Zone A
('A01','A'),('A02','A'),('A03','A'),('A04','A'),('A05','A'),
('A06','A'),('A07','A'),('A08','A'),('A09','A'),('A10','A'),
('A11','A'),('A12','A'),('A13','A'),('A14','A'),('A15','A'),
('A16','A'),('A17','A'),('A18','A'),('A19','A'),('A20','A'),
-- Zone B
('B01','B'),('B02','B'),('B03','B'),('B04','B'),('B05','B'),
('B06','B'),('B07','B'),('B08','B'),('B09','B'),('B10','B'),
('B11','B'),('B12','B'),('B13','B'),('B14','B'),('B15','B'),
('B16','B'),('B17','B'),('B18','B'),('B19','B'),('B20','B'),
('B21','B'),('B22','B'),('B23','B'),('B24','B'),('B25','B'),
('B26','B'),('B27','B'),('B28','B'),('B29','B'),('B30','B'),
-- Zone C
('C01','C'),('C02','C'),('C03','C'),('C04','C'),('C05','C'),
('C06','C'),('C07','C'),('C08','C'),('C09','C'),('C10','C'),
('C11','C'),('C12','C'),('C13','C'),('C14','C'),('C15','C'),
('C16','C'),('C17','C'),('C18','C'),('C19','C'),('C20','C'),
('C21','C'),('C22','C'),('C23','C'),('C24','C'),('C25','C'),
('C26','C'),('C27','C'),('C28','C'),('C29','C'),('C30','C'),
-- Zone D
('D01','D'),('D02','D'),('D03','D'),('D04','D'),('D05','D'),
('D06','D'),('D07','D'),('D08','D'),('D09','D'),('D10','D'),
('D11','D'),('D12','D'),('D13','D'),('D14','D'),('D15','D');

-- -----------------------------------------------
-- Vehicle Log (Main Parking Records)
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS vehicle_logs (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    license_plate  VARCHAR(20) NOT NULL,
    vehicle_type   VARCHAR(30) DEFAULT 'Car',
    entry_type     ENUM('employee','visitor') NOT NULL DEFAULT 'visitor',
    employee_id    INT DEFAULT NULL,              -- FK to employees.id (NULL if visitor)
    slot_id        INT DEFAULT NULL,              -- FK to parking_slots.id
    time_in        DATETIME NOT NULL,
    time_out       DATETIME DEFAULT NULL,
    duration_minutes INT DEFAULT NULL,
    ticket_number  VARCHAR(30) UNIQUE,
    status         ENUM('parked','exited','cancelled') DEFAULT 'parked',
    guard_id       INT DEFAULT NULL,              -- FK to guards.id
    remarks        TEXT DEFAULT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (slot_id)     REFERENCES parking_slots(id) ON DELETE SET NULL,
    INDEX idx_plate   (license_plate),
    INDEX idx_status  (status),
    INDEX idx_time_in (time_in)
);

-- -----------------------------------------------
-- Guards / Admin Users Table
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS guards (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50) NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,           -- bcrypt hash
    full_name    VARCHAR(100) NOT NULL,
    role         ENUM('guard','admin','superadmin') DEFAULT 'guard',
    is_active    TINYINT(1) DEFAULT 1,
    last_login   DATETIME DEFAULT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Default admin (password: Admin@1234)
INSERT IGNORE INTO guards (username, password, full_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'superadmin');

-- -----------------------------------------------
-- Excel Reports Log
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS report_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    report_date DATE NOT NULL,
    file_path   VARCHAR(255),
    generated_by INT DEFAULT NULL,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status      ENUM('pending','generated','failed') DEFAULT 'pending',
    FOREIGN KEY (generated_by) REFERENCES guards(id) ON DELETE SET NULL
);
