<?php

declare(strict_types=1);

/**
 * Edita una relación N:N como una lista de casillas: "estos módulos pertenecen
 * a este grupo", "estos usuarios pertenecen a este grupo".
 *
 * Props:
 *   titulo     string   Encabezado de la pantalla
 *   sujeto     string   Nombre del registro que se está editando
 *   etiqueta   string   Nombre del lado que se asigna ("Módulos", "Usuarios")
 *   opciones   array[]  Filas del lado ajeno: id, la columna 'muestra' y la de 'agrupa'
 *   muestra    string   Columna con el texto de cada casilla
 *   agrupa     ?string  Columna por la que se separan en bloques (opcional)
 *   asignados  int[]    Ids ya asignados
 *   volver     string   Url del módulo al que va "Cancelar"
 *
 * ponytail: casillas en vez de las dos listas con botones > < del sistema
 * anterior; hace lo mismo sin una línea de JavaScript. Si la lista crece tanto
 * que estorba, el paso siguiente es un filtro de texto, no las dos listas.
 */

$bloques = [];

foreach ($opciones as $opcion) {
    $bloques[$agrupa === null ? '' : (string) $opcion[$agrupa]][] = $opcion;
}
?>

<h1><?= e($titulo) ?>: <?= e($sujeto) ?></h1>

<form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

    <fieldset class="asignador">
        <legend><?= e($etiqueta) ?> asignados</legend>

        <?php if ($opciones === []): ?>
            <p class="vacio">No hay <?= e(strtolower($etiqueta)) ?> para asignar.</p>
        <?php endif; ?>

        <?php foreach ($bloques as $nombreBloque => $filas): ?>
            <?php if ($nombreBloque !== ''): ?>
                <h2><?= e($nombreBloque) ?></h2>
            <?php endif; ?>

            <div class="asignador-lista">
                <?php foreach ($filas as $opcion): ?>
                    <label>
                        <input type="checkbox"
                               name="ajenos[]"
                               value="<?= (int) $opcion['id'] ?>"
                            <?= in_array((int) $opcion['id'], $asignados, true) ? 'checked' : '' ?>>
                        <?= e($opcion[$muestra]) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </fieldset>

    <div class="acciones-form">
        <button type="submit" class="boton">Guardar</button>
        <a href="<?= e(urlModulo($volver)) ?>">Cancelar</a>
    </div>
</form>
