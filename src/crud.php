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
 * Filas por página cuando la tabla no define 'por_pagina' en tables.php.
 */
const FILAS_POR_PAGINA = 10;

/**
 * Listado paginado de una tabla.
 *
 * Devuelve un array con:
 *   filas      Las filas de la página pedida (ya ordenadas por id DESC)
 *   pagina     Número de página efectivo (recortado al rango válido: 1..paginas)
 *   paginas    Total de páginas (mínimo 1, aunque la tabla esté vacía)
 *   total      Total de registros en la tabla
 *   porPagina  Filas por página aplicado
 */
function listar(PDO $pdo, string $tabla, int $pagina = 1): array
{
    $cfg  = tablaConfig($tabla);
    $cols = implode(', ', array_map('ident', $cfg['listar']));

    $porPagina = max(1, (int) ($cfg['por_pagina'] ?? FILAS_POR_PAGINA));

    $total   = (int) $pdo->query('SELECT COUNT(*) FROM ' . ident($tabla))->fetchColumn();
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pagina  = max(1, min($pagina, $paginas));
    $offset  = ($pagina - 1) * $porPagina;

    // LIMIT/OFFSET no aceptan marcadores en modo emulado, así que se bindean
    // explícitamente como enteros (los valores ya son ints calculados aquí).
    $stmt = $pdo->prepare(
        'SELECT ' . $cols . ' FROM ' . ident($tabla) . ' ORDER BY id DESC LIMIT ? OFFSET ?'
    );
    $stmt->bindValue(1, $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'filas'     => $stmt->fetchAll(),
        'pagina'    => $pagina,
        'paginas'   => $paginas,
        'total'     => $total,
        'porPagina' => $porPagina,
    ];
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

    $pdo->prepare('DELETE FROM ' . ident($tabla) . ' WHERE id = ?')->execute([$id]);
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
