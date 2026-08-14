# evaluacion_docente

Sistema de evaluacion docente - Servicio Social

## Requisitos

- PHP >= 8.5.0
- MariaDB o PostgreSQL
- Composer (opcional, solo si se agregan dependencias)

## Instalación local

1. Clona el repositorio
   ```bash
   git clone <url-del-repo>
   cd evaluacion docente
   ```

2. Copia el archivo de variables de entorno
   ```bash
   cp .env.example .env
   ```

3. Edita `.env` con tus credenciales de base de datos

4. Importa el esquema
   ```bash
   mysql -u root -p evaluacion docente < database/schema.sql
   ```

5. Levanta el servidor de desarrollo
   ```bash
   php -S localhost:8080 -t public
   ```

6. Abre `http://localhost:8080`

## Estructura del proyecto

```
evaluacion docente/
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
