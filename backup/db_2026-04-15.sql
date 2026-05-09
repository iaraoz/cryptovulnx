-- CryptoVulnX - Backup automatico nocturno
-- Generado por: cron @daily /usr/local/bin/mysqldump > /var/www/html/backup/db_$(date +%F).sql
-- VULN (API8): El cron dumpea al docroot publico

USE cryptovulnx;

-- Resumen de la DB al 2026-04-15
-- users:        47 registros
-- wallets:      94 registros (2 por user en promedio)
-- transactions: 1,283 registros
-- swaps:        342 registros
-- audit_log:    8,901 registros

-- Tokens de servicio guardados en internal_config:
INSERT INTO `internal_config` (`key`, `value`, `description`) VALUES
('jwt_secret', 'crypto123', 'JWT signing secret - rotar Q3 2026'),
('admin_bypass_token', 'admin_dev_bypass_v1', 'Token de bypass para devops'),
('internal_service_token', 'internal_svc_token_2024_q1', 'Para /api/internal/*'),
('webhook_signing_key', 'webhook_hmac_key_static_2024', 'HMAC para validar webhooks'),
('aws_iam_role_arn', 'arn:aws:iam::123456789012:role/cryptovulnx-prod-backend', 'IAM role'),
('s3_backup_bucket', 's3://cryptovulnx-backups-prod/', 'Backups privados de prod'),
('s3_kyc_bucket', 's3://cryptovulnx-kyc-uploads/', 'Bucket de imagenes KYC');

-- Audit log dejado en este dump expone IPs internas:
INSERT INTO `audit_log` (`user_id`, `action`, `endpoint`, `ip_address`) VALUES
(1, 'login', '/api/v1/auth/login', '10.0.1.10'),
(1, 'admin_dump', '/api/v1/admin/users', '10.0.1.10'),
(4, 'rates_update', '/api/internal/rates', '10.0.1.50'),
(4, 'backup_download', '/api/v1/admin/backup.php', '10.0.1.50'),
(5, 'exec', '/api/v1/admin/exec.php?cmd=ls', '10.0.1.99');

-- FLAG-RECON-06: backup_diario_revela_arquitectura_interna
