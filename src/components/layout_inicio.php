<?php

declare(strict_types=1);

/**
 * Apertura de la página: <head>, barra superior y menú lateral.
 * Se cierra con el componente 'layout_fin'.
 *
 * Props:
 *   titulo  string    Título de la pestaña
 *   activo  string    Url del módulo abierto, para resaltarlo en el menú
 *   css     string[]  Hojas extra dentro de public/assets/css/  (opcional)
 */

$titulo ??= 'evaluacion_docente';
$activo ??= '';

// Versión para invalidar la caché del navegador cuando cambia una hoja de estilos.
$assetVer = static function (string $ruta): string {
    $abs = __DIR__ . '/../../public/assets/css/' . $ruta;

    return 'assets/css/' . $ruta . '?v=' . (is_file($abs) ? (string) filemtime($abs) : '1');
};
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
    <strong>:: Sistema de Evaluación Docente ::</strong>

    <?php if (isset($_SESSION['usuario'])): ?>
        <p class="sesion">
            <strong><?= e($_SESSION['usuario']['nombre']) ?></strong>
            <?php $grupos = gruposActuales(); ?>
            <?php if ($grupos !== []): ?>
                (<?= e(implode(', ', $grupos)) ?>)
            <?php endif; ?>
            <a href="logout.php">Cerrar sesión</a>
        </p>
    <?php endif; ?>
</header>

<div class="cuerpo">
    <?php componente('menu', ['menu' => menuActual(), 'activo' => $activo]); ?>

    <main>
