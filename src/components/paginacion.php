<?php

declare(strict_types=1);

/**
 * Pie del listado: cuántos registros se están viendo y los enlaces de página.
 *
 * Props:
 *   m        string  Url del módulo
 *   filtros  array   Búsqueda activa; los enlaces la conservan para no perderla
 *                    al cambiar de página
 *   listado  array   Lo que devuelve listar(): pagina, paginas, total, porPagina
 */

$pagina  = $listado['pagina'];
$paginas = $listado['paginas'];

$url = static fn (int $p): string => urlModulo($m, ['f' => $filtros, 'pagina' => $p]);

// Con cero resultados no hay un "primer registro": mostrar 1–0 sería mentira.
$primero = $listado['total'] === 0 ? 0 : ($pagina - 1) * $listado['porPagina'] + 1;
$ultimo  = min($primero + $listado['porPagina'] - 1, $listado['total']);
?>

<div class="paginacion-pie">
    <p class="paginacion-info">
        Mostrando <?= $primero ?>–<?= $ultimo ?> de <?= $listado['total'] ?>
    </p>

    <?php if ($paginas > 1): ?>
        <nav class="paginacion" aria-label="Paginación">
            <?php if ($pagina > 1): ?>
                <a href="<?= e($url(1)) ?>" aria-label="Primera página">&laquo;</a>
                <a href="<?= e($url($pagina - 1)) ?>" rel="prev">&lsaquo; Anterior</a>
            <?php else: ?>
                <span class="inactiva">&laquo;</span>
                <span class="inactiva">&lsaquo; Anterior</span>
            <?php endif; ?>

            <?php foreach (ventanaPaginas($pagina, $paginas) as $p): ?>
                <?php if ($p === $pagina): ?>
                    <span class="actual" aria-current="page"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= e($url($p)) ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($pagina < $paginas): ?>
                <a href="<?= e($url($pagina + 1)) ?>" rel="next">Siguiente &rsaquo;</a>
                <a href="<?= e($url($paginas)) ?>" aria-label="Última página">&raquo;</a>
            <?php else: ?>
                <span class="inactiva">Siguiente &rsaquo;</span>
                <span class="inactiva">&raquo;</span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
