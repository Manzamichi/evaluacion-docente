<?php

declare(strict_types=1);

/**
 * Listado de una tabla: buscador por columna, filas y acciones.
 *
 * Props:
 *   m         string    Url del módulo, para armar los enlaces
 *   cfg       array     Configuración de la tabla (src/tables.php)
 *   listado   array     Lo que devuelve listar(): filas, pagina, paginas, total...
 *   filtros   array     Búsqueda activa por columna, ya saneada
 *   columnas  string[]  Columnas que muestra el panel de detalle
 */

$filas = $listado['filas'];

// Los enlaces por registro no van en la fila: se pintan al pie del panel de
// detalle, que ya tiene el registro en contexto. La celda de acciones se queda
// solo con lo típico (ver, editar, eliminar) y no crece con cada módulo nuevo.
$acciones = array_values(array_filter(
    $cfg['acciones'] ?? [],
    static fn (array $accion): bool => puede($accion['modulo'])
));
?>

<div class="encabezado">
    <h1><?= e($cfg['etiqueta']) ?></h1>
    <a class="boton" href="<?= e(urlModulo($m, ['accion' => 'nuevo', 'f' => $filtros])) ?>">Nuevo</a>
</div>

<?php if ($filas === [] && $filtros === []): ?>
    <p class="vacio">Todavía no hay registros.</p>
<?php else: ?>
    <?php
    // El formulario de búsqueda va vacío y fuera de la tabla; los inputs de
    // cada encabezado se enlazan con el atributo form="busqueda". Así no queda
    // envolviendo los formularios de "Eliminar", que no pueden ir anidados
    // dentro de otro formulario.
    ?>
    <?php
    // Sin campo 'pagina': una búsqueda nueva siempre arranca en la primera, si
    // no se caería en una página que ya no existe con menos resultados.
    ?>
    <form method="get" id="busqueda">
        <input type="hidden" name="m" value="<?= e($m) ?>">
    </form>

    <div class="tabla-scroll">
        <table>
            <thead>
            <tr>
                <?php foreach ($cfg['listar'] as $col): ?>
                    <th>
                        <?= e($col) ?>
                        <input type="search"
                               form="busqueda"
                               name="f[<?= e($col) ?>]"
                               value="<?= e($filtros[$col] ?? '') ?>"
                               aria-label="Filtrar por <?= e($col) ?>">
                    </th>
                <?php endforeach; ?>
                <th>
                    Acciones
                    <span class="busqueda-acciones">
                        <button type="submit" form="busqueda">Buscar</button>
                        <?php if ($filtros !== []): ?>
                            <a href="<?= e(urlModulo($m)) ?>">Limpiar</a>
                        <?php endif; ?>
                    </span>
                </th>
            </tr>
            </thead>
            <tbody>
            <?php if ($filas === []): ?>
                <tr>
                    <td colspan="<?= count($cfg['listar']) + 1 ?>" class="sin-resultados">
                        Ningún registro coincide con la búsqueda.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($filas as $fila): ?>
                <tr>
                    <?php foreach ($cfg['listar'] as $col): ?>
                        <td><?= e($fila[$col] ?? '') ?></td>
                    <?php endforeach; ?>
                    <td class="acciones">
                        <a class="lupa" href="#detalle-<?= (int) $fila['id'] ?>" aria-label="Ver detalle del registro <?= (int) $fila['id'] ?>">
                            <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">
                                <circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                <line x1="10.6" y1="10.6" x2="14" y2="14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                            </svg>
                        </a>

                        <a href="<?= e(urlModulo($m, ['accion' => 'editar', 'id' => $fila['id'], 'f' => $filtros])) ?>">Editar</a>

                        <form method="post"
                              action="<?= e(urlModulo($m, ['accion' => 'eliminar', 'id' => $fila['id'], 'f' => $filtros])) ?>"
                              onsubmit="return confirm('¿Eliminar este registro?')">
                            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                            <button type="submit" class="peligro">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php componente('paginacion', ['m' => $m, 'filtros' => $filtros, 'listado' => $listado]); ?>

    <?php
    // Los paneles van fuera de .tabla-scroll: ese contenedor tiene
    // overflow-x: auto y recortaría un bloque posicionado dentro de él.
    ?>
    <?php foreach ($filas as $fila): ?>
        <?php componente('detalle', [
            'fila'     => $fila,
            'cfg'      => $cfg,
            'columnas' => $columnas,
            'acciones' => $acciones,
        ]); ?>
    <?php endforeach; ?>
<?php endif; ?>
