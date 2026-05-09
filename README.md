# CryptoVulnX

> Plataforma de trading crypto **deliberadamente vulnerable** para entrenamiento en pentesting de APIs. Cubre el OWASP API Security Top 10 (2023) y enseña la metodologia completa de un pentest profesional en 5 fases: RECON → INVENTORY → ENDPOINT FUZZING → PARAMETER FUZZING → EXPLOITATION.

## ⚠️ Disclaimer

Esta aplicacion contiene vulnerabilidades **intencionales**. Esta diseñada **exclusivamente** para uso educativo en entornos aislados (Docker local, VM). NO la expongas a internet. NO la uses contra sistemas que no te pertenezcan.

---

## Quick Start

### Con Docker (recomendado)
```bash
git clone https://github.com/<tu-org>/cryptovulnx.git
cd cryptovulnx
docker compose up -d

# Esperar ~15s a que MySQL inicialice
docker compose logs -f db   # esperar "ready for connections"

# Acceder
open http://localhost:8080            # Frontend
open http://localhost:8080/playbook   # Playbook gamificado del pentest
```

### Sin Docker (XAMPP / LAMP)
Ver `docs/SETUP.md`.

---

## Cuentas de prueba

| Usuario | Password | Rol |
|---|---|---|
| `carlos` | `carlos2024` | user |
| `maria` | `maria2024` | user |
| `admin` | `admin123` | admin |

(Hay mas creds plantadas en archivos de recon - parte del LAB11.)

---

## Estructura del lab

### Metodologia (5 fases)
| Fase | LAB | Objetivo |
|---|---|---|
| 1 - RECON | [LAB11](labs/LAB11-RECON.md) | Descubrir archivos expuestos (`/.git`, `/.env.bak`, `/backup.sql`, `/notes.txt`, `/composer.lock`...) |
| 2 - API INVENTORY | [LAB12](labs/LAB12-API-INVENTORY-FUZZING.md) | Mapear versiones (`v1`, `v2`, `v3`, `internal`, `test`, `dev`, `staging`) y specs |
| 3 - ENDPOINT FUZZING | [LAB13](labs/LAB13-ENDPOINT-FUZZING.md) | Rutas admin/internal: `exec`, `backup`, `logs`, `private-keys`, `health` |
| 4 - PARAMETER FUZZING | [LAB14](labs/LAB14-PARAMETER-FUZZING.md) | Query/header/body/cookie ocultos |
| 5 - EXPLOITATION | [LAB01..10](labs/) | OWASP API Top 10 |

### OWASP API Top 10 (2023)
| LAB | OWASP | Topic |
|---|---|---|
| [LAB01](labs/LAB01-BOLA.md) | API1 | Broken Object Level Authorization |
| [LAB02](labs/LAB02-BROKEN-AUTH.md) | API2 | Broken Authentication |
| [LAB03](labs/LAB03-BOPLA.md) | API3 | Broken Object Property Level Authorization (Mass Assignment) |
| [LAB04](labs/LAB04-RESOURCE.md) | API4 | Unrestricted Resource Consumption |
| [LAB05](labs/LAB05-BFLA.md) | API5 | Broken Function Level Authorization |
| [LAB06](labs/LAB06-BUSINESS-FLOW.md) | API6 | Unrestricted Access to Sensitive Business Flows |
| [LAB07](labs/LAB07-SSRF.md) | API7 | Server Side Request Forgery |
| [LAB08](labs/LAB08-MISCONFIG.md) | API8 | Security Misconfiguration |
| [LAB09](labs/LAB09-INVENTORY.md) | API9 | Improper Inventory Management |
| [LAB10](labs/LAB10-UNSAFE-CONSUMPTION.md) | API10 | Unsafe Consumption of APIs |

### Docs maestros
- `docs/METHODOLOGY.md` - guia maestra del instructor (referencia, **spoilea**)
- `docs/WORDLISTS.md` - wordlists recomendados por fase
- `docs/TOOLS.md` - herramientas por fase
- `docs/SETUP.md` - instalacion sin Docker
- `docs/API-DOCS.md` - documentacion del API (publica, no completa - parte del lab)

### Wordlists incluidos
- `wordlists/cryptovulnx-api-versions.txt` (Fase 2)
- `wordlists/cryptovulnx-endpoints.txt` (Fase 3)
- `wordlists/cryptovulnx-magic-params.txt` (Fase 4)

---

## Como recorrer el lab

### Modalidad guiada (recomendada para alumnos)
1. `docker compose up -d`
2. Abrir `http://localhost:8080/playbook` (sin auth)
3. Seguir las fases: el playbook pide flags de cada fase para desbloquear la siguiente
4. Consultar el `LAB##.md` correspondiente cuando te traben
5. Al final, entregar al instructor: screenshot de `GET /playbook?phase=final` con header `X-Flags-Collected: <csv>`

### Modalidad libre (CTF / capture-the-flag)
1. `docker compose up -d`
2. Coleccionar todos los `FLAG-*` plantados en el lab (~28 flags totales)
3. Al final, `curl -H "X-Flags-Collected: FLAG-RECON-01,FLAG-INVENT-01,..." http://localhost:8080/playbook?phase=final`

### Modalidad instructor / referencia
Leer `docs/METHODOLOGY.md` directamente.

---

## Herramientas recomendadas

`docs/TOOLS.md` lista herramientas por fase. Lo basico:

```bash
# Brute / fuzzing
sudo apt install ffuf gobuster feroxbuster dirsearch

# Param mining
pip install arjun

# JWT
pip install jwt-tool

# CVE templates
nuclei -ut

# Git recovery
pip install git-dumper

# API
go install github.com/assetnote/kiterunner/cmd/kr@latest
```

---

## Resetear el lab

```bash
docker compose down -v   # borra volumen de DB
docker compose up -d     # vuelve a inicializar la DB con database/init.sql
```

---

## Contribuir

Si encontras una vulnerabilidad **no intencional** (es decir, una que no encaja en ningun LAB), abrir issue. Si encontras una nueva forma creativa de explotar las que ya estan, PR bienvenido.

---

## License

MIT - solo para uso educativo en entornos aislados. Ver `LICENSE`.
