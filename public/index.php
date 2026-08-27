<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/helpers/auth_guard.php';
require_once __DIR__ . '/../src/crud.php';

// requerirSesion() abre la sesión si hace falta, así que no se llama
// session_start() por separado.
requerirSesion();
requerirRol('admin');

$usuario = $_SESSION['usuario'];

$tablas = tablas();
$tabla  = (string) ($_GET['tabla'] ?? array_key_first($tablas));
$accion = (string) ($_GET['accion'] ?? 'listar');
$id     = (int) ($_GET['id'] ?? 0);
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

try {
    $cfg = tablaConfig($tabla);
} catch (InvalidArgumentException) {
    http_response_code(404);
    exit('Tabla no encontrada.');
}

$pdo      = getDbConnection();
$error    = null;
$registro = [];

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

        header("Location: ?tabla={$tabla}");
        exit;
    } catch (InvalidArgumentException $e) {
        $error    = $e->getMessage();
        $registro = $_POST;
    } catch (PDOException $e) {
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

$listado = $accion === 'listar'
    ? listar($pdo, $tabla, $pagina)
    : ['filas' => [], 'pagina' => 1, 'paginas' => 1, 'total' => 0, 'porPagina' => 0];

$filas = $listado['filas'];

require __DIR__ . '/../src/views/crud.php';
