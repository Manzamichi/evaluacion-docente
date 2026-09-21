<?php

declare(strict_types=1);

/**
 * Menú lateral: los módulos que el usuario puede abrir, por categoría.
 *
 * Props:
 *   menu    array<string, array{nombre: string, url: string, acciones: array}[]>  de menuActual()
 *   activo  string  Url del módulo abierto
 *
 * Un módulo con acciones se pinta como <details>, abierto cuando es el activo.
 * El <a> va dentro del <summary>: clic en el nombre navega, clic en el resto
 * despliega. Se usa el elemento nativo porque no necesita una línea de JS.
 *
 * El id="lateral" es el destino de la hamburguesa: en móvil el panel se muestra
 * con :target, el mismo mecanismo que el panel de detalle.
 */
?>
<aside class="lateral" id="lateral">
    <?php // "#!" no corresponde a ningún id: cierra el panel sin mover el scroll. ?>
    <a class="lateral-cerrar" href="#!" aria-label="Cerrar menú">&times;</a>

    <?php foreach ($menu as $categoria => $modulosCategoria): ?>
        <h2><?= e($categoria) ?></h2>
        <nav>
            <?php foreach ($modulosCategoria as $modulo): ?>
                <?php
                $esActivo = $modulo['url'] === $activo;
                $clase    = $esActivo ? 'activo' : '';
                $enlace   = e(urlModulo($modulo['url']));
                ?>
                <?php if ($modulo['acciones'] === []): ?>
                    <a href="<?= $enlace ?>" class="<?= $clase ?>"><?= e($modulo['nombre']) ?></a>
                <?php else: ?>
                    <details class="lateral-modulo"<?= $esActivo ? ' open' : '' ?>>
                        <summary>
                            <a href="<?= $enlace ?>" class="<?= $clase ?>"><?= e($modulo['nombre']) ?></a>
                        </summary>
                        <div class="lateral-acciones">
                            <?php foreach ($modulo['acciones'] as $accion): ?>
                                <a href="<?= e($accion['href']) ?>"><?= e($accion['etiqueta']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    <?php endforeach; ?>
</aside>
