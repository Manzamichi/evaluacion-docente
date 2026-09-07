<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/validation.php';

/**
 * Devuelve la configuración de todas las tablas declaradas en tables.php.
 */
function tablas(): array
{
    static $tablas = null;

    return $tablas ??= require __DIR__ . '/tables.php';
}

/**
 * Configuración de una tabla. Es también la whitelist: una tabla que no esté
 * declarada en tables.php no se puede consultar desde la URL.
 */
function tablaConfig(string $tabla): array
{
    $tablas = tablas();

    if (!isset($tablas[$tabla])) {
        throw new InvalidArgumentException("Tabla no permitida: {$tabla}");
    }

    return $tablas[$tabla];
}

/**
 * Escapa un identificador SQL (tabla o columna).
 *
 * PDO no permite parametrizar identificadores, así que se interpolan. Todos
 * los identificadores salen de tables.php, nunca directo del request, y aquí
 * se valida el formato como segunda barrera.
 */
function ident(string $nombre): string
{
    if (preg_match('/^[a-zA-Z0-9_]+$/', $nombre) !== 1) {
        throw new InvalidArgumentException("Identificador inválido: {$nombre}");
    }

    return "`{$nombre}`";
}

/**
 * Filtra lo que llega en $_GET['f'] a las columnas que la tabla realmente
 * lista. Una columna que no esté en 'listar' se descarta en silencio, igual
 * que un valor vacío (un input en blanco no debe filtrar nada).
 */
function filtrosDesde(array $cfg, array $entrada): array
{
    $filtros = [];

    foreach ($cfg['listar'] as $col) {
        $valor = trim((string) ($entrada[$col] ?? ''));

        if ($valor !== '') {
            $filtros[$col] = $valor;
        }
    }

    return $filtros;
}

/**
 * Columnas que el detalle nunca muestra: las que se guardan con hash y las que
 * la tabla marque en 'ocultar'.
 *
 * @return string[]
 */
function columnasOcultas(array $cfg): array
{
    $ocultas = $cfg['ocultar'] ?? [];

    foreach ($cfg['campos'] ?? [] as $col => $campo) {
        if (!empty($campo['hash'])) {
            $ocultas[] = $col;
        }
    }

    return array_values(array_unique($ocultas));
}

/**
 * Columnas que la tabla tiene en la base, menos las ocultas.
 *
 * Es lo que pide el SELECT del listado: el panel de detalle muestra más
 * columnas de las que caben en la tabla, pero las sensibles no se filtran en la
 * plantilla, se quedan fuera de la consulta. Un hash que nunca sale de la base
 * no se puede escapar por una vista mal escrita.
 *
 * @return string[]
 */
function columnasDetalle(PDO $pdo, string $tabla, array $cfg): array
{
    static $cache = [];

    if (isset($cache[$tabla])) {
        return $cache[$tabla];
    }

    $columnas = $pdo->query('SHOW COLUMNS FROM ' . ident($tabla))->fetchAll(PDO::FETCH_COLUMN);

    return $cache[$tabla] = array_values(array_diff($columnas, columnasOcultas($cfg)));
}

/**
 * Arma el WHERE de una búsqueda. Devuelve ['sql' => ..., 'valores' => [...]].
 *
 * Los nombres de columna pasan por ident(); los valores van siempre como
 * marcadores. Los comodines de LIKE se escapan para que buscar "100%" o "a_b"
 * encuentre el texto literal y no cualquier cosa.
 */
function clausulaWhere(array $filtros): array
{
    if ($filtros === []) {
        return ['sql' => '', 'valores' => []];
    }

    $condiciones = [];
    $valores     = [];

    foreach ($filtros as $col => $valor) {
        $condiciones[] = ident($col) . " LIKE ? ESCAPE '\\\\'";
        $valores[]     = '%' . addcslashes($valor, '%_\\') . '%';
    }

    return [
        'sql'     => ' WHERE ' . implode(' AND ', $condiciones),
        'valores' => $valores,
    ];
}

/**
 * Filas por página cuando la tabla no define 'por_pagina' en tables.php.
 */
const FILAS_POR_PAGINA = 10;

/**
 * Listado de una tabla, filtrado y paginado.
 *
 * Devuelve un array con:
 *   filas      Las filas de la página pedida (ya ordenadas por id DESC)
 *   pagina     Número de página efectivo (recortado al rango válido: 1..paginas)
 *   paginas    Total de páginas (mínimo 1, aunque no haya resultados)
 *   total      Registros que coinciden con la búsqueda
 *   porPagina  Filas por página aplicado
 *
 * El COUNT lleva el mismo WHERE que el SELECT: contar la tabla entera daría
 * páginas de más al buscar, y la última saldría vacía.
 */
function listar(PDO $pdo, string $tabla, array $filtros = [], int $pagina = 1): array
{
    $cfg = tablaConfig($tabla);

    // Se piden las columnas del detalle, no solo las de 'listar': el panel las
    // necesita todas y así no hace falta una segunda consulta por fila.
    $cols  = implode(', ', array_map('ident', columnasDetalle($pdo, $tabla, $cfg)));
    $where = clausulaWhere(filtrosDesde($cfg, $filtros));

    $porPagina = max(1, (int) ($cfg['por_pagina'] ?? FILAS_POR_PAGINA));

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . ident($tabla) . $where['sql']);
    $stmt->execute($where['valores']);

    $total   = (int) $stmt->fetchColumn();
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pagina  = max(1, min($pagina, $paginas));
    $offset  = ($pagina - 1) * $porPagina;

    $stmt = $pdo->prepare(
        'SELECT ' . $cols . ' FROM ' . ident($tabla) . $where['sql']
        . ' ORDER BY id DESC LIMIT ? OFFSET ?'
    );

    // LIMIT/OFFSET no aceptan marcadores en modo emulado, así que se bindean
    // explícitamente como enteros (los valores ya son ints calculados aquí).
    // Al mezclarlos con los de la búsqueda hay que numerar a mano: los del
    // WHERE van primero y en el mismo orden en que se armaron.
    $posicion = 1;

    foreach ($where['valores'] as $valor) {
        $stmt->bindValue($posicion++, $valor);
    }

    $stmt->bindValue($posicion++, $porPagina, PDO::PARAM_INT);
    $stmt->bindValue($posicion, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'filas'     => $stmt->fetchAll(),
        'pagina'    => $pagina,
        'paginas'   => $paginas,
        'total'     => $total,
        'porPagina' => $porPagina,
    ];
}

/**
 * Números de página a mostrar: una ventana deslizante centrada en la actual.
 *
 * @return int[]
 */
function ventanaPaginas(int $pagina, int $paginas, int $ventana = 3): array
{
    $desde = max(1, min($pagina - intdiv($ventana, 2), $paginas - $ventana + 1));

    return range($desde, min($paginas, $desde + $ventana - 1));
}

function obtener(PDO $pdo, string $tabla, int $id): ?array
{
    tablaConfig($tabla);

    $stmt = $pdo->prepare('SELECT * FROM ' . ident($tabla) . ' WHERE id = ?');
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

/**
 * Nombre de usuario de la sesión, para la columna `editado_por`.
 *
 * Devuelve null cuando no hay sesión (seeder, script de consola): la columna
 * queda en NULL, que es justo lo que significa "no lo hizo nadie desde el
 * sistema". No se toma del request nunca; solo de la sesión.
 */
function usuarioActual(): ?string
{
    return isset($_SESSION['usuario']['usuario'])
        ? (string) $_SESSION['usuario']['usuario']
        : null;
}

function crear(PDO $pdo, string $tabla, array $datos): void
{
    tablaConfig($tabla);

    // Se pone aquí y no en saneaEntrada() para que ningún alta pueda saltárselo:
    // la carga masiva de CSV también pasa por esta función.
    $datos['editado_por'] = usuarioActual();

    $cols       = array_map('ident', array_keys($datos));
    $marcadores = array_fill(0, count($datos), '?');

    $sql = 'INSERT INTO ' . ident($tabla)
        . ' (' . implode(', ', $cols) . ')'
        . ' VALUES (' . implode(', ', $marcadores) . ')';

    $pdo->prepare($sql)->execute(array_values($datos));
}

function actualizar(PDO $pdo, string $tabla, int $id, array $datos): void
{
    tablaConfig($tabla);

    $datos['editado_por'] = usuarioActual();

    $asignaciones = implode(', ', array_map(
        static fn (string $col): string => ident($col) . ' = ?',
        array_keys($datos)
    ));

    $sql = 'UPDATE ' . ident($tabla) . ' SET ' . $asignaciones . ' WHERE id = ?';

    $pdo->prepare($sql)->execute([...array_values($datos), $id]);
}

function eliminar(PDO $pdo, string $tabla, int $id): void
{
    tablaConfig($tabla);

    // Un grupo administrador es la única puerta al módulo de seguridad: si se
    // borra el último, nadie queda con permiso para repartir permisos y el
    // sistema solo se recupera metiendo mano a la base.
    if ($tabla === 'grupos') {
        $stmt = $pdo->prepare('SELECT es_admin FROM grupos WHERE id = ?');
        $stmt->execute([$id]);

        if ((int) $stmt->fetchColumn() === 1) {
            throw new InvalidArgumentException('No se puede eliminar un grupo administrador.');
        }
    }

    $pdo->prepare('DELETE FROM ' . ident($tabla) . ' WHERE id = ?')->execute([$id]);
}

// --- Relaciones N:N (usuario_grupo, grupo_modulo) ---
//
// El CRUD de arriba trabaja sobre una tabla; estas tres funciones cubren las
// tablas puente, que no tienen id propio ni formulario: se editan como una
// lista de casillas ("estos módulos pertenecen a este grupo").

/**
 * Identificadores de un pivote, escapados. Salen de modules.php, nunca del
 * request; ident() es la segunda barrera, igual que en el resto del archivo.
 *
 * @return string[] [tabla, columna propia, columna ajena]
 */
function pivoteIdent(array $pivote): array
{
    return [
        ident($pivote['tabla']),
        ident($pivote['propia']),
        ident($pivote['ajena']),
    ];
}

/**
 * Ids del lado ajeno que ya están asignados a $id.
 *
 * @return int[]
 */
function pivoteAsignados(PDO $pdo, array $pivote, int $id): array
{
    [$tabla, $propia, $ajena] = pivoteIdent($pivote);

    $stmt = $pdo->prepare("SELECT {$ajena} FROM {$tabla} WHERE {$propia} = ?");
    $stmt->execute([$id]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Normaliza los ids que llegan del formulario: enteros, sin repetidos y sin
 * vacíos. Un id que no exista lo rechaza después la llave foránea.
 *
 * @return int[]
 */
function pivoteIds(array $ajenos): array
{
    return array_values(array_unique(array_filter(array_map('intval', $ajenos))));
}

/**
 * Deja la asignación de $id exactamente en $ajenos: borra e inserta dentro de
 * una transacción, para que un id inválido (rechazado por la llave foránea) no
 * deje al registro sin nada asignado.
 */
function pivoteGuardar(PDO $pdo, array $pivote, int $id, array $ajenos): void
{
    [$tabla, $propia, $ajena] = pivoteIdent($pivote);

    $ajenos = pivoteIds($ajenos);

    $pdo->beginTransaction();

    try {
        $pdo->prepare("DELETE FROM {$tabla} WHERE {$propia} = ?")->execute([$id]);

        if ($ajenos !== []) {
            $insert = $pdo->prepare("INSERT INTO {$tabla} ({$propia}, {$ajena}) VALUES (?, ?)");

            foreach ($ajenos as $ajenoId) {
                $insert->execute([$id, $ajenoId]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();

        throw $e;
    }
}

/**
 * Filas del lado ajeno de un pivote, para llenar la lista de selección.
 * Con 'agrupa' se ordena y se separa por esa columna (las categorías del menú).
 */
function opcionesDe(PDO $pdo, array $destino): array
{
    $cols  = ['id', $destino['muestra']];
    $orden = [ident($destino['muestra'])];

    if (isset($destino['agrupa'])) {
        $cols[]  = $destino['agrupa'];
        $orden[] = ident($destino['agrupa']);
        $orden   = array_reverse($orden);
    }

    $sql = 'SELECT ' . implode(', ', array_map('ident', $cols))
        . ' FROM ' . ident($destino['tabla'])
        . ' ORDER BY ' . implode(', ', $orden);

    return $pdo->query($sql)->fetchAll();
}

/**
 * Aplica la función de validación declarada en 'patron' (definida en
 * helpers/validation.php). Sin 'patron' no hace nada.
 */
function validaPatron(array $campo, string $valor): void
{
    if (!isset($campo['patron'])) {
        return;
    }

    if (!is_callable($campo['patron'])) {
        throw new LogicException("Validador inexistente: {$campo['patron']}");
    }

    if (($campo['patron'])($valor) !== true) {
        throw new InvalidArgumentException("Formato inválido en {$campo['etiqueta']}.");
    }
}

/**
 * Filtra el request a las columnas declaradas en tables.php y valida cada una.
 * Cualquier campo que llegue por POST y no esté declarado se descarta.
 *
 * @throws InvalidArgumentException si algún valor no pasa la validación
 */
function saneaEntrada(array $cfg, array $entrada, bool $esNuevo): array
{
    $datos = [];

    foreach ($cfg['campos'] as $col => $campo) {
        $valor = trim((string) ($entrada[$col] ?? ''));

        if (!empty($campo['hash'])) {
            if ($valor === '') {
                if ($esNuevo) {
                    throw new InvalidArgumentException("{$campo['etiqueta']} es obligatorio.");
                }
                continue; // al editar, dejarlo vacío significa "no cambiar"
            }

            validaPatron($campo, $valor);

            $datos[$col] = password_hash($valor, PASSWORD_DEFAULT);
            continue;
        }

        if ($valor === '' && !empty($campo['requerido'])) {
            throw new InvalidArgumentException("{$campo['etiqueta']} es obligatorio.");
        }

        if (isset($campo['opciones']) && $valor !== '' && !in_array($valor, $campo['opciones'], true)) {
            throw new InvalidArgumentException("Valor inválido en {$campo['etiqueta']}.");
        }

        if ($valor !== '') {
            validaPatron($campo, $valor);
        }

        $datos[$col] = $valor === '' ? null : $valor;
    }

    return $datos;
}

/**
 * Escapa texto para insertarlo en HTML.
 */
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function verificaCsrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }
}
