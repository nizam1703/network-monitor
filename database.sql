CREATE DATABASE IF NOT EXISTS network_monitor
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE network_monitor;

CREATE TABLE IF NOT EXISTS host_status (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hostname VARCHAR(100) NOT NULL UNIQUE,
    target VARCHAR(255) NOT NULL,
    status ENUM('UP','DOWN') NOT NULL,
    latency INT NULL,
    last_change DATETIME NOT NULL,
    last_check DATETIME NOT NULL,
    INDEX idx_status (status),
    INDEX idx_last_check (last_check)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ping_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hostname VARCHAR(100) NOT NULL,
    target VARCHAR(255) NOT NULL,
    status ENUM('UP','DOWN') NOT NULL,
    latency INT NULL,
    checked_at DATETIME NOT NULL,
    INDEX idx_hostname_checked_at (hostname, checked_at),
    INDEX idx_checked_at (checked_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS incidents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hostname VARCHAR(100) NOT NULL,
    target VARCHAR(255) NOT NULL,
    down_at DATETIME NOT NULL,
    recovered_at DATETIME NULL,
    duration_seconds INT UNSIGNED NULL,
    status ENUM('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
    analysis TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hostname_status (hostname, status),
    INDEX idx_down_at (down_at)
) ENGINE=InnoDB;
