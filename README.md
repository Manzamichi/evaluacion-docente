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
├── public/
│   ├── index.php        # Front controller: enruta y procesa los formularios
│   └── assets/
├── src/
│   ├── tables.php       # Configuración de las tablas del CRUD
│   ├── crud.php         # Operaciones de base de datos y helpers
│   ├── config/          # Conexión PDO
│   └── views/crud.php   # Listado y formulario
├── database/            # Esquema y migraciones
├── tests/               # Pruebas
└── docs/                # Documentación
```

## Agregar una tabla al CRUD

1. Crea la tabla en `database/schema.sql` (necesita una columna `id` autoincremental).
2. Agrega una entrada al array de `src/tables.php`:

   ```php
   'materias' => [
       'etiqueta' => 'Materias',
       'listar'   => ['id', 'clave', 'nombre'],
       'campos'   => [
           'clave'  => ['etiqueta' => 'Clave',  'tipo' => 'text', 'requerido' => true],
           'nombre' => ['etiqueta' => 'Nombre', 'tipo' => 'text', 'requerido' => true],
       ],
   ],
   ```

Aparece sola en el menú, con su listado, alta, edición y borrado. No hay que
tocar ningún otro archivo.

Tipos de campo disponibles: `text`, `email`, `number`, `date`, `textarea`,
`select` (con `opciones`) y `password` (con `hash => true`).

## Agregar una vista

Una página = un archivo en `public/`. El `.htaccess` sirve el archivo si existe,
así que `public/reportes.php` queda en `http://localhost:8080/reportes.php` sin
tocar ningún router.

Cada página sigue el mismo orden: primero la lógica, al final el HTML.

```php
<?php
// public/reportes.php

declare(strict_types=1);

require_once __DIR__ . '/../src/crud.php';

session_start();

$pdo = getDbConnection();

// 1. Procesar el POST, si lo hay. Siempre validar el token primero.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();
    // ... guardar, luego redirigir para que F5 no reenvíe el formulario
    header('Location: reportes.php');
    exit;
}

// 2. Consultar lo que la vista necesita. Siempre con prepare/execute.
$stmt = $pdo->prepare('SELECT rol, COUNT(*) AS total FROM usuarios GROUP BY rol');
$stmt->execute();
$filas = $stmt->fetchAll();

// 3. Entregar a la vista.
require __DIR__ . '/../src/views/reportes.php';
```

```php
<?php
// src/views/reportes.php

declare(strict_types=1);

/** @var array $filas */

$titulo = 'Reportes';
$activo = 'reportes.php';   // resalta el enlace en el menú
$css    = ['reportes.css']; // opcional, public/assets/css/reportes.css
$js     = ['reportes.js'];  // opcional, public/assets/js/reportes.js

require __DIR__ . '/_header.php';
?>

<h1>Reportes</h1>

<ul>
    <?php foreach ($filas as $fila): ?>
        <li><?= e($fila['rol']) ?>: <?= (int) $fila['total'] ?></li>
    <?php endforeach; ?>
</ul>

<?php require __DIR__ . '/_footer.php'; ?>
```

Para que aparezca en el menú, agrégala al array `$navegacion` de
`src/views/_header.php`.

### Reglas que no se rompen

- **Todo lo que salga a HTML pasa por `e()`.** Sin excepciones, aunque el dato
  venga de la base de datos.
- **Todo POST llama a `verificaCsrf()`** antes de tocar nada, y el formulario
  incluye `<input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">`.
- **Nunca concatenar variables dentro del SQL.** Valores con `?` y
  `prepare()/execute()`. Nombres de tabla o columna que vengan del request, solo
  a través de la whitelist de `tables.php`.
- **Después de un POST exitoso, redirigir** (patrón POST-Redirect-GET) para que
  recargar no duplique el registro.
- La lógica va arriba del `require` de la vista; la vista solo imprime. Si una
  vista necesita consultar la base de datos, esa consulta está en el archivo
  equivocado.

### CSS y JS

`style.css` y `app.js` son globales y se cargan en todas las páginas. Lo que
solo use una página va en su propio archivo, declarado con `$css` / `$js` en la
vista. Sin build, sin bundler: son `<link>` y `<script>` normales.

Si el JS de una página crece y necesita compartir código, usa módulos nativos
(`<script type="module">` con `import`), que funcionan en el navegador sin
herramientas.

## Pruebas

```bash
php tests/test_crud.php
```

## Licencia

MIT

## Autor

Carlos Manzanero, Jose Murcia
