<?php

declare(strict_types=1);

/**
 * Un campo del formulario, según su 'tipo' en src/tables.php.
 *
 * Props:
 *   col    string  Nombre de la columna
 *   campo  array   Su configuración
 *   valor  string|array  Valor actual (vacío en los campos con hash). Un campo
 *                        'pares' que vuelve de un POST con error llega como array.
 *   nuevo  bool    true si es un alta; los campos con hash solo son
 *                  obligatorios al dar de alta
 */

$requerido = !empty($campo['requerido']) && ($nuevo || empty($campo['hash']));

if ($nuevo && $valor === '' && isset($campo['defecto'])) {
    $valor = (string) $campo['defecto'];
}

// app.js lo oculta mientras la otra columna no tenga el valor esperado.
$soloSi = isset($campo['solo_si'])
    ? 'data-solo-si="' . e($campo['solo_si'][0]) . '" data-solo-si-valor="' . e($campo['solo_si'][1]) . '"'
    : '';
?>
<?php if ($campo['tipo'] === 'pares'): ?>
    <fieldset class="pares" <?= $soloSi ?>>
        <legend><?= e($campo['etiqueta']) ?></legend>

        <div class="pares-filas">
            <?php foreach (filasDePares($valor) ?: [['', '']] as [$v, $t]): ?>
                <div class="pares-fila">
                    <label>
                        <span>Valor</span>
                        <input type="number" name="<?= e($col) ?>[valor][]" value="<?= e($v) ?>">
                    </label>
                    <label>
                        <span>Respuesta</span>
                        <input type="text" name="<?= e($col) ?>[texto][]" value="<?= e($t) ?>">
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="boton" data-pares="agregar" aria-label="Agregar opción">+</button>
        <button type="button" class="boton" data-pares="quitar" aria-label="Quitar la última opción">−</button>
    </fieldset>
    <?php return; ?>
<?php endif; ?>
<?php if ($campo['tipo'] === 'html'): ?>
    <?php // Fuera de <label>: con los botones del editor dentro, un clic en
          // cualquier parte del label activaría el primero (negrita). ?>
    <div class="campo-html" <?= $soloSi ?>>
        <span id="etiqueta-<?= e($col) ?>"><?= e($campo['etiqueta']) ?></span>
        <input type="hidden" name="<?= e($col) ?>" value="<?= e(limpiaHtml((string) $valor)) ?>">
        <?php // editor.js monta Quill aquí. Sin JavaScript se ve el texto y se conserva. ?>
        <div class="editor-html" aria-labelledby="etiqueta-<?= e($col) ?>"><?= limpiaHtml((string) $valor) ?></div>
    </div>
    <?php return; ?>
<?php endif; ?>
<label <?= $soloSi ?>>
    <span><?= e($campo['etiqueta']) ?></span>

    <?php if ($campo['tipo'] === 'select'): ?>
        <select name="<?= e($col) ?>">
            <?php foreach ($campo['opciones'] as $opcion): ?>
                <option value="<?= e($opcion) ?>" <?= $valor === $opcion ? 'selected' : '' ?>>
                    <?= e($campo['etiquetas'][$opcion] ?? $opcion) ?>
                </option>
            <?php endforeach; ?>
        </select>

    <?php elseif ($campo['tipo'] === 'textarea'): ?>
        <textarea name="<?= e($col) ?>" rows="4" <?= $requerido ? 'required' : '' ?>><?= e($valor) ?></textarea>

    <?php else: ?>
        <input type="<?= e($campo['tipo']) ?>"
               name="<?= e($col) ?>"
               value="<?= e($valor) ?>"
            <?= isset($campo['min']) ? 'min="' . e((string) $campo['min']) . '"' : '' ?>
            <?= isset($campo['max']) ? 'max="' . e((string) $campo['max']) . '"' : '' ?>
            <?= $requerido ? 'required' : '' ?>>
    <?php endif; ?>

    <?php if (!empty($campo['hash']) && !$nuevo): ?>
        <small>Déjalo vacío para conservar la contraseña actual.</small>
    <?php endif; ?>
</label>
