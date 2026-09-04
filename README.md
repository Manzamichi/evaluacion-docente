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

> **Si ya tenías la base creada de antes**, el esquema cambió: `usuarios.rol` se
> reemplazó por grupos y módulos. Corre la migración una vez; conserva el acceso
> de cada usuario pasándolo al grupo que corresponde a su rol anterior.
> ```bash
> docker exec -i evaluacion_docente_db mysql -uapp_user -papp_password \
>     evaluacion_docente < database/migrations/2026-08-25_grupos_modulos.sql
> docker exec -i evaluacion_docente_db mysql -uapp_user -papp_password \
>     evaluacion_docente < database/seed.sql
> ```

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
│   ├── index.php          # Front controller: única puerta de entrada
│   ├── login.php
│   └── assets/
├── src/
│   ├── modules.php        # Registro de módulos: qué código ejecuta cada url
│   ├── tables.php         # Configuración de las tablas del CRUD
│   ├── crud.php           # Operaciones de base de datos y helpers
│   ├── modules/           # Un archivo por módulo que no sea un CRUD simple
│   ├── components/        # Piezas de interfaz reutilizables
│   ├── helpers/           # Sesión, permisos, validación
│   └── config/            # Conexión PDO
├── database/              # Esquema, seed y migraciones
├── tests/                 # Pruebas
└── docs/                  # Documentación
```

## Cómo funciona el control de acceso

```
usuarios  --N:N-->  grupos  --N:N-->  modulos
```

- **Módulo**: una pantalla. Su columna `url` (`grupo/admin`) es la llave.
- **Grupo**: un rol. Un usuario puede tener varios (profesor *y* admin).
  Un grupo con `es_admin = 1` ve todos los módulos sin asignárselos uno por uno.

Tres fuentes, cada una con su responsabilidad:

| Dónde | Qué decide |
|---|---|
| `src/modules.php` | **Qué código se ejecuta.** Es la whitelist. |
| Tabla `modulos` | Qué módulos existen, cómo se llaman y en qué categoría del menú van. |
| Tabla `grupos` + `grupo_modulo` | Quién los ve. |

Una `url` que solo esté en la base de datos no se puede abrir ni aparece en el
menú: **nunca se arma la ruta de un `require` con datos de la base**. Los
permisos se consultan en cada petición, así que quitarle un módulo a un grupo
surte efecto de inmediato, no al siguiente login.

Los permisos se editan desde el módulo **Grupos > Permisos**, y los miembros
desde **Grupos > Usuarios**.

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

3. Regístrala como módulo en `src/modules.php`:

   ```php
   'materia/admin' => ['crud' => 'materias'],
   ```

4. Insértala en la tabla `modulos` con esa misma url, y asígnala a los grupos
   que deban verla:

   ```sql
   INSERT INTO modulos (nombre, url, categoria, orden)
   VALUES ('Materias', 'materia/admin', 'Catálogos', 10);
   ```

Aparece en el menú con su listado, buscador, alta, edición y borrado. No hay que
escribir una línea de HTML.

Tipos de campo disponibles: `text`, `email`, `number`, `date`, `textarea`,
`select` (con `opciones`) y `password` (con `hash => true`).

El listado se pagina solo: 10 filas por página, o las que diga `por_pagina` en
la tabla. La búsqueda y la paginación se combinan — el conteo de páginas se hace
sobre los resultados filtrados, y buscar de nuevo vuelve a la página 1.

Cada fila trae un botón de lupa que abre un panel lateral con **todas** las
columnas que la tabla tiene en la base, no solo las de `listar`. Se abre con
`:target` de CSS: sin JavaScript y sin una petición extra, porque el listado ya
las consultó.

> **Una columna sensible se declara, o se muestra.** Las que llevan
> `hash => true` quedan fuera solas; para el resto (tokens, CURP, RFC) está
> `'ocultar' => ['columna']` a nivel de tabla. La exclusión ocurre en el
> `SELECT`, no en la plantilla: lo que no sale de la base no se puede filtrar
> por una vista mal escrita.

Con `acciones` se agregan enlaces por fila hacia otro módulo, que reciben `?id=`:

```php
'acciones' => [
    ['etiqueta' => 'Permisos', 'modulo' => 'grupo/permisos'],
],
```

El enlace solo se pinta si el usuario tiene permiso sobre ese módulo.

## Agregar un módulo que no sea un CRUD

Cuando la pantalla no es "listar y editar una tabla" (un reporte, el instrumento
de evaluación), el módulo apunta a un archivo de `src/modules/`:

```php
// src/modules.php
'reporte/general' => ['archivo' => 'reporte_general.php', 'volver' => 'reporte/general'],
```

El archivo **solo imprime su contenido**: no abre ni cierra la página. El front
controller lo envuelve en el layout, así que puede redirigir tras un POST y
puede fijar `$titulo`.

```php
<?php
// src/modules/reporte_general.php

declare(strict_types=1);

/** @var string $m */

$pdo = getDbConnection();

// 1. Procesar el POST, si lo hay. Siempre validar el token primero.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();
    // ... guardar, luego redirigir para que F5 no reenvíe el formulario
    header('Location: ' . urlModulo($m));
    exit;
}

// 2. Consultar lo que la pantalla necesita. Siempre con prepare/execute.
$stmt = $pdo->prepare('SELECT g.nombre, COUNT(*) AS total FROM usuario_grupo ug
                       JOIN grupos g ON g.id = ug.grupo_id GROUP BY g.nombre');
$stmt->execute();
$filas = $stmt->fetchAll();

// 3. Imprimir, apoyándose en los componentes que ya existan.
$titulo = 'Reporte general';
?>

<h1>Reporte general</h1>

<ul>
    <?php foreach ($filas as $fila): ?>
        <li><?= e($fila['nombre']) ?>: <?= (int) $fila['total'] ?></li>
    <?php endforeach; ?>
</ul>
```

Falta insertarlo en la tabla `modulos` y asignarlo a un grupo para que aparezca.

## Componentes

`src/components/` guarda las piezas de interfaz. Un componente **recibe props y
solo imprime**; no consulta la base de datos ni lee `$_GET`. Eso lo hace el
módulo que lo llama.

```php
componente('tabla', [
    'm'       => $m,
    'cfg'     => $cfg,
    'filas'   => $filas,
    'filtros' => $filtros,
]);
```

Los props llegan como variables sueltas dentro del componente, como los
`@Input` de Angular. El componente **no ve ninguna otra variable**: el `require`
va dentro de una función, así que el scope de afuera queda fuera. Si a un
componente le falta un dato, se le pasa como prop; nunca se apoya en una
variable que "ya andaba por ahí".

| Componente | Qué pinta |
|---|---|
| `layout_inicio` / `layout_fin` | La página: `<head>`, barra superior, menú lateral |
| `menu` | Los módulos que el usuario puede abrir, por categoría |
| `tabla` | Listado con buscador por columna y acciones por fila |
| `paginacion` | Pie del listado: cuántos registros se ven y los enlaces de página |
| `detalle` | Panel lateral con el registro completo, abierto con `:target` |
| `formulario` | Alta y edición; delega cada campo a `campo` |
| `campo` | Un input, según el `tipo` de `tables.php` |
| `asignador` | Una relación N:N como lista de casillas |

### Reglas que no se rompen

- **Todo lo que salga a HTML pasa por `e()`.** Sin excepciones, aunque el dato
  venga de la base de datos.
- **Todo POST llama a `verificaCsrf()`** antes de tocar nada, y el formulario
  incluye `<input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">`.
- **Nunca concatenar variables dentro del SQL.** Valores con `?` y
  `prepare()/execute()`. Nombres de tabla o columna que vengan del request, solo
  a través de la whitelist de `tables.php`.
- **Nunca armar la ruta de un `require` con datos del request o de la base.**
  El archivo de un módulo sale de `modules.php`, que es código.
- **Toda pantalla nueva pasa por `requerirModulo()`**, y eso lo hace solo el
  front controller: no hay más puertas de entrada que `public/index.php`.
- **Después de un POST exitoso, redirigir** (patrón POST-Redirect-GET) para que
  recargar no duplique el registro.
- La lógica va arriba, el HTML abajo. Un componente solo imprime: si necesita
  consultar la base de datos, esa consulta está en el archivo equivocado.

### CSS y JS

`style.css` y `app.js` son globales y se cargan en todas las páginas. Lo que
solo use una pantalla va en su propio archivo, declarado con `$css` / `$js` al
llamar a `layout_inicio` / `layout_fin`. Sin build, sin bundler: son `<link>` y
`<script>` normales.

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
