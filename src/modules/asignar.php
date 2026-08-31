<?php

declare(strict_types=1);

/**
 * Módulo genérico de asignación N:N. Lo usan 'grupo/permisos' (qué módulos ve
 * un grupo) y 'grupo/usuarios' (quién pertenece al grupo); la diferencia entre
 * ambos está entera en src/modules.php.
 *
 * Recibe del front controller:
 *   $m    string  Url del módulo
 *   $mod  array   Su configuración: sujeto, pivote, destino, volver
 */

/** @var array $mod */

$id = (int) ($_GET['id'] ?? 0);

$pdo    = getDbConnection();
$sujeto = obtener($pdo, $mod['sujeto']['tabla'], $id);

if ($sujeto === null) {
    http_response_code(404);
    exit('Registro no encontrado.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();

    try {
        pivoteGuardar($pdo, $mod['pivote'], $id, (array) ($_POST['ajenos'] ?? []));

        header('Location: ' . urlModulo($mod['volver']));
        exit;
    } catch (PDOException) {
        // Llega aquí si algún id enviado ya no existe; la transacción de
        // pivoteGuardar() dejó la asignación anterior intacta.
        $error = 'No se pudo guardar la asignación. Vuelve a intentarlo.';
    }
}

$titulo = $mod['titulo'];

if ($error !== null) {
    echo '<p class="error">', e($error), '</p>';
}

componente('asignador', [
    'titulo'    => $mod['titulo'],
    'sujeto'    => (string) $sujeto[$mod['sujeto']['muestra']],
    'etiqueta'  => $mod['destino']['etiqueta'],
    'opciones'  => opcionesDe($pdo, $mod['destino']),
    'muestra'   => $mod['destino']['muestra'],
    'agrupa'    => $mod['destino']['agrupa'] ?? null,
    'asignados' => pivoteAsignados($pdo, $mod['pivote'], $id),
    'volver'    => $mod['volver'],
]);
