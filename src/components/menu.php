<?php

declare(strict_types=1);

/**
 * Menú lateral: los módulos que el usuario puede abrir, por categoría.
 *
 * Props:
 *   menu    array<string, array{nombre: string, url: string}[]>  de menuActual()
 *   activo  string  Url del módulo abierto
 */
?>
<aside class="lateral">
    <?php foreach ($menu as $categoria => $modulosCategoria): ?>
        <h2><?= e($categoria) ?></h2>
        <nav>
            <?php foreach ($modulosCategoria as $modulo): ?>
                <a href="<?= e(urlModulo($modulo['url'])) ?>"
                   class="<?= $modulo['url'] === $activo ? 'activo' : '' ?>"><?= e($modulo['nombre']) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endforeach; ?>
</aside>
