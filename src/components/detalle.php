<?php

declare(strict_types=1);

/**
 * Panel lateral con el registro completo: también las columnas que no caben en
 * el listado. Se abre y se cierra con :target, sin JavaScript.
 *
 * Props:
 *   fila      array     El registro, tal como lo devolvió listar()
 *   cfg       array     Configuración de la tabla (src/tables.php)
 *   columnas  string[]  Columnas a mostrar, de columnasDetalle()
 *   acciones  array     Enlaces a otros módulos para este registro, ya filtrados
 *                       por permiso: [['etiqueta' => ..., 'modulo' => ...], ...]
 *
 * Las columnas sensibles no llegan hasta aquí: columnasDetalle() las deja fuera
 * del SELECT.
 */
?>
<aside id="detalle-<?= (int) $fila['id'] ?>" class="panel-detalle" aria-label="Detalle del registro">
    <div class="panel-encabezado">
        <h2><?= e($cfg['etiqueta']) ?> #<?= (int) $fila['id'] ?></h2>
        <?php
        // "#!" no corresponde a ningún id: el navegador desactiva :target y
        // cierra el panel, pero al no encontrar destino no mueve el scroll.
        // Apuntar a un id real (#listado) saltaba al inicio de la tabla.
        ?>
        <a class="panel-cerrar" href="#!" aria-label="Cerrar detalle">&times;</a>
    </div>

    <dl>
        <?php foreach ($columnas as $col): ?>
            <dt><?= e($cfg['campos'][$col]['etiqueta'] ?? $col) ?></dt>
            <dd>
                <?php // Una celda vacía no distingue "sin dato" de un error de pintado. ?>
                <?= $fila[$col] === null || $fila[$col] === '' ? '—' : e($fila[$col]) ?>
            </dd>
        <?php endforeach; ?>
    </dl>

    <?php if ($acciones !== []): ?>
        <div class="panel-acciones">
            <?php foreach ($acciones as $accion): ?>
                <a href="<?= e(urlModulo($accion['modulo'], ['id' => $fila['id']])) ?>"><?= e($accion['etiqueta']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</aside>
