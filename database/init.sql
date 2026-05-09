-- CryptoVulnX - Base de datos vulnerable para pentesting de APIs
-- OWASP API Security Top 10 - 2023

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE DATABASE IF NOT EXISTS cryptovulnx;
USE cryptovulnx;

-- ============================================
-- TABLA: users
-- VULN: Almacena password en texto plano (password_plain)
-- VULN: Campo role modificable via mass assignment (API3)
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_plain VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    kyc_verified TINYINT(1) DEFAULT 0,
    kyc_level INT DEFAULT 0,
    referral_code VARCHAR(20),
    referred_by VARCHAR(20),
    referral_bonus DECIMAL(18,8) DEFAULT 0,
    phone VARCHAR(20),
    full_name VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLA: wallets
-- VULN: private_key expuesto en respuestas API (API3)
-- VULN: wallet_address predecible
-- ============================================
CREATE TABLE wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    wallet_address VARCHAR(100) NOT NULL UNIQUE,
    currency VARCHAR(10) NOT NULL DEFAULT 'BTC',
    balance DECIMAL(18,8) DEFAULT 0.00000000,
    private_key VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLA: transactions
-- VULN: Sin validación de ownership (API1 - BOLA)
-- VULN: Monto negativo permitido
-- ============================================
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tx_hash VARCHAR(100) UNIQUE,
    from_wallet_id INT,
    to_wallet_id INT,
    amount DECIMAL(18,8) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    tx_type ENUM('transfer', 'swap', 'deposit', 'withdrawal', 'referral_bonus') DEFAULT 'transfer',
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_wallet_id) REFERENCES wallets(id),
    FOREIGN KEY (to_wallet_id) REFERENCES wallets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLA: swaps
-- VULN: Rate manipulable desde el cliente (API3/API6)
-- ============================================
CREATE TABLE swaps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    from_currency VARCHAR(10) NOT NULL,
    to_currency VARCHAR(10) NOT NULL,
    amount DECIMAL(18,8) NOT NULL,
    rate_applied DECIMAL(18,8) NOT NULL,
    result_amount DECIMAL(18,8) NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLA: kyc_documents
-- VULN: file_path con path traversal (API7)
-- VULN: Sin validación de tipo de archivo
-- ============================================
CREATE TABLE kyc_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_type ENUM('passport', 'national_id', 'drivers_license', 'selfie') NOT NULL,
    file_path VARCHAR(500),
    file_url VARCHAR(500),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reviewed_by INT,
    review_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLA: webhooks
-- VULN: URL sin validación - SSRF (API7)
-- ============================================
CREATE TABLE webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    event_type ENUM('transaction', 'swap', 'kyc_update', 'login') NOT NULL,
    secret VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    last_triggered TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLA: password_resets
-- VULN: PIN de 4 dígitos, sin expiración, sin rate limit
-- ============================================
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pin VARCHAR(4) NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLA: api_keys
-- VULN: Keys expuestas en respuestas
-- ============================================
CREATE TABLE api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    api_secret VARCHAR(128) NOT NULL,
    permissions VARCHAR(255) DEFAULT 'read',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLA: crypto_prices (cache local)
-- ============================================
CREATE TABLE crypto_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(10) NOT NULL,
    price_usd DECIMAL(18,8) NOT NULL,
    change_24h DECIMAL(8,4),
    volume_24h DECIMAL(18,2),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLA: audit_log
-- ============================================
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    endpoint VARCHAR(255),
    ip_address VARCHAR(45),
    request_data TEXT,
    response_code INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- DATOS DE PRUEBA
-- ============================================

-- Usuarios (passwords en texto plano intencionalmente)
INSERT INTO users (username, email, password_plain, password_hash, role, kyc_verified, kyc_level, referral_code, full_name, phone) VALUES
('admin', 'admin@cryptovulnx.local', 'admin123', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 3, 'REF-ADMIN01', 'System Administrator', '+1-555-0100'),
('carlos', 'carlos@email.com', 'carlos2024', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'user', 1, 2, 'REF-CARL01', 'Carlos Rodriguez', '+1-555-0101'),
('maria', 'maria@email.com', 'maria2024', '$2y$10$YcR/4Dv2iXBn5OEMbSLCkOcGh0J1xDqFGZPMBTMxTTGz4vN6MBVy6', 'user', 1, 1, 'REF-MARI01', 'Maria Gonzalez', '+1-555-0102'),
('juan', 'juan@email.com', 'juan2024', '$2y$10$KIXhPfBfhJHFGR0b0p0CreYjH/N6PbHEGiPRTT3dYiHkyLCr6B0d2', 'user', 0, 0, 'REF-JUAN01', 'Juan Martinez', '+1-555-0103'),
('ana', 'ana@email.com', 'ana2024', '$2y$10$wOoSz/n1LcSwN0h7r.YEfuGFrMFNqP1eSthx1LCxYqXsej0fC3ZHy', 'user', 0, 0, 'REF-ANA001', 'Ana Lopez', '+1-555-0104'),
('testuser', 'test@email.com', 'test1234', '$2y$10$3euPcmQFCiBlnoCJT8O5JOLxELqFOpB5TpHsIGlvIaGhRqSZ1LJqO', 'user', 0, 0, 'REF-TEST01', 'Test User', '+1-555-0105');

-- Wallets (private_key expuesto intencionalmente)
INSERT INTO wallets (user_id, wallet_address, currency, balance, private_key) VALUES
-- Admin wallets
(1, '0xADM1N-0001-BTC-VULNX', 'BTC', 100.00000000, 'pk_admin_btc_5f4dcc3b5aa765d61d8327deb882cf99'),
(1, '0xADM1N-0001-ETH-VULNX', 'ETH', 5000.00000000, 'pk_admin_eth_e99a18c428cb38d5f260853678922e03'),
-- Carlos wallets
(2, '0xCARL0-0002-BTC-VULNX', 'BTC', 2.50000000, 'pk_carlos_btc_d8578edf8458ce06fbc5bb76a58c5ca4'),
(2, '0xCARL0-0002-ETH-VULNX', 'ETH', 150.00000000, 'pk_carlos_eth_96e79218965eb72c92a549dd5a330112'),
(2, '0xCARL0-0002-USDT-VULNX', 'USDT', 25000.00000000, 'pk_carlos_usdt_c33367701511b4f6020ec61ded352059'),
-- Maria wallets
(3, '0xMARI0-0003-BTC-VULNX', 'BTC', 0.75000000, 'pk_maria_btc_6c44e5cd17f0024928bf4b5b2e7fae49'),
(3, '0xMARI0-0003-ETH-VULNX', 'ETH', 45.00000000, 'pk_maria_eth_3c59dc048e8850243be8079a5c74d079'),
-- Juan wallets
(4, '0xJUAN0-0004-BTC-VULNX', 'BTC', 0.10000000, 'pk_juan_btc_b6589fc6ab0dc82cf12099d1c2d40ab9'),
(4, '0xJUAN0-0004-ETH-VULNX', 'ETH', 5.00000000, 'pk_juan_eth_356a192b7913b04c54574d18c28d46e6'),
-- Ana wallets
(5, '0xANA00-0005-BTC-VULNX', 'BTC', 0.05000000, 'pk_ana_btc_da4b9237bacccdf19c0760cab7aec4a8'),
(5, '0xANA00-0005-USDT-VULNX', 'USDT', 500.00000000, 'pk_ana_usdt_77de68daecd823babbb58edb1c8e14d7'),
-- Test user wallets
(6, '0xTEST0-0006-BTC-VULNX', 'BTC', 1.00000000, 'pk_test_btc_1b6453892473a467d07372d45eb05abc'),
(6, '0xTEST0-0006-ETH-VULNX', 'ETH', 10.00000000, 'pk_test_eth_ac3478d69a3c81fa62e60f5c3696165a');

-- Transacciones de ejemplo
INSERT INTO transactions (tx_hash, from_wallet_id, to_wallet_id, amount, currency, tx_type, status, description, ip_address) VALUES
('TX-0001-abcdef1234567890', 3, 5, 0.50000000, 'BTC', 'transfer', 'completed', 'Pago por servicio de diseño', '192.168.1.100'),
('TX-0002-bcdef1234567890a', 5, 7, 0.25000000, 'BTC', 'transfer', 'completed', 'Transferencia a Maria', '192.168.1.101'),
('TX-0003-cdef1234567890ab', 1, 3, 1.00000000, 'BTC', 'transfer', 'completed', 'Bonus de bienvenida', '10.0.0.1'),
('TX-0004-def1234567890abc', 4, 8, 100.00000000, 'ETH', 'transfer', 'completed', 'Compra de NFT', '192.168.1.100'),
('TX-0005-ef1234567890abcd', 9, 11, 0.02000000, 'BTC', 'transfer', 'completed', 'Split de gastos', '192.168.1.102'),
('TX-0006-f1234567890abcde', NULL, 3, 2.00000000, 'BTC', 'deposit', 'completed', 'Depósito externo', '192.168.1.100'),
('TX-0007-1234567890abcdef', 5, NULL, 5000.00000000, 'USDT', 'withdrawal', 'pending', 'Retiro a wallet externa', '192.168.1.100');

-- Swaps de ejemplo
INSERT INTO swaps (user_id, from_currency, to_currency, amount, rate_applied, result_amount, status) VALUES
(2, 'BTC', 'ETH', 0.50000000, 15.50000000, 7.75000000, 'completed'),
(3, 'ETH', 'USDT', 10.00000000, 3500.00000000, 35000.00000000, 'completed'),
(2, 'USDT', 'BTC', 10000.00000000, 0.00001470, 0.14700000, 'completed');

-- Precios de crypto
INSERT INTO crypto_prices (symbol, price_usd, change_24h, volume_24h) VALUES
('BTC', 68000.00000000, 2.35, 45000000000.00),
('ETH', 3500.00000000, -1.20, 22000000000.00),
('USDT', 1.00000000, 0.01, 85000000000.00),
('BNB', 580.00000000, 3.10, 2500000000.00),
('SOL', 145.00000000, 5.40, 4800000000.00),
('XRP', 0.62000000, -0.80, 3200000000.00),
('ADA', 0.45000000, 1.75, 1200000000.00),
('DOGE', 0.15000000, 8.20, 2100000000.00);

-- API Keys
INSERT INTO api_keys (user_id, api_key, api_secret, permissions) VALUES
(1, 'ak_admin_live_7f8c9d0e1a2b3c4d5e6f7a8b9c0d1e2f', 'as_admin_secret_1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b', 'read,write,admin'),
(2, 'ak_carlos_live_a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6', 'as_carlos_secret_b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1', 'read,write'),
(3, 'ak_maria_live_f1e2d3c4b5a6f7e8d9c0b1a2f3e4d5c6', 'as_maria_secret_c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2', 'read');

-- Password resets (PIN predecible)
INSERT INTO password_resets (user_id, pin, used) VALUES
(2, '1234', 0),
(3, '5678', 0);

-- Webhooks
INSERT INTO webhooks (user_id, url, event_type, secret, is_active) VALUES
(2, 'https://webhook.site/carlos-notifications', 'transaction', 'whsec_carlos123', 1),
(3, 'https://webhook.site/maria-notifications', 'swap', 'whsec_maria456', 1);
