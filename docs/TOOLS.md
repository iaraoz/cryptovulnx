# Tools - CryptoVulnX

## Por fase

### Fase 1 - RECON

| Tool | Comando ejemplo | Que hace |
|---|---|---|
| `curl` | `curl -s $T/robots.txt` | Recon manual rapido |
| `ffuf` | `ffuf -w files.txt -u $T/FUZZ -mc 200` | Brute de files |
| `gobuster dir` | `gobuster dir -u $T -w files.txt` | Idem |
| `feroxbuster` | `feroxbuster -u $T -w files.txt -d 3` | Brute recursivo |
| `dirsearch` | `dirsearch -u $T -e php,json,sql,bak` | Multi-extension |
| `git-dumper` | `git-dumper $T/.git ./out/` | Recupera codigo desde .git/ expuesto |
| `nuclei` | `nuclei -t exposures/ -u $T` | Templates de exposicion |
| `wget --mirror` | `wget --mirror -np $T` | Espejar sitio para grep |
| `dotenv-recover` | manual | Identificar `.env` files via wordlists |

### Fase 2 - API INVENTORY

| Tool | Comando ejemplo | Que hace |
|---|---|---|
| `kiterunner` | `kr scan $T -w routes-large.kite` | API-focused brute |
| `ffuf` | `ffuf -w versions.txt -u $T/api/FUZZ/auth/login.php -mc 200,401,405` | Brute de versiones |
| `swagger-ui` local | servir local | Visualizar specs encontrados |
| `arjun` | `arjun --get -u $T/api/swagger.json` | Detectar params en specs |
| `Postman` | importar `postman_collection.json` | Replay de colecciones |
| `curl + jq` | `curl $T/api/swagger.json | jq '.paths | keys'` | Comparar specs |

### Fase 3 - ENDPOINT FUZZING

| Tool | Comando ejemplo | Que hace |
|---|---|---|
| `feroxbuster` | `feroxbuster -u $T/api/v1/ -w api-endpoints.txt -d 3 -x php` | Recursivo bajo /api/v1/ |
| `ffuf` | `ffuf -w endpoints.txt -u $T/api/v1/admin/FUZZ.php` | Targeted |
| `kiterunner` | `kr brute $T -w endpoints.txt` | API-aware |
| `httpx` | `cat urls.txt \| httpx -mc 200,401` | Filtrar URLs validas |
| `Burp Discover Content` | extension Burp | Brute interactive |
| Verb fuzzing | `for V in GET POST PUT DELETE; do curl -X $V ...; done` | Method enumeration |

### Fase 4 - PARAMETER FUZZING

| Tool | Comando ejemplo | Que hace |
|---|---|---|
| `arjun` | `arjun -u $T/endpoint --get` | Query params |
| `arjun` | `arjun -u $T/endpoint -m POST -d '{}'` | Body JSON params |
| `Param Miner` (Burp) | Right-click -> Guess headers | Headers ocultos |
| `x8` | `x8 -u $T -w params.txt` | Param mining (Rust) |
| `ffuf -H "FUZZ: 1"` | `ffuf -w headers.txt -H "FUZZ: 1" -u $T/...` | Headers manuales |
| `ffuf -b "FUZZ=1"` | `ffuf -w params.txt -b "FUZZ=1" -u $T/...` | Cookies manuales |
| `wfuzz` | `wfuzz -z file,params.txt -d 'FUZZ=1' $T/...` | Multi-payload |

### Fase 5 - EXPLOITATION

| Tool | Para que |
|---|---|
| `Burp Suite` | Proxy + Intruder + Repeater |
| `sqlmap` | SQLi automatico |
| `jwt-tool` | Forjar JWT (alg=none, weak secret) |
| `hashcat` | Crackear `password_hash` |
| `john` | Idem |
| `nuclei -t cves/` | Explotacion de CVEs en libs |
| Scripts Python custom | Chains complejos |

---

## Setup en Kali / WSL

```bash
# Esenciales
sudo apt update
sudo apt install ffuf gobuster feroxbuster dirsearch sqlmap nuclei

# Pip
pip install arjun git-dumper jwt-tool

# Go
go install github.com/projectdiscovery/httpx/cmd/httpx@latest
go install github.com/assetnote/kiterunner/cmd/kr@latest

# Burp Suite Community: https://portswigger.net/burp/communitydownload
# Postman: https://www.postman.com/downloads/
```

## Setup en Docker (alternativa)

```bash
docker run --rm -it \
    -v $(pwd):/work \
    --network host \
    secsi/ffuf -w /work/wordlist.txt -u http://localhost:8080/FUZZ
```
