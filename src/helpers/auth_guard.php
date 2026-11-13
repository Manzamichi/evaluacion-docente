<?php

declare(strict_types=1);

require_once __DIR__ . '/modulos.php';
require_once __DIR__ . '/../components/_render.php';

/*
 * Segundos de inactividad tras los cuales se cierra la sesión (60 minutos/1 hora).
 * Para cambiarlo, editar solo esta constante: p. ej. 30 * 60 son 30 minutos.
 * El contador se reinicia con cada petición; es inactividad, no una duración
 * máxima de la sesión.
 */
const SESION_INACTIVIDAD = 3600; 

/*
 * Segundos máximos de duración de una sesión (16 horas), cuente o no con
 * actividad. Pasado ese tiempo se pide iniciar sesión de nuevo. Para cambiarlo,
 * editar solo esta constante: p. ej. 4 * 3600 son 4 horas. Debe ser
 * mayor que SESION_INACTIVIDAD; si no, el límite de inactividad nunca se alcanza.
 */
const SESION_DURACION_MAXIMA = 16 * 3600;

function requerirSesion(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Sin esto PHP puede borrar el archivo de sesión (24 min por defecto)
        // antes de que venza el límite si se sube SESION_INACTIVIDAD.
        ini_set('session.gc_maxlifetime', (string) max(SESION_INACTIVIDAD, SESION_DURACION_MAXIMA, 1440));
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        header('Location: login.php');
        exit;
    }

    $ahora  = time();
    $ultima = (int) ($_SESSION['ultima_actividad'] ?? 0);
    // Una sesión abierta antes de este control no trae inicio: cuenta desde ahora.
    $inicio = (int) ($_SESSION['inicio_sesion'] ??= $ahora);

    if (
        ($ultima > 0 && $ahora - $ultima > SESION_INACTIVIDAD)
        || $ahora - $inicio > SESION_DURACION_MAXIMA
    ) {
        session_destroy();
        header('Location: login.php?expirada=1');
        exit;
    }

    $_SESSION['ultima_actividad'] = $ahora;
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
