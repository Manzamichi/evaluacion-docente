<?php

declare(strict_types=1);

/**
 * Vista del CRUD genérico: listado o formulario según $accion.
 *
 * Variables que recibe de public/index.php:
 *   $tablas $tabla $cfg $accion $id $filas $registro $error
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
