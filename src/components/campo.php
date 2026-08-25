<?php

declare(strict_types=1);

/**
 * Un campo del formulario, según su 'tipo' en src/tables.php.
 *
 * Props:
 *   col    string  Nombre de la columna
 *   campo  array   Su configuración
 *   valor  string  Valor actual (vacío en los campos con hash)
 *   nuevo  bool    true si es un alta; los campos con hash solo son
 *                  obligatorios al dar de alta
 */

$requerido = !empty($campo['requerido']) && ($nuevo || empty($campo['hash']));
?>
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
        <textarea name="<?= e($col) ?>" rows="4" <?= $requerido ? 'required' : '' ?>><?= e($valor) ?></textarea>

    <?php else: ?>
        <input type="<?= e($campo['tipo']) ?>"
               name="<?= e($col) ?>"
               value="<?= e($valor) ?>"
            <?= $requerido ? 'required' : '' ?>>
    <?php endif; ?>

    <?php if (!empty($campo['hash']) && !$nuevo): ?>
        <small>Déjalo vacío para conservar la contraseña actual.</small>
    <?php endif; ?>
</label>
