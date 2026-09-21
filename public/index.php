<?php

declare(strict_types=1);

/**
 * Front controller. Una sola puerta de entrada:
 *
 *   1. Exige sesión
 *   2. Resuelve ?m=<url> contra el registro de src/modules.php (whitelist)
 *   3. Exige que alguno de los grupos del usuario tenga ese módulo
 *   4. Ejecuta el módulo y envuelve su salida en el layout
 *
 * El módulo solo imprime su contenido: no abre ni cierra la página. Su salida
 * se guarda en un buffer para que pueda redirigir tras un POST (header() no
 * funciona si ya se mandó HTML) y para que pueda fijar $titulo, que el layout
 * necesita antes de imprimir el <head>.
 */

require_once __DIR__ . '/../src/helpers/auth_guard.php';
require_once __DIR__ . '/../src/crud.php';
require_once __DIR__ . '/../src/components/_render.php';

requerirSesion();

$m = (string) ($_GET['m'] ?? '');

// Sin módulo en la URL se manda al primero que el usuario pueda abrir.
if ($m === '') {
    $inicio = moduloInicial();

    if ($inicio === null) {
        http_response_code(403);

        $css = htmlspecialchars(assetVer('assets/css/style.css'), ENT_QUOTES, 'UTF-8');
        $logo = htmlspecialchars(assetVer('assets/img/logo-uady.png'), ENT_QUOTES, 'UTF-8');

        exit(<<<HTML
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Sin módulos — evaluacion_docente</title>
                <link rel="stylesheet" href="{$css}">
            </head>
            <body>
            <header class="barra">
                <img class="barra-logo" src="{$logo}" alt="Universidad Autónoma de Yucatán">
                <span class="barra-titulo">Sistema de Evaluación Docente</span>
            </header>
            <main class="aviso">
                <h1>Tu cuenta no tiene ningún módulo asignado</h1>
                <p>Pide a un administrador que te agregue a un grupo.</p>
                <p><a href="logout.php">Cerrar sesión y entrar con otra cuenta</a></p>
            </main>
            </body>
            </html>
            HTML);
    }

    header('Location: ' . urlModulo($inicio));
    exit;
}

try {
    $mod = moduloConfig($m);
} catch (InvalidArgumentException) {
    http_response_code(404);
    exit('Módulo no encontrado.');
}

requerirModulo($m);

$titulo = null;

ob_start();

if (isset($mod['crud'])) {
    require __DIR__ . '/../src/modules/tabla_crud.php';
} else {
    // El nombre sale de modules.php, nunca del request ni de la base de datos.
    // basename() es la segunda barrera contra un ../ que se colara ahí.
    require __DIR__ . '/../src/modules/' . basename((string) $mod['archivo']);
}

$contenido = ob_get_clean();

componente('layout_inicio', [
    'titulo' => $titulo ?? '',
    // Un módulo auxiliar (permisos, usuarios del grupo) resalta el listado del
    // que cuelga, que es el que sí está en el menú.
    'activo' => $mod['volver'] ?? $m,
]);

echo $contenido;

componente('layout_fin');
