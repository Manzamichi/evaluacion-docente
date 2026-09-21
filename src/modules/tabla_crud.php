<?php

declare(strict_types=1);

/**
 * Módulo genérico de CRUD: atiende cualquier entrada de src/modules.php que
 * traiga 'crud' => '<tabla>'.
 *
 * Recibe del front controller:
 *   $m    string  Url del módulo
 *   $mod  array   Su configuración
 *
 * Y le devuelve $titulo para la pestaña.
 */

/** @var string $m */
/** @var array $mod */

$tabla  = (string) $mod['crud'];
$accion = (string) ($_GET['accion'] ?? 'listar');
$id     = (int) ($_GET['id'] ?? 0);
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

$pdo      = getDbConnection();
$cfg      = resuelveOpciones($pdo, tablaConfig($tabla));
$error    = null;
$registro = [];

// Búsqueda por columna. filtrosDesde() descarta cualquier columna que la tabla
// no liste, así que $filtros ya viene saneado para el SQL, la vista y la URL.
$filtros = filtrosDesde($cfg, (array) ($_GET['f'] ?? []));

// El listado al que se vuelve tras guardar: conserva la búsqueda y la página.
// Si al borrar la página deja de existir, listar() recorta al rango válido.
$urlListado = urlModulo($m, ['f' => $filtros, 'pagina' => $pagina]);

// Exportar e importar solo donde el módulo las declaró en modules.php. Sin este
// filtro bastaría escribir &accion=importar a mano para habilitar la carga
// masiva en cualquier CRUD, aunque nadie la hubiera activado ahí.
$declaradas = array_column(array_column($mod['acciones'] ?? [], 'params'), 'accion');

if (in_array($accion, ['exportar', 'importar'], true)
    && !in_array($accion, $declaradas, true)) {
    http_response_code(404);
    exit('Acción no disponible para este módulo.');
}

if ($accion === 'exportar') {
    // El front controller abrió un buffer para poder envolver la salida en el
    // layout. Un CSV no lleva layout: se tira el buffer y se escribe directo.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $stmt = exportarFilas($pdo, $tabla, $filtros);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $tabla . '-' . date('Y-m-d') . '.csv"');

    $salida = fopen('php://output', 'w');

    // BOM UTF-8: sin él, Excel abre el archivo en la codificación del sistema y
    // los acentos salen rotos.
    fwrite($salida, "\xEF\xBB\xBF");
    // El escape vacío es el valor que PHP tomará por defecto, y el que hace que
    // una celda con "\\" salga tal cual en vez de escaparse.
    fputcsv($salida, columnasDetalle($pdo, $tabla, $cfg), ',', '"', '');

    while (($fila = $stmt->fetch(PDO::FETCH_NUM)) !== false) {
        fputcsv($salida, $fila, ',', '"', '');
    }

    fclose($salida);
    exit;
}

$erroresImport = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();

    if ($accion === 'importar') {
        // La importación reporta un error por fila, no uno solo: se queda en su
        // propia pantalla en vez de caer al listado.
        $erroresImport = importarCsv($pdo, $tabla, $cfg, $_FILES['archivo'] ?? []);

        if ($erroresImport === []) {
            header('Location: ' . $urlListado);
            exit;
        }
    } else {
        try {
            if ($accion === 'eliminar') {
                eliminar($pdo, $tabla, $id);
            } else {
                $datos = saneaEntrada($cfg, $_POST, $id === 0);

                if ($id === 0) {
                    crear($pdo, $tabla, $datos);
                } else {
                    actualizar($pdo, $tabla, $id, $datos);
                }
            }

            header('Location: ' . $urlListado);
            exit;
        } catch (InvalidArgumentException $e) {
            $error    = $e->getMessage();
            $registro = $_POST;
        } catch (PDOException) {
            // Al borrar, lo único que la base rechaza es una FK con RESTRICT
            // (una categoría con módulos); al guardar, un UNIQUE repetido.
            $error = $accion === 'eliminar'
                ? 'No se pudo eliminar: hay registros que dependen de este.'
                : 'No se pudo guardar el registro. Revisa que no haya valores duplicados.';
            $registro = $_POST;
        }
    }
}

if ($accion === 'editar' && $id > 0 && $registro === []) {
    $registro = obtener($pdo, $tabla, $id) ?? [];

    if ($registro === []) {
        http_response_code(404);
        exit('Registro no encontrado.');
    }
}

if ($accion === 'importar') {
    $titulo = 'Importar ' . $cfg['etiqueta'];

    componente('importador', [
        'm'          => $m,
        'cfg'        => $cfg,
        'columnas'   => array_keys($cfg['campos']),
        'errores'    => $erroresImport,
        'urlListado' => $urlListado,
    ]);

    return;
}

// Un borrado que falló deja $error y hay que seguir mostrando el listado.
$listando = $accion === 'listar' || $accion === 'eliminar';

$listado = $listando
    ? listar($pdo, $tabla, $filtros, $pagina)
    : ['filas' => [], 'pagina' => 1, 'paginas' => 1, 'total' => 0, 'porPagina' => 0];

$titulo = $cfg['etiqueta'];

if ($error !== null) {
    echo '<p class="error">', e($error), '</p>';
}

if ($listando) {
    componente('tabla', [
        'm'        => $m,
        'cfg'      => $cfg,
        'listado'  => $listado,
        'filtros'  => $filtros,
        'columnas' => columnasDetalle($pdo, $tabla, $cfg),
    ]);
} else {
    componente('formulario', [
        'm'          => $m,
        'cfg'        => $cfg,
        'registro'   => $registro,
        'id'         => $id,
        'filtros'    => $filtros,
        'urlListado' => $urlListado,
    ]);
}
