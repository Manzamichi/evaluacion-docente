<?php

declare(strict_types=1);

/**
 * Elige sobre qué registro se va a trabajar.
 *
 * Las pantallas de asignación N:N necesitan un ?id=. Cuando se llega a ellas
 * desde el panel de detalle el registro ya viene elegido; cuando se llega desde
 * el menú lateral no, así que primero se pregunta cuál.
 *
 * Props:
 *   titulo    string   Encabezado de la pantalla
 *   m         string   Url del módulo, que se repite con ?id=
 *   opciones  array[]  Registros entre los que elegir: id y la columna 'muestra'
 *   muestra   string   Columna con el texto de cada registro
 *   volver    string   Url del módulo al que va "Cancelar"
 */
?>

<h1><?= e($titulo) ?></h1>

<?php if ($opciones === []): ?>
    <p class="vacio">Todavía no hay registros que elegir.</p>
<?php else: ?>
    <p class="ayuda">Elige el registro que quieres editar.</p>

    <ul class="selector">
        <?php foreach ($opciones as $opcion): ?>
            <li>
                <a href="<?= e(urlModulo($m, ['id' => $opcion['id']])) ?>">
                    <?= e($opcion[$muestra]) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<div class="acciones-form">
    <a href="<?= e(urlModulo($volver)) ?>">Cancelar</a>
</div>
