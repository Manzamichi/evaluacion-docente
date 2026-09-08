<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Módulos: el registro de código (src/modules.php), los permisos del usuario en
 * sesión y el menú que sale de cruzar ambos con la tabla `modulos`.
 *
 * Reparto de responsabilidades:
 *   src/modules.php   qué código ejecuta cada url. Es la whitelist.
 *   tabla `modulos`   qué módulos existen, cómo se llaman y en qué categoría.
 *   tabla `grupos`    quién los ve.
 *
 * Una url que solo esté en la base no se puede abrir ni aparece en el menú.
 */

/**
 * Registro de módulos declarado en src/modules.php.
 */
function modulos(): array
{
    static $modulos = null;

    return $modulos ??= require __DIR__ . '/../modules.php';
}

/**
 * Configuración de un módulo. Es también la whitelist: una url que no esté
 * declarada no se puede abrir desde el navegador.
 *
 * @throws InvalidArgumentException
 */
function moduloConfig(string $url): array
{
    $modulos = modulos();

    if (!isset($modulos[$url])) {
        throw new InvalidArgumentException("Módulo no permitido: {$url}");
    }

    return $modulos[$url];
}

/**
 * Acceso del usuario en sesión, resuelto una vez por petición.
 *
 * Se consulta en cada petición en vez de guardarse en la sesión: quitarle un
 * permiso a un grupo surte efecto de inmediato y no al siguiente login.
 *
 * @return array{admin: bool, urls: string[]}
 */
function accesoActual(): array
{
    static $acceso = null;

    if ($acceso !== null) {
        return $acceso;
    }

    $usuarioId = (int) ($_SESSION['usuario']['id'] ?? 0);

    if ($usuarioId === 0) {
        return $acceso = ['admin' => false, 'urls' => []];
    }

    $pdo = getDbConnection();

    $stmt = $pdo->prepare(
        'SELECT MAX(g.es_admin) FROM usuario_grupo ug
         JOIN grupos g ON g.id = ug.grupo_id
         WHERE ug.usuario_id = ?'
    );
    $stmt->execute([$usuarioId]);
    $admin = (int) $stmt->fetchColumn() === 1;

    $stmt = $pdo->prepare(
        'SELECT DISTINCT m.url FROM usuario_grupo ug
         JOIN grupo_modulo gm ON gm.grupo_id = ug.grupo_id
         JOIN modulos m ON m.id = gm.modulo_id
         WHERE ug.usuario_id = ?'
    );
    $stmt->execute([$usuarioId]);

    return $acceso = [
        'admin' => $admin,
        'urls'  => $stmt->fetchAll(PDO::FETCH_COLUMN),
    ];
}

/**
 * Nombres de los grupos del usuario en sesión, para mostrarlos en la cabecera.
 *
 * @return string[]
 */
function gruposActuales(): array
{
    static $grupos = null;

    if ($grupos !== null) {
        return $grupos;
    }

    $usuarioId = (int) ($_SESSION['usuario']['id'] ?? 0);

    if ($usuarioId === 0) {
        return $grupos = [];
    }

    $stmt = getDbConnection()->prepare(
        'SELECT g.nombre FROM usuario_grupo ug
         JOIN grupos g ON g.id = ug.grupo_id
         WHERE ug.usuario_id = ? ORDER BY g.nombre'
    );
    $stmt->execute([$usuarioId]);

    return $grupos = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * ¿El usuario en sesión puede abrir este módulo? Un grupo con es_admin = 1 los
 * puede abrir todos sin necesidad de que se los asignen uno por uno.
 */
function puede(string $url): bool
{
    $acceso = accesoActual();

    return $acceso['admin'] || in_array($url, $acceso['urls'], true);
}

/**
 * Acciones de un módulo: las que no dependen de ningún registro y por eso
 * cuelgan de él en el menú lateral en vez de repetirse en cada fila de la tabla.
 *
 * Salen de la clave 'acciones' de src/modules.php. Una acción con 'params' va al
 * mismo módulo; una con 'modulo' va a otro y se descarta si el usuario no tiene
 * permiso sobre él, igual que los enlaces por registro del listado.
 *
 * @return array<int, array{etiqueta: string, href: string}>
 */
function accionesDeModulo(string $url): array
{
    $acciones = [];

    foreach (modulos()[$url]['acciones'] ?? [] as $accion) {
        $destino = $accion['modulo'] ?? $url;

        if (isset($accion['modulo']) && !puede($destino)) {
            continue;
        }

        $acciones[] = [
            'etiqueta' => (string) $accion['etiqueta'],
            'href'     => urlModulo($destino, $accion['params'] ?? []),
        ];
    }

    return $acciones;
}

/**
 * Módulos que el usuario puede ver, agrupados por categoría y en el orden de la
 * columna `orden`. Se descartan los que no tienen código registrado (enlaces
 * rotos) y los marcados 'oculto' (necesitan un ?id= para tener sentido).
 *
 * @return array<string, array<int, array{nombre: string, url: string, acciones: array}>>
 */
function menuActual(): array
{
    $registro = modulos();
    $menu     = [];

    $filas = getDbConnection()
        ->query('SELECT nombre, url, categoria FROM modulos ORDER BY orden, nombre')
        ->fetchAll();

    foreach ($filas as $fila) {
        $url = (string) $fila['url'];

        if (!isset($registro[$url]) || !empty($registro[$url]['oculto']) || !puede($url)) {
            continue;
        }

        $menu[(string) $fila['categoria']][] = [
            'nombre'   => (string) $fila['nombre'],
            'url'      => $url,
            'acciones' => accionesDeModulo($url),
        ];
    }

    return $menu;
}

/**
 * Primer módulo que el usuario puede abrir. Es el destino por defecto: a dónde
 * llega alguien que entra a la raíz del sitio.
 */
function moduloInicial(): ?string
{
    foreach (menuActual() as $modulosCategoria) {
        foreach ($modulosCategoria as $modulo) {
            return $modulo['url'];
        }
    }

    return null;
}

/**
 * Enlace a un módulo, con los parámetros que reciba.
 */
function urlModulo(string $url, array $params = []): string
{
    // La diagonal de "grupo/admin" no se codifica: es legal en un query string
    // y deja la barra de direcciones legible.
    return '?' . str_replace('%2F', '/', http_build_query(['m' => $url] + $params));
}
