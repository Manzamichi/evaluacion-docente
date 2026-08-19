# evaluacion_docente

Sistema de evaluacion docente - Servicio Social

## Requisitos

- Docker y Docker Compose
- Composer (opcional, solo si se agregan dependencias)

## Instalación con Docker (recomendado)

1. Clona el repositorio
   ```bash
   git clone <url-del-repo>
   cd evaluacion-docente
   ```

2. Levanta los contenedores (app + MySQL + phpMyAdmin)
   ```bash
   docker compose up --build -d
   ```
   La primera vez crea la base de datos y ejecuta `database/schema.sql` y `database/seed.sql` automáticamente. No hace falta crear `.env` ni correr `mysql` a mano: las credenciales ya están definidas en `docker-compose.yml`.

3. Abre `http://localhost:8082` (la app) y `http://localhost:8081` (phpMyAdmin, usuario `root` / contraseña `root_password`)

> Si tu máquina ya tiene un MySQL nativo corriendo en el puerto 3306 (fuera de Docker), ignóralo para este proyecto: las credenciales `app_user`/`app_password` y `root`/`root_password` son solo del contenedor `db`, y ese MySQL nativo te va a responder "Access denied" porque no las conoce. Para entrar directo al MySQL del contenedor sin pasar por el puerto del host:
> ```bash
> docker exec -it evaluacion_docente_db mysql -uapp_user -papp_password evaluacion_docente
> ```

Para bajar los contenedores: `docker compose down` (agrega `-v` solo si quieres borrar también los datos de la base).

## Instalación manual (sin Docker)

Requiere PHP >= 8.5.0 y un MariaDB/MySQL propio instalado y corriendo.

1. Copia el archivo de variables de entorno y edítalo con las credenciales de **tu** MySQL local
   ```bash
   cp .env.example .env
   ```
2. Importa el esquema
   ```bash
   mysql -u root -p evaluacion_docente < database/schema.sql
   mysql -u root -p evaluacion_docente < database/seed.sql
   ```
3. Levanta el servidor de desarrollo
   ```bash
   php -S localhost:8080 -t public
   ```
4. Abre `http://localhost:8080`

## Estructura del proyecto

```
evaluacion-docente/
├── public/       # Document root (frontend + entry point)
├── src/          # Lógica backend (fuera del document root)
├── database/     # Esquema y migraciones
├── tests/        # Pruebas
└── docs/         # Documentación
```

## Licencia

MIT

## Autor

Carlos Manzanero, Jose Murcia
