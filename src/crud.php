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

function listar(PDO $pdo, string $tabla, array $filtros = []): array
{
    $cfg   = tablaConfig($tabla);
    $cols  = implode(', ', array_map('ident', $cfg['listar']));
    $where = clausulaWhere(filtrosDesde($cfg, $filtros));

    // ponytail: sin paginación; agregar LIMIT/OFFSET cuando una tabla pase de unos cientos de filas
    $stmt = $pdo->prepare(
        'SELECT ' . $cols . ' FROM ' . ident($tabla) . $where['sql'] . ' ORDER BY id DESC'
    );
    $stmt->execute($where['valores']);

    return $stmt->fetchAll();
}

function obtener(PDO $pdo, string $tabla, int $id): ?array
{
    tablaConfig($tabla);

    $stmt = $pdo->prepare('SELECT * FROM ' . ident($tabla) . ' WHERE id = ?');
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

function crear(PDO $pdo, string $tabla, array $datos): void
{
    tablaConfig($tabla);

    $cols         = array_map('ident', array_keys($datos));
    $marcadores   = array_fill(0, count($datos), '?');

    $sql = 'INSERT INTO ' . ident($tabla)
        . ' (' . implode(', ', $cols) . ')'
        . ' VALUES (' . implode(', ', $marcadores) . ')';

    $pdo->prepare($sql)->execute(array_values($datos));
}

function actualizar(PDO $pdo, string $tabla, int $id, array $datos): void
{
    tablaConfig($tabla);

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
