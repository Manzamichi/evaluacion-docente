<?php

declare(strict_types=1);

/**
 * Vista del CRUD genérico: listado o formulario según $accion.
 *
 * Variables que recibe de public/index.php:
 *   $tablas $tabla $cfg $accion $id $filas $listado $registro $error
 */

$titulo = $cfg['etiqueta'];

require __DIR__ . '/_header.php';


?>

<?php if ($error !== null): ?>
    <p class="error"><?= e($error) ?></p>
<?php endif; ?>

<?php if ($accion === 'listar'): ?>

    <div class="encabezado">
        <h1><?= e($cfg['etiqueta']) ?></h1>
        <a class="boton" href="?tabla=<?= e($tabla) ?>&accion=nuevo">Nuevo</a>
    </div>

    <?php if ($filas === []): ?>
        <p class="vacio">Todavía no hay registros.</p>
    <?php else: ?>
        <div class="tabla-scroll">
            <table>
                <thead>
                <tr>
                    <?php foreach ($cfg['listar'] as $col): ?>
                        <th><?= e($col) ?></th>
                    <?php endforeach; ?>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($filas as $fila): ?>
                    <tr>
                        <?php foreach ($cfg['listar'] as $col): ?>
                            <td><?= e($fila[$col] ?? '') ?></td>
                        <?php endforeach; ?>
                        <td class="acciones">
                            <a href="?tabla=<?= e($tabla) ?>&accion=editar&id=<?= (int) $fila['id'] ?>">Editar</a>
                            <form method="post"
                                  action="?tabla=<?= e($tabla) ?>&accion=eliminar&id=<?= (int) $fila['id'] ?>"
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

        <?php
        $paginaActual = $listado['pagina'];
        $totalPaginas = $listado['paginas'];

        // Ventana deslizante de como máximo 3 números centrada en la página actual.
        $ventana = 3;
        $desde   = max(1, min($paginaActual - 1, $totalPaginas - $ventana + 1));
        $hasta   = min($totalPaginas, $desde + $ventana - 1);

        $url = static fn (int $p): string => '?tabla=' . e($tabla) . '&accion=listar&pagina=' . $p;

        $primerReg = ($paginaActual - 1) * $listado['porPagina'] + 1;
        $ultimoReg = min($primerReg + $listado['porPagina'] - 1, $listado['total']);
        ?>

        <div class="paginacion-pie">
            <p class="paginacion-info">
                Mostrando <?= $primerReg ?>–<?= $ultimoReg ?> de <?= $listado['total'] ?>
            </p>

            <?php if ($totalPaginas > 1): ?>
                <nav class="paginacion" aria-label="Paginación">
                    <?php if ($paginaActual > 1): ?>
                        <a href="<?= $url(1) ?>" aria-label="Primera página">&laquo;</a>
                        <a href="<?= $url($paginaActual - 1) ?>" rel="prev">&lsaquo; Anterior</a>
                    <?php else: ?>
                        <span class="inactiva">&laquo;</span>
                        <span class="inactiva">&lsaquo; Anterior</span>
                    <?php endif; ?>

                    <?php for ($p = $desde; $p <= $hasta; $p++): ?>
                        <?php if ($p === $paginaActual): ?>
                            <span class="actual" aria-current="page"><?= $p ?></span>
                        <?php else: ?>
                            <a href="<?= $url($p) ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($paginaActual < $totalPaginas): ?>
                        <a href="<?= $url($paginaActual + 1) ?>" rel="next">Siguiente &rsaquo;</a>
                        <a href="<?= $url($totalPaginas) ?>" aria-label="Última página">&raquo;</a>
                    <?php else: ?>
                        <span class="inactiva">Siguiente &rsaquo;</span>
                        <span class="inactiva">&raquo;</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>

    <h1><?= $id > 0 ? 'Editar' : 'Nuevo' ?> — <?= e($cfg['etiqueta']) ?></h1>

    <form method="post" action="?tabla=<?= e($tabla) ?>&accion=<?= $id > 0 ? 'editar&id=' . $id : 'nuevo' ?>">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

        <?php foreach ($cfg['campos'] as $col => $campo): ?>
            <?php $valor = !empty($campo['hash']) ? '' : (string) ($registro[$col] ?? ''); ?>
            <label>
                <span><?= e($campo['etiqueta']) ?></span>

                <?php if ($campo['tipo'] === 'select'): ?>
                    <select name="<?= e($col) ?>">
                        <?php foreach ($campo['opciones'] as $opcion): ?>
                            <option value="<?= e($opcion) ?>" <?= $valor === $opcion ? 'selected' : '' ?>>
                                <?= e($opcion) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($campo['tipo'] === 'textarea'): ?>
                    <textarea name="<?= e($col) ?>" rows="4"
                        <?= !empty($campo['requerido']) && $id === 0 ? 'required' : '' ?>><?= e($valor) ?></textarea>

                <?php else: ?>
                    <input type="<?= e($campo['tipo']) ?>"
                           name="<?= e($col) ?>"
                           value="<?= e($valor) ?>"
                        <?= !empty($campo['requerido']) && ($id === 0 || empty($campo['hash'])) ? 'required' : '' ?>>
                <?php endif; ?>

                <?php if (!empty($campo['hash']) && $id > 0): ?>
                    <small>Déjalo vacío para conservar la contraseña actual.</small>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>

        <div class="acciones-form">
            <button type="submit" class="boton">Guardar</button>
            <a href="?tabla=<?= e($tabla) ?>">Cancelar</a>
        </div>
    </form>

<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
