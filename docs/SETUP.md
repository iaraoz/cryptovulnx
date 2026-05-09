# CryptoVulnX - Guia de Instalacion

## Opcion 1: Docker Compose (Recomendado)

### Requisitos
- Docker Desktop instalado

### Pasos
```bash
# 1. Clonar o copiar el proyecto
cd cryptovulnx

# 2. Levantar los servicios
docker-compose up -d

# 3. Esperar a que MySQL inicie (~15 segundos)
docker-compose logs -f db  # Esperar "ready for connections"

# 4. Acceder
# Frontend: http://localhost:8080
# API: http://localhost:8080/api/v1/
```

### Comandos utiles
```bash
# Ver logs
docker-compose logs -f

# Reiniciar desde cero (borra datos)
docker-compose down -v
docker-compose up -d

# Entrar al contenedor web
docker exec -it cryptovulnx-web bash

# Entrar a MySQL
docker exec -it cryptovulnx-db mysql -ucrypto -pcrypto123 cryptovulnx
```

---

## Opcion 2: XAMPP (Local)

### Requisitos
- XAMPP con PHP 8.x y MySQL
- Extensiones PHP: mysqli, curl, json

### Pasos

1. **Copiar el proyecto** a `C:\xampp\htdocs\appsec\`

2. **Crear la base de datos:**
   - Abrir phpMyAdmin (http://localhost/phpmyadmin)
   - Crear base de datos `cryptovulnx`
   - Importar `database/init.sql`

   O por consola:
   ```bash
   mysql -u root < database/init.sql
   ```

3. **Configurar conexion a DB:**
   - Editar `api/config/database.php`
   - Ajustar `DB_USER` y `DB_PASS` segun tu configuracion de XAMPP
   - Por defecto: user=root, password="" (vacio)

4. **Habilitar mod_rewrite en Apache:**
   - Verificar que `LoadModule rewrite_module` este descomentado en `httpd.conf`
   - Verificar que `AllowOverride All` este configurado

5. **Habilitar extensiones PHP:**
   - Verificar que `extension=curl` y `extension=mysqli` esten descomentadas en `php.ini`

6. **Acceder:**
   - Frontend: http://localhost/appsec/frontend/
   - API: http://localhost/appsec/api/v1/

---

## Verificar Instalacion

1. Abrir http://localhost/appsec/api/v1/crypto/prices.php
   - Deberia mostrar JSON con precios de criptomonedas

2. Probar login:
   ```bash
   curl -X POST http://localhost/appsec/api/v1/auth/login.php \
     -H "Content-Type: application/json" \
     -d '{"username":"carlos","password":"carlos2024"}'
   ```
   - Deberia devolver un token JWT

3. Probar debug (sin auth):
   ```bash
   curl http://localhost/appsec/api/v1/admin/debug.php?action=info
   ```
   - Deberia exponer info del servidor (esto es una vulnerabilidad intencional)

---

## Herramientas Recomendadas para Pentesting

- **Burp Suite Community** - Proxy de interceptacion HTTP
- **Postman** - Cliente de API
- **curl** - Linea de comandos
- **jwt.io** - Decodificar/editar tokens JWT
- **SQLMap** - Testing automatizado de SQL injection
- **ffuf** - Fuzzing de directorios y parametros
- **Python + requests** - Scripts de explotacion personalizados

---

## Troubleshooting

### "Database connection failed"
- Verificar que MySQL/MariaDB este corriendo
- Verificar credenciales en `api/config/database.php`
- Verificar que la base de datos `cryptovulnx` exista

### "404 Not Found"
- Verificar que mod_rewrite este habilitado
- Verificar que el proyecto este en la ruta correcta

### Docker: "port already in use"
- Cambiar el puerto en `docker-compose.yml` (ej: 8081:80)
- O detener el servicio que usa el puerto

### CORS errors en el navegador
- Esto es normal - la API tiene CORS permisivo por diseno
- Si tienes problemas, verifica que la URL base en `frontend/js/api.js` sea correcta
