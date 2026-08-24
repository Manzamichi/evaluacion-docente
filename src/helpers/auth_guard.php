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
        exit('No tienes permiso para ver esta página.');
    }
}
