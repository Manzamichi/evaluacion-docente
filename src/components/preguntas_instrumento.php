<?php

declare(strict_types=1);

/**
 * Un instrumento con sus preguntas en orden, para reordenarlas.
 *
 * Cada <li> lleva su <input name="orden[]">, así que el orden en que quedan los
 * renglones en la página es el orden que se guarda. ordenar.js solo mueve
 * renglones y envía; los botones ↑ ↓ envían sin JavaScript.
 *
 * Props:
 *   m            string   Url del módulo
 *   instrumento  array    El registro de `instrumentos`
 *   preguntas    array[]  id, pregunta, tipo, dimension; ya en orden
 *   editable     bool     Si el usuario puede abrir 'pregunta/admin'
 *   volver       string   Url del módulo al que lleva "Volver"
 */

$ultima = count($preguntas) - 1;
?>

<h1>Preguntas de “<?= e($instrumento['nombre']) ?>”</h1>

<dl class="ficha">
    <dt>Fecha de elaboración</dt>
    <dd><?= e($instrumento['fecha_elaboracion']) ?></dd>

    <?php if (($instrumento['instruccion'] ?? '') !== ''): ?>
        <dt>Instrucciones</dt>
        <?php // Sin e(): limpiaHtml() es la lista blanca de lo que puede salir crudo. ?>
        <dd class="texto-html"><?= limpiaHtml((string) $instrumento['instruccion']) ?></dd>
    <?php endif; ?>

    <dt>No. de preguntas</dt>
    <dd><?= count($preguntas) ?></dd>
</dl>

<?php if ($preguntas === []): ?>
    <p class="vacio">Este instrumento todavía no tiene preguntas.</p>
<?php else: ?>
    <p class="ayuda">
        Arrastra una pregunta a su nuevo lugar, o usa ↑ ↓. Se guarda sola y las
        demás se recorren.
    </p>

    <form method="post" action="<?= e(urlModulo($m, ['id' => $instrumento['id']])) ?>">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="foco" value="">

        <ol class="ordenable">
            <?php foreach ($preguntas as $i => $p): ?>
                <li id="pregunta-<?= (int) $p['id'] ?>" draggable="true" data-id="<?= (int) $p['id'] ?>">
                    <input type="hidden" name="orden[]" value="<?= (int) $p['id'] ?>">
                    <span class="asa" aria-hidden="true">⠿</span>
                    <span class="numero"><?= $i + 1 ?></span>

                    <div class="texto">
                        <?= e(textoPlano((string) $p['pregunta'])) ?>
                        <small><?= e($p['tipo']) ?> · <?= e($p['dimension']) ?></small>
                    </div>

                    <div class="mover">
                        <button type="submit" name="mover" value="<?= (int) $p['id'] ?>:-1"
                                aria-label="Subir la pregunta <?= $i + 1 ?>" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
                        <button type="submit" name="mover" value="<?= (int) $p['id'] ?>:1"
                                aria-label="Bajar la pregunta <?= $i + 1 ?>" <?= $i === $ultima ? 'disabled' : '' ?>>↓</button>
                    </div>

                    <?php if ($editable): ?>
                        <a href="<?= e(urlModulo('pregunta/admin', ['accion' => 'editar', 'id' => $p['id']])) ?>">Editar</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </form>
<?php endif; ?>

<div class="acciones-form">
    <?php if ($editable): ?>
        <a class="boton" href="<?= e(urlModulo('pregunta/admin', [
            'accion' => 'nuevo',
            'v'      => ['instrumento_id' => $instrumento['id']],
        ])) ?>">Nueva pregunta</a>
    <?php endif; ?>
    <a href="<?= e(urlModulo($volver)) ?>">Volver</a>
</div>
