#!/usr/bin/env python3
"""
Crea un directorio limpio listo para subir a GitHub.

- Copia todos los archivos del proyecto excepto los listados en .gitignore
   y un par de paths internos del autor.
- Renombra .git/ -> _git/ (parte del lab, servido via rewrite Apache).
- Inicializa git en el destino, hace primer commit.
- Muestra git status / log / size para que el usuario verifique antes de push.
"""

import os
import shutil
import stat
import subprocess
import sys
from pathlib import Path


def _rm_readonly(func, path, _):
    """Handler para shutil.rmtree en Windows: quita el read-only de los objects de git."""
    try:
        os.chmod(path, stat.S_IWRITE)
        func(path)
    except Exception:
        pass

ROOT = Path(__file__).parent.parent
DIST = ROOT.parent / "appsec-github-dist"

# Patrones a EXCLUIR (no subir a GitHub)
EXCLUDES = {
    # Hidden tooling de Claude Code
    ".claude",

    # Build artifacts del autor
    "appsec-github-dist",  # paranoia: por si DIST esta dentro de ROOT

    # Imagenes reales de KYC (privacidad)
    # Solo el .gitkeep de uploads/kyc/ se conserva
}

EXCLUDE_FILE_PATTERNS = (
    ".swp", ".swo", "~",
)

EXCLUDE_GLOB_DIRS = {
    "node_modules", "vendor", "__pycache__", ".pytest_cache",
}

# Mapping de renames (path-relativo: nombre nuevo)
RENAMES = {
    Path(".git"): Path("_git"),  # fake git del lab
}

# Paths que se conservan SIEMPRE aunque empiecen con punto
KEEP_DOTFILES = {
    ".env", ".env.bak", ".env.old", ".env.example",
    ".gitignore", ".dockerignore",
    ".htaccess", ".DS_Store",
    ".well-known",
    "_git",  # ya renombrado
}


def should_skip(rel_path: Path) -> bool:
    parts = rel_path.parts

    # Top-level excludes
    if parts[0] in EXCLUDES:
        return True

    # Glob dirs en cualquier nivel
    for p in parts:
        if p in EXCLUDE_GLOB_DIRS:
            return True

    # Patrones de archivos
    name = rel_path.name
    for pat in EXCLUDE_FILE_PATTERNS:
        if name.endswith(pat):
            return True

    # Real .git/ que git init pueda crear (no aplica al source porque ya lo renombramos)
    if parts[0] == ".git":
        return True

    # Imagenes reales en uploads/kyc/ (excepto .gitkeep)
    if (
        len(parts) >= 3
        and parts[0] == "uploads"
        and parts[1] == "kyc"
        and parts[-1] != ".gitkeep"
        and rel_path.suffix.lower() in (".jpg", ".jpeg", ".png", ".pdf", ".gif")
    ):
        return True

    # Logs
    if rel_path.suffix == ".log":
        return True

    return False


def copy_tree():
    if DIST.exists():
        print(f"[*] Limpiando {DIST}")
        # En Windows, .git/objects/ tiene archivos read-only; usamos onexc para forzar
        if sys.version_info >= (3, 12):
            shutil.rmtree(DIST, onexc=_rm_readonly)
        else:
            shutil.rmtree(DIST, onerror=lambda f, p, _e: _rm_readonly(f, p, _e))
    DIST.mkdir(parents=True)

    files_copied = 0
    bytes_copied = 0

    for src in ROOT.rglob("*"):
        rel = src.relative_to(ROOT)

        # Aplicar renames
        rel_target = rel
        for old, new in RENAMES.items():
            try:
                rel_target = new / rel.relative_to(old)
            except ValueError:
                pass  # rel no esta debajo de "old"

        if should_skip(rel):
            continue

        dst = DIST / rel_target
        if src.is_dir():
            dst.mkdir(parents=True, exist_ok=True)
        elif src.is_file():
            dst.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(src, dst)
            files_copied += 1
            bytes_copied += src.stat().st_size

    return files_copied, bytes_copied


def run(cmd, cwd=None, check=True):
    result = subprocess.run(cmd, cwd=cwd, capture_output=True, text=True, shell=False)
    if check and result.returncode != 0:
        print(f"ERROR ({' '.join(cmd)}):")
        print(result.stderr)
        sys.exit(1)
    return result


def init_git():
    print(f"\n[*] Inicializando git en {DIST}")
    run(["git", "init", "-q", "-b", "main"], cwd=DIST)
    run(["git", "config", "user.email", "you@example.com"], cwd=DIST)
    run(["git", "config", "user.name", "CryptoVulnX"], cwd=DIST)

    print("[*] git add -A")
    run(["git", "add", "-A"], cwd=DIST)

    print("[*] git commit")
    msg = (
        "initial commit: CryptoVulnX lab completo\n"
        "\n"
        "- Plataforma vulnerable de trading crypto para entrenamiento de pentest API\n"
        "- 14 LABs: 4 de metodologia (recon/inventory/endpoints/params) + 10 OWASP API Top 10\n"
        "- Docker compose listo para levantar con un comando\n"
        "- Wordlists custom para fuzzing dirigido al dominio del lab\n"
        "- Playbook gamificado en /playbook.php\n"
        "- Documento Word con resolucion paso a paso\n"
    )
    run(["git", "commit", "-q", "-m", msg], cwd=DIST)


def show_status():
    print("\n" + "=" * 70)
    print(" RESULTADO")
    print("=" * 70)

    print(f"\n[+] Directorio: {DIST}")
    print(f"[+] Tamano:     {sum(f.stat().st_size for f in DIST.rglob('*') if f.is_file()) // 1024} KB")

    n_files = sum(1 for _ in DIST.rglob('*') if _.is_file())
    print(f"[+] Archivos:   {n_files}")

    print("\n--- git log ---")
    print(run(["git", "log", "--oneline"], cwd=DIST, check=False).stdout)

    print("--- git status ---")
    print(run(["git", "status"], cwd=DIST, check=False).stdout)

    print("--- estructura raiz ---")
    for item in sorted(DIST.iterdir()):
        marker = "/" if item.is_dir() else ""
        size = item.stat().st_size if item.is_file() else 0
        print(f"  {item.name}{marker}" + (f"  ({size} B)" if size else ""))

    print("\n" + "=" * 70)
    print(" PROXIMOS PASOS")
    print("=" * 70)
    print(f"""
1. Crear el repo en GitHub (publico o privado):
     gh repo create cryptovulnx --public --description "OWASP API Top 10 lab" --source={DIST} --push

   O manualmente:
     a) En github.com -> New repository -> name=cryptovulnx
     b) Sin README, sin .gitignore, sin license (ya los tenemos)
     c) Ejecutar:
          cd {DIST}
          git remote add origin https://github.com/<TU_USUARIO>/cryptovulnx.git
          git push -u origin main

2. Verificar despues del push:
     - El repo debe mostrar el README con el quick-start.
     - Settings -> Secrets: NO hay nada (todos los secretos del lab son intencionales).
     - GitHub Secret Scanning va a alertar sobre AWS_ACCESS_KEY_ID, JWT_SECRET, etc.
       Esos son valores ficticios del lab. Podes ignorarlos o crear allowlist.

3. Compartir:
     - URL del repo
     - docker compose up -d
     - http://localhost:8080
""")


if __name__ == "__main__":
    print(f"[*] Source: {ROOT}")
    print(f"[*] Dest:   {DIST}")

    n_files, n_bytes = copy_tree()
    print(f"[+] Copiados {n_files} archivos ({n_bytes // 1024} KB)")

    init_git()
    show_status()
