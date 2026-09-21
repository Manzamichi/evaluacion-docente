<?php

declare(strict_types=1);

/**
 * Alta o edición de un registro. Delega cada campo al componente 'campo'.
 *
 * Props:
 *   m           string  Url del módulo
 *   cfg         array   Configuración de la tabla
 *   registro    array   Valores actuales (vacío en un alta)
 *   id          int     0 en un alta
 *   filtros     array   Búsqueda activa, para conservarla al volver
 *   urlListado  string  A dónde va "Cancelar"
 */

$nuevo  = $id === 0;
$accion = $nuevo
    ? urlModulo($m, ['accion' => 'nuevo', 'f' => $filtros])
    : urlModulo($m, ['accion' => 'editar', 'id' => $id, 'f' => $filtros]);
?>

<h1><?= $nuevo ? 'Nuevo' : 'Editar' ?> — <?= e($cfg['etiqueta']) ?></h1>

<form method="post" action="<?= e($accion) ?>">
    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

    <?php foreach ($cfg['campos'] as $col => $campo): ?>
        <?php componente('campo', [
            'col'   => $col,
            'campo' => $campo,
            // Un hash nunca se devuelve al formulario: el campo va en blanco y
            // vacío significa "no cambiar".
            'valor' => !empty($campo['hash']) ? '' : (string) ($registro[$col] ?? ''),
            'nuevo' => $nuevo,
        ]); ?>
    <?php endforeach; ?>

    <div class="acciones-form">
        <button type="submit" class="boton">Guardar</button>
        <a href="<?= e($urlListado) ?>">Cancelar</a>
    </div>
</form>
