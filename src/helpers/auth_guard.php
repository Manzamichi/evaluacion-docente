<?php

declare(strict_types=1);

require_once __DIR__ . '/modulos.php';
require_once __DIR__ . '/../components/_render.php';

function requerirSesion(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Corta la petición si el usuario en sesión no tiene el módulo asignado por
 * ninguno de sus grupos.
 *
 * Se usa después de requerirSesion(): aquí ya hay sesión, así que un permiso
 * faltante es 403 y no un redirect al login.
 */
function requerirModulo(string $url): void
{
    requerirSesion();

    if (puede($url)) {
        return;
    }

    http_response_code(403);

    // La página ofrece cerrar sesión a propósito: login.php redirige a
    // index.php cuando ya hay sesión, así que sin esta salida un usuario sin el
    // permiso no puede llegar al formulario para cambiar de cuenta.
    $grupos = gruposActuales();
    $grupos = $grupos === [] ? 'ningún grupo' : implode(', ', $grupos);
    $grupos = htmlspecialchars($grupos, ENT_QUOTES, 'UTF-8');

    $css = htmlspecialchars(assetVer('assets/css/style.css'), ENT_QUOTES, 'UTF-8');
    $logo = htmlspecialchars(assetVer('assets/img/logo-uady.png'), ENT_QUOTES, 'UTF-8');

    exit(<<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Sin permiso — evaluacion_docente</title>
            <link rel="stylesheet" href="{$css}">
        </head>
        <body>
        <header class="barra">
            <img class="barra-logo" src="{$logo}" alt="Universidad Autónoma de Yucatán">
            <span class="barra-titulo">Sistema de Evaluación Docente</span>
        </header>
        <main class="aviso">
            <h1>No tienes permiso para ver esta página</h1>
            <p>Tu sesión pertenece a: <strong>{$grupos}</strong>.</p>
            <p><a class="boton" href="?">Ir al inicio</a></p>
            <p><a href="logout.php">Cerrar sesión y entrar con otra cuenta</a></p>
        </main>
        </body>
        </html>
        HTML);
}
