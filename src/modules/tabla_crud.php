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
$cfg    = tablaConfig($tabla);
$accion = (string) ($_GET['accion'] ?? 'listar');
$id     = (int) ($_GET['id'] ?? 0);

$pdo      = getDbConnection();
$error    = null;
$registro = [];

// Búsqueda por columna. filtrosDesde() descarta cualquier columna que la tabla
// no liste, así que $filtros ya viene saneado para el SQL, la vista y la URL.
$filtros = filtrosDesde($cfg, (array) ($_GET['f'] ?? []));

// El listado al que se vuelve tras guardar: conserva la búsqueda activa.
$urlListado = urlModulo($m, ['f' => $filtros]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();

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
        $error    = 'No se pudo guardar el registro. Revisa que no haya valores duplicados.';
        $registro = $_POST;
    }
}

if ($accion === 'editar' && $id > 0 && $registro === []) {
    $registro = obtener($pdo, $tabla, $id) ?? [];

    if ($registro === []) {
        http_response_code(404);
        exit('Registro no encontrado.');
    }
}

// Un borrado que falló deja $error y hay que seguir mostrando el listado.
$listando = $accion === 'listar' || $accion === 'eliminar';
$filas    = $listando ? listar($pdo, $tabla, $filtros) : [];

$titulo = $cfg['etiqueta'];

if ($error !== null) {
    echo '<p class="error">', e($error), '</p>';
}

if ($listando) {
    componente('tabla', [
        'm'       => $m,
        'cfg'     => $cfg,
        'filas'   => $filas,
        'filtros' => $filtros,
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
