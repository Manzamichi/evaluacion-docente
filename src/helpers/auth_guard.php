<?php

declare(strict_types=1);

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
 * Corta la petición si el usuario en sesión no tiene ninguno de los roles dados.
 *
 * Se usa después de requerirSesion(): aquí ya hay sesión, así que un rol
 * insuficiente es 403 y no un redirect al login.
 */
function requerirRol(string ...$roles): void
{
    requerirSesion();

    if (!in_array($_SESSION['usuario']['rol'] ?? '', $roles, true)) {
        http_response_code(403);

        // La página ofrece cerrar sesión a propósito: login.php redirige a
        // index.php cuando ya hay sesión, así que sin esta salida un usuario
        // con el rol equivocado no puede llegar al formulario para cambiar de
        // cuenta.
        $rol = htmlspecialchars((string) ($_SESSION['usuario']['rol'] ?? ''), ENT_QUOTES, 'UTF-8');

        exit(<<<HTML
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Sin permiso — evaluacion_docente</title>
                <link rel="stylesheet" href="assets/css/style.css">
            </head>
            <body>
            <main class="aviso">
                <h1>No tienes permiso para ver esta página</h1>
                <p>Tu sesión tiene el rol <strong>{$rol}</strong>.</p>
                <p><a class="boton" href="logout.php">Cerrar sesión y entrar con otra cuenta</a></p>
            </main>
            </body>
            </html>
            HTML);
    }
}
