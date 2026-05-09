# TODO List - CryptoVulnX Dev Team

## Urgente (P0)
- [ ] Rotar `JWT_SECRET` (actual: `crypto123`, lo se, esta debil)
- [ ] Borrar `/api/v2/auth/login.php` (era para QA, quedo en prod)
- [ ] Borrar `/api/v3/` ENTERO (rama experimental con alg=none y RCE en exec.php)
- [ ] Borrar `/api/test/` y `/api/dev/` (mismo problema)
- [ ] Bloquear `/api/internal/*` con allowlist de IPs en Apache
- [ ] Mover `.env`, `.env.bak`, `.env.old` fuera del docroot
- [ ] Borrar `notes.txt`, `TODO.md` antes del deploy

## Importante (P1)
- [ ] Migrar de `password_plain` a solo `password_hash` (campo legacy)
- [ ] Validar `role` en register (mass assignment activo)
- [ ] Bloquear `Options +Indexes` en `/uploads/`
- [ ] Borrar `phpinfo.php` e `info.php` (estaban para debug del deploy)
- [ ] Limitar parametros aceptados en cada endpoint (hay muchos `?debug`, `?include`, `?fields` que no documentamos)

## Headers magicos pendientes de remover
- [ ] `X-Debug-Token` que habilita modo debug
- [ ] `X-Forwarded-For` que skipea allowlist de IP
- [ ] `X-Original-URL` para URL rewriting
- [ ] `X-Api-Version: internal` para routing oculto

## Backups
- [ ] Mover `/backup/` a S3 privado
- [ ] El cron diario sigue dejando `/backup/db_<fecha>.sql.gz` en docroot
- [ ] El dump completo `/backup.sql` es de la demo, borrar

## Endpoints sin documentar en swagger publico
- `/api/v1/admin/backup.php`
- `/api/v1/admin/exec.php`
- `/api/v1/admin/logs.php`
- `/api/v1/internal/health`
- `/api/v1/wallets/private-keys.php`
- `/api/v1/users/export.csv`

## Refactors postergados
- [ ] Reescribir queries SQL con prepared statements (todos)
- [ ] Validar ownership en BOLA endpoints (`/wallets/balance`, `/transactions/*`)
- [ ] Bloquear acceso a `.git/` en Apache
- [ ] Sanitizar `?file=` en logs.php (LFI)

---
FLAG-RECON-03: pista_para_fase3_endpoints_admin_via_logs_php
