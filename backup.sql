-- CryptoVulnX - Backup parcial (DEMO 2026-03-15)
-- VULN (API8): Dump SQL accesible publicamente con password_plain y private_key
-- Archivo dejado por error despues de la demo de inversores

-- ----------------------------
-- Records of users (parcial - top 5)
-- ----------------------------
INSERT INTO `users` (`id`, `username`, `email`, `password_plain`, `password_hash`, `role`, `kyc_verified`, `referral_code`) VALUES
(1, 'admin', 'admin@cryptovulnx.internal', 'admin123', '$2y$10$abcdef...', 'admin', 1, 'REF-ADMI01'),
(2, 'carlos', 'carlos@cryptovulnx.com', 'carlos2024', '$2y$10$ghijkl...', 'user', 1, 'REF-CARL02'),
(3, 'maria', 'maria@cryptovulnx.com', 'maria2024', '$2y$10$mnopqr...', 'user', 1, 'REF-MARI03'),
(4, 'devops_internal', 'devops@cryptovulnx.internal', 'devops_master_2024!', '$2y$10$stuvwx...', 'admin', 1, 'REF-DEVO04'),
(5, 'qa_bot', 'qa@cryptovulnx.internal', 'qa_bot_static_pass', '$2y$10$yzabcd...', 'admin', 1, 'REF-QABO05');

-- ----------------------------
-- Records of wallets (parcial - top 3, expone private_key)
-- ----------------------------
INSERT INTO `wallets` (`id`, `user_id`, `wallet_address`, `currency`, `balance`, `private_key`) VALUES
(1, 1, '0xADMIN0001-BTC-VULNX', 'BTC', 999.50000000, 'pk_admin_btc_a1b2c3d4e5f6a7b8c9d0e1f2'),
(2, 1, '0xADMIN0001-ETH-VULNX', 'ETH', 5000.00000000, 'pk_admin_eth_b2c3d4e5f6a7b8c9d0e1f2a3'),
(3, 4, '0xDEVOP0004-BTC-VULNX', 'BTC', 100.00000000, 'pk_devops_btc_master_static_2024');

-- ----------------------------
-- Internal config (vol 1)
-- ----------------------------
INSERT INTO `internal_config` (`key`, `value`) VALUES
('jwt_secret_v1', 'crypto123'),
('jwt_secret_v2_beta', 'beta_secret_v2_temp'),
('admin_bypass_token', 'admin_dev_bypass_v1'),
('internal_service_token', 'internal_svc_token_2024_q1'),
('master_api_key', 'master_api_key_static_do_not_change'),
('rates_api_endpoint', 'http://10.0.1.50:8080/api/internal/rates.php'),
('backup_endpoint', 'http://10.0.1.50:8080/api/v1/admin/backup.php');

-- FLAG-RECON-05: backup_revela_password_plain_de_admin
-- Pista: ahora ya tenes admin/admin123 y devops_internal/devops_master_2024!
-- Probar contra /api/v1/auth/login.php para Fase 5
