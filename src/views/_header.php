<?php

declare(strict_types=1);

/**
 * Cabecera compartida por todas las vistas.
 *
 * Variables opcionales que la vista puede definir ANTES de incluir este archivo:
 *   $titulo  string    Título de la pestaña. Por defecto el nombre del sistema.
 *   $activo  string    href del enlace del menú que va resaltado.
 *   $css     string[]  Hojas extra dentro de public/assets/css/
 *
 * Para agregar un enlace al menú que no sea una tabla del CRUD (login, reportes,
 * dashboard...), añádelo al array $navegacion de abajo.
 */

$titulo ??= 'evaluacion_docente';
$activo ??= isset($_GET['tabla']) ? '?tabla=' . $_GET['tabla'] : '';

// Versión para invalidar la caché del navegador cuando cambia una hoja de estilos.
$assetVer = static function (string $ruta): string {
    $abs = __DIR__ . '/../../public/assets/css/' . $ruta;

    return 'assets/css/' . $ruta . '?v=' . (is_file($abs) ? (string) filemtime($abs) : '1');
};

$navegacion = [];

foreach (tablas() as $nombre => $t) {
    $navegacion['?tabla=' . $nombre] = $t['etiqueta'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> — evaluacion_docente</title>
    <link rel="stylesheet" href="<?= e($assetVer('style.css')) ?>">
    <?php foreach ($css ?? [] as $hoja): ?>
        <link rel="stylesheet" href="<?= e($assetVer($hoja)) ?>">
    <?php endforeach; ?>
</head>
<body>
<header class="barra">
    <strong>evaluacion_docente</strong>
    <nav>
        <?php foreach ($navegacion as $href => $etiqueta): ?>
            <a href="<?= e($href) ?>" class="<?= $href === $activo ? 'activo' : '' ?>"><?= e($etiqueta) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (isset($_SESSION['usuario'])): ?>
        <p class="sesion">
            <strong><?= e($_SESSION['usuario']['nombre']) ?></strong>
            (<?= e($_SESSION['usuario']['rol']) ?>)
            <a href="logout.php">Cerrar sesión</a>
        </p>
    <?php endif; ?>
</header>

<main>
