<?php

declare(strict_types=1);

/**
 * Edita una relación N:N con dos listas: a la izquierda lo disponible, a la
 * derecha lo asignado al sujeto. Se mueve con los botones del centro, con
 * doble clic sobre un elemento o arrastrando de una lista a la otra.
 *
 * Lo usan 'grupo/permisos' (qué módulos ve un grupo), 'grupo/usuarios' y
 * 'usuario/grupos'. La diferencia entre ellos está entera en src/modules.php.
 *
 * Props:
 *   titulo     string   Encabezado de la pantalla
 *   sujeto     string   Nombre del registro que se está editando
 *   etiqueta   string   Nombre del lado que se asigna ("Módulos", "Usuarios")
 *   opciones   array[]  Filas del lado ajeno: id, la columna 'muestra' y la de 'agrupa'
 *   muestra    string   Columna con el texto de cada elemento
 *   agrupa     ?string  Columna con la categoría, se muestra como pista (opcional)
 *   asignados  int[]    Ids ya asignados
 *   volver     string   Url del módulo al que va "Cancelar"
 *
 * El <form> manda un ajenos[] por cada elemento de la lista derecha; los arma
 * el JS de public/assets/js/app.js al enviar. El <noscript> conserva la
 * asignación actual cuando no hay JavaScript: sin él, guardar la borraría.
 */

$asignadosSet = array_flip($asignados);

// Se reparten en dos listas conservando el orden en que llegaron (opcionesDe()
// ya las trae ordenadas por categoría y nombre).
$disponibles = [];
$puestos     = [];

foreach ($opciones as $i => $opcion) {
    $fila = [
        'id'        => (int) $opcion['id'],
        'texto'     => (string) $opcion[$muestra],
        'categoria' => $agrupa !== null ? (string) $opcion[$agrupa] : '',
        'orden'     => $i,
    ];

    if (isset($asignadosSet[$fila['id']])) {
        $puestos[] = $fila;
    } else {
        $disponibles[] = $fila;
    }
}

/**
 * Pinta los <li> de una lista.
 *
 * @param array<int, array{id: int, texto: string, categoria: string, orden: int}> $filas
 */
$items = static function (array $filas): void {
    foreach ($filas as $fila) {
        echo '<li class="dualbox-item" draggable="true"'
            , ' data-id="', $fila['id'], '"'
            , ' data-orden="', $fila['orden'], '">'
            , e($fila['texto']);

        if ($fila['categoria'] !== '') {
            echo ' <span class="dualbox-cat">', e($fila['categoria']), '</span>';
        }

        echo '</li>';
    }
};
?>

<h1><?= e($titulo) ?>: <?= e($sujeto) ?></h1>

<form method="post" class="asignador-form">
    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

    <noscript>
        <p class="error">Se necesita JavaScript para editar la asignación desde esta pantalla.</p>
        <?php foreach ($puestos as $fila): ?>
            <input type="hidden" name="ajenos[]" value="<?= $fila['id'] ?>">
        <?php endforeach; ?>
    </noscript>

    <?php if ($opciones === []): ?>
        <p class="vacio">No hay <?= e(strtolower($etiqueta)) ?> para asignar.</p>
    <?php else: ?>
        <div class="dualbox" data-nombre="ajenos">
            <div class="dualbox-col">
                <p class="dualbox-titulo"><?= e($etiqueta) ?> disponibles</p>
                <ul class="dualbox-lista" data-lado="disponibles" aria-label="<?= e($etiqueta) ?> disponibles">
                    <?php $items($disponibles); ?>
                </ul>
            </div>

            <div class="dualbox-flechas">
                <button type="button" data-accion="asignar" aria-label="Asignar seleccionados">&rsaquo;</button>
                <button type="button" data-accion="quitar" aria-label="Quitar seleccionados">&lsaquo;</button>
            </div>

            <div class="dualbox-col">
                <p class="dualbox-titulo"><?= e($etiqueta) ?> asignados</p>
                <ul class="dualbox-lista" data-lado="asignados" aria-label="<?= e($etiqueta) ?> asignados">
                    <?php $items($puestos); ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="acciones-form">
        <button type="submit" class="boton">Guardar</button>
        <a href="<?= e(urlModulo($volver)) ?>">Cancelar</a>
    </div>
</form>
